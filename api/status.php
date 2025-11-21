<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(503);
    echo json_encode(['error' => 'System nicht konfiguriert']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $participantId = $_GET['participant_id'] ?? '';
    
    if (empty($participantId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Teilnehmer-ID ist erforderlich']);
        exit;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Teilnehmer-Info abrufen
        $stmt = $db->prepare("SELECT name FROM participants WHERE id = ?");
        $stmt->execute([$participantId]);
        $participant = $stmt->fetch();
        
        if (!$participant) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Teilnehmer nicht gefunden'
            ]);
            exit;
        }
        
        // Angemeldete Workshops abrufen
        $stmt = $db->prepare("
            SELECT 
                w.id,
                w.title,
                w.description,
                w.timeslot,
                w.location,
                r.status,
                r.registered_at
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.participant_id = ?
            ORDER BY w.timeslot
        ");
        $stmt->execute([$participantId]);
        $workshops = $stmt->fetchAll();
        
        // Event-Status prüfen
        $stmt = $db->query("SELECT is_closed FROM event_status LIMIT 1");
        $eventStatus = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'participant' => [
                'id' => $participantId,
                'name' => $participant['name']
            ],
            'workshops' => $workshops,
            'event_closed' => (bool)($eventStatus['is_closed'] ?? false)
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
}
?>