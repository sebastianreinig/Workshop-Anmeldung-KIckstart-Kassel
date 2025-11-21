<?php
/**
 * Admin Export Tool - Autarke Seite zum CSV-Export
 * Kann im /organisator/ Verzeichnis liegen
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['export_token'])) {
    $_SESSION['export_token'] = bin2hex(random_bytes(32));
}

$error = '';
$pdo = null;

// Datenbankverbindung
try {
    $possiblePaths = [
        __DIR__ . '/config.php',
        __DIR__ . '/../config.php',
        dirname(__DIR__) . '/config.php'
    ];
    
    $configPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $configPath = $path;
            break;
        }
    }
    
    if ($configPath === null) {
        throw new Exception('config.php nicht gefunden');
    }
    
    require_once $configPath;
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    $error = 'DB-Fehler: ' . $e->getMessage();
}

// Authentifizierung
if (!isset($_SESSION['export_authenticated']) && $pdo !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authenticate'])) {
        try {
            $stmt = $pdo->query("SELECT password FROM admins LIMIT 1");
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($_POST['password'] ?? '', $admin['password'])) {
                $_SESSION['export_authenticated'] = true;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error = 'Falsches Passwort';
            }
        } catch (PDOException $e) {
            $error = 'Fehler: ' . $e->getMessage();
        }
    }
    
    // Login-Form
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Export - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .box {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 100%;
            }
            h1 { color: #2196f3; margin-bottom: 20px; }
            .info {
                background: #e3f2fd;
                color: #1565c0;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            label { display: block; margin-bottom: 8px; font-weight: 600; }
            input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 16px;
                margin-bottom: 20px;
            }
            button {
                width: 100%;
                padding: 14px;
                background: #2196f3;
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }
            .error {
                background: #ffebee;
                color: #c62828;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>📊 Admin Export</h1>
            <div class="info"><strong>ℹ️ Info</strong><br>CSV-Export für Workshops und Teilnehmer.</div>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>Admin-Passwort:</label>
                <input type="password" name="password" required autofocus>
                <button type="submit" name="authenticate">Anmelden</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// CSV-Export: Workshops
if (isset($_GET['export']) && $_GET['export'] === 'workshops' && $pdo !== null) {
    try {
        $stmt = $pdo->query("
            SELECT 
                w.id,
                w.title,
                w.description,
                w.timeslot,
                w.location,
                w.max_participants,
                COUNT(r.id) as current_participants,
                w.created_at
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id
            GROUP BY w.id
            ORDER BY w.timeslot, w.title
        ");
        
        $workshops = $stmt->fetchAll();
        
        // CSV generieren
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="workshops_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM für Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['ID', 'Titel', 'Beschreibung', 'Zeitslot', 'Ort', 'Max. Teilnehmer', 'Anmeldungen', 'Erstellt am'], ';');
        
        // Daten
        foreach ($workshops as $workshop) {
            fputcsv($output, [
                $workshop['id'],
                $workshop['title'],
                $workshop['description'],
                $workshop['timeslot'],
                $workshop['location'],
                $workshop['max_participants'],
                $workshop['current_participants'],
                $workshop['created_at']
            ], ';');
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        $error = 'Export-Fehler: ' . $e->getMessage();
    }
}

// CSV-Export: Teilnehmer
if (isset($_GET['export']) && $_GET['export'] === 'participants' && $pdo !== null) {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.name,
                p.created_at,
                GROUP_CONCAT(w.title ORDER BY w.title SEPARATOR ', ') as workshops,
                COUNT(r.id) as workshop_count
            FROM participants p
            LEFT JOIN registrations r ON p.id = r.participant_id
            LEFT JOIN workshops w ON r.workshop_id = w.id
            GROUP BY p.id
            ORDER BY p.name
        ");
        
        $participants = $stmt->fetchAll();
        
        // CSV generieren
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="teilnehmer_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM für Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['Teilnehmer-ID', 'Name', 'Angemeldete Workshops', 'Anzahl Workshops', 'Registriert am'], ';');
        
        // Daten
        foreach ($participants as $participant) {
            fputcsv($output, [
                $participant['id'],
                $participant['name'],
                $participant['workshops'] ?: 'Keine Anmeldungen',
                $participant['workshop_count'],
                $participant['created_at']
            ], ';');
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        $error = 'Export-Fehler: ' . $e->getMessage();
    }
}

// CSV-Export: Detaillierte Anmeldungen
if (isset($_GET['export']) && $_GET['export'] === 'registrations' && $pdo !== null) {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.name as teilnehmer_name,
                p.id as teilnehmer_id,
                w.title as workshop_titel,
                w.timeslot as workshop_zeit,
                w.location as workshop_ort,
                r.status as status,
                r.registered_at as anmeldung_am
            FROM registrations r
            JOIN participants p ON r.participant_id = p.id
            JOIN workshops w ON r.workshop_id = w.id
            ORDER BY w.timeslot, w.title, p.name
        ");
        
        $registrations = $stmt->fetchAll();
        
        // CSV generieren
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="anmeldungen_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM für Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, ['Teilnehmer', 'Teilnehmer-ID', 'Workshop', 'Zeitslot', 'Ort', 'Status', 'Angemeldet am'], ';');
        
        // Daten
        foreach ($registrations as $reg) {
            fputcsv($output, [
                $reg['teilnehmer_name'],
                $reg['teilnehmer_id'],
                $reg['workshop_titel'],
                $reg['workshop_zeit'],
                $reg['workshop_ort'],
                $reg['status'],
                $reg['anmeldung_am']
            ], ';');
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        $error = 'Export-Fehler: ' . $e->getMessage();
    }
}

// Statistiken laden
$stats = null;
if ($pdo !== null) {
    try {
        $stats = [
            'participants' => $pdo->query("SELECT COUNT(*) FROM participants")->fetchColumn(),
            'registrations' => $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
            'workshops' => $pdo->query("SELECT COUNT(*) FROM workshops")->fetchColumn()
        ];
    } catch (PDOException $e) {
        $error = 'Fehler beim Laden: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Export Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { color: #2196f3; font-size: 2rem; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-primary { background: #2196f3; color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-info { background: #00bcd4; color: white; }
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2196f3;
        }
        .stat-label { color: #1565c0; margin-top: 5px; font-size: 14px; }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
        }
        .export-option {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .export-info h3 {
            color: #333;
            margin-bottom: 8px;
        }
        .export-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .export-info ul {
            margin-left: 20px;
            color: #666;
            font-size: 13px;
        }
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📊 Admin Export</h1>
                <p>CSV-Exporte für Workshops & Teilnehmer</p>
            </div>
            <a href="?logout" class="btn btn-secondary">Abmelden</a>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📈 Aktuelle Statistiken</h2>
            <?php if ($stats): ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['workshops']; ?></div>
                        <div class="stat-label">Workshops</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['participants']; ?></div>
                        <div class="stat-label">Teilnehmer</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['registrations']; ?></div>
                        <div class="stat-label">Anmeldungen</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>📥 CSV-Exporte</h2>
            
            <div class="info-box">
                <h3>ℹ️ Hinweise zu den Exporten:</h3>
                <ul>
                    <li>CSV-Dateien sind kompatibel mit Excel, LibreOffice und Google Sheets</li>
                    <li>UTF-8 kodiert mit BOM für korrekte Umlaute</li>
                    <li>Semikolon (;) als Trennzeichen</li>
                    <li>Dateiname enthält Datum und Uhrzeit</li>
                </ul>
            </div>

            <div class="export-option">
                <div class="export-info">
                    <h3>📚 Workshops-Export</h3>
                    <p><strong>Enthält:</strong></p>
                    <ul>
                        <li>Workshop-ID, Titel, Beschreibung</li>
                        <li>Zeitslot, Ort</li>
                        <li>Max. Teilnehmer vs. aktuelle Anmeldungen</li>
                        <li>Erstellungsdatum</li>
                    </ul>
                </div>
                <a href="?export=workshops" class="btn btn-primary">📥 Workshops exportieren</a>
            </div>

            <div class="export-option">
                <div class="export-info">
                    <h3>👥 Teilnehmer-Export</h3>
                    <p><strong>Enthält:</strong></p>
                    <ul>
                        <li>Teilnehmer-ID und Name</li>
                        <li>Liste aller angemeldeten Workshops</li>
                        <li>Anzahl der Anmeldungen</li>
                        <li>Registrierungsdatum</li>
                    </ul>
                </div>
                <a href="?export=participants" class="btn btn-success">📥 Teilnehmer exportieren</a>
            </div>

            <div class="export-option">
                <div class="export-info">
                    <h3>📋 Detaillierte Anmeldungen</h3>
                    <p><strong>Enthält:</strong></p>
                    <ul>
                        <li>Teilnehmer mit Workshop-Zuordnung</li>
                        <li>Workshop-Details (Zeit, Ort)</li>
                        <li>Anmeldestatus (pending/confirmed)</li>
                        <li>Anmeldezeitpunkt</li>
                    </ul>
                </div>
                <a href="?export=registrations" class="btn btn-info">📥 Anmeldungen exportieren</a>
            </div>
        </div>

        <div class="card">
            <h2>💡 Verwendungstipps</h2>
            <ul style="line-height: 1.8; color: #666; margin-left: 20px;">
                <li><strong>Excel:</strong> Datei direkt öffnen, Umlaute werden korrekt angezeigt</li>
                <li><strong>Google Sheets:</strong> Datei → Importieren → CSV hochladen</li>
                <li><strong>Weiterverarbeitung:</strong> Alle Exporte können in Pivot-Tabellen verwendet werden</li>
                <li><strong>Teilnehmerlisten:</strong> Export "Anmeldungen" nach Workshop-Zeit sortieren</li>
                <li><strong>Statistiken:</strong> Workshop-Export für Auslastungsanalyse nutzen</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-secondary">← Zurück zum Organisator</a>
        </div>
    </div>
</body>
</html>