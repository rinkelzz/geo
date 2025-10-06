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
$mailer = new Mailer($config['mail'] ?? []);

$error = null;
$success = null;
$photoError = null;
$photoSuccess = null;
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
            $color = trim($_POST['new_category_color'] ?? '');
            if ($name === '') {
                $categoryError = 'Bitte einen Kategorienamen eingeben.';
            } elseif ($color !== '' && !preg_match('/^#([0-9a-fA-F]{6})$/', $color)) {
                $categoryError = 'Bitte eine gültige Farbe im Format #RRGGBB wählen.';
            } else {
                $createdId = $categoryRepo->create($user['id'], $name, $color);
                if ($createdId) {
                    $categorySuccess = 'Kategorie hinzugefügt.';
                } else {
                    $categoryError = 'Kategorie konnte nicht gespeichert werden (existiert sie bereits?).';
                }
            }
            break;
        case 'update_category_color':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $color = trim($_POST['category_color'] ?? '');
            if ($categoryId <= 0) {
                $categoryError = 'Ungültige Kategorie.';
                break;
            }
            if ($color !== '' && !preg_match('/^#([0-9a-fA-F]{6})$/', $color)) {
                $categoryError = 'Bitte eine gültige Farbe im Format #RRGGBB wählen.';
                break;
            }
            if ($categoryRepo->updateColor($categoryId, $user['id'], $color)) {
                $categorySuccess = 'Kategoriefarbe aktualisiert.';
            } else {
                $categoryError = 'Farbe konnte nicht gespeichert werden.';
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
        case 'delete_photo':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            if ($photoId <= 0) {
                $photoError = 'Ungültiges Foto.';
                break;
            }
            try {
                $photoService->deletePhoto($photoId, $user['id']);
                $photoSuccess = 'Foto wurde gelöscht.';
            } catch (Throwable $e) {
                $photoError = $e->getMessage();
            }
            break;
        case 'update_photo':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            if ($photoId <= 0) {
                $photoError = 'Ungültiges Foto.';
                break;
            }
            try {
                $categoryIds = array_map('intval', $_POST['categories'] ?? []);
                $photoService->updateDetails($photoId, $user['id'], $_POST, $categoryIds);
                $photoSuccess = 'Foto aktualisiert.';
            } catch (Throwable $e) {
                $photoError = $e->getMessage();
            }
            break;
        case 'update_photo_location':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            if ($photoId <= 0) {
                $photoError = 'Ungültiges Foto.';
                break;
            }
            try {
                $photoService->updateLocation(
                    $photoId,
                    $user['id'],
                    $_POST['latitude'] ?? null,
                    $_POST['longitude'] ?? null,
                    $_POST['taken_at'] ?? null
                );
                $photoSuccess = 'Koordinaten gespeichert.';
            } catch (Throwable $e) {
                $photoError = $e->getMessage();
            }
            break;
        case 'approve_user':
            if ($auth->isAdmin()) {
                $userIdToApprove = (int)($_POST['user_id'] ?? 0);
                if ($userIdToApprove > 0) {
                    $userToApprove = $userRepo->findById($userIdToApprove);
                    if ($userToApprove === null) {
                        $adminError = 'Benutzer wurde nicht gefunden.';
                        break;
                    }

                    if ((int)($userToApprove['is_approved'] ?? 0) === 1) {
                        $adminSuccess = 'Benutzer war bereits freigeschaltet.';
                        break;
                    }

                    $userRepo->approve($userIdToApprove);
                    $adminSuccess = 'Benutzer erfolgreich freigeschaltet.';

                    $email = $userToApprove['email'] ?? null;
                    if ($email && $mailer->hasSender()) {
                        $loginUrl = rtrim($config['app']['base_url'] ?? '', '/') . '/login.php';
                        $subject = 'Dein Zugang zur Geo Photothek ist freigeschaltet';
                        $body = 'Hallo ' . ($userToApprove['name'] ?? 'Besucher') . "\n\n" .
                            "dein Account wurde soeben freigegeben. Du kannst dich jetzt mit deiner E-Mail-Adresse anmelden.\n" .
                            ($loginUrl !== '/login.php' && $loginUrl !== '' ? "\nZum Login: {$loginUrl}\n" : '') .
                            "\nViel Freude mit der Geo Photothek!";
                        $mailer->send($email, $subject, $body);
                    }
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
$hasUncategorized = array_reduce($photos, static function ($carry, $photo) {
    return $carry || empty($photo['category_ids']);
}, false);
$showCategoryFilters = !empty($categories) || $hasUncategorized;
$homeLatitude = null;
$homeLongitude = null;

if (isset($config['app']['home_latitude']) && $config['app']['home_latitude'] !== null && $config['app']['home_latitude'] !== '') {
    $homeLatitude = is_numeric($config['app']['home_latitude']) ? (float)$config['app']['home_latitude'] : null;
}

if (isset($config['app']['home_longitude']) && $config['app']['home_longitude'] !== null && $config['app']['home_longitude'] !== '') {
    $homeLongitude = is_numeric($config['app']['home_longitude']) ? (float)$config['app']['home_longitude'] : null;
}

$photosForJs = array_map(static function (array $photo) {
    $photo['image_url'] = '/uploads/' . $photo['file_path'];
    $photo['original_image_url'] = !empty($photo['original_file_path'])
        ? '/uploads/' . $photo['original_file_path']
        : $photo['image_url'];
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
    <script>
        window.PHOTOS = <?php echo json_encode($photosForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.APP_CONFIG = <?php echo json_encode([
            'home_latitude' => $homeLatitude,
            'home_longitude' => $homeLongitude,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="/map.js" defer></script>
</head>
<body data-geocode-url="/geocode.php">
<header class="topbar">
    <div class="brand">Geo Photothek</div>
    <nav class="main-nav">
        <a href="#dashboard" data-panel-target="dashboard" class="active">Dashboard</a>
        <a href="#upload" data-panel-target="upload">Upload</a>
        <a href="#photos" data-panel-target="photos">Bilder</a>
        <a href="#share" data-panel-target="share">Freigabe</a>
        <?php if ($auth->isAdmin()): ?>
            <a href="#approvals" data-panel-target="approvals">Benutzer</a>
        <?php endif; ?>
    </nav>
    <div class="user-info">
        <span>Hallo <?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?></span>
        <a class="button" href="/logout.php">Logout</a>
    </div>
</header>
<main id="dashboard" class="dashboard-main" data-panel="dashboard">
    <section class="map-panel" id="map-section">
        <h2>Karte</h2>
        <div id="map"></div>
        <?php if ($showCategoryFilters): ?>
            <div class="map-controls">
                <div class="map-controls-header">
                    <h3>Kartenfilter</h3>
                    <p>Deaktiviere Kategorien, um deren Pins auszublenden.</p>
                </div>
                <div class="category-filter-list">
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryId = (int)$category['id']; ?>
                        <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                        <label class="checkbox category-filter-item" for="map_category_<?php echo $categoryId; ?>">
                            <input type="checkbox" id="map_category_<?php echo $categoryId; ?>" data-category-filter="<?php echo $categoryId; ?>" checked>
                            <span class="color-dot" style="--dot-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"></span>
                            <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($hasUncategorized): ?>
                        <label class="checkbox category-filter-item" for="map_category_none">
                            <input type="checkbox" id="map_category_none" data-category-filter="none" checked>
                            <span class="color-dot" style="--dot-color: rgba(148, 163, 184, 0.9);"></span>
                            <span>Ohne Kategorie</span>
                        </label>
                    <?php endif; ?>
                </div>
                <button type="button" class="button-ghost" data-category-filter-reset>Alle anzeigen</button>
            </div>
        <?php endif; ?>
        <aside class="photo-detail" data-photo-detail>
            <div class="detail-empty" data-photo-detail-empty>
                <h3>Details</h3>
                <p>Wähle eine Pinnadel oder ein Foto aus der Liste, um mehr Informationen zu sehen.</p>
            </div>
            <div class="detail-body hidden" data-photo-detail-body>
                <button type="button" class="detail-close" data-photo-detail-close aria-label="Detailansicht schließen">&times;</button>
                <img src="" alt="" data-photo-detail-image>
                <div class="detail-meta">
                    <h3 data-photo-detail-title></h3>
                    <p class="detail-hint">Passe Titel, Beschreibung, Kategorien oder Koordinaten an und speichere deine Änderungen.</p>
                    <?php if ($photoError): ?>
                        <div class="error detail-feedback"><?php echo htmlspecialchars($photoError, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                    <?php if ($photoSuccess): ?>
                        <div class="success detail-feedback"><?php echo htmlspecialchars($photoSuccess, ENT_QUOTES); ?></div>
                    <?php endif; ?>
                    <p class="detail-distance hidden" data-photo-detail-distance></p>
                    <form method="post" class="detail-edit-form" data-photo-detail-form>
                        <input type="hidden" name="action" value="update_photo">
                        <input type="hidden" name="photo_id" value="" data-photo-detail-id>

                        <label for="detail_title">Titel</label>
                        <input type="text" name="title" id="detail_title" data-photo-detail-title-input placeholder="Titel des Fotos">

                        <label for="detail_description">Beschreibung</label>
                        <textarea name="description" id="detail_description" data-photo-detail-description-input rows="4" placeholder="Beschreibung"></textarea>

                        <label for="detail_taken">Aufnahmedatum</label>
                        <input type="datetime-local" name="taken_at" id="detail_taken" data-photo-detail-taken>

                        <div class="detail-coordinates">
                            <div>
                                <label for="detail_latitude">Breitengrad</label>
                                <input type="text" name="latitude" id="detail_latitude" data-photo-detail-latitude placeholder="48.13710">
                            </div>
                            <div>
                                <label for="detail_longitude">Längengrad</label>
                                <input type="text" name="longitude" id="detail_longitude" data-photo-detail-longitude placeholder="11.57540">
                            </div>
                        </div>
                        <div class="geocode-search" data-geocode data-lat-field="detail_latitude" data-lng-field="detail_longitude">
                            <label for="detail_geocode">Adresse oder Ort suchen</label>
                            <div class="geocode-row">
                                <input type="text" id="detail_geocode" placeholder="z. B. Marienplatz München" data-geocode-input>
                                <button type="button" class="button-ghost" data-geocode-button>Suche</button>
                            </div>
                            <p class="geocode-feedback hidden" data-geocode-feedback></p>
                            <ul class="geocode-results" data-geocode-results></ul>
                            <p class="detail-note">Wähle einen Treffer aus, um die Koordinaten zu übernehmen.</p>
                        </div>
                        <p class="detail-note">Lasse beide Koordinatenfelder leer, um Positionen zu entfernen.</p>

                        <fieldset class="detail-categories">
                            <legend>Kategorien</legend>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                                    <?php $categoryId = (int)$category['id']; ?>
                                    <label class="checkbox">
                                        <input type="checkbox" name="categories[]" value="<?php echo $categoryId; ?>" data-category-checkbox id="detail_category_<?php echo $categoryId; ?>">
                                        <span class="color-dot" style="--dot-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"></span>
                                        <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="hint">Lege unter <strong>Kategorien</strong> zuerst Kategorien an.</p>
                            <?php endif; ?>
                        </fieldset>

                        <div class="form-actions">
                            <button type="submit" class="button">Änderungen speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </section>
</main>

<section id="upload" class="content-section hidden" data-panel="upload">
    <div class="panel">
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
                        <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                        <label class="checkbox">
                            <input type="checkbox" name="categories[]" value="<?php echo (int)$category['id']; ?>">
                            <span class="color-dot" style="--dot-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"></span>
                            <span><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php else: ?>
                <p class="hint">Noch keine Kategorien vorhanden. Du kannst unten welche hinzufügen.</p>
            <?php endif; ?>

            <details class="upload-advanced">
                <summary>Koordinaten und Datum manuell angeben</summary>
                <div class="upload-advanced-grid">
                    <div>
                        <label for="upload_latitude">Breitengrad</label>
                        <input type="text" name="latitude" id="upload_latitude" placeholder="z. B. 48.1371">
                    </div>
                    <div>
                        <label for="upload_longitude">Längengrad</label>
                        <input type="text" name="longitude" id="upload_longitude" placeholder="z. B. 11.5754">
                    </div>
                    <div class="upload-date">
                        <label for="upload_taken_at">Aufnahmedatum</label>
                        <input type="datetime-local" name="taken_at" id="upload_taken_at">
                    </div>
                </div>
                <div class="geocode-search" data-geocode data-lat-field="upload_latitude" data-lng-field="upload_longitude">
                    <label for="upload_geocode">Adresse oder Ort suchen</label>
                    <div class="geocode-row">
                        <input type="text" id="upload_geocode" placeholder="z. B. Eiffelturm Paris" data-geocode-input>
                        <button type="button" class="button-ghost" data-geocode-button>Suche</button>
                    </div>
                    <p class="geocode-feedback hidden" data-geocode-feedback></p>
                    <ul class="geocode-results" data-geocode-results></ul>
                    <p class="hint">Nutze die Suche, wenn das Foto keine GPS-Daten enthält.</p>
                </div>
                <p class="hint">Die Angaben werden nur genutzt, wenn keine GPS-Daten im Foto vorhanden sind.</p>
            </details>

            <button type="submit">Hochladen</button>
        </form>
        <section class="category-manager" id="categories">
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
                <label for="new_category_color">Farbe</label>
                <input type="color" id="new_category_color" name="new_category_color" value="#3388FF">
                <button type="submit">Kategorie speichern</button>
            </form>
            <?php if (!empty($categories)): ?>
                <h4>Vorhandene Kategorien</h4>
                <ul class="category-color-list">
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                        <li>
                            <form method="post" class="category-color-form">
                                <input type="hidden" name="action" value="update_category_color">
                                <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                                <span class="color-dot" style="--dot-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"></span>
                                <span class="category-name"><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                                <input type="color" name="category_color" value="<?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>">
                                <button type="submit">Speichern</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>

<section id="share" class="content-section hidden" data-panel="share">
    <div class="panel share">
        <h2>Weltkugel teilen</h2>
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
                    <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                    <label class="checkbox">
                        <input type="checkbox" name="share_categories[]" value="<?php echo (int)$category['id']; ?>" <?php echo in_array((int)$category['id'], $shareSettings['categories'], true) ? 'checked' : ''; ?>>
                        <span class="color-dot" style="--dot-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"></span>
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
    </div>
</section>

<?php if ($auth->isAdmin()): ?>
<section id="approvals" class="content-section hidden" data-panel="approvals">
    <div class="panel admin-approval">
        <h2>Benutzerfreigaben</h2>
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
    </div>
</section>
<?php endif; ?>

<section id="photos" class="content-section hidden" data-panel="photos">
    <div class="photo-list">
        <div class="photo-list-header">
            <h2>Bilder</h2>
            <div class="photo-list-messages">
                <?php if ($photoError): ?>
                    <div class="error"><?php echo htmlspecialchars($photoError, ENT_QUOTES); ?></div>
                <?php endif; ?>
                <?php if ($photoSuccess): ?>
                    <div class="success"><?php echo htmlspecialchars($photoSuccess, ENT_QUOTES); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (empty($photos)): ?>
            <p class="hint">Noch keine Fotos vorhanden. Lade dein erstes Bild hoch!</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="photo-table">
                    <thead>
                        <tr>
                            <th scope="col">Vorschau</th>
                            <th scope="col">Details</th>
                            <th scope="col">Kategorien</th>
                            <th scope="col">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($photos as $photo): ?>
                        <?php $photoTitleAttr = htmlspecialchars($photo['title'] ?? 'Ohne Titel', ENT_QUOTES); ?>
                        <tr class="photo-row" data-photo-card data-photo-id="<?php echo (int)$photo['id']; ?>" tabindex="0" role="button" aria-label="Foto-Details anzeigen: <?php echo $photoTitleAttr; ?>">
                            <td class="photo-thumb">
                                <img src="/uploads/<?php echo htmlspecialchars($photo['file_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($photo['title'] ?? 'Foto', ENT_QUOTES); ?>">
                            </td>
                            <td class="photo-info">
                                <strong><?php echo htmlspecialchars($photo['title'] ?? 'Ohne Titel', ENT_QUOTES); ?></strong>
                                <?php if (!empty($photo['description'])): ?>
                                    <div class="photo-description"><?php echo nl2br(htmlspecialchars($photo['description'], ENT_QUOTES)); ?></div>
                                <?php endif; ?>
                                <?php if ($photo['taken_at']): ?>
                                    <div class="photo-meta-line">Aufgenommen am: <?php echo htmlspecialchars($photo['taken_at'], ENT_QUOTES); ?></div>
                                <?php endif; ?>
                                <?php if ($photo['latitude'] && $photo['longitude']): ?>
                                    <div class="photo-meta-line">Koordinaten: <?php echo round($photo['latitude'], 5) . ', ' . round($photo['longitude'], 5); ?></div>
                                <?php else: ?>
                                    <div class="photo-meta-line warning">Keine Koordinaten hinterlegt.</div>
                                <?php endif; ?>
                            </td>
                            <td class="photo-categories-cell">
                                <?php if (!empty($photo['category_details'])): ?>
                                    <div class="photo-categories">
                                        <?php foreach ($photo['category_details'] as $category): ?>
                                            <?php $categoryColor = $category['color'] ?? '#3388FF'; ?>
                                            <span class="category-pill" style="--category-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES); ?>"><?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="photo-meta-line">Keine</span>
                                <?php endif; ?>
                            </td>
                            <td class="photo-actions">
                                <button type="button" class="detail-button" data-photo-trigger>Details</button>
                                <form method="post" onsubmit="return confirm('Foto wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
                                    <button type="submit" class="danger-link">Löschen</button>
                                </form>
                                <?php if (!$photo['latitude'] || !$photo['longitude']): ?>
                                    <details class="location-editor" data-ignore-card>
                                        <summary>Koordinaten hinzufügen</summary>
                                        <form method="post" class="location-form">
                                            <input type="hidden" name="action" value="update_photo_location">
                                            <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
                                            <label for="latitude_<?php echo (int)$photo['id']; ?>">Breitengrad</label>
                                            <input type="text" name="latitude" id="latitude_<?php echo (int)$photo['id']; ?>" placeholder="48.1371" required>
                                            <label for="longitude_<?php echo (int)$photo['id']; ?>">Längengrad</label>
                                            <input type="text" name="longitude" id="longitude_<?php echo (int)$photo['id']; ?>" placeholder="11.5754" required>
                                            <label for="taken_<?php echo (int)$photo['id']; ?>">Aufnahmedatum</label>
                                            <input type="datetime-local" name="taken_at" id="taken_<?php echo (int)$photo['id']; ?>" required>
                                            <div class="geocode-search" data-geocode data-lat-field="latitude_<?php echo (int)$photo['id']; ?>" data-lng-field="longitude_<?php echo (int)$photo['id']; ?>">
                                                <label for="geocode_<?php echo (int)$photo['id']; ?>">Adresse oder Ort suchen</label>
                                                <div class="geocode-row">
                                                    <input type="text" id="geocode_<?php echo (int)$photo['id']; ?>" placeholder="z. B. Brandenburger Tor" data-geocode-input>
                                                    <button type="button" class="button-ghost" data-geocode-button>Suche</button>
                                                </div>
                                                <p class="geocode-feedback hidden" data-geocode-feedback></p>
                                                <ul class="geocode-results" data-geocode-results></ul>
                                                <p class="hint">Ausgewählte Treffer tragen die Felder automatisch ein.</p>
                                            </div>
                                            <button type="submit">Speichern</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const panels = Array.from(document.querySelectorAll('[data-panel]'));
    const navLinks = Array.from(document.querySelectorAll('[data-panel-target]'));
    const panelNames = panels.map(panel => panel.dataset.panel);
    let ignoreHash = false;
    let activePanel = 'dashboard';

    const setActive = (panelName, updateHash = true) => {
        if (!panelNames.includes(panelName)) {
            panelName = 'dashboard';
        }

        panels.forEach(panel => {
            if (panel.dataset.panel === panelName) {
                panel.classList.remove('hidden');
                panel.classList.add('active-panel');
            } else {
                panel.classList.add('hidden');
                panel.classList.remove('active-panel');
            }
        });

        navLinks.forEach(link => {
            link.classList.toggle('active', link.dataset.panelTarget === panelName);
        });

        if (updateHash) {
            ignoreHash = true;
            history.replaceState(null, '', '#' + panelName);
            setTimeout(() => {
                ignoreHash = false;
            }, 0);
        }

        activePanel = panelName;

        if (window.GeoPhotothek && typeof window.GeoPhotothek.notifyPanelChange === 'function') {
            window.GeoPhotothek.notifyPanelChange(panelName);
        }
    };

    window.GeoPhotothek = window.GeoPhotothek || {};
    window.GeoPhotothek.setActivePanel = setActive;
    window.GeoPhotothek.getActivePanel = () => activePanel;

    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const target = link.dataset.panelTarget;
            setActive(target);
        });
    });

    window.addEventListener('hashchange', () => {
        if (ignoreHash) {
            return;
        }
        const hash = window.location.hash.replace('#', '');
        if (panelNames.includes(hash)) {
            setActive(hash, false);
        }
    });

    const initialHash = window.location.hash.replace('#', '');
    if (panelNames.includes(initialHash)) {
        setActive(initialHash, false);
    } else {
        setActive('dashboard', false);
    }
});
</script>
</body>
</html>
