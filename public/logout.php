<?php
require __DIR__ . '/../bootstrap.php';

$auth = new Auth(Database::getInstance($config['db'])->getConnection());
$auth->logout();
header('Location: /login.php');
exit;
