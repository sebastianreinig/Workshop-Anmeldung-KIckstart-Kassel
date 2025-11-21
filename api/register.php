<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $participantId = $data['participant_id'] ?? '';
    $workshopId = $data['workshop_id'] ?? '';
    
    if (empty($participantId) || empty($workshopId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Teilnehmer-ID und Workshop-ID sind erforderlich']);
        exit;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Event-Status prüfen
        $stmt = $db->query("SELECT is_closed FROM event_status LIMIT 1");
        $eventStatus = $stmt->fetch();
        if ($eventStatus['is_closed']) {
            http_response_code(403);
            echo json_encode(['error' => 'Anmeldungen sind geschlossen']);
            exit;
        }
        
        // Prüfen ob Teilnehmer existiert
        $stmt = $db->prepare("SELECT id FROM participants WHERE id = ?");
        $stmt->execute([$participantId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Teilnehmer nicht gefunden']);
            exit;
        }
        
        // Anzahl der Anmeldungen prüfen (max 2)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE participant_id = ?");
        $stmt->execute([$participantId]);
        $result = $stmt->fetch();
        
        if ($result['count'] >= 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Maximale Anzahl von 2 Workshops erreicht']);
            exit;
        }
        
        // Prüfen ob Workshop voll ist
        $stmt = $db->prepare("
            SELECT w.max_participants, COUNT(r.id) as current_participants
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id
            WHERE w.id = ?
            GROUP BY w.id
        ");
        $stmt->execute([$workshopId]);
        $workshop = $stmt->fetch();
        
        if (!$workshop) {
            http_response_code(404);
            echo json_encode(['error' => 'Workshop nicht gefunden']);
            exit;
        }
        
        if ($workshop['current_participants'] >= $workshop['max_participants']) {
            http_response_code(400);
            echo json_encode(['error' => 'Workshop ist bereits voll']);
            exit;
        }
        
        // Anmeldung erstellen
        $stmt = $db->prepare("INSERT INTO registrations (participant_id, workshop_id) VALUES (?, ?)");
        $stmt->execute([$participantId, $workshopId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Erfolgreich angemeldet'
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            http_response_code(400);
            echo json_encode(['error' => 'Sie sind bereits für diesen Workshop angemeldet']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Datenbankfehler']);
        }
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $participantId = $data['participant_id'] ?? '';
    $workshopId = $data['workshop_id'] ?? '';
    
    if (empty($participantId) || empty($workshopId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Teilnehmer-ID und Workshop-ID sind erforderlich']);
        exit;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Event-Status prüfen
        $stmt = $db->query("SELECT is_closed FROM event_status LIMIT 1");
        $eventStatus = $stmt->fetch();
        if ($eventStatus['is_closed']) {
            http_response_code(403);
            echo json_encode(['error' => 'Abmeldungen sind geschlossen']);
            exit;
        }
        
        // Anmeldung löschen
        $stmt = $db->prepare("DELETE FROM registrations WHERE participant_id = ? AND workshop_id = ?");
        $stmt->execute([$participantId, $workshopId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Erfolgreich abgemeldet'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Anmeldung nicht gefunden']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
}
?>