<?php
// config/db.php

// TiDB Cloud Credentials
$DB_HOST = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$DB_USER = '4HemRxYCqKos4PM.root';
$DB_PASS = 'NlOYRtCpfIWeE2Nk';
$DB_NAME = 'test';
$DB_PORT = 4000; // TiDB requires Port 4000

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Port 4000 is added here
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
    
    // SSL Connection settings (TiDB needs this for security)
    if (defined('MYSQLI_CLIENT_SSL')) {
       $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Database connection error: ' . $e->getMessage(); // For debugging only
    exit;
}
?>
