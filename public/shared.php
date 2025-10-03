<?php
require __DIR__ . '/../bootstrap.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(404);
    echo 'Freigabe nicht gefunden.';
    exit;
}

$db = Database::getInstance($config['db'])->getConnection();
$photoRepo = new PhotoRepository($db, $config['app']);
$photos = $photoRepo->findSharedByToken($token);

if (!$photos) {
    http_response_code(404);
    echo 'Freigabe nicht gefunden oder keine Fotos vorhanden.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geteilte Geo Photothek</title>
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" defer></script>
    <script>window.PHOTOS = <?php echo json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script src="/map.js" defer></script>
</head>
<body class="shared-view">
<header class="topbar">
    <div class="brand">Geo Photothek (Freigabe)</div>
</header>
<main class="shared-layout">
    <section class="map-panel">
        <div id="map"></div>
    </section>
    <section class="photo-grid">
        <?php foreach ($photos as $photo): ?>
            <article class="photo-card">
                <img src="/uploads/<?php echo htmlspecialchars($photo['file_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($photo['title'] ?? 'Foto', ENT_QUOTES); ?>">
                <div class="photo-meta">
                    <h3><?php echo htmlspecialchars($photo['title'] ?? 'Ohne Titel', ENT_QUOTES); ?></h3>
                    <p><?php echo nl2br(htmlspecialchars($photo['description'] ?? '', ENT_QUOTES)); ?></p>
                    <?php if ($photo['latitude'] && $photo['longitude']): ?>
                        <p>Koordinaten: <?php echo round($photo['latitude'], 5) . ', ' . round($photo['longitude'], 5); ?></p>
                    <?php endif; ?>
                    <?php if ($photo['taken_at']): ?>
                        <p>Aufgenommen am: <?php echo htmlspecialchars($photo['taken_at'], ENT_QUOTES); ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
