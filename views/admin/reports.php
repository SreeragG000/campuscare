<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('admin');

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
        r.building, 
        r.floor, 
        r.room_no,
        u.name AS staff_name, 
        reporter.name AS reporter_name
    FROM damage_reports dr
    JOIN assets a ON a.id = dr.asset_id
    JOIN asset_names an ON an.id = a.asset_name_id
    JOIN rooms r ON r.id = a.room_id
    LEFT JOIN users u ON u.id = dr.assigned_to
    LEFT JOIN users reporter ON reporter.id = dr.reported_by
    ORDER BY CASE dr.urgency_priority
        WHEN 'Critical' THEN 1
        WHEN 'High' THEN 2
        WHEN 'Medium' THEN 3
        WHEN 'Low' THEN 4
        ELSE 5
    END, dr.created_at DESC
");

include __DIR__.'/../partials/header.php';
?>

<div class="table-card">
    <h3 class="card-title">All Reports (Sorted by Urgency Priority)</h3>
    <div class="table-scroll">
        <table class="table">
            <tr>
                <th>Image</th>
                <th>Issue</th>
                <th>Asset</th>
                <th>Room</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Staff</th>
                <th>Reporter</th>
                <th>Created</th>
            </tr>
            <?php while($dr = $reports->fetch_assoc()):
                // Set urgency badge class
                $urgencyClass = match($dr['urgency_priority']) {
                    'Critical' => 'bad',
                    'High' => 'warn',
                    'Medium' => 'na',
                    'Low' => 'good',
                    default => 'na'
                };
                // Issue type badge style
                $issueClass = ($dr['issue_type'] === 'Missing Sticker') ? 'info' : 'na';
            ?>
                <tr>
                    <td>
                        <?php if(!empty($dr['image_path'])): ?>
                            <img class="img-thumb"
                                 src="<?= BASE_URL . htmlspecialchars(ltrim($dr['image_path'], '/')) ?>"
                                 onclick="showImage('<?= BASE_URL . htmlspecialchars(ltrim($dr['image_path'], '/')) ?>')">
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $issueClass ?>"><?= htmlspecialchars($dr['issue_type'] ?? 'Damage') ?></span></td>
                    <td><?= htmlspecialchars($dr['asset_code'].' - '.$dr['asset_name']) ?></td>
                    <td><?= htmlspecialchars($dr['building'].'/'.$dr['floor'].'/'.$dr['room_no']) ?></td>
                    <td><span class="badge <?= $urgencyClass ?>"><?= htmlspecialchars($dr['urgency_priority']) ?></span></td>
                    <td><span class="badge <?= strtolower($dr['status']) ?>"><?= htmlspecialchars($dr['status']) ?></span></td>
                    <td><?= $dr['staff_name'] ? htmlspecialchars($dr['staff_name']) : '-' ?></td>
                    <td><?= $dr['reporter_name'] ? htmlspecialchars($dr['reporter_name']) : '-' ?></td>
                    <td><?= date('M j, Y', strtotime($dr['created_at'])) ?></td>
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

<?php include __DIR__.'/../../common/footer.php'; ?>