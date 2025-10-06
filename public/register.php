<?php
require __DIR__ . '/../bootstrap.php';

$pdo = Database::getInstance($config['db'])->getConnection();
$auth = new Auth($pdo);
$userRepo = new UserRepository($pdo);
$mailer = new Mailer($config['mail'] ?? []);

$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name && $email && $password) {
        $isFirstUser = $userRepo->countAll() === 0;
        if ($auth->register($name, $email, $password)) {
            if ($isFirstUser) {
                $success = 'Registrierung erfolgreich. Du kannst dich jetzt anmelden.';
            } else {
                $success = 'Registrierung erfolgreich. Bitte warte auf die Freigabe durch einen Admin.';
                if ($mailer->hasSender() && $mailer->hasAdminRecipients()) {
                    $dashboardUrl = rtrim($config['app']['base_url'] ?? '', '/') . '/dashboard.php#approvals';
                    $subject = 'Neue Registrierung in der Geo Photothek';
                    $body = "Hallo Admin,\n\n" .
                        "es hat sich ein neuer Benutzer registriert und wartet auf die Freigabe.\n\n" .
                        "Name: {$name}\n" .
                        "E-Mail: {$email}\n" .
                        ($dashboardUrl !== '/dashboard.php#approvals' && $dashboardUrl !== ''
                            ? "\nZum Freischalten: {$dashboardUrl}\n"
                            : '') .
                        "\nViele Grüße";
                    $mailer->notifyAdmins($subject, $body);
                }
            }
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
