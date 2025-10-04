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
   cp config/config.example.php config/config.php
   ```

2. Datenbank anlegen und Schema importieren:

   ```bash
   mysql -u root -p -e "CREATE DATABASE geo_photothek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   php install.php
   ```

   Alternativ kann das Schema weiterhin manuell mit `database/schema.sql` eingespielt werden.

3. `config/config.php` mit den eigenen Zugangsdaten füllen.

   - `upload_dir` legt den Pfad für optimierte Anzeige-Bilder fest (standardmäßig `public/uploads`).
   - `original_upload_dir` speichert unveränderte Originaldateien (standardmäßig `public/uploads/originals`).
   - `display_max_dimension` bestimmt die maximale Breite/Höhe der optimierten Bilder.

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

5. Entwicklungserver starten:

   ```bash
   php -S localhost:8000 -t public
   ```

6. Im Browser `http://localhost:8000` öffnen, registrieren und Fotos hochladen.

## Sicherheitshinweise

- Für die Produktion sollten Uploads in ein Verzeichnis außerhalb des Webroots gelegt und über einen Controller ausgeliefert werden.
- HTTPS verwenden, Sessions absichern und weitere Prüfungen (z. B. CSRF) ergänzen.
- Die Anwendung dient als Starterprojekt und sollte vor dem produktiven Einsatz gehärtet werden.

## Lizenz

MIT
