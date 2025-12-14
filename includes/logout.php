 <?php
require_once __DIR__ . '/../config/init.php';
session_destroy();
header('Location: ' . BASE_URL . 'views/login.php');
exit;
?>
