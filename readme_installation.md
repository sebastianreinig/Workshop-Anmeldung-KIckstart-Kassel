# KIckstart Kassel - Workshop Management System

Ein datenschutzkonformes Workshop-Organisations-Tool für Events.

## 🎯 Features

### Teilnehmer-Ansicht
- ✅ Mobile-optimiertes Interface
- ✅ Einfache Workshop-Auswahl
- ✅ Maximale Anmeldung zu 2 Workshops
- ✅ Eindeutige Teilnehmer-ID (lokal gespeichert)
- ✅ Echtzeit-Übersicht der Anmeldungen
- ✅ Responsive Design

### Organisator-Ansicht
- ✅ Workshop-Verwaltung (Erstellen, Löschen)
- ✅ Teilnehmer-Übersicht
- ✅ Teilnehmer zwischen Workshops verschieben
- ✅ Organisation beenden (Anmeldungen schließen)
- ✅ Dashboard mit Statistiken

### REST-API
- ✅ `/api/init.php` - Teilnehmer registrieren
- ✅ `/api/workshops.php` - Workshop-Liste abrufen
- ✅ `/api/register.php` - Anmeldung/Abmeldung
- ✅ `/api/status.php` - Teilnehmer-Status abrufen

## 📁 Projektstruktur

```
/workshop-system/
├── setup.php                  # Einmaliges Setup
├── config.php                 # Automatisch generiert
├── setup.lock                 # Automatisch generiert
├── db/
│   └── Database.php          # Datenbankklasse
├── api/
│   ├── init.php              # Teilnehmer-ID generieren
│   ├── workshops.php         # Workshop-Liste
│   ├── register.php          # An-/Abmeldung
│   └── status.php            # Teilnehmer-Status
├── teilnehmer/
│   └── index.html            # Teilnehmer-Frontend
└── organisator/
    └── index.php             # Organisator-Backend
```

## 🚀 Installation

### Voraussetzungen
- PHP 7.4 oder höher
- MySQL/MariaDB
- Webserver (Apache/Nginx)

### Schritt 1: Dateien hochladen
Alle Dateien in das Webserver-Verzeichnis hochladen.

### Schritt 2: Setup ausführen
1. Browser öffnen und zu `https://ihre-domain.de/setup.php` navigieren
2. Datenbank-Zugangsdaten eingeben:
   - **Datenbank Host**: meist `localhost`
   - **Datenbank Name**: z.B. `kickstart_workshops`
   - **Datenbank Benutzer**: Ihr MySQL-Benutzername
   - **Datenbank Passwort**: Ihr MySQL-Passwort

3. Admin-Zugangsdaten festlegen:
   - **Admin Benutzername**: Wählen Sie einen Namen
   - **Admin Passwort**: Sicheres Passwort wählen

4. "Setup starten" klicken

### Schritt 3: Fertig!
Nach erfolgreichem Setup sind folgende URLs verfügbar:

- **Teilnehmer-Ansicht**: `https://ihre-domain.de/teilnehmer/`
- **Organisator-Ansicht**: `https://ihre-domain.de/organisator/`

## 🔒 Datenschutz

### Gespeicherte Daten
- Teilnehmer-ID (UUID)
- Teilnehmer-Name
- Workshop-Anmeldungen
- Zeitstempel der Anmeldungen

### Lokale Speicherung
Die Teilnehmer-ID wird im Browser-LocalStorage gespeichert, um Mehrfachanmeldungen zu verhindern.

### Keine sensiblen Daten
Es werden bewusst keine E-Mail-Adressen, Telefonnummern oder andere sensible Daten gespeichert.

## 📱 Verwendung

### Als Teilnehmer

1. Öffne die Teilnehmer-URL
2. Gib deinen Namen ein
3. Wähle bis zu 2 Workshops aus
4. Deine Anmeldungen werden sofort gespeichert

**Hinweis**: Deine Teilnehmer-ID wird im Browser gespeichert. Lösche keine Browser-Daten, sonst musst du dich neu registrieren!

### Als Organisator

