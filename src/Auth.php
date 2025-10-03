<?php

class Auth
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function register(string $name, string $email, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password)');
        try {
            return $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hash,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ];
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function requireLogin(): void
    {
        if (!$this->user()) {
            header('Location: /login.php');
            exit;
        }
    }
}
