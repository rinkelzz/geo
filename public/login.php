<?php
require __DIR__ . '/../bootstrap.php';

$pdo = Database::getInstance($config['db'])->getConnection();
$auth = new Auth($pdo);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($auth->attempt($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: /dashboard.php');
        exit;
    }
    $error = $auth->lastError() ?? 'Anmeldung fehlgeschlagen.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geo Photothek - Login</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <div class="auth-container">
        <h1>Geo Photothek</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
        <p>Noch keinen Account? <a href="/register.php">Jetzt registrieren</a></p>
    </div>
</body>
</html>
