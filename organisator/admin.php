<?php
/**
 * Admin Cleanup Tool - Autarke Seite zum Löschen aller Teilnehmer
 * Kann im /organisator/ Verzeichnis liegen
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['cleanup_token'])) {
    $_SESSION['cleanup_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$stats = null;
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
if (!isset($_SESSION['cleanup_authenticated']) && $pdo !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authenticate'])) {
        try {
            $stmt = $pdo->query("SELECT password FROM admins LIMIT 1");
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($_POST['password'] ?? '', $admin['password'])) {
                $_SESSION['cleanup_authenticated'] = true;
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
        <title>Admin Cleanup - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
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
            h1 { color: #f44336; margin-bottom: 20px; }
            .warning {
                background: #fff3e0;
                color: #e65100;
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
                background: #f44336;
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
            <h1>🔐 Admin Cleanup</h1>
            <div class="warning"><strong>⚠️ Achtung!</strong><br>Löscht Teilnehmerdaten.</div>
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

// Statistiken laden
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

// Teilnehmer löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_participants']) && $pdo !== null) {
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['cleanup_token']) {
        $error = 'Ungültiges Token';
    } else {
        $confirmText = trim($_POST['confirm_text'] ?? '');
        
        if ($confirmText !== 'ALLES LÖSCHEN') {
            $error = 'Bestätigungstext falsch. Bitte "ALLES LÖSCHEN" eingeben.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM registrations");
                $stmt->execute();
                $deletedReg = $stmt->rowCount();
                
                $stmt = $pdo->prepare("DELETE FROM participants");
                $stmt->execute();
                $deletedPart = $stmt->rowCount();
                
                $pdo->commit();
                
                $message = "✅ Gelöscht: $deletedPart Teilnehmer, $deletedReg Anmeldungen";
                $stats = [
                    'participants' => 0,
                    'registrations' => 0,
                    'workshops' => $pdo->query("SELECT COUNT(*) FROM workshops")->fetchColumn()
                ];
                $_SESSION['cleanup_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Fehler: ' . $e->getMessage();
            }
        }
    }
}

// Komplett zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_everything']) && $pdo !== null) {
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['cleanup_token']) {
        $error = 'Ungültiges Token';
    } else {
        $confirmText = trim($_POST['confirm_text_full'] ?? '');
        
        if ($confirmText !== 'KOMPLETT ZURÜCKSETZEN') {
            $error = 'Bestätigungstext falsch. Bitte "KOMPLETT ZURÜCKSETZEN" eingeben.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->exec("DELETE FROM registrations");
                $pdo->exec("DELETE FROM participants");
                $pdo->exec("DELETE FROM workshops");
                $pdo->exec("UPDATE event_status SET is_closed = FALSE, closed_at = NULL");
                $pdo->commit();
                
                $message = "✅ System komplett zurückgesetzt!";
                $stats = ['participants' => 0, 'registrations' => 0, 'workshops' => 0];
                $_SESSION['cleanup_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Fehler: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Cleanup Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
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
        .header h1 { color: #f44336; font-size: 2rem; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-danger { background: #f44336; color: white; }
        .btn-warning { background: #ff9800; color: white; }
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
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #f44336;
        }
        .stat-label { color: #666; margin-top: 5px; font-size: 14px; }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .alert-warning {
            background: #fff3e0;
            color: #e65100;
            border-left: 4px solid #ff9800;
        }
        .danger-zone {
            border: 3px solid #f44336;
            border-radius: 15px;
            padding: 25px;
            background: #ffebee;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: monospace;
        }
        .confirm-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        h3 { margin: 25px 0 15px 0; color: #333; }
        hr { margin: 30px 0; border: none; border-top: 2px solid #ccc; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🗑️ Admin Cleanup</h1>
                <p>Teilnehmerverwaltung</p>
            </div>
            <a href="?logout" class="btn btn-secondary">Abmelden</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📊 Aktuelle Statistiken</h2>
            <?php if ($stats): ?>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['participants']; ?></div>
                        <div class="stat-label">Teilnehmer</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['registrations']; ?></div>
                        <div class="stat-label">Anmeldungen</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['workshops']; ?></div>
                        <div class="stat-label">Workshops</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="danger-zone">
                <h2 style="color: #f44336; border: none; padding: 0; margin-bottom: 15px;">⚠️ Gefahrenbereich</h2>
                
                <div class="alert alert-warning">
                    <strong>Hinweis:</strong> Diese Aktionen können nicht rückgängig gemacht werden!
                </div>

                <h3>Option 1: Nur Teilnehmer löschen</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    Löscht alle Teilnehmer und deren Anmeldungen. Workshops bleiben erhalten.
                </p>
                
                <form method="POST" onsubmit="return confirm('Wirklich ALLE Teilnehmer löschen?');">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['cleanup_token']; ?>">
                    <div class="form-group">
                        <label>Bestätigung (Gib ein: ALLES LÖSCHEN):</label>
                        <input type="text" name="confirm_text" required placeholder="ALLES LÖSCHEN">
                        <div class="confirm-hint">Groß-/Kleinschreibung beachten!</div>
                    </div>
                    <button type="submit" name="delete_participants" class="btn btn-danger">
                        🗑️ Alle Teilnehmer löschen (<?php echo $stats['participants'] ?? 0; ?>)
                    </button>
                </form>

                <hr>

                <h3>Option 2: Komplett zurücksetzen</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    Löscht ALLES: Teilnehmer, Workshops, Anmeldungen.
                </p>
                
                <form method="POST" onsubmit="return confirm('ACHTUNG! Wirklich ALLES zurücksetzen?');">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['cleanup_token']; ?>">
                    <div class="form-group">
                        <label>Bestätigung (Gib ein: KOMPLETT ZURÜCKSETZEN):</label>
                        <input type="text" name="confirm_text_full" required placeholder="KOMPLETT ZURÜCKSETZEN">
                        <div class="confirm-hint">Groß-/Kleinschreibung beachten!</div>
                    </div>
                    <button type="submit" name="reset_everything" class="btn btn-warning">
                        ⚠️ System komplett zurücksetzen
                    </button>
                </form>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-secondary">← Zurück zum Organisator</a>
        </div>
    </div>
</body>
</html>