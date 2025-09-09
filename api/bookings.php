<?php
// CORS Headers - TREBUIE SA FIE PRIMELE
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('Content-Type: application/json');

// Gestionăm requesturile OPTIONS pentru CORS PREFLIGHT
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Doar returnăm headerele CORS și ieșim
    http_response_code(200);
    exit();
}

// Debug: Log toate requesturile
error_log("=== API BOOKINGS DEBUG ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers: " . print_r(getallheaders(), true));

// Pentru GET - returnăm un mesaj de status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'API is working',
        'message' => 'Booking API endpoint is accessible',
        'method' => 'GET',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Verificăm dacă requestul este POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Doar metoda POST este permisă pentru rezervări'
    ]);
    exit();
}

try {
    error_log("Step 1: POST method received");
    
    // Preluăm datele JSON
    $input = file_get_contents('php://input');
    error_log("Step 2: Input received: " . $input);
    
    if (empty($input)) {
        throw new Exception('Nu s-au primit date JSON');
    }
    
    $data = json_decode($input, true);
    error_log("Step 3: JSON decoded");
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Date JSON invalide: ' . json_last_error_msg());
    }
    
    error_log("Step 4: JSON validation passed");
    error_log("Received data: " . print_r($data, true));
    
    // Include database
    error_log("Step 5: Including database...");
    if (!file_exists('db.php')) {
        throw new Exception('Fișierul db.php nu există');
    }
    
    include_once 'db.php';
    error_log("Step 6: Database included successfully");
    
    // Verificăm conexiunea PDO
    if (!isset($pdo)) {
        throw new Exception('Conexiunea PDO nu este disponibilă');
    }
    error_log("Step 7: PDO connection available");
    
    // Extragem și validăm datele
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    $name = trim($firstName . ' ' . $lastName);
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $consult_type = $data['serviceId'] ?? '';
    $date = $data['date'] ?? '';
    $hour = $data['hour'] ?? '';
    $payment_method = $data['paymentMethod'] ?? 'card';
    $notes = trim($data['notes'] ?? '');
    
    error_log("Step 8: Data extracted");
    error_log("Name: '$name', Email: '$email', Date: '$date', Hour: '$hour'");
    
    // Validări
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Numele este obligatoriu";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email valid este obligatoriu";
    }
    
    if (empty($date)) {
        $errors[] = "Data este obligatorie";
    }
    
    if (empty($hour)) {
        $errors[] = "Ora este obligatorie";
    }
    
    if (!empty($errors)) {
        throw new Exception('Erori de validare: ' . implode(', ', $errors));
    }
    
    error_log("Step 9: Validation passed");
    
    // Formatăm ora corect
    if (preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $hour)) {
        $hour_formatted = $hour . ':00';
    } else {
        $hour_formatted = $hour;
    }
    
    // Începem tranzacția
    $pdo->beginTransaction();
    error_log("Step 10: Transaction started");
    
    try {
        // Verificăm dacă clientul există
        $checkClient = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $checkClient->execute([$email]);
        $client = $checkClient->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $client_id = $client['id'];
            error_log("Step 10.1: Existing client found with ID: $client_id");
        } else {
            // Creăm client nou
            $insertClient = $pdo->prepare("INSERT INTO clients (name, email, phone, created_at) VALUES (?, ?, ?, NOW())");
            $insertClient->execute([$name, $email, $phone]);
            $client_id = $pdo->lastInsertId();
            error_log("Step 10.2: New client created with ID: $client_id");
        }
        
        // Verificăm dacă există tabela available_slots
        $checkTable = $pdo->query("SHOW TABLES LIKE 'available_slots'");
        if ($checkTable->rowCount() == 0) {
            error_log("Warning: Table 'available_slots' does not exist, proceeding without slot validation");
            $slot_id = null;
        } else {
            // Căutăm slot disponibil
            $findSlot = $pdo->prepare("SELECT id FROM available_slots WHERE slot_date = ? AND (slot_time = ? OR slot_time = ?) AND status = 'available' LIMIT 1");
            $findSlot->execute([$date, $hour, $hour_formatted]);
            $slot = $findSlot->fetch(PDO::FETCH_ASSOC);
            
            if ($slot) {
                $slot_id = $slot['id'];
                error_log("Step 10.3: Found slot_id: $slot_id");
            } else {
                // Nu există slot, dar continuăm (poate nu folosești sistemul de sloturi)
                error_log("Warning: No available slot found for $date at $hour, proceeding without slot");
                $slot_id = null;
            }
        }
        
        // Inserăm rezervarea
        $sql = "INSERT INTO bookings (client_id, slot_id, name, email, phone, consult_type, date, hour, payment_method, notes, booked_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $client_id,
            $slot_id,
            $name,
            $email,
            $phone,
            $consult_type,
            $date,
            $hour,
            $payment_method,
            $notes
        ]);
        
        if (!$result) {
            throw new Exception("Eroare la inserarea rezervării în baza de date");
        }
        
        $booking_id = $pdo->lastInsertId();
        error_log("Step 11: Booking saved with ID: $booking_id");
        
        // Marcăm slotul ca ocupat (dacă există)
        if ($slot_id) {
            $updateSlot = $pdo->prepare("UPDATE available_slots SET status = 'booked' WHERE id = ?");
            $updateSlot->execute([$slot_id]);
            error_log("Step 11.1: Slot marked as booked");
        }
        
        // Confirmăm tranzacția
        $pdo->commit();
        error_log("Step 12: Transaction committed successfully");
        
        // Răspuns de succes
        echo json_encode([
            'success' => true,
            'message' => 'Rezervarea a fost salvată cu succes!',
            'data' => [
                'bookingId' => $booking_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'consult_type' => $consult_type,
                'date' => $date,
                'hour' => $hour,
                'payment_method' => $payment_method,
                'client_id' => $client_id,
                'slot_id' => $slot_id
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback în caz de eroare
        $pdo->rollBack();
        error_log("Transaction rolled back: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("FINAL ERROR: " . $e->getMessage());
    error_log("Error on line: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
?>