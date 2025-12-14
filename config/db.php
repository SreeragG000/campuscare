<?php
// config/db.php
// Update these credentials for your environment.
$DB_HOST = getenv('DB_HOST') ?: 'ftpupload.net';
$DB_USER = getenv('DB_USER') ?: 'if0_40680244';
$DB_PASS = getenv('DB_PASS') ?: 'PtdpsLL8fcf';
$DB_NAME = getenv('DB_NAME') ?: 'if0_40680244_yadu';

// Enable persistent connections
$host_prefix = 'p:';
if (strpos($DB_HOST, 'p:') === 0) {
    $host_prefix = '';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Global exception handler to prevent path disclosure
set_exception_handler(function($e) {
    error_log($e->getMessage()); // Log the actual error
    http_response_code(500);
    if (ini_get('display_errors')) {
        echo "Error: " . $e->getMessage();
    } else {
        echo "A database error occurred. Please try again later.";
    }
    exit;
});

try {
    $conn = new mysqli($host_prefix . $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Database connection error.';
    exit;
}
?>