<?php
// common/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$user = $_SESSION['user'] ?? null;

// Determine path prefix (root vs nested)
$pathPrefix = file_exists('assets/css/style.css') ? '' : '../../';

// Determine home URL based on role
$homeUrl = $pathPrefix . 'index.php'; // default
if (!empty($user['role'])) {
    if ($user['role'] === 'admin') $homeUrl = $pathPrefix . 'pages/admin/dashboard.php';
    elseif ($user['role'] === 'faculty') $homeUrl = $pathPrefix . 'pages/faculty/dashboard.php';
    elseif ($user['role'] === 'student') $homeUrl = $pathPrefix . 'pages/student/dashboard.php';
}
?>

<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $pathPrefix ?>favicon.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $pathPrefix ?>favicon.png">
<link rel="apple-touch-icon" href="<?= $pathPrefix ?>favicon.png">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>College Asset Damage Reporting</title>
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<script defer src="<?= BASE_URL ?>assets/js/app.js"></script>

<script>
let lastCount = 0, audioEnabled = false;

['click', 'keydown'].forEach(e => document.addEventListener(e, () => {
    audioEnabled = true;
    console.log('🔊 Audio enabled');
    const audio = new Audio('<?= BASE_URL ?>sounds/notification.mp3');
    audio.volume = 0;
    audio.play().then(() => audio.pause()).catch(() => {});
}, {once: true}));

function checkNewNotifications() {
    <?php if(isset($_SESSION['user'])): ?>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'check_notifications=1&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.text())
    .then(data => {
        const count = parseInt(data) || 0;
        
        if (count > lastCount && lastCount > 0) {
            const newCount = count - lastCount;
            console.log(`🎵 ${newCount} new notifications!`);
            
            // Play sound
            if (audioEnabled) {
                const audio = new Audio('<?= BASE_URL ?>sounds/notification.mp3');
                audio.volume = 0.6;
                audio.play().then(() => console.log('🔊 Sound played!')).catch(e => console.log('🔇 Failed:', e));
            }
            
            // Show mobile-friendly alert
            const alert = document.createElement('div');
            alert.innerHTML = audioEnabled ? `🔔 ${newCount} new notification${newCount > 1 ? 's' : ''}!` : 'Tap to enable sounds';
            alert.className = 'alert success';
            alert.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:9999;cursor:pointer;max-width:90%;text-align:center;';
            alert.onclick = () => { audioEnabled = true; alert.remove(); };
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 4000);
        }
        lastCount = count;
    })
    .catch(e => console.log('❌ Failed:', e));
    <?php endif; ?>
}

// Mobile menu toggle
function toggleMobileMenu() {
    const menu = document.querySelector('.nav-mobile');
    const toggle = document.querySelector('.menu-toggle');
    menu.classList.toggle('active');
    toggle.classList.toggle('active');
}

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    const menu = document.querySelector('.nav-mobile');
    const toggle = document.querySelector('.menu-toggle');
    if (menu && menu.classList.contains('active') && 
        !menu.contains(e.target) && !toggle.contains(e.target)) {
        menu.classList.remove('active');
        toggle.classList.remove('active');
    }
});

setInterval(checkNewNotifications, 8000);
setTimeout(checkNewNotifications, 1000);
</script>
</head>
<body>
<header class="topbar">
  <div class="brand"><a href="<?= $homeUrl ?>">College Assets</a></div>
  
  <!-- Desktop Navigation -->
  <nav class="nav">
    <?php if ($user): ?>
      <span class="tag"><?= htmlspecialchars($user['role']) ?></span>
      <a href="<?= $pathPrefix ?>notifications.php" class="btn small">📱</a>
      <a href="<?= $pathPrefix ?>logout.php" class="btn outline small">Exit</a>
    <?php else: ?>
      <a href="<?= $pathPrefix ?>index.php" class="btn small">Login</a>
    <?php endif; ?>
  </nav>
  
  <!-- Mobile Menu Toggle -->
  <div class="menu-toggle" onclick="toggleMobileMenu()">
    <span></span>
    <span></span>
    <span></span>
  </div>
</header>

<!-- Mobile Navigation Menu -->
<div class="nav-mobile">
  <div class="card" style="margin:0;">
    <?php if ($user): ?>
      <div style="text-align:center;margin-bottom:12px;">
        <span class="tag"><?= htmlspecialchars($user['role']) ?></span>
      </div>
      <div class="button-row" style="flex-direction:column;">
        <a href="<?= $pathPrefix ?>notifications.php" class="btn">📱 Notifications</a>
        <a href="<?= $pathPrefix ?>logout.php" class="btn outline">Logout</a>
      </div>
    <?php else: ?>
      <a href="<?= $pathPrefix ?>index.php" class="btn" style="width:100%;">Login</a>
    <?php endif; ?>
  </div>
</div>

<main class="container">
