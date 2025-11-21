<?php
    define( 'WP_DEBUG_LOG', true );
    define( 'WP_DEBUG_DISPLAY', false );
session_start();
require_once __DIR__ . '/../db/Database.php';

// Login prüfen
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, password FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                header('Location: index.php');
                exit;
            } else {
                $loginError = 'Ungültige Zugangsdaten';
            }
        } catch (PDOException $e) {
            $loginError = 'Datenbankfehler';
        }
    }
    
    // Login-Formular anzeigen
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Organisator Login - KIckstart Kassel</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-box {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 100%;
            }
            h1 { color: #667eea; margin-bottom: 30px; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
            input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 16px;
            }
            input:focus { outline: none; border-color: #667eea; }
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
            }
            button:hover { opacity: 0.9; }
            .error {
                background: #fee;
                color: #c33;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Organisator Login</h1>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Benutzername:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Passwort:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login">Anmelden</button>
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
    header('Location: index.php');
    exit;
}

// API-Endpunkte für AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $db = Database::getInstance()->getConnection();
    
    try {
        switch ($_POST['action']) {
            case 'create_workshop':
                $stmt = $db->prepare("INSERT INTO workshops (title, description, max_participants, timeslot, location) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['max_participants'],
                    $_POST['timeslot'],
                    $_POST['location']
                ]);
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_workshop':
                $stmt = $db->prepare("DELETE FROM workshops WHERE id = ?");
                $stmt->execute([$_POST['workshop_id']]);
                echo json_encode(['success' => true]);
                break;
                
            case 'move_participant':
                $db->beginTransaction();
                // Alte Anmeldung löschen
                $stmt = $db->prepare("DELETE FROM registrations WHERE participant_id = ? AND workshop_id = ?");
                $stmt->execute([$_POST['participant_id'], $_POST['from_workshop']]);
                // Neue Anmeldung erstellen
                $stmt = $db->prepare("INSERT INTO registrations (participant_id, workshop_id) VALUES (?, ?)");
                $stmt->execute([$_POST['participant_id'], $_POST['to_workshop']]);
                $db->commit();
                echo json_encode(['success' => true]);
                break;
                
            case 'close_event':
                $stmt = $db->prepare("UPDATE event_status SET is_closed = TRUE, closed_at = NOW()");
                $stmt->execute();
                // Alle Registrierungen auf "confirmed" setzen
                $stmt = $db->prepare("UPDATE registrations SET status = 'confirmed'");
                $stmt->execute();
                echo json_encode(['success' => true]);
                break;
                
            case 'reopen_event':
                $stmt = $db->prepare("UPDATE event_status SET is_closed = FALSE, closed_at = NULL");
                $stmt->execute();
                echo json_encode(['success' => true]);
                break;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Daten laden
$db = Database::getInstance()->getConnection();

// Event-Status
$stmt = $db->query("SELECT * FROM event_status LIMIT 1");
$eventStatus = $stmt->fetch();

// Workshops mit Teilnehmern
$stmt = $db->query("
    SELECT 
        w.*,
        COUNT(r.id) as participant_count
    FROM workshops w
    LEFT JOIN registrations r ON w.id = r.workshop_id
    GROUP BY w.id
    ORDER BY w.timeslot, w.title
");
$workshops = $stmt->fetchAll();

// Alle Teilnehmer mit ihren Anmeldungen
$stmt = $db->query("
    SELECT 
        p.id,
        p.name,
        GROUP_CONCAT(w.title SEPARATOR ', ') as workshops,
        COUNT(r.id) as workshop_count
    FROM participants p
    LEFT JOIN registrations r ON p.id = r.participant_id
    LEFT JOIN workshops w ON r.workshop_id = w.id
    GROUP BY p.id
    ORDER BY p.name
");
$participants = $stmt->fetchAll();

// Detaillierte Workshop-Teilnehmer
$workshopDetails = [];
foreach ($workshops as $workshop) {
    $stmt = $db->prepare("
        SELECT p.id, p.name, r.status
        FROM registrations r
        JOIN participants p ON r.participant_id = p.id
        WHERE r.workshop_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$workshop['id']]);
    $workshopDetails[$workshop['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisator - KIckstart Kassel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header h1 { font-size: 2rem; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-small { padding: 8px 16px; font-size: 13px; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover td { background: #f8f9fa; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #4caf50; color: white; }
        .badge-warning { background: #ff9800; color: white; }
        .badge-info { background: #2196f3; color: white; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .alert-warning {
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc80;
        }
        .workshop-detail {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
        }
        .participant-list {
            list-style: none;
        }
        .participant-item {
            padding: 10px;
            background: white;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media (max-width: 768px) {
            table { font-size: 14px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🎯 Organisator Dashboard</h1>
            <p>KIckstart Kassel Workshop Management</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($eventStatus['is_closed']): ?>
                <span class="badge badge-warning" style="padding: 10px 20px; font-size: 14px;">📋 Event geschlossen</span>
                <button class="btn btn-secondary" onclick="reopenEvent()">Wiedereröffnen</button>
            <?php else: ?>
                <button class="btn btn-success" onclick="showCloseConfirm()">Organisation beenden</button>
            <?php endif; ?>
            <a href="?logout" class="btn btn-secondary">Abmelden</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($workshops); ?></div>
            <div class="stat-label">Workshops</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($participants); ?></div>
            <div class="stat-label">Teilnehmer</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php 
                $totalRegistrations = 0;
                foreach ($participants as $p) {
                    $totalRegistrations += $p['workshop_count'];
                }
                echo $totalRegistrations;
                ?>
            </div>
            <div class="stat-label">Anmeldungen</div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; padding: 0; border: none;">📚 Workshops</h2>
            <button class="btn btn-primary" onclick="showCreateWorkshop()">+ Workshop erstellen</button>
        </div>

        <?php if (empty($workshops)): ?>
            <p style="color: #999; text-align: center; padding: 40px;">Noch keine Workshops vorhanden</p>
        <?php else: ?>
            <?php foreach ($workshops as $workshop): ?>
                <div class="workshop-detail">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div>
                            <h3 style="color: #333; margin-bottom: 8px;"><?php echo htmlspecialchars($workshop['title']); ?></h3>
                            <p style="color: #666; margin-bottom: 5px;">
                                📅 <?php echo htmlspecialchars($workshop['timeslot'] ?: 'Zeit nicht festgelegt'); ?>
                                <?php if ($workshop['location']): ?>
                                    • 📍 <?php echo htmlspecialchars($workshop['location']); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($workshop['description']): ?>
                                <p style="color: #666; font-size: 14px;"><?php echo htmlspecialchars($workshop['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <span class="badge badge-info">
                                <?php echo $workshop['participant_count']; ?> / <?php echo $workshop['max_participants']; ?> Teilnehmer
                            </span>
                            <button class="btn btn-danger btn-small" onclick="deleteWorkshop(<?php echo $workshop['id']; ?>)">Löschen</button>
                        </div>
                    </div>

                    <?php if (!empty($workshopDetails[$workshop['id']])): ?>
                        <ul class="participant-list">
                            <?php foreach ($workshopDetails[$workshop['id']] as $participant): ?>
                                <li class="participant-item">
                                    <span>
                                        👤 <?php echo htmlspecialchars($participant['name']); ?>
                                        <?php if ($participant['status'] === 'confirmed'): ?>
                                            <span class="badge badge-success">Bestätigt</span>
                                        <?php endif; ?>
                                    </span>
                                    <button class="btn btn-secondary btn-small" 
                                            onclick="showMoveParticipant('<?php echo $participant['id']; ?>', '<?php echo htmlspecialchars($participant['name']); ?>', <?php echo $workshop['id']; ?>)">
                                        Verschieben
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #999; font-style: italic;">Noch keine Anmeldungen</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>👥 Alle Teilnehmer</h2>
        <?php if (empty($participants)): ?>
            <p style="color: #999; text-align: center; padding: 40px;">Noch keine Teilnehmer registriert</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Angemeldete Workshops</th>
                        <th>Anzahl</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $participant): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($participant['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($participant['workshops'] ?: 'Keine Anmeldungen'); ?></td>
                            <td>
                                <?php if ($participant['workshop_count'] == 0): ?>
                                    <span class="badge badge-warning">0</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?php echo $participant['workshop_count']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Modal: Workshop erstellen -->
    <div id="modalCreateWorkshop" class="modal">
        <div class="modal-content">
            <h2>Workshop erstellen</h2>
            <form id="formCreateWorkshop" onsubmit="createWorkshop(event)">
                <div class="form-group">
                    <label>Titel *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Beschreibung</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>Zeitslot</label>
                    <input type="text" name="timeslot" placeholder="z.B. 10:00 - 12:00">
                </div>
                <div class="form-group">
                    <label>Ort</label>
                    <input type="text" name="location" placeholder="z.B. Raum A">
                </div>
                <div class="form-group">
                    <label>Max. Teilnehmer *</label>
                    <input type="number" name="max_participants" value="20" min="1" required>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Erstellen</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalCreateWorkshop')">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Teilnehmer verschieben -->
    <div id="modalMoveParticipant" class="modal">
        <div class="modal-content">
            <h2>Teilnehmer verschieben</h2>
            <p id="moveParticipantInfo" style="margin-bottom: 20px; color: #666;"></p>
            <form id="formMoveParticipant" onsubmit="moveParticipant(event)">
                <input type="hidden" name="participant_id" id="moveParticipantId">
                <input type="hidden" name="from_workshop" id="moveFromWorkshop">
                <div class="form-group">
                    <label>Ziel-Workshop *</label>
                    <select name="to_workshop" id="moveToWorkshop" required>
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($workshops as $w): ?>
                            <option value="<?php echo $w['id']; ?>">
                                <?php echo htmlspecialchars($w['title']); ?> 
                                (<?php echo $w['participant_count']; ?>/<?php echo $w['max_participants']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Verschieben</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalMoveParticipant')">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Event schließen bestätigen -->
    <div id="modalCloseConfirm" class="modal">
        <div class="modal-content">
            <h2>⚠️ Organisation beenden?</h2>
            <div class="alert alert-warning">
                <strong>Achtung!</strong><br>
                Nach dem Beenden der Organisation können Teilnehmer sich nicht mehr an- oder abmelden. 
                Alle aktuellen Anmeldungen werden bestätigt.
            </div>
            <p>Möchten Sie die Organisation wirklich beenden?</p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-danger" onclick="closeEvent()">Ja, beenden</button>
                <button class="btn btn-secondary" onclick="closeModal('modalCloseConfirm')">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showCreateWorkshop() {
            document.getElementById('formCreateWorkshop').reset();
            showModal('modalCreateWorkshop');
        }

        function showMoveParticipant(participantId, participantName, fromWorkshop) {
            document.getElementById('moveParticipantId').value = participantId;
            document.getElementById('moveFromWorkshop').value = fromWorkshop;
            document.getElementById('moveParticipantInfo').textContent = 
                `Teilnehmer: ${participantName}`;
            
            // Aktuellen Workshop aus Auswahl entfernen
            const select = document.getElementById('moveToWorkshop');
            for (let option of select.options) {
                option.disabled = (option.value == fromWorkshop);
            }
            
            showModal('modalMoveParticipant');
        }

        function showCloseConfirm() {
            showModal('modalCloseConfirm');
        }

        async function createWorkshop(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'create_workshop');

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Workshop erfolgreich erstellt!');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Verbindungsfehler');
            }
        }

        async function deleteWorkshop(workshopId) {
            if (!confirm('Workshop wirklich löschen? Alle Anmeldungen gehen verloren!')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_workshop');
            formData.append('workshop_id', workshopId);

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Workshop gelöscht!');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Verbindungsfehler');
            }
        }

        async function moveParticipant(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'move_participant');

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Teilnehmer erfolgreich verschoben!');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Verbindungsfehler');
            }
        }

        async function closeEvent() {
            const formData = new FormData();
            formData.append('action', 'close_event');

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Organisation wurde beendet! Teilnehmer wurden benachrichtigt.');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Verbindungsfehler');
            }
        }

        async function reopenEvent() {
            if (!confirm('Event wirklich wiedereröffnen?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reopen_event');

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Event wurde wiedereröffnet!');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                alert('Verbindungsfehler');
            }
        }

        // Modal schließen bei Klick außerhalb
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>