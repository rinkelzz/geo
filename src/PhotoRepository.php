<?php

class PhotoRepository
{
    private \PDO $pdo;
    private array $config;
    private CategoryRepository $categories;

    public function __construct(\PDO $pdo, array $config, ?CategoryRepository $categories = null)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->categories = $categories ?? new CategoryRepository($pdo);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO photos (user_id, title, description, file_path, original_file_path, latitude, longitude, taken_at) VALUES (:user_id, :title, :description, :file_path, :original_file_path, :latitude, :longitude, :taken_at)');
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':file_path' => $data['file_path'],
            ':original_file_path' => $data['original_file_path'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':taken_at' => $data['taken_at'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateLocation(int $photoId, int $userId, ?float $latitude, ?float $longitude, ?string $takenAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE photos SET latitude = :latitude, longitude = :longitude, taken_at = :taken_at WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':taken_at' => $takenAt,
            ':id' => $photoId,
            ':user_id' => $userId,
        ]);
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute([':user_id' => $userId]);
        $photos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateCategories($photos);
    }

    public function findOne(int $photoId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            ':id' => $photoId,
            ':user_id' => $userId,
        ]);

        $photo = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$photo) {
            return null;
        }

        $hydrated = $this->hydrateCategories([$photo]);

        return $hydrated[0] ?? null;
    }

    public function findSharedByToken(string $token): array
    {
        $shareMap = $this->shareMetaByToken($token);
        if (!$shareMap) {
            return [];
        }

        $shareMapId = (int)$shareMap['id'];
        $userId = (int)$shareMap['user_id'];
        $allowedCategories = $shareMap['categories'];

        $photos = $this->findPhotosForShare($userId, $allowedCategories);

        return $this->hydrateCategories($photos);
    }

    public function upsertShareSettings(int $userId, array $categoryIds, bool $regenerateToken = false): string
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, share_token FROM shared_maps WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId]);
            $shareMap = $stmt->fetch(\PDO::FETCH_ASSOC);

            $token = $shareMap ? $shareMap['share_token'] : bin2hex(random_bytes(16));
            if ($regenerateToken || !$shareMap) {
                $token = bin2hex(random_bytes(16));
            }

            if ($shareMap) {
                $updateStmt = $this->pdo->prepare('UPDATE shared_maps SET share_token = :share_token WHERE id = :id');
                $updateStmt->execute([
                    ':share_token' => $token,
                    ':id' => $shareMap['id'],
                ]);
                $shareMapId = (int)$shareMap['id'];
            } else {
                $insertStmt = $this->pdo->prepare('INSERT INTO shared_maps (user_id, share_token) VALUES (:user_id, :share_token)');
                $insertStmt->execute([
                    ':user_id' => $userId,
                    ':share_token' => $token,
                ]);
                $shareMapId = (int)$this->pdo->lastInsertId();
            }

            $this->categories->syncShareCategories($shareMapId, $userId, $categoryIds);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $token;
    }

    public function deleteShare(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM shared_maps WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    }

    public function getShareSettings(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, share_token FROM shared_maps WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $shareMap = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$shareMap) {
            return [
                'url' => null,
                'token' => null,
                'categories' => [],
            ];
        }

        $categoryIds = $this->categories->findShareCategoryIds((int)$shareMap['id']);

        return [
            'url' => rtrim($this->config['base_url'], '/') . '/shared.php?token=' . $shareMap['share_token'],
            'token' => $shareMap['share_token'],
            'categories' => $categoryIds,
        ];
    }

    private function hydrateCategories(array $photos): array
    {
        if (empty($photos)) {
            return $photos;
        }

        $photoIds = array_column($photos, 'id');
        $categoryMap = $this->categories->mapCategoriesForPhotos($photoIds);

        return array_map(function (array $photo) use ($categoryMap) {
            $photoId = (int)$photo['id'];
            $categoryRows = $categoryMap[$photoId] ?? [];
            $photo['categories'] = array_map(static function (array $category) {
                return $category['name'];
            }, $categoryRows);
            $photo['category_ids'] = array_map(static function (array $category) {
                return (int)$category['id'];
            }, $categoryRows);
            $photo['category_details'] = array_map(static function (array $category) {
                return [
                    'id' => (int)$category['id'],
                    'name' => $category['name'],
                    'color' => $category['color'],
                ];
            }, $categoryRows);
            $photo['primary_color'] = $categoryRows[0]['color'] ?? null;
            return $photo;
        }, $photos);
    }

    private function findPhotosForShare(int $userId, array $allowedCategories): array
    {
        if (empty($allowedCategories)) {
            $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE user_id = :user_id ORDER BY created_at DESC');
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $placeholders = implode(', ', array_fill(0, count($allowedCategories), '?'));
        $sql = sprintf(
            'SELECT p.* FROM photos p
             LEFT JOIN photo_categories pc ON pc.photo_id = p.id
             WHERE p.user_id = ?
             GROUP BY p.id
             HAVING SUM(CASE WHEN pc.category_id IN (%s) THEN 1 ELSE 0 END) > 0
             ORDER BY p.created_at DESC',
            $placeholders
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$userId], $allowedCategories));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function shareMetaByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_id, share_token FROM shared_maps WHERE share_token = :token');
        $stmt->execute([':token' => $token]);
        $shareMap = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$shareMap) {
            return null;
        }

        $shareMap['categories'] = $this->categories->findShareCategoryIds((int)$shareMap['id']);

        return $shareMap;
    }

    public function delete(int $photoId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM photos WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':id' => $photoId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
