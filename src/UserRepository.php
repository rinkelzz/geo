<?php

class UserRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');
        return (int)$stmt->fetchColumn();
    }

    public function findPending(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, email, created_at FROM users WHERE is_approved = 0 ORDER BY created_at');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function approve(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_approved = 1 WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}
