<?php
require __DIR__ . '/../bootstrap.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(404);
    echo 'Freigabe nicht gefunden.';
    exit;
}

$db = Database::getInstance($config['db'])->getConnection();
$categoryRepo = new CategoryRepository($db);
$photoRepo = new PhotoRepository($db, $config['app'], $categoryRepo);
$shareMeta = $photoRepo->shareMetaByToken($token);

if (!$shareMeta) {
    http_response_code(404);
    echo 'Freigabe nicht gefunden.';
    exit;
}

$photos = $photoRepo->findSharedByToken($token);
$sharedCategoryNames = $categoryRepo->findNamesByIds($shareMeta['categories']);
$photosForJs = array_map(static function (array $photo) {
    $photo['image_url'] = '/uploads/' . $photo['file_path'];
    return $photo;
}, $photos);

$hasPhotos = !empty($photos);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geteilte Geo Photothek</title>
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" defer></script>
    <script>window.PHOTOS = <?php echo json_encode($photosForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
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
        <?php if (!empty($sharedCategoryNames)): ?>
            <div class="shared-hint">Freigegebene Kategorien: <?php echo htmlspecialchars(implode(', ', $sharedCategoryNames), ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if ($hasPhotos): ?>
            <?php foreach ($photos as $photo): ?>
                <article class="photo-card">
                    <img src="/uploads/<?php echo htmlspecialchars($photo['file_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($photo['title'] ?? 'Foto', ENT_QUOTES); ?>">
                    <div class="photo-meta">
                        <h3><?php echo htmlspecialchars($photo['title'] ?? 'Ohne Titel', ENT_QUOTES); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($photo['description'] ?? '', ENT_QUOTES)); ?></p>
                        <?php if (!empty($photo['categories'])): ?>
                            <p class="photo-categories">Kategorien: <?php echo htmlspecialchars(implode(', ', $photo['categories']), ENT_QUOTES); ?></p>
                        <?php endif; ?>
                        <?php if ($photo['latitude'] && $photo['longitude']): ?>
                            <p>Koordinaten: <?php echo round($photo['latitude'], 5) . ', ' . round($photo['longitude'], 5); ?></p>
                        <?php endif; ?>
                        <?php if ($photo['taken_at']): ?>
                            <p>Aufgenommen am: <?php echo htmlspecialchars($photo['taken_at'], ENT_QUOTES); ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="hint">Für diese Freigabe wurden noch keine passenden Fotos veröffentlicht.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
