<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Temporar - comentează verificarea de sesiune pentru debugging
/*
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
*/

// Includem conexiunea la baza de date
include 'db.php';

try {
    // Verifică și adaugă coloana rating dacă nu există
    $checkRatingColumn = "SHOW COLUMNS FROM testimonials_simple LIKE 'rating'";
    $stmt = $pdo->query($checkRatingColumn);
    if ($stmt->rowCount() == 0) {
        $addRatingColumn = "ALTER TABLE testimonials_simple ADD COLUMN rating INT DEFAULT 5 AFTER role";
        $pdo->exec($addRatingColumn);
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Testimonials API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Funcția pentru GET - obține toate testimonialele
function handleGet($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials_simple WHERE status = 'active' ORDER BY created_at DESC");
        $stmt->execute();
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => $testimonials,
            'count' => count($testimonials)
        ]);
    } catch (Exception $e) {
        throw new Exception('Eroare la obținerea testimonialelor: ' . $e->getMessage());
    }
}

// Funcția pentru POST - adaugă testimonial nou
function handlePost($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug logging
        error_log("POST Input received: " . print_r($input, true));
        
        if (!$input) {
            throw new Exception('Date invalide - JSON nu poate fi decodat');
        }
        
        // Validare cu debugging
        $name = trim($input['name'] ?? '');
        $role = trim($input['role'] ?? 'Clientă mulțumită');
        $text = trim($input['text'] ?? '');
        $rating = intval($input['rating'] ?? 5);
        
        // Debug info
        error_log("Validating - Name: '$name' (length: " . strlen($name) . "), Text: '$text' (length: " . strlen($text) . ")");
        
        if (empty($name) || strlen($name) < 2) {
            throw new Exception('Numele trebuie să aibă cel puțin 2 caractere. Primit: "' . $name . '" (lungime: ' . strlen($name) . ')');
        }
        
        if (empty($text) || strlen($text) < 10) {
            throw new Exception('Textul trebuie să aibă cel puțin 10 caractere. Primit: "' . substr($text, 0, 50) . '..." (lungime: ' . strlen($text) . ')');
        }
        
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }
        
        // Sanitizare
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $role = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Inserare în baza de date
        $stmt = $pdo->prepare("
            INSERT INTO testimonials_simple (text, name, role, rating, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        
        $result = $stmt->execute([$text, $name, $role, $rating]);
        
        if (!$result) {
            throw new Exception('Eroare la inserarea în baza de date');
        }
        
        $newId = $pdo->lastInsertId();
        
        // Returnează testimonialul creat
        $stmt = $pdo->prepare("SELECT * FROM testimonials_simple WHERE id = ?");
        $stmt->execute([$newId]);
        $newTestimonial = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Testimonial adăugat cu succes',
            'data' => $newTestimonial
        ]);
        
    } catch (Exception $e) {
        error_log("Add testimonial error: " . $e->getMessage());
        throw new Exception('Eroare la adăugarea testimonialului: ' . $e->getMessage());
    }
}

// Funcția pentru DELETE - șterge testimonial
function handleDelete($pdo) {
    try {
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID invalid');
        }
        
        // Verifică dacă testimonialul există
        $stmt = $pdo->prepare("SELECT id FROM testimonials_simple WHERE id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Testimonialul nu există');
        }
        
        // Șterge testimonialul
        $stmt = $pdo->prepare("DELETE FROM testimonials_simple WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Testimonial șters cu succes'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Eroare la ștergerea testimonialului: ' . $e->getMessage());
    }
}
?>