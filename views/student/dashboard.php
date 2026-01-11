<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('student');

// ✅ OPTIMIZATION 1: Fix Session Locking for AJAX
if (isset($_POST['check_notifications']) && isset($_SESSION['user'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $user_id = (int)$_SESSION['user']['id'];
    
    // Unlock session immediately
    session_write_close();
    
    $res = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
    $count = $res->fetch_assoc()['count'];
    exit($count);
}

// ✅ OPTIMIZATION 2: Close session for main page load
// POST റിക്വസ്റ്റ് അല്ലെങ്കിൽ മാത്രം സെഷൻ ക്ലോസ് ചെയ്യുക (കാരണം POST ചിലപ്പോൾ ഫ്ലാഷ് മെസേജ് സെറ്റ് ചെയ്യാറുണ്ട്)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

// Fetch current Telegram ID
$user_id = (int)$_SESSION['user']['id'];
$telegram_chat_id = '';
$stmt = $conn->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $telegram_chat_id = $row['telegram_chat_id'];
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_telegram'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $new_id = trim($_POST['telegram_chat_id']);
    
    $stmt = $conn->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
    $stmt->bind_param("si", $new_id, $user_id);
    
    if ($stmt->execute()) {
        $telegram_chat_id = $new_id;
        echo "<script>alert('Telegram Chat ID updated successfully!'); window.location.href='" . BASE_URL . "views/student/dashboard.php';</script>";
    } else {
        echo "<script>alert('Error updating Chat ID.');</script>";
    }
}

include __DIR__.'/../partials/header.php';
?>

<div class="card">
    <h3 class="card-title">Student Dashboard</h3>
    <div class="btn-grid">
        <a class="btn" href="<?= BASE_URL ?>views/student/report_new.php">Report Damage</a>
        <a class="btn outline" href="<?= BASE_URL ?>views/student/history.php">View Report History</a>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Quick Links</h3>
    <p style="color: #ccc; margin-bottom: 15px;">Important resources and settings:</p>
    <div class="btn-grid">
        <a class="btn outline" href="<?= BASE_URL ?>views/telegram_setup.php">🔔 Connect Telegram</a>
    </div>
</div>

<?php include __DIR__.'/../partials/footer.php'; ?>
