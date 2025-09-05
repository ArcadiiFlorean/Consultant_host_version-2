<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
include_once 'db.php';  
    
    // Verifică dacă conexiunea la baza de date există
    if (!isset($pdo)) {
        throw new Exception("Database connection failed");
    }
    
    // Preia sloturile disponibile din baza de date
    // Doar sloturile cu status 'available' și care nu sunt rezervate
    $stmt = $pdo->prepare("
        SELECT 
            slot_date, 
            slot_time,
            CONCAT(slot_date, 'T', slot_time) as datetime_combined
        FROM available_slots 
        WHERE status = 'available' 
        AND slot_date >= CURDATE()
        AND CONCAT(slot_date, ' ', slot_time) > NOW()
        ORDER BY slot_date ASC, slot_time ASC
    ");
    $stmt->execute();
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verifică dacă există sloturi
    if (empty($slots)) {
        // Returnează răspuns de succes dar cu array gol
        echo json_encode([
            'success' => true,
            'slots' => [],
            'count' => 0,
            'message' => 'No available slots found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Returnează răspunsul JSON
    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'count' => count($slots),
        'message' => 'Available slots loaded successfully'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Eroare de bază de date
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'Could not retrieve available slots from database',
        'debug' => $e->getMessage() // Elimină asta în producție pentru securitate
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Alte erori
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error', 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>