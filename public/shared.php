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
    $photo['original_image_url'] = !empty($photo['original_file_path'])
        ? '/uploads/' . $photo['original_file_path']
        : $photo['image_url'];
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
        <aside class="photo-detail" data-photo-detail>
            <div class="detail-empty" data-photo-detail-empty>
                <h3>Details</h3>
                <p>Wähle eine Pinnadel oder ein Foto, um Details zu sehen.</p>
            </div>
            <div class="detail-body hidden" data-photo-detail-body>
                <button type="button" class="detail-close" data-photo-detail-close aria-label="Detailansicht schließen">&times;</button>
                <img src="" alt="" data-photo-detail-image>
                <div class="detail-meta">
                    <h3 data-photo-detail-title></h3>
                    <p data-photo-detail-description></p>
                    <dl>
                        <div class="detail-row" data-photo-detail-categories-row>
                            <dt>Kategorien</dt>
                            <dd data-photo-detail-categories></dd>
                        </div>
                        <div class="detail-row" data-photo-detail-coordinates-row>
                            <dt>Koordinaten</dt>
                            <dd data-photo-detail-coordinates></dd>
                        </div>
                        <div class="detail-row" data-photo-detail-date-row>
                            <dt>Aufgenommen am</dt>
                            <dd data-photo-detail-date></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </aside>
    </section>
    <section class="photo-grid">
        <?php if (!empty($sharedCategoryNames)): ?>
            <div class="shared-hint">Freigegebene Kategorien: <?php echo htmlspecialchars(implode(', ', $sharedCategoryNames), ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if ($hasPhotos): ?>
            <?php foreach ($photos as $photo): ?>
                <?php $photoTitleAttr = htmlspecialchars($photo['title'] ?? 'Ohne Titel', ENT_QUOTES); ?>
                <article class="photo-card" data-photo-card data-photo-id="<?php echo (int)$photo['id']; ?>" tabindex="0" role="button" aria-label="Foto-Details anzeigen: <?php echo $photoTitleAttr; ?>">
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
                        <button type="button" class="detail-button" data-photo-trigger>Details anzeigen</button>
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
