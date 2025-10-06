<?php
require __DIR__ . '/../bootstrap.php';

$photoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($photoId <= 0) {
    http_response_code(404);
    exit('Foto nicht gefunden.');
}

$variant = $_GET['variant'] ?? 'display';
$variant = $variant === 'original' ? 'original' : 'display';
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : null;

$db = Database::getInstance($config['db'])->getConnection();
$categoryRepo = new CategoryRepository($db);
$photoRepo = new PhotoRepository($db, $config['app'], $categoryRepo);
$photo = null;

if ($token !== null && $token !== '') {
    $photo = $photoRepo->findOneForShare($photoId, $token);
} else {
    $auth = new Auth($db);
    $user = $auth->user();
    if (!$user) {
        http_response_code(403);
        exit('Nicht autorisiert.');
    }
    $photo = $photoRepo->findOne($photoId, (int) $user['id']);
}

if (!$photo) {
    http_response_code(404);
    exit('Foto nicht gefunden.');
}

$baseDir = rtrim((string) ($config['app']['upload_dir'] ?? ''), '/');
$originalBaseDir = rtrim((string) ($config['app']['original_upload_dir'] ?? ($baseDir !== '' ? $baseDir . '/originals' : '')), '/');

$relativeDisplay = ltrim((string) ($photo['file_path'] ?? ''), '/');
$relativeOriginal = ltrim((string) ($photo['original_file_path'] ?? ''), '/');

$paths = [];
if ($variant === 'original' && $relativeOriginal !== '') {
    if ($baseDir !== '') {
        $paths[] = $baseDir . '/' . $relativeOriginal;
    }
    if ($originalBaseDir !== '') {
        $paths[] = $originalBaseDir . '/' . $relativeOriginal;
        $paths[] = $originalBaseDir . '/' . basename($relativeOriginal);
    }
}

if ($baseDir !== '' && $relativeDisplay !== '') {
    $paths[] = $baseDir . '/' . $relativeDisplay;
}
if ($originalBaseDir !== '' && $relativeDisplay !== '') {
    $paths[] = $originalBaseDir . '/' . $relativeDisplay;
}

$paths = array_values(array_unique(array_filter($paths, static function ($path) {
    return $path !== '';
})));

$filePath = null;
foreach ($paths as $candidate) {
    if (is_file($candidate)) {
        $filePath = $candidate;
        break;
    }
}

if (!$filePath) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
$filename = basename($filePath);
$safeFilename = str_replace(["\"", "\\", "\r", "\n"], '', $filename);
$filesize = filesize($filePath) ?: 0;

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $filesize);
header('Content-Disposition: inline; filename="' . $safeFilename . '"; filename*=UTF-8\'' . rawurlencode($safeFilename));
header('Cache-Control: private, max-age=86400');
header('Pragma: private');
header('X-Content-Type-Options: nosniff');

$resource = fopen($filePath, 'rb');
if ($resource !== false) {
    fpassthru($resource);
    fclose($resource);
} else {
    readfile($filePath);
}

exit;
