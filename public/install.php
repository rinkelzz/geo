<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$configDir = $baseDir . '/config';
$configPath = $configDir . '/config.php';
$configExamplePath = $configDir . '/config.example.php';
$schemaPath = $baseDir . '/database/schema.sql';

if (PHP_SAPI === 'cli') {
    runCli($configPath, $configExamplePath, $schemaPath);
    return;
}

session_start();

$config = loadConfig($configPath, $configExamplePath);
$dbConfig = $config['db'] ?? [];
$mailConfig = $config['mail'] ?? [];
$appConfig = $config['app'] ?? [];
$isDbConfigured = isDbConfigComplete($dbConfig);
$messages = [];
$errors = [];
$sqlResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_config'])) {
        [$config, $isDbConfigured, $messages, $errors] = handleConfigSave($_POST, $config, $configPath, $configDir, $messages, $errors);
        $dbConfig = $config['db'] ?? [];
        $mailConfig = $config['mail'] ?? [];
        $appConfig = $config['app'] ?? [];
    }

    if (isset($_POST['run_install']) && $isDbConfigured) {
        [$messages, $errors, $sqlResults] = runSchema($dbConfig, $schemaPath, $messages, $errors);
    } elseif (isset($_POST['run_install']) && !$isDbConfigured) {
        $errors[] = 'Bitte zuerst gültige Datenbank-Zugangsdaten speichern.';
    }
}

renderHtml($dbConfig, $mailConfig, $appConfig, $isDbConfigured, $messages, $errors, $sqlResults, file_exists($configPath));

function loadConfig(string $configPath, string $fallbackPath): array
{
    if (file_exists($configPath)) {
        /** @var array $config */
        $config = require $configPath;
        return $config;
    }

    /** @var array $fallback */
    $fallback = require $fallbackPath;
    return $fallback;
}

function isDbConfigComplete(array $dbConfig): bool
{
    $required = ['host', 'port', 'database', 'username', 'password'];
    foreach ($required as $key) {
        if (!isset($dbConfig[$key]) || trim((string) $dbConfig[$key]) === '') {
            return false;
        }
    }
    return true;
}

function handleConfigSave(array $input, array $config, string $configPath, string $configDir, array $messages, array $errors): array
{
    $host = trim($input['db_host'] ?? '');
    $port = (int) ($input['db_port'] ?? 3306);
    $database = trim($input['db_database'] ?? '');
    $username = trim($input['db_username'] ?? '');
    $password = trim($input['db_password'] ?? '');
    $charset = trim($input['db_charset'] ?? 'utf8mb4');
    $fromAddress = trim($input['mail_from_address'] ?? '');
    $fromName = trim($input['mail_from_name'] ?? '');
    $adminRecipientsRaw = trim($input['mail_admin_recipients'] ?? '');
    $homeLatitudeRaw = trim($input['app_home_latitude'] ?? '');
    $homeLongitudeRaw = trim($input['app_home_longitude'] ?? '');

    if ($host === '' || $database === '' || $username === '' || $password === '') {
        $errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
        return [$config, false, $messages, $errors];
    }

    if ($fromAddress === '') {
        $errors[] = 'Bitte eine Absender-Adresse für Benachrichtigungen angeben.';
        return [$config, false, $messages, $errors];
    }

    $config['db'] = [
        'host' => $host,
        'port' => $port ?: 3306,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => $charset !== '' ? $charset : 'utf8mb4',
    ];

    $config['mail'] = [
        'from_address' => $fromAddress,
        'from_name' => $fromName,
        'admin_recipients' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $adminRecipientsRaw) ?: []))),
    ];

    $homeLatitude = null;
    $homeLongitude = null;
    if ($homeLatitudeRaw !== '' || $homeLongitudeRaw !== '') {
        if ($homeLatitudeRaw === '' || $homeLongitudeRaw === '') {
            $errors[] = 'Bitte beide Felder für Zuhause-Koordinaten ausfüllen oder leer lassen.';
            return [$config, false, $messages, $errors];
        }
        if (!is_numeric($homeLatitudeRaw) || !is_numeric($homeLongitudeRaw)) {
            $errors[] = 'Koordinaten für Zuhause müssen numerisch sein.';
            return [$config, false, $messages, $errors];
        }
        $homeLatitude = (float) $homeLatitudeRaw;
        $homeLongitude = (float) $homeLongitudeRaw;
        if ($homeLatitude < -90 || $homeLatitude > 90 || $homeLongitude < -180 || $homeLongitude > 180) {
            $errors[] = 'Koordinaten für Zuhause liegen außerhalb des gültigen Bereichs.';
            return [$config, false, $messages, $errors];
        }
    }

    $appConfig = $config['app'] ?? [];
    $appConfig['home_latitude'] = $homeLatitude;
    $appConfig['home_longitude'] = $homeLongitude;
    $config['app'] = $appConfig;

    if (!is_dir($configDir) && !mkdir($configDir, 0775, true) && !is_dir($configDir)) {
        $errors[] = 'Konfigurationsverzeichnis konnte nicht erstellt werden.';
        return [$config, false, $messages, $errors];
    }

    $export = var_export($config, true);
    $content = "<?php\nreturn $export;\n";

    if (file_put_contents($configPath, $content) === false) {
        $errors[] = 'Konfigurationsdatei konnte nicht geschrieben werden.';
        return [$config, false, $messages, $errors];
    }

    $messages[] = 'Konfiguration wurde gespeichert.';
    $isDbConfigured = isDbConfigComplete($config['db'] ?? []);
    return [$config, $isDbConfigured, $messages, $errors];
}

