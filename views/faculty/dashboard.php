<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('faculty');

// Check notifications
if (isset($_POST['check_notifications']) && isset($_SESSION['user'])) {
    $user_id = (int)$_SESSION['user']['id'];
    $res = $conn->query("SELECT COUNT(*) AS count FROM notifications WHERE user_id=$user_id AND is_read = 0");
    $count = $res->fetch_assoc()['count'];
    exit($count);
}

$uid = $_SESSION['user']['id'];
// Updated: Query now includes asset_names JOIN and gets urgency_priority from damage_reports table
$sql = "
    SELECT 
        dr.id, 
        dr.description, 
        dr.status, 
        dr.urgency_priority,
        dr.issue_type,
        dr.created_at, 
        dr.image_path,
        a.asset_code, 
        an.name AS asset_name,
        r.building, 
        r.floor, 
        r.room_no,
        u.name AS reporter
    FROM room_assignments ra
    JOIN rooms r ON ra.room_id = r.id
    JOIN assets a ON a.room_id = r.id
    JOIN damage_reports dr ON dr.asset_id = a.id
    JOIN asset_names an ON a.asset_name_id = an.id
    LEFT JOIN users u ON dr.reported_by = u.id
    WHERE ra.faculty_id = ? AND dr.status != 'resolved'
    ORDER BY 
        CASE dr.urgency_priority
            WHEN 'Critical' THEN 1
            WHEN 'High' THEN 2
            WHEN 'Medium' THEN 3
            WHEN 'Low' THEN 4
            ELSE 5
        END, 
        dr.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$reports = $stmt->get_result();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $id = (int)$_POST['id'];
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    
    if ($id > 0) {
        try {
            $conn->begin_transaction();
            
            // Update report status
            $stmt = $conn->prepare("UPDATE damage_reports SET status=?, assigned_to=? WHERE id=?");
            $stmt->bind_param("sii", $status, $uid, $id);
            $stmt->execute();
            
            // Get asset and room info
            $assetQuery = $conn->prepare("SELECT a.id AS asset_id, a.room_id, a.asset_code, r.room_type
                                        FROM assets a
                                        JOIN damage_reports dr ON dr.asset_id = a.id
                                        JOIN rooms r ON r.id = a.room_id
                                        WHERE dr.id=?");
            $assetQuery->bind_param("i", $id);
            $assetQuery->execute();
            $assetResult = $assetQuery->get_result();
            
            if ($assetResult && $assetData = $assetResult->fetch_assoc()) {
                $asset_id = $assetData['asset_id'];
                $room_id = $assetData['room_id'];
                $asset_code = $assetData['asset_code']; // NEW
                $room_type = $assetData['room_type'];
                
                // Update asset status
                $updateAssetStmt = $conn->prepare("UPDATE assets a SET
                    a.status = CASE
                        WHEN EXISTS (SELECT 1 FROM damage_reports dr
                                   WHERE dr.asset_id = a.id
                                   AND dr.status IN ('pending','in_progress'))
                        THEN 'Needs Repair' ELSE 'Good' END
                    WHERE a.id=?");
                $updateAssetStmt->bind_param("i", $asset_id);
                $updateAssetStmt->execute();
                
                // Updated: Update exam room status using the new normalized structure
                if (in_array(strtolower($room_type), ['classroom', 'lab', 'laboratory'])) {
                    // Calculate the new exam ready status
                    $newExamStatus = isRoomExamReady($conn, $room_id, $room_type);
                    // Update the exam_rooms table
                    $updateExamStmt = $conn->prepare("
                        UPDATE exam_rooms
                        SET status_exam_ready = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE room_id = ?
                    ");
                    $updateExamStmt->bind_param("si", $newExamStatus, $room_id);
                    $updateExamStmt->execute();
                }

                // Notify admins
                $admin_query = $conn->query("SELECT id FROM users WHERE role='admin'");
                while ($admin = $admin_query->fetch_assoc()) {
                    $msg = "✅ Status Update: Maintenance for $asset_code marked as '$status'.";
                    notify_user($conn, (int)$admin['id'], $msg);
                }
            }
            
            $conn->commit();
            
            // Notify reporter
            $reporterQuery = $conn->prepare("SELECT reported_by FROM damage_reports WHERE id=?");
            $reporterQuery->bind_param("i", $id);
            $reporterQuery->execute();
            $reporterResult = $reporterQuery->get_result();
            if ($reporterResult && $reporterData = $reporterResult->fetch_assoc()) {
                if (!empty($reporterData['reported_by']) && isset($asset_code)) {
                    $msg = "🔔 Update: Your report for $asset_code is now '$status'.";
                    notify_user($conn, (int)$reporterData['reported_by'], $msg);
                }
            }
            
            set_flash('ok', 'Status updated');
            
        } catch (Exception $e) {
            $conn->rollback();
            set_flash('err', 'Error updating status: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'views/faculty/dashboard.php');
    exit;
}

include __DIR__ . '/../partials/header.php';
?>

<div class="btn-grid">
    <a class="btn" href="<?= BASE_URL ?>views/admin/assets.php">Assets</a>
    <a class="btn" href="<?= BASE_URL ?>views/faculty/workers.php">Workers</a>
    <a class="btn" href="<?= BASE_URL ?>views/telegram_setup.php">🔔 Telegram Alerts</a>
</div>

<div class="table-card">
    <h3 class="card-title">Your Assigned Rooms' Reports (Sorted by Urgency)</h3>
    
    <?php if ($m = flash('ok')): ?>
        <div class="alert success"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>
    <?php if ($m = flash('err')): ?>
        <div class="alert error"><?= htmlspecialchars($m) ?></div>
    <?php endif; ?>
    
    <div class="table-scroll">
        <table class="table">
            <tr>
                <th>Image</th><th>Issue</th><th>Asset</th><th>Description</th>
                <th>Urgency</th><th>Status</th><th>Action</th><th>Room</th>
            </tr>
            <?php
            if ($reports && $reports->num_rows > 0) {
                while ($dr = $reports->fetch_assoc()):
                    $dr['id'] = $dr['id'] ?? 0;
                    $dr['image_path'] = $dr['image_path'] ?? '';
                    $dr['asset_code'] = $dr['asset_code'] ?? 'N/A';
                    $dr['asset_name'] = $dr['asset_name'] ?? 'N/A';
                    $dr['building'] = $dr['building'] ?? 'N/A';
                    $dr['floor'] = $dr['floor'] ?? 'N/A';
                    $dr['room_no'] = $dr['room_no'] ?? 'N/A';
                    $dr['description'] = $dr['description'] ?? 'N/A';
                    $dr['urgency_priority'] = $dr['urgency_priority'] ?? 'Medium';
                    $dr['status'] = $dr['status'] ?? 'pending';
                    
                    // Set urgency badge class
                    $urgencyClass = match($dr['urgency_priority']) {
                        'Critical' => 'bad',
                        'Low' => 'good',
                        default => 'na'
                    };
                    $issueClass = ($dr['issue_type'] === 'Missing Sticker') ? 'info' : 'na';
            ?>
                    <tr>
                        <form method="post">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int)$dr['id'] ?>">
                            <td>
                                <?php if (!empty($dr['image_path'])): ?>
                                    <img class="img-thumb"
                                         src="<?= BASE_URL . htmlspecialchars(ltrim($dr['image_path'], '/')) ?>"
                                         onclick="showImage('<?= BASE_URL . htmlspecialchars(ltrim($dr['image_path'], '/')) ?>')"
                                         alt="Damage report image">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $issueClass ?>"><?= htmlspecialchars($dr['issue_type'] ?? 'Damage') ?></span></td>
                            <td><?= htmlspecialchars($dr['asset_code']) ?></td>
                            <td><?= htmlspecialchars($dr['description']) ?></td>
                            <td><span class="badge <?= $urgencyClass ?>"><?= htmlspecialchars($dr['urgency_priority']) ?></span></td>
                            <td>
                                <select class="input" name="status">
                                    <?php foreach (['pending','in_progress','resolved'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $dr['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button class="btn small">Save</button></td>
                            <td><?= htmlspecialchars($dr['building'] . '/' . $dr['floor'] . '/' . $dr['room_no']) ?></td>
                        </form>
                    </tr>
            <?php 
                endwhile;
            } else {
                echo '<tr><td colspan="7" style="text-align:center;">No reports found</td></tr>';
            }
            ?>
        </table>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="this.style.display='none'">
    <img id="modalImage" alt="Full size damage report image">
</div>

<script>
function showImage(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>