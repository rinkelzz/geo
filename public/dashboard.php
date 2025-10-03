<?php
require __DIR__ . '/../bootstrap.php';

$db = Database::getInstance($config['db'])->getConnection();
$auth = new Auth($db);
$auth->requireLogin();
$user = $auth->user();

$photoRepo = new PhotoRepository($db, $config['app']);
$photoService = new PhotoService($photoRepo, $config['app']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    try {
        $photo = $photoService->handleUpload($_FILES['photo'], $_POST, $user['id']);
        $success = 'Foto erfolgreich hochgeladen!';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_POST['share_map'])) {
    $photoRepo->upsertShareToken($user['id']);
}

$photos = $photoRepo->findByUser($user['id']);
$shareUrl = $photoRepo->getShareUrl($user['id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geo Photothek - Dashboard</title>
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" defer></script>
    <script>window.PHOTOS = <?php echo json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script src="/map.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="brand">Geo Photothek</div>
    <div class="user-info">
        <span>Hallo <?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?></span>
        <a class="button" href="/logout.php">Logout</a>
    </div>
</header>
<main class="layout">
    <section class="panel">
        <h2>Foto hochladen</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <label for="title">Titel</label>
            <input type="text" name="title" id="title" placeholder="Titel des Fotos">

            <label for="description">Beschreibung</label>
            <textarea name="description" id="description" rows="3" placeholder="Beschreibung"></textarea>

            <label for="photo">Foto</label>
            <input type="file" name="photo" id="photo" accept="image/*" required>

            <button type="submit">Hochladen</button>
        </form>
        <form method="post" class="share-form">
            <button type="submit" name="share_map" value="1">Weltkugel teilen</button>
        </form>
        <?php if ($shareUrl): ?>
            <div class="share-link">
                <span>Freigabelink:</span>
                <input type="text" value="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES); ?>" readonly onclick="this.select();">
            </div>
        <?php endif; ?>
    </section>
    <section class="map-panel">
        <div id="map"></div>
    </section>
</main>
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
</body>
</html>
