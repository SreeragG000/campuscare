<?php
// config/db.php

// TiDB Cloud Credentials (നിൻ്റെ വിവരങ്ങൾ കൃത്യമാണെന്ന് ഉറപ്പുവരുത്തുക)
$DB_HOST = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$DB_USER = '4HemRxYCqKos4PM.root';
$DB_PASS = 'NlOYRtCpfIWeE2Nk';
$DB_NAME = 'test';
$DB_PORT = 4000;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. കണക്ഷൻ ഒബ്ജക്റ്റ് ഉണ്ടാക്കുന്നു (പക്ഷേ കണക്റ്റ് ചെയ്യുന്നില്ല)
    $conn = mysqli_init();

    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }

    // 2. SSL നിർബന്ധമാണെന്ന് പറയുന്നു (ഏറ്റവും പ്രധാനം!)
    // NULL കൊടുത്താൽ സിസ്റ്റത്തിലെ ഡിഫോൾട്ട് സർട്ടിഫിക്കറ്റ് ഉപയോഗിക്കും
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

    // 3. റിയൽ കണക്ഷൻ ഉണ്ടാക്കുന്നു (MYSQLI_CLIENT_SSL ഫ്ലാഗ് ഉപയോഗിച്ച്)
    // ഇവിടെയാണ് കണക്ഷൻ നടക്കുന്നത്
    if (!$conn->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
        throw new Exception("Connect Error: " . mysqli_connect_error());
    }

    // ക്യാരക്ടർ സെറ്റ് സെറ്റ് ചെയ്യുന്നു
    $conn->set_charset('utf8mb4');

} catch (Exception $e) {
    // എറർ ലോഗ് ചെയ്യുന്നു
    error_log($e->getMessage());
    http_response_code(500);
    
    // ഡീബഗ്ഗിംഗിനായി എറർ സ്ക്രീനിൽ കാണിക്കുന്നു (പിന്നീട് ഇത് മാറ്റണം)
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