1. Öffne die Organisator-URL
2. Melde dich mit deinen Admin-Zugangsdaten an
3. Erstelle Workshops:
   - Titel, Beschreibung, Zeitslot, Ort
   - Maximale Teilnehmerzahl festlegen
4. Verwalte Anmeldungen:
   - Sehe wer sich wo angemeldet hat
   - Verschiebe Teilnehmer zwischen Workshops
5. Organisation beenden:
   - Klicke auf "Organisation beenden"
   - Alle Anmeldungen werden bestätigt
   - Teilnehmer können sich nicht mehr an-/abmelden

## 🔧 API-Dokumentation

### POST /api/init.php
Erstellt einen neuen Teilnehmer.

**Request:**
```json
{
  "name": "Max Mustermann"
}
```

**Response:**
```json
{
  "success": true,
  "participant_id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Max Mustermann"
}
```

### GET /api/workshops.php
Ruft alle Workshops ab.

**Response:**
```json
{
  "success": true,
  "workshops": [
    {
      "id": 1,
      "title": "KI-Grundlagen",
      "description": "Einführung in künstliche Intelligenz",
      "max_participants": 20,
      "current_participants": 15,
      "timeslot": "10:00 - 12:00",
      "location": "Raum A"
    }
  ],
  "event_closed": false
}
```

### POST /api/register.php
Meldet einen Teilnehmer zu einem Workshop an.

**Request:**
```json
{
  "participant_id": "550e8400-e29b-41d4-a716-446655440000",
  "workshop_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Erfolgreich angemeldet"
}
```

### DELETE /api/register.php
Meldet einen Teilnehmer von einem Workshop ab.

**Request:**
```json
{
  "participant_id": "550e8400-e29b-41d4-a716-446655440000",
  "workshop_id": 1
}
```

### GET /api/status.php?participant_id=XXX
Ruft den Status eines Teilnehmers ab.

**Response:**
```json
{
  "success": true,
  "participant": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Max Mustermann"
  },
  "workshops": [
    {
      "id": 1,
      "title": "KI-Grundlagen",
      "timeslot": "10:00 - 12:00",
      "location": "Raum A",
      "status": "pending"
    }
  ],
  "event_closed": false
}
```

## ⚙️ Konfiguration

Nach dem Setup wird automatisch eine `config.php` erstellt. Diese enthält:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'kickstart_workshops');
define('DB_USER', 'ihr_benutzer');
define('DB_PASS', 'ihr_passwort');
define('SITE_URL', 'https://ihre-domain.de');
```

## 🛡️ Sicherheit

### Setup-Schutz
Das Setup kann nur einmal ausgeführt werden. Die Datei `setup.lock` verhindert weitere Ausführungen.

### Admin-Bereich
- Passwörter werden mit `password_hash()` verschlüsselt
- Session-basierte Authentifizierung
- Keine Passwörter im Klartext

### API-Schutz
- Prepared Statements gegen SQL-Injection
- Input-Validierung
- Rate-Limiting durch Geschäftslogik (max. 2 Workshops)

## 🐛 Fehlerbehebung

### Setup funktioniert nicht
- Prüfe die Datenbank-Zugangsdaten
- Stelle sicher, dass PHP PDO-MySQL aktiviert ist
- Prüfe die Schreibrechte im Verzeichnis

### Teilnehmer kann sich nicht anmelden
- Prüfe, ob das Event geschlossen ist
- Prüfe, ob der Workshop voll ist
- Lösche Browser-Cache und versuche es erneut

### Organisator kann sich nicht anmelden
- Prüfe die Zugangsdaten
- Stelle sicher, dass das Setup abgeschlossen ist

## 📞 Support

Bei Problemen oder Fragen:
1. Prüfe die Datenbankverbindung
2. Schaue in die Browser-Konsole (F12) für JavaScript-Fehler
3. Prüfe die PHP-Error-Logs

## 📄 Lizenz

Dieses Projekt ist für den Einsatz beim KIckstart Kassel Event entwickelt worden.

---

**Viel Erfolg mit dem Workshop-Management! 🚀**