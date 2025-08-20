<?php
// Setează headers pentru CORS și JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Funcție pentru logging (opțional)
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - API Error: " . $message . "\n", 3, "api_errors.log");
}

try {
    // Încearcă să includă fișierul de conexiune la baza de date
    $db_file = 'db.php';
    if (!file_exists($db_file)) {
        throw new Exception("Database configuration file not found: $db_file");
    }
    
    include $db_file;
    
    // Verifică dacă conexiunea PDO există
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
    
    // Testează conexiunea
    $pdo->query('SELECT 1');
    
    // Execută query-ul pentru servicii
    $stmt = $pdo->prepare("SELECT * FROM services ORDER BY popular DESC, id ASC");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transformă datele pentru frontend
    $transformedServices = [];
    foreach ($services as $service) {
        // Split features by | separator
        $features = [];
        if (!empty($service['features'])) {
            $features = array_filter(explode('|', $service['features']));
            $features = array_map('trim', $features); // Remove whitespace
        }
        
        // Mapează iconurile la culori
        $colorMap = [
            'consultation' => 'orange',
            'premium' => 'red',
            'emergency' => 'amber'
        ];
        
        $icon = $service['icon'] ?? 'consultation';
        $color = $colorMap[$icon] ?? 'orange';
        
        // Generează stats bazat pe tipul serviciului
        $stats = '';
        switch ($icon) {
            case 'consultation':
                $stats = '90 min';
                break;
            case 'premium':
                $stats = '6 luni suport';
                break;
            case 'emergency':
                $stats = '< 2h răspuns';
                break;
            default:
                $stats = 'suport inclus';
        }
        
        $transformedServices[] = [
            'id' => (int)$service['id'],
            'name' => $service['name'] ?? '',
            'description' => $service['description'] ?? '',
            'price' => $service['price'] ?? '0',
            'currency' => $service['currency'] ?? 'GBP',
            'popular' => (bool)($service['popular'] ?? false),
            'features' => $features,
            'icon' => $icon,
            'color' => $color,
            'stats' => $service['stats'] ?? $stats
        ];
    }
    
    // Returnează răspunsul JSON
    $response = [
        'success' => true,
        'data' => $transformedServices,
        'count' => count($transformedServices),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // Erroare de bază de date
    logError("Database error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error',
        'error' => 'Unable to connect to database',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Alte erori
    logError("General error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>