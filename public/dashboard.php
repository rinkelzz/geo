<?php
require __DIR__ . '/../bootstrap.php';

$db = Database::getInstance($config['db'])->getConnection();
$auth = new Auth($db);
$auth->requireLogin();
$user = $auth->user();

$categoryRepo = new CategoryRepository($db);
$photoRepo = new PhotoRepository($db, $config['app'], $categoryRepo);
$photoService = new PhotoService($photoRepo, $categoryRepo, $config['app']);
$userRepo = new UserRepository($db);

$error = null;
$success = null;
$categoryError = null;
$categorySuccess = null;
$shareSuccess = null;
$shareError = null;
$adminSuccess = null;
$adminError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    switch ($action) {
        case 'upload':
            if (isset($_FILES['photo'])) {
                try {
                    $categoryIds = array_map('intval', $_POST['categories'] ?? []);
                    $photoService->handleUpload($_FILES['photo'], $_POST, $user['id'], $categoryIds);
                    $success = 'Foto erfolgreich hochgeladen!';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
            break;
        case 'add_category':
            $name = trim($_POST['new_category'] ?? '');
            if ($name === '') {
                $categoryError = 'Bitte einen Kategorienamen eingeben.';
            } else {
                $createdId = $categoryRepo->create($user['id'], $name);
                if ($createdId) {
                    $categorySuccess = 'Kategorie hinzugefügt.';
                } else {
                    $categoryError = 'Kategorie konnte nicht gespeichert werden (existiert sie bereits?).';
                }
            }
            break;
        case 'share_update':
            try {
                $categoryIds = array_map('intval', $_POST['share_categories'] ?? []);
                $photoRepo->upsertShareSettings($user['id'], $categoryIds, false);
                $shareSuccess = 'Freigabe gespeichert.';
            } catch (Throwable $e) {
                $shareError = $e->getMessage();
            }
            break;
        case 'share_regenerate':
            try {
                $categoryIds = array_map('intval', $_POST['share_categories'] ?? []);
                $photoRepo->upsertShareSettings($user['id'], $categoryIds, true);
                $shareSuccess = 'Neuer Freigabelink erstellt.';
            } catch (Throwable $e) {
                $shareError = $e->getMessage();
            }
            break;
        case 'share_delete':
            $photoRepo->deleteShare($user['id']);
            $shareSuccess = 'Freigabelink entfernt.';
            break;
        case 'approve_user':
            if ($auth->isAdmin()) {
                $userIdToApprove = (int)($_POST['user_id'] ?? 0);
                if ($userIdToApprove > 0) {
                    $userRepo->approve($userIdToApprove);
                    $adminSuccess = 'Benutzer erfolgreich freigeschaltet.';
                }
            } else {
                $adminError = 'Keine Berechtigung.';
            }
            break;
    }
}

$categories = $categoryRepo->findByUser($user['id']);
$photos = $photoRepo->findByUser($user['id']);
$shareSettings = $photoRepo->getShareSettings($user['id']);
$pendingUsers = $auth->isAdmin() ? $userRepo->findPending() : [];

$photosForJs = array_map(static function (array $photo) {
    $photo['image_url'] = '/uploads/' . $photo['file_path'];
    return $photo;
}, $photos);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Geo Photothek - Dashboard</title>
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" defer></script>
    <script>window.PHOTOS = <?php echo json_encode($photosForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
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
            <input type="hidden" name="action" value="upload">
            <label for="title">Titel</label>
            <input type="text" name="title" id="title" placeholder="Titel des Fotos">

            <label for="description">Beschreibung</label>
            <textarea name="description" id="description" rows="3" placeholder="Beschreibung"></textarea>

            <label for="photo">Foto</label>
            <input type="file" name="photo" id="photo" accept="image/*" required>

            <?php if (!empty($categories)): ?>
                <fieldset class="category-select">
                    <legend>Kategorien</legend>
                    <?php foreach ($categories as $category): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="categories[]" value="<?php echo (int)$category['id']; ?>">
                            <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php else: ?>
                <p class="hint">Noch keine Kategorien vorhanden. Du kannst unten welche hinzufügen.</p>
            <?php endif; ?>

            <button type="submit">Hochladen</button>
        </form>
        <section class="category-manager">
            <h3>Kategorie hinzufügen</h3>
            <?php if ($categoryError): ?>
                <div class="error"><?php echo htmlspecialchars($categoryError, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <?php if ($categorySuccess): ?>
                <div class="success"><?php echo htmlspecialchars($categorySuccess, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <form method="post" class="category-form">
                <input type="hidden" name="action" value="add_category">
                <label for="new_category">Name der Kategorie</label>
                <input type="text" id="new_category" name="new_category" placeholder="z. B. Männerurlaub">
                <button type="submit">Kategorie speichern</button>
            </form>
        </section>
        <section class="share">
            <h3>Weltkugel teilen</h3>
            <?php if ($shareError): ?>
                <div class="error"><?php echo htmlspecialchars($shareError, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <?php if ($shareSuccess): ?>
                <div class="success"><?php echo htmlspecialchars($shareSuccess, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <form method="post" class="share-form">
                <input type="hidden" name="action" value="share_update">
                <p>Wähle, welche Kategorien über den Freigabelink sichtbar sein sollen.</p>
                <div class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="share_categories[]" value="<?php echo (int)$category['id']; ?>" <?php echo in_array((int)$category['id'], $shareSettings['categories'], true) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="hint">Ohne Auswahl werden alle Fotos geteilt.</p>
                <button type="submit">Freigabe speichern</button>
            </form>
            <?php if ($shareSettings['token']): ?>
            <form method="post" class="share-form inline">
                <input type="hidden" name="action" value="share_regenerate">
                <?php foreach ($shareSettings['categories'] as $categoryId): ?>
                    <input type="hidden" name="share_categories[]" value="<?php echo (int)$categoryId; ?>">
                <?php endforeach; ?>
                <button type="submit">Neuen Link erzeugen</button>
            </form>
            <form method="post" class="share-form inline">
                <input type="hidden" name="action" value="share_delete">
                <button type="submit" class="danger">Freigabelink löschen</button>
            </form>
            <?php endif; ?>
            <?php if ($shareSettings['url']): ?>
                <div class="share-link">
                    <span>Aktueller Link:</span>
                    <input type="text" value="<?php echo htmlspecialchars($shareSettings['url'], ENT_QUOTES); ?>" readonly onclick="this.select();">
                </div>
            <?php endif; ?>
        </section>
        <?php if ($auth->isAdmin()): ?>
            <section class="admin-approval">
                <h3>Benutzerfreigaben</h3>
                <?php if ($adminError): ?>
                    <div class="error"><?php echo htmlspecialchars($adminError, ENT_QUOTES); ?></div>
                <?php endif; ?>
                <?php if ($adminSuccess): ?>
                    <div class="success"><?php echo htmlspecialchars($adminSuccess, ENT_QUOTES); ?></div>
                <?php endif; ?>
                <?php if (empty($pendingUsers)): ?>
                    <p>Keine Benutzer warten auf Freigabe.</p>
                <?php else: ?>
                    <ul class="pending-users">
                        <?php foreach ($pendingUsers as $pending): ?>
                            <li>
                                <div>
                                    <strong><?php echo htmlspecialchars($pending['name'], ENT_QUOTES); ?></strong><br>
                                    <span><?php echo htmlspecialchars($pending['email'], ENT_QUOTES); ?></span>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="action" value="approve_user">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$pending['id']; ?>">
                                    <button type="submit">Freigeben</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
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
</section>
</body>
</html>
