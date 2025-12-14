<?php
require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../config/asset_helper.php';
ensure_role('admin');

// Run Notification Cleanup
purgeOldNotifications($conn);

// Run Warranty Checks
checkWarrantyExpirations($conn);

if (isset($_POST['check_notifications']) && isset($_SESSION['user'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $user_id = (int) $_SESSION['user']['id'];
    $res = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
    exit($res->fetch_assoc()['count']);
}

// NEW: Handle urgency_priority update by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_urgency'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $report_id = (int)$_POST['report_id'];
    $new_urgency = $_POST['new_urgency_priority'];
    if ($report_id > 0 && in_array($new_urgency, ['Critical', 'High', 'Medium', 'Low'])) {
        $updateUrgencyStmt = $conn->prepare("UPDATE damage_reports SET urgency_priority = ? WHERE id = ?");
        $updateUrgencyStmt->bind_param("si", $new_urgency, $report_id);
        $updateUrgencyStmt->execute();
        
        // Notify faculty and reporter of urgency change
        $facultyQuery = $conn->prepare("SELECT assigned_to FROM damage_reports WHERE id = ?");
        $facultyQuery->bind_param("i", $report_id);
        $facultyQuery->execute();
        $facultyResult = $facultyQuery->get_result();
        if ($facultyResult && $facultyData = $facultyResult->fetch_assoc()) {
            if (!empty($facultyData['assigned_to'])) {
                $msg = "❗ Priority Escalation: Urgency for Report #$report_id has been upgraded to '$new_urgency'. Please attend immediately.";
                notify_user($conn, (int)$facultyData['assigned_to'], $msg);
            }
        }
        
        $reporterQuery = $conn->prepare("SELECT reported_by FROM damage_reports WHERE id=?");
        $reporterQuery->bind_param("i", $report_id);
        $reporterQuery->execute();
        $reporterResult = $reporterQuery->get_result();
        if ($reporterResult && $reporterData = $reporterResult->fetch_assoc()) {
            if (!empty($reporterData['reported_by'])) {
                $msg = "🔔 Update: Urgency for your report #$report_id has been changed to '$new_urgency'.";
                notify_user($conn, (int)$reporterData['reported_by'], $msg);
            }
        }
        
        set_flash('ok', 'Urgency priority updated to ' . $new_urgency);
    }
    
    $redirect_url = BASE_URL . 'views/admin/dashboard.php';
    if (!headers_sent()) {
        header('Location: ' . $redirect_url);
    } else {
        echo '<script>window.location.href="' . $redirect_url . '";</script>';
    }
    exit;
}

$rooms = $conn->query("
    SELECT 
        r.id, r.building, r.floor, r.room_no, r.room_type,
        COUNT(DISTINCT CASE WHEN dr.status IN ('pending', 'assigned', 'in_progress') THEN dr.id END) AS open_reports,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN a.status IN ('damaged', 'broken', 'Needs Repair') THEN a.id END) > 0 THEN 'Issues'
            WHEN COUNT(DISTINCT CASE WHEN dr.status IN ('pending', 'assigned', 'in_progress') THEN dr.id END) > 0 THEN 'Issues'
            ELSE 'Good'
        END AS room_status
    FROM rooms r
    LEFT JOIN assets a ON a.room_id = r.id
    LEFT JOIN damage_reports dr ON dr.asset_id = a.id 
    GROUP BY r.id, r.building, r.floor, r.room_no, r.room_type
    ORDER BY r.building, r.floor, r.room_no
");

// 2. Optimized Reports Query: Selects specific columns only
$reports = $conn->query("
    SELECT 
        dr.id, 
        dr.description, 
        dr.status, 
        dr.created_at, 
        dr.image_path, 
        dr.image_path, 
        dr.urgency_priority,
        dr.issue_type,
        a.asset_code, 
        an.name AS asset_name, 
        u.name AS reporter
    FROM damage_reports dr
    JOIN assets a ON a.id = dr.asset_id
    JOIN asset_names an ON an.id = a.asset_name_id
    LEFT JOIN users u ON u.id = dr.reported_by
    WHERE dr.status IN ('pending', 'in_progress')
    ORDER BY CASE dr.urgency_priority
        WHEN 'Critical' THEN 1
        WHEN 'High' THEN 2
        WHEN 'Medium' THEN 3
        WHEN 'Low' THEN 4
        ELSE 5
    END, dr.created_at DESC
    LIMIT 10
");

// 3. Optimized KPI Query: Combines 4 queries into 1
$kpiQuery = "
    SELECT 
        (SELECT COUNT(*) FROM assets) AS total_assets,
        (SELECT COUNT(*) FROM damage_reports WHERE status IN ('pending', 'assigned', 'in_progress')) AS open_reports,
        (SELECT COUNT(*) FROM rooms) AS total_rooms,
        (SELECT COUNT(*) 
         FROM rooms r 
         WHERE r.room_type IN ('classroom', 'lab', 'laboratory')
         AND NOT EXISTS (
            SELECT 1 FROM assets a 
            LEFT JOIN damage_reports dr ON dr.asset_id = a.id AND dr.status IN ('pending', 'assigned', 'in_progress')
            WHERE a.room_id = r.id 
            AND (a.status IN ('damaged', 'broken', 'Needs Repair') OR dr.id IS NOT NULL)
         )
        ) AS rooms_ok
";
$kpiResult = $conn->query($kpiQuery)->fetch_assoc();

// Assign variables for the view
$kpi_total_assets = $kpiResult['total_assets'];
$kpi_open_reports = $kpiResult['open_reports'];
$kpi_rooms_ok     = $kpiResult['rooms_ok'];
$total_rooms      = $kpiResult['total_rooms'];

include __DIR__ . '/../partials/header.php';
?>
<div class="grid-2x2">
    <div class="kpi-card">
        <div class="kpi"><?= (int) $kpi_total_assets ?></div>
        <div class="kpi-label">Total Assets</div>
    </div>
    <div class="kpi-card">
        <div class="kpi"><?= (int) $kpi_open_reports ?></div>
        <div class="kpi-label">Open Reports</div>
    </div>
    <div class="kpi-card">
        <div class="kpi"><?= (int) $kpi_rooms_ok ?></div>
        <div class="kpi-label">Exam Ready</div>
    </div>
    <div class="kpi-card">
        <div class="kpi"><?= (int) $total_rooms ?></div>
        <div class="kpi-label">Total Rooms</div>
    </div>
</div>

<?php if ($m = flash('ok')): ?>
    <div class="alert success"><?= htmlspecialchars($m) ?></div>
<?php endif; ?>

<div class="btn-grid">
    <a class="btn" href="<?= BASE_URL ?>views/admin/users.php">Users</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/rooms.php">Rooms</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/assets.php">Assets</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/categories.php">Categories</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/asset_names.php">Asset Names</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/reports.php">Reports</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/dealers.php">Dealers</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/workers.php">Workers</a>
    <a class="btn" href="<?= BASE_URL ?>views/admin/assignments.php">Assign Faculty</a>
    <a class="btn" href="<?= BASE_URL ?>views/telegram_setup.php">Telegram Alerts</a>
</div>

<div class="table-card">
    <h3 class="card-title">Recent Damage Reports (Sorted by Urgency)</h3>
    <div class="table-scroll">
        <table class="table">
            <tr><th>Image</th><th>Asset</th><th>Type</th><th>Issue</th><th>Status</th><th>Reporter</th><th>Date</th><th>Priority</th></tr>
            <?php while ($r = $reports->fetch_assoc()): 
                $issueType = $r['issue_type'] ?? 'Damage';
                $issueClass = match($issueType) {
                    'Missing Sticker' => 'warn',
                    'Damage' => 'bad',
                    default => 'neutral'
                };
            ?>
                <tr>
                    <td>
                        <?php if (!empty($r['image_path'])): ?>
                            <img class="img-thumb"
                                 src="<?= BASE_URL . htmlspecialchars(ltrim($r['image_path'], '/')) ?>"
                                 onclick="showImage('<?= BASE_URL . htmlspecialchars(ltrim($r['image_path'], '/')) ?>')">
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['asset_code']) ?></td>
                    <td><span class="badge <?= $issueClass ?>"><?= htmlspecialchars($issueType) ?></span></td>
                    <td><?= htmlspecialchars(substr($r['description'] ?? '-', 0, 30)) ?><?= strlen($r['description'] ?? '') > 30 ? '...' : '' ?></td>
                    <td><span class="badge <?= strtolower($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td><?= htmlspecialchars($r['reporter'] ?? '-') ?></td>
                    <td><?= date('M j', strtotime($r['created_at'])) ?></td>
                    <td>
                        <!-- Admin can change urgency priority -->
                        <form method="post" style="display: inline;">
                            <?= get_csrf_input() ?>
                            <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                            <select class="input" name="new_urgency_priority" onchange="this.form.submit()">
                                <?php foreach (['Critical', 'High', 'Medium', 'Low'] as $urgency): ?>
                                    <option value="<?= $urgency ?>" <?= $r['urgency_priority'] === $urgency ? 'selected' : '' ?>>
                                        <?= $urgency ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_urgency" value="1">
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<div class="table-card">
    <h3 class="card-title">Rooms & Health Status</h3>
    <div class="table-scroll">
        <table class="table">
            <tr><th>Room</th><th>Type</th><th>Status</th><th>Issues</th></tr>
            <?php while ($r = $rooms->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['building'] . '/' . $r['floor'] . '/' . $r['room_no']) ?></td>
                    <td><?= htmlspecialchars($r['room_type']) ?></td>
                    <td><span class="badge <?= $r['room_status'] === 'Good' ? 'good' : 'bad' ?>"><?= $r['room_status'] ?></span></td>
                    <td><?= (int) $r['open_reports'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<div id="imageModal" class="modal" onclick="this.style.display='none'">
    <img id="modalImage">
</div>

<script>
function showImage(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>