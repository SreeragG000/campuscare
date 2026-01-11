<?php
require_once __DIR__ . '/../../config/init.php';
// ✅ Missing file included
require_once __DIR__ . '/../../config/room_utils.php'; 

ensure_role('admin');

// ✅ OPTIMIZATION 1: Handle Sync BEFORE fetching data
// ടേബിൾ ലോഡ് ചെയ്യുന്നതിന് മുമ്പ് സിങ്ക് നടന്നാൽ, പുതിയ ഡാറ്റ താഴെ കാണാം.
$sync_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_exam_statuses'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $updated_count = syncAllExamReadyStatuses($conn);
    $sync_msg = "Synchronized $updated_count exam room statuses.";
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['sync_exam_statuses'])) {
    if (!verify_csrf()) die('CSRF validation failed');
    $building = trim($_POST['building']??'');
    $floor = trim($_POST['floor']??'');
    $room_no = trim($_POST['room_no']??'');
    $room_type = trim($_POST['room_type']??'classroom');
    $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
    $notes = trim($_POST['notes']??''); 
    
    if ($building && $room_no && $room_type){
        try {
            $conn->begin_transaction();
            
            // Insert into rooms table
            $stmt = $conn->prepare("INSERT INTO rooms(building,floor,room_no,room_type,capacity,notes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssis",$building,$floor,$room_no,$room_type,$capacity,$notes);
            $stmt->execute();
            
            $room_id = $conn->insert_id;
            
            // If it's a classroom or lab, add to exam_rooms table
            if (in_array(strtolower($room_type), ['classroom', 'lab', 'laboratory'])) {
                addExamRoom($conn, $room_id, 'Yes');
            }
            
            $conn->commit();
            set_flash('ok','Room added successfully');
            
            // Redirect to prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
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

<div class="card" style="margin-top: 20px;">
    <h3 class="card-title">Maintenance</h3>
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <p style="margin: 0; color: #8899ac;">Synchronize statuses if you suspect data mismatch:</p>
        <form method="post" style="margin: 0;">
            <?= get_csrf_input() ?>
            <button type="submit" name="sync_exam_statuses" class="btn outline small">Sync All Statuses</button>
        </form>
    </div>
    <?php if ($sync_msg): ?>
        <div class="alert success" style="margin-top: 10px;"><?= htmlspecialchars($sync_msg) ?></div>
    <?php endif; ?>
</div>

<div class="table-card">
    <h3 class="card-title">All Rooms</h3>
    <div class="table-scroll">
        <table class="table">
            <tr>
                <th>Building</th>
                <th>Room</th>
                <th>Type</th>
                <th>Capacity</th>
                <th>Notes</th>
                <th>Exam Ready</th>
                <th>Actions</th>
            </tr>
            <?php while($r=$rooms->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($r['building']) ?></td>
                <td>
                    <span style="font-weight: bold; color: #fff;"><?= htmlspecialchars($r['room_no']) ?></span>
                    <div style="font-size: 0.8em; color: #8899ac;">Floor: <?= htmlspecialchars($r['floor']) ?></div>
                </td>
                <td><?= htmlspecialchars($r['room_type']) ?></td>
                <td><?= $r['capacity'] ? (int)$r['capacity'] : '-' ?></td>
                <td><?= htmlspecialchars($r['notes'] ?: '-') ?></td>
                <td>
                    <?php 
                    // ✅ OPTIMIZATION 2: No more heavy calculation inside loop!
                    // Just use the value from DB (er.status_exam_ready)
                    if ($r['status_exam_ready'] !== null) {
                        $badge_class = $r['status_exam_ready'] === 'Yes' ? 'yes' : 'no';
                        echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($r['status_exam_ready']) . '</span>';
                    } else {
                        // For non-exam rooms (toilets, offices etc)
                        echo '<span class="badge na" style="opacity:0.5">N/A</span>';
                    }
                    ?>
                </td>
                <td>
                    <a href="?delete=<?= (int)$r['id'] ?>"
                       class="btn btn-small btn-danger"
                       style="padding: 4px 8px; font-size: 0.8rem;"
                       onclick="return confirm('Delete this room?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<?php include __DIR__.'/../partials/footer.php'; ?>
