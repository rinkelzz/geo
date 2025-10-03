# Geo Photothek

Eine PHP/MySQL-Anwendung, um Reise-Fotos mit Geo-Informationen zu verwalten.

## Funktionen

- 🌍 Weltkarte (Leaflet) mit Markern für alle hochgeladenen Fotos
- 📸 Foto-Upload mit automatischer Auswertung von EXIF-Koordinaten (falls vorhanden)
- 👥 Multi-User mit Registrierung/Login
- 🔗 Teilen der eigenen Weltkarte über einen öffentlichen Link
- 🗂️ Verwaltung von Fotos im Dashboard mit Beschreibung und Aufnahmezeit

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
   mysql -u root -p geo_photothek < database/schema.sql
   ```

3. `config/config.php` mit den eigenen Zugangsdaten füllen.

4. Upload- und Shared-Verzeichnisse sicher beschreibbar machen:

   ```bash
   mkdir -p public/uploads shared
   chmod 775 public/uploads shared
   ```

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
