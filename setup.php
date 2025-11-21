<?php
session_start();

// Prüfen ob Setup bereits durchgeführt wurde
$setupCheckFile = __DIR__ . '/setup.lock';

if (file_exists($setupCheckFile)) {
    die('Setup wurde bereits durchgeführt. Bitte löschen Sie die Datei "setup.lock" um das Setup erneut auszuführen.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = $_POST['admin_user'] ?? '';
    $adminPass = $_POST['admin_pass'] ?? '';
    
    if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPass)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } else {
        try {
            // Datenbankverbindung testen
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Datenbank erstellen falls nicht vorhanden
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            
            // Tabellen erstellen
            $sql = "
            CREATE TABLE IF NOT EXISTS setup_status (
                id INT PRIMARY KEY AUTO_INCREMENT,
                completed BOOLEAN DEFAULT TRUE,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS admins (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS workshops (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                max_participants INT DEFAULT 20,
                timeslot VARCHAR(50),
                location VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS participants (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                participant_id VARCHAR(36) NOT NULL,
                workshop_id INT NOT NULL,
                registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'confirmed') DEFAULT 'pending',
                FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
                FOREIGN KEY (workshop_id) REFERENCES workshops(id) ON DELETE CASCADE,
                UNIQUE KEY unique_registration (participant_id, workshop_id)
            );
            
            CREATE TABLE IF NOT EXISTS event_status (
                id INT PRIMARY KEY AUTO_INCREMENT,
                is_closed BOOLEAN DEFAULT FALSE,
                closed_at TIMESTAMP NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
            
            INSERT INTO event_status (is_closed) VALUES (FALSE);
            ";
            
            $pdo->exec($sql);
            
            // Admin-Benutzer erstellen
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->execute([$adminUser, $hashedPassword]);
            
            // Setup-Status speichern
            $pdo->exec("INSERT INTO setup_status (completed) VALUES (TRUE)");
            
            // Config-Datei erstellen
            $configContent = "<?php
// Datenbankkonfiguration
define('DB_HOST', " . var_export($dbHost, true) . ");
define('DB_NAME', " . var_export($dbName, true) . ");
define('DB_USER', " . var_export($dbUser, true) . ");
define('DB_PASS', " . var_export($dbPass, true) . ");

// Weitere Konfigurationen
define('SITE_URL', " . var_export((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), true) . ");
";
            
            file_put_contents(__DIR__ . '/config.php', $configContent);
            
            // Lock-Datei erstellen
            file_put_contents($setupCheckFile, date('Y-m-d H:i:s'));
            
            $success = 'Setup erfolgreich abgeschlossen! Sie können sich nun im Organisator-Bereich anmelden.';
            
        } catch (PDOException $e) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - KIckstart Kassel Workshop System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }
        
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Setup</h1>
        <p class="subtitle">KIckstart Kassel Workshop System</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div class="info-box">
                <h3>Nächste Schritte:</h3>
                <ul>
                    <li><strong>Teilnehmer-Ansicht:</strong> /teilnehmer/</li>
                    <li><strong>Organisator-Ansicht:</strong> /organisator/</li>
                </ul>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Datenbank Host:</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Datenbank Name:</label>
                    <input type="text" id="db_name" name="db_name" value="kickstart_workshops" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Datenbank Benutzer:</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Datenbank Passwort:</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">
                
                <div class="form-group">
                    <label for="admin_user">Admin Benutzername:</label>
                    <input type="text" id="admin_user" name="admin_user" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_pass">Admin Passwort:</label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                </div>
                
                <button type="submit">Setup starten</button>
            </form>
            
            <div class="info-box">
                <h3>ℹ️ Hinweise:</h3>
                <ul>
                    <li>Das Setup kann nur einmal ausgeführt werden</li>
                    <li>Die Datenbank wird automatisch erstellt</li>
                    <li>Alle Tabellen werden automatisch angelegt</li>
                    <li>Merken Sie sich Ihre Admin-Zugangsdaten!</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>