<?php

class Auth
{
    private \PDO $pdo;
    private ?string $lastError = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function register(string $name, string $email, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userRepo = new UserRepository($this->pdo);
        $isFirstUser = $userRepo->countAll() === 0;

        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin, is_approved) VALUES (:name, :email, :password, :is_admin, :is_approved)');
        try {
            return $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hash,
                ':is_admin' => $isFirstUser ? 1 : 0,
                ':is_approved' => $isFirstUser ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function attempt(string $email, string $password): bool
    {
        $this->lastError = null;
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            $this->lastError = 'Anmeldung fehlgeschlagen.';
            return false;
        }
        if (password_verify($password, $user['password_hash'])) {
            if (!(bool)$user['is_approved']) {
                $this->lastError = 'Dein Account ist noch nicht freigeschaltet. Bitte den Admin kontaktieren.';
                return false;
            }
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (bool)$user['is_admin'],
            ];
            return true;
        }
        $this->lastError = 'Anmeldung fehlgeschlagen.';
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

    public function isAdmin(): bool
    {
        $user = $this->user();
        return (bool)($user['is_admin'] ?? false);
    }

    public function requireLogin(): void
    {
        if (!$this->user()) {
            header('Location: /login.php');
            exit;
        }
    }

    public function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo 'Zugriff verweigert';
            exit;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
