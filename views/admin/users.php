<?php
require_once __DIR__ . '/../../config/init.php';
ensure_role('admin');

// Create user
if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!verify_csrf()) die('CSRF validation failed');

  if (isset($_POST['approve_user_id'])) {
      $uid = (int)$_POST['approve_user_id'];
      $conn->query("UPDATE users SET is_verified=1 WHERE id=$uid");
      
      // Notify the user
      $msg = "✅ Account Verified: Your account has been approved by the administrator. You can now access the dashboard.";
      notify_user($conn, $uid, $msg);
      
      set_flash('ok', 'User approved successfully');
  } else {
      $name = trim($_POST['name']??'');
      $email = trim($_POST['email']??'');
      $role = $_POST['role']??'student';
      $password = $_POST['password']??'';
      
      if ($name && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Admin created users are auto-verified (is_verified=1)
        $stmt = $conn->prepare("INSERT INTO users(name,email,password,role,is_verified) VALUES (?,?,?,?,1)");
        $stmt->bind_param("ssss", $name,$email,$hash,$role);
        try{
          $stmt->execute();
          set_flash('ok','User created');
        } catch(Exception $e){
          set_flash('err','Error: '.$e->getMessage());
        }
      } else set_flash('err','All fields required');
  }
}

// list users
$users = $conn->query("SELECT id,name,email,role,created_at,register_number,is_verified FROM users ORDER BY created_at DESC");

include __DIR__.'/../partials/header.php';
?>

<div class="card">
    <h3 class="card-title">Add User</h3>
    <?php if ($m = flash('ok')): ?><div class="alert success"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    <?php if ($m = flash('err')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    
    <form method="post" class="grid cols-3">
        <?= get_csrf_input() ?>
        <div><label>Name</label><input class="input" name="name" required></div>
        <div><label>Email</label><input class="input" type="email" name="email" required></div>
        <div><label>Password</label><input class="input" type="text" name="password" required></div>
        <div><label>Role</label>
            <select class="input" name="role">
                <option value="student">student</option>
                <option value="faculty">faculty</option>
                <option value="admin">admin</option>
            </select>
        </div>
        <div class="actions" style="text-align: center;"><button class="btn">Add User</button></div>
    </form>
</div>

<div class="table-card">
    <h3 class="card-title">All Users</h3>
    <div class="table-scroll">
        <table class="table">
            <tr><th>ID</th><th>Register No</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr>
            <?php while($u=$users->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['register_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge <?= strtolower($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_verified']): ?>
                            <span class="badge success">Verified</span>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <?= get_csrf_input() ?>
                                <input type="hidden" name="approve_user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm">Approve</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<?php include __DIR__.'/../partials/footer.php'; ?>
