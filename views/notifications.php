<?php
require_once __DIR__ . '/../config/init.php';
ensure_logged_in();

$user = $_SESSION['user'] ?? null;
$user_id = $_SESSION['user']['id'];
$res = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 50");

// Mark unread notifications as read when viewing the page
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");

include __DIR__ . '/partials/header.php';
?>

<div class="table-card">
    <h3 class="card-title">Your Notifications</h3>
    <div class="table-scroll">
        <table class="table">
            <tr><th>Message</th><th>When</th></tr>
            <?php while ($n = $res->fetch_assoc()): ?>
                <tr class="<?= $n['is_read'] ? '' : 'highlight' ?>">
                    <td><?= htmlspecialchars($n['message']) ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>