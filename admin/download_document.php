<?php
// download_document.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Logging pentru debugging
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Download Error: " . $message . "\n", 3, "download_errors.log");
}

try {
    // Verifică dacă ID-ul este furnizat
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID document lipsește');
    }

    $documentId = intval($_GET['id']);
    if ($documentId <= 0) {
        throw new Exception('ID document invalid');
    }

    // Include conexiunea la baza de date
    include 'db.php';

    // Caută documentul în baza de date (FĂRĂ file_path)
    $stmt = $pdo->prepare("
        SELECT 
            id, title, original_filename, 
            file_type, file_size, downloads_count 
        FROM public_documents 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception("Documentul cu ID $documentId nu a fost găsit");
    }

    // Construiește calea către fișier cu mapare inteligentă
    $uploadsDir = __DIR__ . '/uploads/documents/';
    $originalFilename = $document['original_filename'];
    
    // Strategii multiple pentru a găsi fișierul
    $filePath = null;
    
    // 1. Încearcă calea directă
    $directPath = $uploadsDir . $originalFilename;
    if (file_exists($directPath)) {
        $filePath = $directPath;
        logError("Fișier găsit direct: $directPath pentru documentul ID $documentId");
    } else {
        // 2. Caută prin toate fișierele și încearcă maparea inteligentă
        if (is_dir($uploadsDir)) {
            $files = scandir($uploadsDir);
            $originalExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $originalBasename = strtolower(pathinfo($originalFilename, PATHINFO_FILENAME));
            
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($files as $file) {
                if (in_array($file, ['.', '..']) || !is_file($uploadsDir . $file)) continue;
                
                $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Prioritate 1: Aceeași extensie
                if ($fileExtension === $originalExtension) {
                    $fileBasename = strtolower(pathinfo($file, PATHINFO_FILENAME));
                    
                    // Prioritate 2: Nume similar sau conține părți din nume
                    $score = 0;
                    if ($fileBasename === $originalBasename) {
                        $score = 100; // Perfect match
                    } elseif (strpos($fileBasename, $originalBasename) !== false || strpos($originalBasename, $fileBasename) !== false) {
                        $score = 80; // Partial match
                    } elseif ($fileExtension === $originalExtension) {
                        $score = 60; // Same extension only
                    }
                    
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $file;
                    }
                }
            }
            
            // Dacă am găsit o potrivire, folosește-o
            if ($bestMatch && $bestScore >= 60) {
                $filePath = $uploadsDir . $bestMatch;
                logError("Fișier găsit prin mapare inteligentă: $filePath (score: $bestScore) pentru documentul ID $documentId");
            }
        }
    }
    
    if (!$filePath) {
        $debugInfo = [
            'original_filename' => $originalFilename,
            'uploads_dir' => $uploadsDir,
            'files_in_documents_dir' => is_dir($uploadsDir) ? array_values(array_filter(scandir($uploadsDir), function($f) use ($uploadsDir) { 
                return !in_array($f, ['.', '..']) && is_file($uploadsDir . $f); 
            })) : 'Directory not found',
            'direct_path_tried' => $directPath,
            'direct_path_exists' => file_exists($directPath)
        ];
        logError("Fișierul nu poate fi găsit. Debug: " . json_encode($debugInfo));
        throw new Exception("Fișierul fizic nu poate fi găsit pe server. Se caută: $originalFilename");
    }

    // Verifică permisiunile de citire
    if (!is_readable($filePath)) {
        throw new Exception("Fișierul nu poate fi citit (probleme de permisiuni)");
    }

    // Actualizează contorul de descărcări
    $updateStmt = $pdo->prepare("
        UPDATE public_documents 
        SET downloads_count = downloads_count + 1 
        WHERE id = ?
    ");
    $updateStmt->execute([$documentId]);

    // Setează header-ele pentru descărcare
    $fileName = $document['original_filename'];
    $fileSize = filesize($filePath);
    $mimeType = $document['file_type'] ?: 'application/octet-stream';

    // Curăță orice output anterior
    if (ob_get_level()) {
        ob_clean();
    }

    // Header-e pentru descărcare
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Trimite fișierul
    readfile($filePath);
    
    // Log successful download
    logError("Descărcare reușită: $fileName (ID: $documentId) - fișier fizic: " . basename($filePath));
    
    exit;

} catch (Exception $e) {
    logError($e->getMessage());
    
    // Header pentru eroare
    http_response_code(404);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'document_id' => $documentId ?? 'N/A',
            'expected_filename' => $document['original_filename'] ?? 'N/A',
            'uploads_dir' => __DIR__ . '/uploads/documents/',
            'current_dir' => __DIR__
        ]
    ]);
    exit;
}
?>