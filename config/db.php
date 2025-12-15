<?php
$DB_HOST = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$DB_USER = '4HemRxYCqKos4PM.root';
$DB_PASS = 'NlOYRtCpfIWeE2Nk';
$DB_NAME = 'college_assets';
$DB_PORT = 4000;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    
    $conn = mysqli_init();

    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }

    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

   if (!$conn->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
        throw new Exception("Connect Error: " . mysqli_connect_error());
    }

    $conn->set_charset('utf8mb4');

} catch (Exception $e) {
       error_log($e->getMessage());
    http_response_code(500);
    
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}






