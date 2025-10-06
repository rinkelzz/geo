<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $respond(405, ['error' => 'Method not allowed', 'results' => []]);
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    $respond(400, ['error' => 'Leere Anfrage', 'results' => []]);
}

$limit = 5;
$endpoint = 'https://nominatim.openstreetmap.org/search';
$params = http_build_query([
    'format' => 'jsonv2',
    'limit' => $limit,
    'q' => $query,
]);

$contactAddress = trim($config['mail']['from_address'] ?? '');
$baseUrl = trim($config['app']['base_url'] ?? '');
if ($contactAddress !== '') {
    $userAgent = sprintf('GeoPhotothek/1.0 (%s)', $contactAddress);
} elseif ($baseUrl !== '') {
    $userAgent = sprintf('GeoPhotothek/1.0 (%s)', $baseUrl);
} else {
    $userAgent = 'GeoPhotothek/1.0';
}

$ch = curl_init($endpoint . '?' . $params);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
    ],
]);

$responseBody = curl_exec($ch);

if ($responseBody === false) {
    curl_close($ch);
    $respond(502, ['error' => 'Geokodierungsdienst nicht erreichbar', 'results' => []]);
}

$statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($statusCode < 200 || $statusCode >= 300) {
    $respond(502, ['error' => 'Geokodierung fehlgeschlagen', 'results' => []]);
}

$decoded = json_decode($responseBody, true);
if (!is_array($decoded)) {
    $respond(502, ['error' => 'Ungültige Antwort vom Geokodierungsdienst', 'results' => []]);
}

$results = [];
foreach ($decoded as $item) {
    if (!is_array($item)) {
        continue;
    }
    $lat = isset($item['lat']) ? (string)$item['lat'] : null;
    $lon = isset($item['lon']) ? (string)$item['lon'] : null;
    if ($lat === null || $lon === null) {
        continue;
    }
    $displayName = trim((string)($item['display_name'] ?? ''));
    $results[] = [
        'lat' => $lat,
        'lon' => $lon,
        'display_name' => $displayName !== '' ? $displayName : sprintf('%s, %s', $lat, $lon),
    ];
    if (count($results) >= $limit) {
        break;
    }
}

$respond(200, ['results' => $results]);