function runSchema(array $dbConfig, string $schemaPath, array $messages, array $errors): array
{
    if (!file_exists($schemaPath)) {
        $errors[] = 'Schema-Datei wurde nicht gefunden.';
        return [$messages, $errors, []];
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        $errors[] = 'Schema-Datei konnte nicht gelesen werden.';
        return [$messages, $errors, []];
    }

    require_once dirname(__DIR__) . '/src/Database.php';

    try {
        $pdo = Database::getInstance($dbConfig)->getConnection();
    } catch (Throwable $e) {
        $errors[] = 'Verbindung zur Datenbank fehlgeschlagen: ' . $e->getMessage();
        return [$messages, $errors, []];
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $schemaSql)));
    $results = [];
    $hadErrors = false;

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
            $results[] = ['statement' => $statement, 'success' => true];
        } catch (Throwable $e) {
            $hadErrors = true;
            $results[] = [
                'statement' => $statement,
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    if ($hadErrors) {
        $errors[] = 'Es sind Fehler beim Ausführen des Schemas aufgetreten. Details siehe unten.';
    } else {
        $messages[] = 'Schema wurde erfolgreich ausgeführt.';
    }

    return [$messages, $errors, $results];
}

function renderHtml(array $dbConfig, array $mailConfig, array $appConfig, bool $isDbConfigured, array $messages, array $errors, array $sqlResults, bool $configExists): void
{
    $title = 'Geo Photothek Installation & Upgrade';
    $baseUrl = htmlspecialchars((($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), ENT_QUOTES, 'UTF-8');
    $deleteNotice = 'Bitte lösche diese Datei nach der Einrichtung aus dem public-Verzeichnis.';
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: 'Segoe UI', Roboto, sans-serif;
            --bg: #f2f5f9;
            --bg-card: #ffffff;
            --border: #d0d6e1;
            --text: #1c2733;
            --accent: #0060df;
            --danger: #c92a2a;
            --success: #0f9d58;
        }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            max-width: 960px;
            width: 100%;
            padding: 32px;
        }
        h1 {
            margin-top: 0;
            font-size: 2rem;
        }
        p.lead {
            margin-top: 0.25rem;
            color: rgba(28, 39, 51, 0.75);
        }
        form {
            margin-top: 24px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }
        input[type="text"],
        input[type="number"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 96, 223, 0.15);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        button {
            border: none;
            border-radius: 999px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        button.primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            box-shadow: 0 12px 24px rgba(30, 60, 114, 0.35);
        }
        button.secondary {
            background: #e2e8f0;
            color: #1c2733;
        }
        button:hover {
            transform: translateY(-1px);
        }
        .notice {
            border-radius: 12px;
            padding: 16px 18px;
            margin-top: 16px;
            background: rgba(0, 96, 223, 0.08);
            border: 1px solid rgba(0, 96, 223, 0.15);
        }
        .messages, .errors {
            border-radius: 12px;
            padding: 16px 18px;
            margin-top: 16px;
        }
        .messages {
            background: rgba(15, 157, 88, 0.1);
            border: 1px solid rgba(15, 157, 88, 0.2);
        }
        .errors {
            background: rgba(201, 42, 42, 0.1);
            border: 1px solid rgba(201, 42, 42, 0.2);
        }
        ul {
            margin: 0;
            padding-left: 20px;
        }
        .sql-results {
            margin-top: 24px;
            border-collapse: collapse;
            width: 100%;
            font-size: 0.95rem;
        }
        .sql-results th,
        .sql-results td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            vertical-align: top;
        }
        .sql-results th {
            text-align: left;
            font-weight: 600;
        }
        .sql-results tr:last-child td {
            border-bottom: none;
        }
        .sql-results .ok {
            color: var(--success);
            font-weight: 600;
        }
        .sql-results .fail {
            color: var(--danger);
            font-weight: 600;
        }
        .footer {
            margin-top: 32px;
            font-size: 0.85rem;
            color: rgba(28, 39, 51, 0.65);
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(15, 23, 42, 0.05);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        small {
            display: block;
            margin-top: 4px;
            color: rgba(28, 39, 51, 0.6);
        }
        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }
            button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <header>
            <h1><?= $title ?></h1>
            <p class="lead">Führe die Datenbankinstallation oder -aktualisierung für deine Geo Photothek durch.</p>
            <div class="notice">
                <strong>Hinweis:</strong> <?= htmlspecialchars($deleteNotice, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </header>

        <?php if ($messages): ?>
            <section class="messages">
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($errors): ?>
            <section class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section>
            <h2>1. Konfiguration</h2>
            <p>Hinterlege die Verbindungsdaten für deine MySQL/MariaDB-Datenbank, die Absenderdaten für Benachrichtigungs-E-Mails sowie optional die Koordinaten deines Zuhauses. Die Angaben werden in <code>config/config.php</code> gespeichert.</p>
            <form method="post" class="config-form">
                <div class="grid">
                    <div>
                        <label for="db_host">Host *</label>
                        <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($dbConfig['host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div>
                        <label for="db_port">Port *</label>
                        <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars((string)($dbConfig['port'] ?? 3306), ENT_QUOTES, 'UTF-8') ?>" min="1" required>
                    </div>
                    <div>
                        <label for="db_database">Datenbank *</label>
                        <input type="text" id="db_database" name="db_database" value="<?= htmlspecialchars($dbConfig['database'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div>
                        <label for="db_username">Benutzername *</label>
                        <input type="text" id="db_username" name="db_username" value="<?= htmlspecialchars($dbConfig['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div>
                        <label for="db_password">Passwort *</label>
                        <input type="password" id="db_password" name="db_password" value="<?= htmlspecialchars($dbConfig['password'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div>
                        <label for="db_charset">Zeichensatz</label>
                        <input type="text" id="db_charset" name="db_charset" value="<?= htmlspecialchars($dbConfig['charset'] ?? 'utf8mb4', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="mail_from_address">Absender-Adresse *</label>
                        <input type="text" id="mail_from_address" name="mail_from_address" value="<?= htmlspecialchars($mailConfig['from_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div>
                        <label for="mail_from_name">Absender-Name</label>
                        <input type="text" id="mail_from_name" name="mail_from_name" value="<?= htmlspecialchars($mailConfig['from_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="mail_admin_recipients">Admin-Benachrichtigungen</label>
                        <input type="text" id="mail_admin_recipients" name="mail_admin_recipients" placeholder="admin@example.com, weitere@example.com" value="<?= htmlspecialchars(is_array($mailConfig['admin_recipients'] ?? null) ? implode(', ', $mailConfig['admin_recipients']) : (string)($mailConfig['admin_recipients'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <small>Mehrere Adressen durch Komma oder Zeilenumbruch trennen.</small>
                    </div>
                    <div>
                        <label for="app_home_latitude">Zuhause – Breitengrad</label>
                        <input type="text" id="app_home_latitude" name="app_home_latitude" placeholder="z. B. 48.137154" value="<?= htmlspecialchars(isset($appConfig['home_latitude']) ? (string) $appConfig['home_latitude'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="app_home_longitude">Zuhause – Längengrad</label>
                        <input type="text" id="app_home_longitude" name="app_home_longitude" placeholder="z. B. 11.576124" value="<?= htmlspecialchars(isset($appConfig['home_longitude']) ? (string) $appConfig['home_longitude'] : '', ENT_QUOTES, 'UTF-8') ?>">
                        <small>Optional: Wird für die Luftlinien-Anzeige im Dashboard genutzt.</small>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" name="save_config" class="primary">Konfiguration speichern</button>
                    <?php if ($configExists): ?>
                        <span class="badge">Speicherort: config/config.php</span>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section>
            <h2>2. Schema anwenden</h2>
            <p>Installiere oder aktualisiere das Datenbankschema aus <code>database/schema.sql</code>.</p>
            <form method="post">
                <div class="actions">
                    <button type="submit" name="run_install" class="secondary" <?= $isDbConfigured ? '' : 'disabled' ?>>Schema ausführen</button>
                </div>
            </form>
            <?php if (!$isDbConfigured): ?>
                <p class="lead">Bitte speichere zuerst gültige Datenbank-Zugangsdaten.</p>
            <?php endif; ?>
        </section>

        <?php if ($sqlResults): ?>
            <section>
                <h2>Protokoll</h2>
                <table class="sql-results">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Anweisung</th>
                            <th>Nachricht</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sqlResults as $result): ?>
                            <tr>
                                <td class="<?= $result['success'] ? 'ok' : 'fail' ?>"><?= $result['success'] ? 'OK' : 'Fehler' ?></td>
                                <td><code><?= htmlspecialchars($result['statement'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= isset($result['message']) ? htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <footer class="footer">
            &copy; <?= date('Y') ?> Geo Photothek – Admin-Werkzeug. URL: <?= $baseUrl ?>/install.php
        </footer>
    </main>
</body>
</html>
<?php
}

function runCli(string $configPath, string $fallbackPath, string $schemaPath): void
{
    $config = loadConfig($configPath, $fallbackPath);
    $dbConfig = $config['db'] ?? [];

    if (!isDbConfigComplete($dbConfig)) {
        fwrite(STDERR, "Konfiguration unvollständig. Bitte install.php im Browser aufrufen, um die Zugangsdaten zu hinterlegen." . PHP_EOL);
        exit(1);
    }

    [$messages, $errors, $results] = runSchema($dbConfig, $schemaPath, [], []);

    foreach ($messages as $message) {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    if ($errors) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . PHP_EOL);
        }
        foreach ($results as $result) {
            fwrite(STDERR, sprintf('[%s] %s%s', $result['success'] ? 'OK' : 'ERR', $result['statement'], isset($result['message']) ? ' — ' . $result['message'] : '') . PHP_EOL);
        }
        exit(1);
    }

    exit(0);
}
