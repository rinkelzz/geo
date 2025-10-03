<?php
require __DIR__ . '/bootstrap.php';

$schemaPath = __DIR__ . '/database/schema.sql';

if (!file_exists($schemaPath)) {
    $message = 'Schema-Datei wurde nicht gefunden.';
    output($message, false);
    exit(1);
}

try {
    $pdo = Database::getInstance($config['db'])->getConnection();
    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        throw new RuntimeException('Schema-Datei konnte nicht gelesen werden.');
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $schemaSql)));

    if (!$statements) {
        throw new RuntimeException('Keine SQL-Anweisungen gefunden.');
    }

    $pdo->beginTransaction();
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    $pdo->commit();

    output('Installation erfolgreich abgeschlossen.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    output('Installation fehlgeschlagen: ' . $e->getMessage(), false);
    exit(1);
}

function output(string $message, bool $success = true): void
{
    $isCli = PHP_SAPI === 'cli';

    if ($isCli) {
        fwrite($success ? STDOUT : STDERR, $message . PHP_EOL);
        return;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
}
