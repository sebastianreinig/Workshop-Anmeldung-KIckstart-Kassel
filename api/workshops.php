<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Event-Status prüfen
        $stmt = $db->query("SELECT is_closed FROM event_status LIMIT 1");
        $eventStatus = $stmt->fetch();
        
        // Workshops mit Teilnehmerzahl abrufen
        $stmt = $db->query("
            SELECT 
                w.id,
                w.title,
                w.description,
                w.max_participants,
                w.timeslot,
                w.location,
                COUNT(r.id) as current_participants
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id
            GROUP BY w.id
            ORDER BY w.timeslot, w.title
        ");
        
        $workshops = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
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