<?php
require __DIR__ . '/../bootstrap.php';

$auth = new Auth(Database::getInstance($config['db'])->getConnection());
if ($auth->user()) {
    header('Location: /dashboard.php');
    exit;
}
header('Location: /login.php');
exit;
