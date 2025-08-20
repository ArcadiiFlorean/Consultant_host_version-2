<?php
// debug_document_38.php
header('Content-Type: application/json');

try {
    include 'db.php';
    
    $documentId = 38;
    $stmt = $pdo->prepare("SELECT * FROM public_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $uploadsDir = __DIR__ . '/uploads/documents/';
    $uploadsDir2 = __DIR__ . '/uploads/';
    
    $info = [
        'document_data' => $document,
        'uploads_documents_dir' => [
            'path' => $uploadsDir,
            'exists' => is_dir($uploadsDir),
            'files' => is_dir($uploadsDir) ? scandir($uploadsDir) : []
        ],
        'uploads_dir' => [
            'path' => $uploadsDir2,
            'exists' => is_dir($uploadsDir2),
            'files' => is_dir($uploadsDir2) ? scandir($uploadsDir2) : []
        ]
    ];
    
    if ($document) {
        $filename = $document['original_filename'];
        $info['file_search'] = [
            'looking_for' => $filename,
            'path1' => $uploadsDir . $filename,
            'path1_exists' => file_exists($uploadsDir . $filename),
            'path2' => $uploadsDir2 . $filename,
            'path2_exists' => file_exists($uploadsDir2 . $filename)
        ];
    }
    
    echo json_encode($info, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>