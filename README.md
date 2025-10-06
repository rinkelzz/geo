# Geo Photothek

Eine PHP/MySQL-Anwendung, um Reise-Fotos mit Geo-Informationen zu verwalten.

## Funktionen

- 🌍 Weltkarte (Leaflet) mit Markern für alle hochgeladenen Fotos
- 🏠 Home-Schaltfläche auf der Karte zum schnellen Zurücksetzen der Ansicht
- 📏 Anzeige der Luftlinie vom Zuhause zum ausgewählten Foto inklusive gestrichelter Verbindungslinie
- 📸 Foto-Upload mit automatischer Auswertung von EXIF-Koordinaten (falls vorhanden)
- 👥 Multi-User mit Registrierung/Login
- ✅ Neue Nutzer:innen müssen durch Admins freigeschaltet werden (der erste Account wird automatisch Admin)
- 🗂️ Eigene Kategorien für Fotos verwalten und beim Upload zuweisen
- 🎨 Kategorien lassen sich mit individuellen Farben versehen; Marker übernehmen automatisch die gewählte Farbe
- 🔐 Geteilte Weltkarten lassen sich auf ausgewählte Kategorien einschränken, Links können neu erzeugt oder gelöscht werden
- 🔗 Teilen der eigenen Weltkarte über einen öffentlichen Link
- 🗂️ Verwaltung von Fotos im Dashboard mit Beschreibung und Aufnahmezeit
- 🖼️ Detailansicht mit Originalbild nach Klick auf eine Pinnadel oder Foto-Kachel
- 🧭 Fehlende Koordinaten lassen sich direkt über eine integrierte Adresssuche nachtragen
- ✉️ E-Mail-Benachrichtigungen informieren Admins über neue Registrierungen und Nutzer:innen über Freischaltungen

## Voraussetzungen

- PHP 8.1 oder höher mit aktivierten Erweiterungen `pdo_mysql`, `fileinfo`, `exif`, `curl`
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

   Rufe anschließend `http://localhost:8000/install.php` auf, trage deine Datenbank-Zugangsdaten ein, hinterlege eine Absender-Adresse für Benachrichtigungs-Mails, optional die Koordinaten deines Zuhauses und starte die Schema-Installation. Nach erfolgreicher Einrichtung sollte `public/install.php` aus Sicherheitsgründen gelöscht werden.

   - In `config/config.php` kannst du anschließend Pfade und Limits wie `upload_dir`, `original_upload_dir` oder `display_max_dimension`, die Mail-Einstellungen (Absendername, Admin-Verteiler) sowie optional `home_latitude` und `home_longitude` nach Bedarf anpassen.

   > Tipp: Die Routine lässt sich alternativ per CLI starten (`php public/install.php`), sofern `config/config.php` bereits ausgefüllt ist.

4. Upload- und Shared-Verzeichnisse (inklusive Originalbildern) sicher beschreibbar machen (Standard liegt jetzt außerhalb des Webroots):

   ```bash
   mkdir -p storage/uploads storage/uploads/originals shared
   chmod 775 storage/uploads storage/uploads/originals shared
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
   >
   > Ergänze in `config/config.php` außerdem einen neuen Abschnitt `mail` mit `from_address`, optionalem `from_name` sowie den gewünschten Admin-Empfänger:innen, damit Benachrichtigungen versendet werden können. Trage bei Bedarf auch `home_latitude` und `home_longitude` im `app`-Block ein, um die Luftlinien-Anzeige zu aktivieren. Passe außerdem `upload_dir`/`original_upload_dir` an, falls du einen anderen Speicherort für Fotos verwenden möchtest.

5. Im Browser `http://localhost:8000` öffnen, registrieren und Fotos hochladen.

## Sicherheitshinweise

- Für die Produktion werden Uploads in `storage/uploads` (außerhalb des Webroots) abgelegt und über `public/photo.php` geschützt ausgeliefert. Passe die Pfade bei Bedarf an und beschränke die Dateirechte auf den Webserver-Benutzer.
- HTTPS verwenden, Sessions absichern und weitere Prüfungen (z. B. CSRF) ergänzen.
- Die Anwendung dient als Starterprojekt und sollte vor dem produktiven Einsatz gehärtet werden.

## Lizenz

Siehe [`LICENSE.md`](LICENSE.md) und passe die Platzhalter mit deinem Namen sowie Kontaktdaten an, falls du kommerzielle Lizenzen gegen Gebühr vergeben möchtest.
