<?php
/**
 * ============================================
 * NACOS DASHBOARD - ADMIN LOGIN
 * ============================================
 * Purpose: Secure administrator login page
 * Security: Password verification, CSRF protection, rate limiting
 * Created: November 2, 2025
 * ============================================
 */

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize session
initSession();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Attempt authentication
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Login successful
            loginUser($user);
            
            // Log security event
            logSecurityEvent("Successful login: $username", 'info');
            
            // Redirect to dashboard
            redirectWithMessage('index.php', 'Welcome back, ' . $user['full_name'] . '!', 'success');
        } else {
            // Login failed
            $error_message = 'Invalid username or password, or account is inactive.';
            logSecurityEvent("Failed login attempt: $username", 'warning');
        }
    }
}

// Get flash message if any
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success_message = $flash['message'];
    } else {
        $error_message = $flash['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NACOS Dashboard</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0F6B3E;
            --secondary-color: #1B8A56;
            --bg-dark: #0f1722;
            --bg-mid: #132435;
            --card-bg: #f8fafc;
            --text-primary: #223043;
            --text-muted: #64748b;
            --lamp-intensity: 0;
            --cord-pull: 0px;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #e5edf7;
            background: linear-gradient(145deg, var(--bg-dark), var(--bg-mid));
            overflow-x: hidden;
            transition: background 0.35s ease;
        }

        @media (min-width: 1024px) {
            body {
                background:
                    radial-gradient(ellipse 600px 500px at 150px 50%, rgba(255, 243, 195, calc(0.12 * var(--lamp-intensity))) 0%, rgba(255, 243, 195, 0) 50%),
                    radial-gradient(ellipse 800px 600px at 100px 50%, rgba(255, 235, 173, calc(0.06 * var(--lamp-intensity))) 0%, rgba(255, 235, 173, 0) 60%),
                    linear-gradient(145deg, var(--bg-dark), var(--bg-mid));
            }
        }

        .login-page {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 210px 16px 28px;
        }

        .lamp-wrap {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            pointer-events: none;
        }

        @media (min-width: 1024px) {
            .lamp-wrap {
                position: absolute;
                top: 50%;
                left: 8%;
                transform: translate(0, -50%);
                z-index: 10;
                width: 120px;
                height: 200px;
                pointer-events: none;
            }
        }

        .lamp-cord {
            width: 4px;
            height: calc(120px + var(--cord-pull));
            background: #d1d5db;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.08);
            margin: 0 auto;
            pointer-events: none;
            transition: height 0.14s ease;
        }

        @media (min-width: 1024px) {
            .lamp-cord {
                display: none;
            }
        }

        .lamp-head {
            width: 100px;
            height: 46px;
            border-radius: 0 0 58px 58px;
            margin: 0 auto;
            background: linear-gradient(180deg, #f1f5f9, #cbd5e1);
            box-shadow:
                0 6px 18px rgba(0, 0, 0, 0.22),
                0 0 24px rgba(255, 235, 173, calc(0.25 * var(--lamp-intensity)));
            position: relative;
            pointer-events: none;
        }

        .lamp-head::after {
            content: '';
            position: absolute;
            left: 16px;
            right: 16px;
            top: 40px;
            height: 6px;
            border-radius: 999px;
            background: rgba(255, 228, 150, calc(0.85 * var(--lamp-intensity)));
            filter: blur(2px);
        }

        @media (min-width: 1024px) {
            .lamp-head {
                display: none;
            }

            .lamp-head::after {
                display: none;
            }
        }

        svg.lamp-svg {
            display: none;
            width: 100%;
            height: 100%;
        }

        @media (min-width: 1024px) {
            svg.lamp-svg {
                display: block;
                filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
            }
        }

        .lamp-handle {
            position: fixed;
            top: 146px;
            left: 50%;
            transform: translateX(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(145deg, #facc15, #eab308);
            color: #1f2937;
            box-shadow: 0 8px 20px rgba(0,0,0,0.28);
            cursor: grab;
            z-index: 20;
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.16s ease;
        }

        .lamp-handle:active {
            cursor: grabbing;
        }

        .lamp-handle i {
            font-size: 13px;
        }

        @media (min-width: 1024px) {
            .lamp-handle {
                position: absolute;
                top: 80px;
                left: 50%;
                transform: translate(-50%, 0);
                width: 28px;
                height: 28px;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            }

            .lamp-handle i {
                font-size: 11px;
            }
        }

        .light-cone {
            position: fixed;
            top: 154px;
            left: 50%;
            transform: translateX(-50%);
            width: min(88vw, 740px);
            height: min(66vh, 520px);
            pointer-events: none;
            z-index: 1;
            opacity: calc(0.98 * var(--lamp-intensity));
            background: radial-gradient(ellipse at top,
                rgba(255, 243, 195, 0.88) 0%,
                rgba(255, 243, 195, 0.25) 46%,
                rgba(255, 243, 195, 0) 74%);
            clip-path: polygon(48% 0, 52% 0, 96% 100%, 4% 100%);
            filter: blur(1px);
            transition: opacity 0.3s ease;
        }

        @media (min-width: 1024px) {
            .light-cone {
                display: none;
            }
        }

        .layout {
            width: 100%;
            display: flex;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        @media (min-width: 1024px) {
            .layout {
                width: 100%;
                display: flex;
                justify-content: flex-end;
                align-items: center;
                padding-right: 12%;
                padding-top: 0;
            }
        }

        .form-panel {
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.14);
            backdrop-filter: blur(8px);
            box-shadow: 0 20px 48px rgba(2, 10, 24, 0.32);
        }

        .form-panel {
            background: rgba(248, 250, 252, calc(0.35 + 0.6 * var(--lamp-intensity)));
            width: min(92vw, 420px);
            padding: 18px;
            transform: translateY(-10px) scale(0.96);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.38s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s ease, background 0.3s ease;
        }

        .login-form {
            color: var(--text-primary);
        }

        .login-form.active {
            animation: formAwake 0.38s ease;
        }

        @keyframes formAwake {
            from { transform: translateY(8px); opacity: 0.2; }
            to { transform: translateY(0); opacity: 1; }
        }

        body[data-lamp="on"] .form-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: auto;
        }

        @media (min-width: 1024px) {
            .form-panel {
                width: min(90%, 360px);
                padding: 14px;
                transform: translateX(20px) scale(0.96);
                flex-shrink: 0;
            }

            body[data-lamp="on"] .form-panel {
                transform: translateX(0) scale(1);
            }
        }

        .form-title {
            color: #203243;
            font-size: 1.55rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .form-subtitle {
            color: var(--text-muted);
            margin-bottom: 16px;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 10px;
            padding: 11px 14px;
            border: 2px solid #d8dee7;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(15, 107, 62, 0.18);
        }

        .input-group-text {
            border: 2px solid #d8dee7;
            border-right: none;
            border-radius: 10px 0 0 10px;
            background: #f6f8fb;
            color: #3f4f63;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            width: 100%;
            padding: 12px;
            transition: transform 0.16s ease, box-shadow 0.16s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 9px 24px rgba(15, 107, 62, 0.28);
        }

        .login-note {
            background: #eef6ff;
            border: 1px solid #d4e6ff;
            color: #1f3a5a;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.86rem;
            margin-top: 12px;
        }

        .login-note code {
            color: #0f172a;
            font-weight: 700;
        }

        .login-footer {
            margin-top: 14px;
            color: #67768a;
            text-align: center;
            font-size: 0.86rem;
        }

        .login-footer a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: underline;
        }

        .site-back {
            text-align: center;
            margin-top: 12px;
        }

        .site-back a {
            color: #d2e6fb;
            text-decoration: none;
        }

        .site-back a:hover {
            color: #ffffff;
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 14px;
        }

        @media (min-width: 1024px) {
            .login-page {
                padding-top: 0;
                padding-left: 0;
                padding-right: 0;
                align-items: center;
            }

            .form-title {
                font-size: 1.3rem;
                margin-bottom: 2px;
            }

            .form-subtitle {
                font-size: 0.86rem;
                margin-bottom: 12px;
            }

            .form-label {
                font-size: 0.9rem;
                margin-bottom: 5px;
            }

            .mb-3 {
                margin-bottom: 10px !important;
            }

            .mb-4 {
                margin-bottom: 10px !important;
            }

            .form-control {
                padding: 8px 11px;
                font-size: 0.9rem;
            }

            .btn-login {
                padding: 8px;
                font-size: 0.9rem;
            }

            .login-note {
                padding: 8px 9px;
                font-size: 0.75rem;
                margin-top: 7px;
            }

            .login-footer {
                font-size: 0.75rem;
                margin-top: 7px;
            }

            .site-back {
                margin-top: 6px;
            }

            .site-back a {
                font-size: 0.82rem;
            }
        }

        @media (max-width: 991.98px) {
            .form-panel {
                width: min(94vw, 420px);
            }
        }

        @media (max-width: 575.98px) {
            .login-page {
                padding-top: 190px;
            }

            .lamp-head {
                width: 84px;
            }

            .lamp-handle {
                width: 32px;
                height: 32px;
            }

            .form-panel {
                padding: 14px;
            }

            .form-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body class="login-page" data-lamp="off">
    <div class="lamp-wrap" aria-hidden="true">
        <div class="lamp-cord"></div>
        <div class="lamp-head"></div>
        <svg class="lamp-svg" viewBox="0 0 120 200" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
            <!-- Lamp Shade -->
            <path d="M 25 30 L 35 60 L 85 60 L 95 30 Z" fill="none" stroke="#8B8680" stroke-width="2" stroke-linejoin="round"/>
            <ellipse cx="60" cy="30" rx="35" ry="8" fill="none" stroke="#8B8680" stroke-width="2"/>
            <ellipse cx="60" cy="60" rx="50" ry="12" fill="none" stroke="#8B8680" stroke-width="2"/>
            
            <!-- Bulb glow inside shade -->
            <ellipse cx="60" cy="40" rx="28" ry="22" fill="rgba(255, 235, 173, 0.3)" opacity="calc(var(--lamp-intensity, 0) * 0.8)"/>
            
            <!-- Pole connector -->
            <rect x="57" y="58" width="6" height="8" fill="#8B8680" stroke="#5A5855" stroke-width="0.5"/>
            
            <!-- Pole -->
            <rect x="56" y="66" width="8" height="60" fill="#8B8680" stroke="#5A5855" stroke-width="0.5" rx="2"/>
            
            <!-- Pole details (vertical line) -->
            <line x1="60" y1="66" x2="60" y2="126" stroke="#5A5855" stroke-width="0.5"/>
            
            <!-- Base support -->
            <rect x="45" y="125" width="30" height="8" fill="#8B8680" stroke="#5A5855" stroke-width="0.5" rx="2"/>
            
            <!-- Base bottom -->
            <ellipse cx="60" cy="133" rx="35" ry="10" fill="#8B8680" stroke="#5A5855" stroke-width="1"/>
            <ellipse cx="60" cy="133" rx="32" ry="8" fill="#A0998E" stroke="none"/>
            
            <!-- Base details -->
            <path d="M 25 133 Q 60 140 95 133" fill="none" stroke="#5A5855" stroke-width="0.5" opacity="0.5"/>
        </svg>
    </div>
    <button type="button" class="lamp-handle" id="lampHandle" aria-label="Toggle lamp">
        <i class="fas fa-grip-lines"></i>
    </button>
    <div class="light-cone" aria-hidden="true"></div>

    <div class="layout">
        <section class="form-panel" id="loginPanel" aria-live="polite">
            <form method="POST" action="login.php" id="loginForm" class="login-form" novalidate>
                <h2 class="form-title">Admin Login</h2>
                <p class="form-subtitle">Faculty of Science - Adeleke University</p>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="username" class="form-label"><i class="fas fa-user me-1"></i> Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label"><i class="fas fa-lock me-1"></i> Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">Remember me for 30 days</label>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Login to Dashboard
                </button>

                <div class="login-note">
                    <strong><i class="fas fa-info-circle me-1"></i> Test Credentials</strong><br>
                    Username: <code>super_admin</code> | Password: <code>Admin@2025</code>
                </div>

                <div class="login-footer">
                    <div><i class="fas fa-shield-alt me-1"></i> Secure Login Portal</div>
                    <div>&copy; 2025 NACOSAU. All rights reserved.</div>
                    <div>Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" rel="noopener noreferrer">Johnicity</a></div>
                </div>
            </form>
            <div class="site-back">
                <a href="../public/index.php"><i class="fas fa-arrow-left me-1"></i> Back to Public Site</a>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const body = document.body;
            const root = document.documentElement;
            const lampHandle = document.getElementById('lampHandle');
            const loginPanel = document.getElementById('loginPanel');
            const loginForm = document.getElementById('loginForm');

            let startY = 0;
            let currentPull = 0;
            let dragging = false;
            const pullThreshold = 46;

            function playClickSound() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(980, ctx.currentTime);
                    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.055, ctx.currentTime + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.08);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.085);
                } catch (e) {
                    // Silent fallback when audio context is blocked.
                }
            }

            function setLampState(isOn) {
                body.setAttribute('data-lamp', isOn ? 'on' : 'off');
                root.style.setProperty('--lamp-intensity', isOn ? '1' : '0');

                if (loginForm.classList) {
                    if (isOn) {
                        loginForm.classList.add('active');
                        loginPanel.classList.add('active');
                        setTimeout(() => {
                            const userField = document.getElementById('username');
                            if (userField) userField.focus();
                        }, 220);
                    } else {
                        loginForm.classList.remove('active');
                        loginPanel.classList.remove('active');
                    }
                }
            }

            function toggleLamp() {
                const isOn = body.getAttribute('data-lamp') === 'on';
                playClickSound();
                setLampState(!isOn);
            }

            function resetPullVisual() {
                currentPull = 0;
                root.style.setProperty('--cord-pull', '0px');
                lampHandle.style.transform = 'translateX(-50%) translateY(0px)';
            }

            function startDrag(clientY) {
                dragging = true;
                startY = clientY;
            }

            function moveDrag(clientY) {
                if (!dragging) return;
                const delta = Math.max(0, Math.min(90, clientY - startY));
                currentPull = delta;
                root.style.setProperty('--cord-pull', delta + 'px');
                lampHandle.style.transform = `translateX(-50%) translateY(${delta}px)`;
            }

            function endDrag() {
                if (!dragging) return;
                dragging = false;
                if (currentPull >= pullThreshold) {
                    toggleLamp();
                }
                resetPullVisual();
            }

            lampHandle.addEventListener('click', function (event) {
                if (currentPull > 4) return;
                event.preventDefault();
                toggleLamp();
            });

            function isDesktop() {
                return window.matchMedia('(min-width: 1024px)').matches;
            }

            lampHandle.addEventListener('mousedown', (e) => {
                if (!isDesktop()) startDrag(e.clientY);
            });
            window.addEventListener('mousemove', (e) => {
                if (!isDesktop()) moveDrag(e.clientY);
            });
            window.addEventListener('mouseup', () => {
                if (!isDesktop()) endDrag();
            });

            lampHandle.addEventListener('touchstart', (e) => {
                const touch = e.touches[0];
                if (!isDesktop()) startDrag(touch.clientY);
            }, { passive: true });

            window.addEventListener('touchmove', (e) => {
                if (!dragging || isDesktop()) return;
                const touch = e.touches[0];
                moveDrag(touch.clientY);
            }, { passive: true });

            window.addEventListener('touchend', () => {
                if (!isDesktop()) endDrag();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key.toLowerCase() === 'l' || e.code === 'Space') {
                    toggleLamp();
                }
            });

            loginForm.addEventListener('submit', function (e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                }
            });

            setTimeout(function () {
                document.querySelectorAll('.alert').forEach(function (alertEl) {
                    const closeBtn = alertEl.querySelector('.btn-close');
                    if (closeBtn) closeBtn.click();
                });
            }, 5000);

            // Start with lamp ON on desktop, OFF on mobile
            if (isDesktop()) {
                setLampState(true);
            } else {
                setLampState(false);
            }
        })();
    </script>
</body>
</html>
