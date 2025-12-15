<?php
require_once __DIR__ . '/../includes/auth_logic.php';

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    redirect_by_role();
}

$error = handle_register($conn);

include __DIR__ . '/partials/header.php';
?>

<div class="card">
    <h3 class="card-title">Register</h3>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form method="post" class="grid">
        <?= get_csrf_input() ?>
        <div>
            <label>Full Name</label>
            <input class="input" type="text" name="name" placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
        <div>
            <label>Register Number</label>
            <input class="input" type="text" name="register_number" placeholder="Enter ID/Register No" value="<?= htmlspecialchars($_POST['register_number'] ?? '') ?>" required>
        </div>
        <div>
            <label>Email</label>
            <input class="input" type="email" name="email" placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div>
            <label>Role</label>
            <select class="input" name="role" required>
                <option value="" disabled selected>Select Role</option>
                <option value="student" <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : '' ?>>Student</option>
                <option value="faculty" <?= (isset($_POST['role']) && $_POST['role'] === 'faculty') ? 'selected' : '' ?>>Faculty</option>
            </select>
        </div>
        <div>
            <label>Password</label>
            <input class="input" type="password" name="password" required>
        </div>
        <div>
            <label>Confirm Password</label>
            <input class="input" type="password" name="confirm_password" required>
        </div>
        <div class="actions" style="text-align: center;">
            <button class="btn">Register</button>
        </div>
        <div style="text-align: center; margin-top: 1rem;">
            <a href="<?= BASE_URL ?>views/login.php">Already have an account? Login</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

