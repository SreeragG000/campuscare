<?php
// views/partials/header.php
require_once __DIR__ . '/../../config/init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
$user = $_SESSION['user'] ?? null;

// Determine home URL based on role
$homeUrl = BASE_URL . 'index.php'; // default
if (!empty($user['role'])) {
    if ($user['role'] === 'admin') {
        $homeUrl = BASE_URL . 'views/admin/dashboard.php';
    } elseif ($user['role'] === 'faculty') {
        $homeUrl = BASE_URL . 'views/faculty/dashboard.php';
    } else {
        $homeUrl = BASE_URL . 'views/student/dashboard.php';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>favicon.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>favicon.png">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>favicon.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>College Asset Damage Reporting</title>
  <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <script defer src="<?= BASE_URL ?>assets/js/app.js"></script>

<script>
let lastCount = 0;
let audioEnabled = false;
const audioPath = '<?= BASE_URL ?>sounds/notification.mp3';

// 1. Initialize Audio Context on User Interaction
const enableAudio = () => {
    if (audioEnabled) return;
    audioEnabled = true;
    console.log('🔊 Audio Context Unlocked');
    
    // Play silent buffer to unlock
    const audio = new Audio(audioPath);
    audio.volume = 0;
    audio.play().then(() => {
        audio.pause();
        audio.currentTime = 0;
    }).catch(e => console.log('Audio unlock failed:', e));
    
    // Remove listeners
    ['click', 'keydown', 'touchstart'].forEach(e => 
        document.removeEventListener(e, enableAudio)
    );
};

['click', 'keydown', 'touchstart'].forEach(e => 
    document.addEventListener(e, enableAudio)
);

function checkNewNotifications() {
    <?php if(isset($_SESSION['user'])): ?>
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return;
    
    const csrfToken = csrfMeta.getAttribute('content');
    
    fetch('<?= BASE_URL ?>includes/check_notif.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'check_notifications=1&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.text())
    .then(data => {
        const count = parseInt(data) || 0;
        
        // Update Badge
        const badges = document.querySelectorAll('.notif-badge');
        badges.forEach(badge => {
            if (count > 0) {
                badge.style.display = 'inline-block';
                badge.innerText = count > 99 ? '99+' : count;
            } else {
                badge.style.display = 'none';
            }
        });

        if (count > lastCount && lastCount > 0) {
            const newCount = count - lastCount;
            console.log(`🎵 ${newCount} new notifications!`);
            
            // Play Sound
            if (audioEnabled) {
                const audio = new Audio(audioPath);
                audio.volume = 0.6;
                audio.play()
                    .then(() => console.log('🔊 Notification sound played'))
                    .catch(e => console.error('🚫 Autoplay blocked:', e));
            } else {
                console.warn('🔇 Audio blocked - User interaction required');
            }
            
            // Show Alert
            const alert = document.createElement('div');
            alert.innerHTML = `🔔 ${newCount} new notification${newCount > 1 ? 's' : ''}!`;
            alert.className = 'alert success';
            alert.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:9999;cursor:pointer;max-width:90%;text-align:center;box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            alert.onclick = () => { alert.remove(); };
            document.body.appendChild(alert);
            setTimeout(() => { if(alert) alert.remove(); }, 5000);
        }
        lastCount = count;
    })
    .catch(e => console.log('❌ Polling Error:', e));
    <?php endif; ?>
}

// Mobile menu toggle
function toggleMobileMenu() {
    const menu = document.querySelector('.nav-mobile');
    const toggle = document.querySelector('.menu-toggle');
    if (menu && toggle) {
        menu.classList.toggle('active');
        toggle.classList.toggle('active');
    }
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
      <a href="<?= BASE_URL ?>views/notifications.php" class="btn small" style="position:relative;">
        📱 <span class="notif-badge" style="display:none; position:absolute; top:-8px; right:-8px; background:red; color:white; border-radius:50%; padding:2px 5px; font-size:10px; min-width:18px; text-align:center;"></span>
      </a>
      <a href="<?= BASE_URL ?>includes/logout.php" class="btn outline small">Exit</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>views/login.php" class="btn small">Login</a>
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
        <a href="<?= BASE_URL ?>views/notifications.php" class="btn" style="position:relative;">
            📱 Notifications <span class="notif-badge" style="display:none; background:red; color:white; border-radius:50%; padding:2px 8px; font-size:12px; margin-left: 5px;"></span>
        </a>
        <a href="<?= BASE_URL ?>includes/logout.php" class="btn outline">Logout</a>
      </div>
    <?php else: ?>
      <a href="<?= BASE_URL ?>views/login.php" class="btn" style="width:100%;">Login</a>
    <?php endif; ?>
  </div>
</div>

<main class="container">

