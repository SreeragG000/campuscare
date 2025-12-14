<?php
// views/login.php
require_once __DIR__ . '/../includes/auth_logic.php';

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    redirect_by_role();
}

$error = handle_login($conn);

include __DIR__ . '/partials/header.php';
?>
<div class="card">
    <h3 class="card-title">Login</h3>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?><div class="alert"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
    
    <form method="post" class="grid">
        <?= get_csrf_input() ?>
        <div>
            <label>Email or Username</label>
            <input class="input" type="text" name="login" placeholder="admin@example.com" required>
        </div>
        <div>
            <label>Password</label>
            <input class="input" type="password" name="password" required>
        </div>
        <div class="actions" style="text-align: center;" ><button class="btn ">Login</button></div>
        <div style="text-align: center; margin-top: 1rem;">
            <a href="<?= BASE_URL ?>views/register.php">Don't have an account? Register</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
