# Geo Photothek

Eine PHP/MySQL-Anwendung, um Reise-Fotos mit Geo-Informationen zu verwalten.

## Funktionen

- 🌍 Weltkarte (Leaflet) mit Markern für alle hochgeladenen Fotos
- 🏠 Home-Schaltfläche auf der Karte zum schnellen Zurücksetzen der Ansicht
- 📸 Foto-Upload mit automatischer Auswertung von EXIF-Koordinaten (falls vorhanden)
- 👥 Multi-User mit Registrierung/Login
- ✅ Neue Nutzer:innen müssen durch Admins freigeschaltet werden (der erste Account wird automatisch Admin)
- 🗂️ Eigene Kategorien für Fotos verwalten und beim Upload zuweisen
- 🎨 Kategorien lassen sich mit individuellen Farben versehen; Marker übernehmen automatisch die gewählte Farbe
- 🔐 Geteilte Weltkarten lassen sich auf ausgewählte Kategorien einschränken, Links können neu erzeugt oder gelöscht werden
- 🔗 Teilen der eigenen Weltkarte über einen öffentlichen Link
- 🗂️ Verwaltung von Fotos im Dashboard mit Beschreibung und Aufnahmezeit
- 🖼️ Detailansicht mit Originalbild nach Klick auf eine Pinnadel oder Foto-Kachel

## Voraussetzungen

- PHP 8.1 oder höher mit aktivierten Erweiterungen `pdo_mysql`, `fileinfo`, `exif`
- MySQL 8 oder kompatible MariaDB-Version
- Composer (optional für spätere Erweiterungen)
- Ein Webserver (z. B. PHP Built-in Server, Apache, Nginx)

## Installation

1. Repository klonen und Abhängigkeiten installieren (falls benötigt):

   ```bash
   git clone <repo>
   cd geo
   ```

2. Datenbank anlegen (falls noch nicht geschehen):

   ```bash
   mysql -u root -p -e "CREATE DATABASE geo_photothek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

3. Installations-/Upgrade-Assistent im Browser ausführen und Konfiguration speichern:

   ```bash
   php -S localhost:8000 -t public
   ```

   Rufe anschließend `http://localhost:8000/install.php` auf, trage deine Datenbank-Zugangsdaten ein und starte die Schema-Installation. Nach erfolgreicher Einrichtung sollte `public/install.php` aus Sicherheitsgründen gelöscht werden.

   - In `config/config.php` kannst du anschließend Pfade und Limits wie `upload_dir`, `original_upload_dir` oder `display_max_dimension` nach Bedarf anpassen.

   > Tipp: Die Routine lässt sich alternativ per CLI starten (`php public/install.php`), sofern `config/config.php` bereits ausgefüllt ist.

4. Upload- und Shared-Verzeichnisse (inklusive Originalbildern) sicher beschreibbar machen:

   ```bash
   mkdir -p public/uploads public/uploads/originals shared
   chmod 775 public/uploads public/uploads/originals shared
   ```

   > Upgrade-Hinweis: Bestehende Installationen sollten nach dem Update die Spalte `original_file_path` ergänzen und mit den bisherigen Dateinamen füllen, z. B.:
   >
   > ```sql
   > ALTER TABLE photos ADD COLUMN original_file_path VARCHAR(255) NOT NULL DEFAULT '';
   > UPDATE photos SET original_file_path = file_path WHERE original_file_path = '';
   > ```
   >
   > Zusätzlich benötigen Kategorien nun eine Farbinformation:
   >
   > ```sql
   > ALTER TABLE categories ADD COLUMN color CHAR(7) NOT NULL DEFAULT '#3388FF';
   > UPDATE categories SET color = '#3388FF' WHERE color IS NULL OR color = '';
   > ```

5. Im Browser `http://localhost:8000` öffnen, registrieren und Fotos hochladen.

## Sicherheitshinweise

- Für die Produktion sollten Uploads in ein Verzeichnis außerhalb des Webroots gelegt und über einen Controller ausgeliefert werden.
- HTTPS verwenden, Sessions absichern und weitere Prüfungen (z. B. CSRF) ergänzen.
- Die Anwendung dient als Starterprojekt und sollte vor dem produktiven Einsatz gehärtet werden.

## Lizenz

MIT
