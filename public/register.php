<?php
require __DIR__ . '/../bootstrap.php';

$pdo = Database::getInstance($config['db'])->getConnection();
$auth = new Auth($pdo);

$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name && $email && $password) {
        if ($auth->register($name, $email, $password)) {
            $success = 'Registrierung erfolgreich. Bitte anmelden.';
        } else {
            $error = 'Registrierung fehlgeschlagen. E-Mail möglicherweise bereits vergeben.';
        }
    } else {
        $error = 'Bitte alle Felder ausfüllen.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geo Photothek - Registrierung</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="auth-container">
    <h1>Geo Photothek</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required>

        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Registrieren</button>
    </form>
    <p>Bereits einen Account? <a href="/login.php">Jetzt anmelden</a></p>
</div>
</body>
</html>
