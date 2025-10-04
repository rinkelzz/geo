<?php

class CategoryRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $name, ?string $color = null): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $normalizedColor = $this->normalizeColor($color);
        if ($normalizedColor === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (:user_id, :name, :color)');
        try {
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $name,
                ':color' => $normalizedColor,
            ]);
        } catch (\PDOException $e) {
            // Duplicate entries are ignored gracefully
            return null;
        }

        return (int)$this->pdo->lastInsertId();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, color FROM categories WHERE user_id = :user_id ORDER BY name');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateColor(int $categoryId, int $userId, string $color): bool
    {
        $normalizedColor = $this->normalizeColor($color);
        if ($normalizedColor === null) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE categories SET color = :color WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':color' => $normalizedColor,
            ':id' => $categoryId,
            ':user_id' => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $checkStmt = $this->pdo->prepare('SELECT color FROM categories WHERE id = :id AND user_id = :user_id');
        $checkStmt->execute([
            ':id' => $categoryId,
            ':user_id' => $userId,
        ]);

        $existing = $checkStmt->fetchColumn();
        if ($existing === false) {
            return false;
        }

        return strtoupper((string)$existing) === $normalizedColor;
    }

    public function filterIdsForUser(int $userId, array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        $sql = sprintf(
            'SELECT id FROM categories WHERE user_id = ? AND id IN (%s)',
            $placeholders
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$userId], $categoryIds));

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function syncPhotoCategories(int $photoId, int $userId, array $categoryIds): void
    {
        $allowedIds = $this->filterIdsForUser($userId, $categoryIds);

        $manageTransaction = !$this->pdo->inTransaction();
        if ($manageTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM photo_categories WHERE photo_id = :photo_id');
            $deleteStmt->execute([':photo_id' => $photoId]);

            if (!empty($allowedIds)) {
                $insertStmt = $this->pdo->prepare('INSERT INTO photo_categories (photo_id, category_id) VALUES (:photo_id, :category_id)');
                foreach ($allowedIds as $categoryId) {
                    $insertStmt->execute([
                        ':photo_id' => $photoId,
                        ':category_id' => $categoryId,
                    ]);
                }
            }

            if ($manageTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function mapCategoriesForPhotos(array $photoIds): array
    {
        $photoIds = array_values(array_unique(array_map('intval', $photoIds)));
        if (empty($photoIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($photoIds), '?'));
        $sql = sprintf(
            'SELECT pc.photo_id, c.id, c.name, c.color FROM photo_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.photo_id IN (%s) ORDER BY c.name',
            $placeholders
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($photoIds);

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $photoId = (int)$row['photo_id'];
            if (!isset($result[$photoId])) {
                $result[$photoId] = [];
            }
            $result[$photoId][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
            ];
        }

        return $result;
    }

    public function syncShareCategories(int $shareMapId, int $userId, array $categoryIds): void
    {
        $allowedIds = $this->filterIdsForUser($userId, $categoryIds);

        $manageTransaction = !$this->pdo->inTransaction();
        if ($manageTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM shared_map_categories WHERE shared_map_id = :shared_map_id');
            $deleteStmt->execute([':shared_map_id' => $shareMapId]);

            if (!empty($allowedIds)) {
                $insertStmt = $this->pdo->prepare('INSERT INTO shared_map_categories (shared_map_id, category_id) VALUES (:shared_map_id, :category_id)');
                foreach ($allowedIds as $categoryId) {
                    $insertStmt->execute([
                        ':shared_map_id' => $shareMapId,
                        ':category_id' => $categoryId,
                    ]);
                }
            }

            if ($manageTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findShareCategoryIds(int $shareMapId): array
    {
        $stmt = $this->pdo->prepare('SELECT category_id FROM shared_map_categories WHERE shared_map_id = :shared_map_id ORDER BY category_id');
        $stmt->execute([':shared_map_id' => $shareMapId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function findNamesByIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        $sql = sprintf('SELECT name FROM categories WHERE id IN (%s) ORDER BY name', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($categoryIds);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function normalizeColor(?string $color): ?string
    {
        $color = trim((string)$color);
        if ($color === '') {
            return '#3388FF';
        }

        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches)) {
            return null;
        }

        return '#' . strtoupper($matches[1]);
    }
}
