<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('student');
if (isset($_POST['check_notifications']) && isset($_SESSION['user'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $user_id = (int)$_SESSION['user']['id'];
    $res = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
    $count = $res->fetch_assoc()['count'];
    exit($count);
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
        $telegram_chat_id = $new_id; // Update local var to show new value
        echo "<script>alert('Telegram Chat ID updated successfully!');</script>";
        // Ideally use flash messages but simple alert works for this request context
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

  <p>Links to important resources:</p>
  <div class="btn-grid">
      <a class="btn outline" href="<?= BASE_URL ?>views/telegram_setup.php">🔔 Connect Telegram</a>
  </div>
</div>
<?php include __DIR__.'/../partials/footer.php'; ?>
