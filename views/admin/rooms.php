<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('admin');

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $room_id = (int)$_GET['delete'];
    
    try {
        $conn->begin_transaction();
        
        // Delete from exam_rooms first (if exists) due to foreign key constraint
        $conn->query("DELETE FROM exam_rooms WHERE room_id = $room_id");
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            set_flash('ok', 'Room deleted successfully');
        } else {
            $conn->rollback();
            set_flash('err', 'Room not found');
        }
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('err', 'Error deleting room: ' . $e->getMessage());
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
    if (!verify_csrf()) die('CSRF validation failed');
    $building = trim($_POST['building']??'');
    $floor = trim($_POST['floor']??'');
    $room_no = trim($_POST['room_no']??'');
    $room_type = trim($_POST['room_type']??'classroom');
    $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
    $notes = trim($_POST['notes']??''); // Remove default '-' value
    
    if ($building && $room_no && $room_type){
        try {
            $conn->begin_transaction();
            
            // Insert into rooms table
            $stmt = $conn->prepare("INSERT INTO rooms(building,floor,room_no,room_type,capacity,notes) VALUES (?,?,?,?,?,?)");
            // Fixed: notes should be string (s), not integer (i)
            $stmt->bind_param("ssssis",$building,$floor,$room_no,$room_type,$capacity,$notes);
            $stmt->execute();
            
            $room_id = $conn->insert_id;
            
            // If it's a classroom or lab, add to exam_rooms table
            if (in_array(strtolower($room_type), ['classroom', 'lab', 'laboratory'])) {
                addExamRoom($conn, $room_id, 'Yes');
            }
            
            $conn->commit();
            set_flash('ok','Room added successfully');
            
        } catch(Exception $e) {
            $conn->rollback();
            set_flash('err',$e->getMessage());
        }
    } else {
        set_flash('err','Building, Room No, and Room Type required');
    }
}

// Updated query to include exam room status with proper LEFT JOIN
$rooms = $conn->query("
    SELECT r.*, er.status_exam_ready, er.updated_at as status_updated_at
    FROM rooms r
    LEFT JOIN exam_rooms er ON er.room_id = r.id
    ORDER BY r.building, r.floor, r.room_no
");

include __DIR__.'/../partials/header.php';
?>

<div class="card">
    <h3 class="card-title">Add Room</h3>
    <?php if ($m = flash('ok')): ?><div class="alert success"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    <?php if ($m = flash('err')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    
    <form method="post" class="grid cols-2">
        <?= get_csrf_input() ?>
        <div><label>Building</label><input class="input" name="building" required></div>
        <div><label>Floor</label><input class="input" name="floor"></div>
        <div><label>Room No</label><input class="input" name="room_no" required></div>
        <div><label>Room Type</label>
            <select class="input" name="room_type" required>
                <option value="">Select Type</option>
                <option value="classroom">Classroom</option>
                <option value="lab">Laboratory</option>
                <option value="library">Library</option>
                <option value="toilet">Toilet</option>
                <option value="office">Office</option>
            </select>
        </div>
        <div><label>Capacity</label><input class="input" name="capacity" type="number" min="1"></div>
        <div><label>Notes</label><input class="input" name="notes" type="text" placeholder="Equipment, special features, etc."></div>
        <div class="actions col-span-2" style="text-align: center;"><button class="btn">Add Room</button></div>
    </form>
</div>

<div class="table-card">
    <h3 class="card-title">All Rooms</h3>
    <div class="table-scroll">
        <table class="table">
            <tr>
                <th>ID</th>
                <th>Building</th>
                <th>Floor</th>
                <th>Room</th>
                <th>Type</th>
                <th>Capacity</th>
                <th>Notes</th>
                <th>Exam Ready</th>
                <th>Actions</th>
            </tr>
            <?php while($r=$rooms->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['building']) ?></td>
                <td><?= htmlspecialchars($r['floor']) ?></td>
                <td><?= htmlspecialchars($r['room_no']) ?></td>
                <td><?= htmlspecialchars($r['room_type']) ?></td>
                <td><?= $r['capacity'] ? (int)$r['capacity'] : '-' ?></td>
                <td><?= htmlspecialchars($r['notes'] ?: '-') ?></td>
                <td>
                    <?php 
                    // Display exam ready status only for exam rooms
                    if ($r['status_exam_ready'] !== null) {
                        $calculated_status = isRoomExamReady($conn, $r['id'], $r['room_type']);
                        $badge_class = $calculated_status === 'Yes' ? 'yes' : 'no';
                        echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($calculated_status) . '</span>';
                        
                        // Show if stored status differs from calculated
                        if ($r['status_exam_ready'] !== $calculated_status) {
                            echo '<br><small class="text-muted">Stored: ' . htmlspecialchars($r['status_exam_ready']) . '</small>';
                        }
                    } else {
                        echo '<span class="badge na">N/A</span>';
                    }
                    ?>
                </td>
                <td>
                    <a href="?delete=<?= (int)$r['id'] ?>"
                       class="btn btn-small btn-danger"
                       onclick="return confirm('Are you sure you want to delete this room? This will also remove it from exam_rooms if applicable.')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<!-- Add sync button for maintenance -->
<div class="card">
    <h3 class="card-title">Maintenance</h3>
    <p>Use this to synchronize exam room statuses with actual asset conditions:</p>
    <form method="post">
        <?= get_csrf_input() ?>
        <button type="submit" name="sync_exam_statuses" class="btn outline">Sync All Exam Room Statuses</button>
    </form>
    
    <?php if (isset($_POST['sync_exam_statuses'])): ?>
        <?php 
        $updated_count = syncAllExamReadyStatuses($conn);
        ?>
        <div class="alert success">Synchronized <?= $updated_count ?> exam room statuses.</div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../partials/footer.php'; ?>