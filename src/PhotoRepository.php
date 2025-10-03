<?php

class PhotoRepository
{
    private \PDO $pdo;
    private array $config;

    public function __construct(\PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO photos (user_id, title, description, file_path, latitude, longitude, taken_at) VALUES (:user_id, :title, :description, :file_path, :latitude, :longitude, :taken_at)');
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':file_path' => $data['file_path'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':taken_at' => $data['taken_at'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM photos WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findSharedByToken(string $token): array
    {
        $stmt = $this->pdo->prepare('SELECT p.* FROM photos p JOIN shared_maps s ON p.user_id = s.user_id WHERE s.share_token = :token');
        $stmt->execute([':token' => $token]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function upsertShareToken(int $userId): string
    {
        $token = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare('INSERT INTO shared_maps (user_id, share_token) VALUES (:user_id, :share_token)
            ON DUPLICATE KEY UPDATE share_token = VALUES(share_token), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([
            ':user_id' => $userId,
            ':share_token' => $token,
        ]);
        return $token;
    }

    public function getShareUrl(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT share_token FROM shared_maps WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $token = $stmt->fetchColumn();
        if (!$token) {
            return null;
        }
        return rtrim($this->config['base_url'], '/') . '/shared.php?token=' . $token;
    }
}
