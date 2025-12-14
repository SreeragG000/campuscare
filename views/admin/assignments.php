<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('admin');

if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!verify_csrf()) die('CSRF validation failed');
  $room_id = (int)($_POST['room_id']??0);
  $faculty_id = (int)($_POST['faculty_id']??0);
  if ($room_id && $faculty_id){
    $stmt = $conn->prepare("INSERT INTO room_assignments(room_id, faculty_id) VALUES (?,?)");
    $stmt->bind_param("ii",$room_id,$faculty_id);
    try{$stmt->execute(); set_flash('ok','Assigned');}catch(Exception $e){set_flash('err',$e->getMessage());}
  } else set_flash('err','Select both room and faculty');
}

$rooms = $conn->query("SELECT id,building,floor,room_no FROM rooms ORDER BY building,floor,room_no");
$faculty = $conn->query("SELECT id,name FROM users WHERE role='faculty' ORDER BY name");
$assign = $conn->query("SELECT ra.id, r.building,r.floor,r.room_no, u.name FROM room_assignments ra JOIN rooms r ON r.id=ra.room_id JOIN users u ON u.id=ra.faculty_id ORDER BY r.building, r.room_no");

include __DIR__.'/../partials/header.php';
?>

<div class="card">
  <h3 class="card-title">Room Assignments</h3>
  <?php if ($m = flash('ok')): ?><div class="alert success"><?= htmlspecialchars($m) ?></div><?php endif; ?>
  <?php if ($m = flash('err')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>
  <form method="post" class="grid cols-3">
    <?= get_csrf_input() ?>
    <div><label>Room</label>
      <select class="input" name="room_id" required>
        <option value="">Select</option>
        <?php while($r=$rooms->fetch_assoc()): ?>
          <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['building'].'/'.$r['floor'].'/'.$r['room_no']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div><label>Faculty</label>
      <select class="input" name="faculty_id" required>
        <option value="">Select</option>
        <?php while($f=$faculty->fetch_assoc()): ?>
          <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="actions" style="text-align: center;">
      <button class="btn">Assign</button>
  </div>
  </form>
</div><br>

<div class="table-card">
  <div class="table-scroll">
    <table class="table">
      <tr><th>Room</th><th>Faculty</th></tr>
      <?php while($a=$assign->fetch_assoc()): ?>
        <tr><td><?= htmlspecialchars($a['room_no']) ?></td><td><?= htmlspecialchars($a['name']) ?></td></tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>

<?php include __DIR__.'/../partials/footer.php'; ?>
