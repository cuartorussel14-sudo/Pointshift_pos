<?php
require_once '../config.php';
User::requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Text data is required']);
    exit();
}

try {
    // Function to extract date from text using regex patterns
    function extractExpiryDate($text) {
        // Common date formats to check
        $patterns = [
            // DD/MM/YYYY or DD-MM-YYYY
            '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})\b/',
            // YYYY/MM/DD or YYYY-MM-DD
            '/\b(20\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/',
            // Month DD, YYYY
            '/\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{1,2}),?\s+(20\d{2})\b/i',
            // Best Before/Expiry prefix
            '/(?:Best Before|Expiry|Exp|Use By)[\s:]+(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Convert all matched dates to YYYY-MM-DD format
                if (count($matches) >= 3) {
                    if (strlen($matches[1]) == 4) { // If first number is year (YYYY-MM-DD)
                        return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
                    } else { // If it's DD-MM-YYYY
                        return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
                    }
                }
            }
        }

        return null;
    }

    $expiryDate = extractExpiryDate($data['text']);

    if ($expiryDate) {
        echo json_encode(['expiryDate' => $expiryDate]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No valid expiry date found in the text']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Processing error']);
}
?>
