<?php
/**
 * ============================================
 * NACOS DASHBOARD - MEMBER LOGIN
 * ============================================
 * Purpose: Handle member login authentication
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Bootstrap and includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize database
$db = getDB();

// Initialize session
initSession();

// Redirect if already logged in
if (isMemberLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Prepare messages
$login_error = '';
$reg_error = '';
$reg_success = '';

// Handle combined form submission (login or register)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        if ($action === 'register') {
            $reg_error = 'Invalid request. Please try again.';
        } else {
            $login_error = 'Invalid request. Please try again.';
        }
    } else {
        if ($action === 'register') {
            // Registration logic
            $full_name = sanitizeInput($_POST['full_name'] ?? '');
            $matric_no = sanitizeInput($_POST['matric_no'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone_number = sanitizeInput($_POST['phone_number'] ?? '');
            $department = sanitizeInput($_POST['department'] ?? '');
            $level = sanitizeInput($_POST['level'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $formatted_matric = formatMatricNumber($matric_no);

            if (empty($full_name) || empty($matric_no) || empty($email) || empty($department) || empty($level) || empty($password)) {
                $reg_error = 'All fields with * are required.';
            } elseif (!$formatted_matric) {
                $reg_error = 'Invalid matriculation number format. Use format: XX/XXXX (e.g., 23/0223)';
            } elseif (!isValidEmail($email)) {
                $reg_error = 'Invalid email format.';
            } elseif ($password !== $confirm_password) {
                $reg_error = 'Passwords do not match.';
            } elseif (strlen($password) < 5) {
                $reg_error = 'Password must be at least 5 characters long.';
            } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $reg_error = 'Password must contain at least 1 letter and 1 number.';
            } else {
                $matric_no = $formatted_matric;
                $existing_member = $db->fetchOne("SELECT * FROM members WHERE matric_no = :matric_no OR email = :email", [
                    ':matric_no' => $matric_no,
                    ':email' => $email
                ]);

                if ($existing_member) {
                    if ($existing_member['matric_no'] === $matric_no) {
                        $reg_error = 'A member with this matriculation number already exists.';
                    } else {
                        $reg_error = 'A member with this email address already exists.';
                    }
                } else {
                    $password_hash = hashPassword($password);
                    $query = "INSERT INTO members (full_name, matric_no, email, phone, department, `level`, password_hash, membership_status, registration_date) VALUES (:full_name, :matric_no, :email, :phone, :department, :level, :password_hash, 'pending', CURDATE())";
                    $params = [
                        ':full_name' => $full_name,
                        ':matric_no' => $matric_no,
                        ':email' => $email,
                        ':phone' => $phone_number,
                        ':department' => $department,
                        ':level' => $level,
                        ':password_hash' => $password_hash
                    ];

                    try {
                        $db->query($query, $params);
                        $reg_success = 'Registration successful! Your account is pending admin approval. You can sign in once approved.';
                    } catch (Exception $e) {
                        $reg_error = 'An error occurred during registration. Please try again later.';
                        logSecurityEvent('Member registration failed: ' . $e->getMessage(), 'error');
                    }
                }
            }

        } else {
            // Login logic
            $matric_no = sanitizeInput($_POST['matric_no'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($matric_no) || empty($password)) {
                $login_error = 'Matriculation number and password are required.';
            } else {
                $member = $db->fetchOne("SELECT * FROM members WHERE matric_no = :matric_no", [':matric_no' => $matric_no]);

                if (!$member) {
                    $admin = $db->fetchOne("SELECT * FROM administrators WHERE username = :username", [':username' => $matric_no]);
                    if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password_hash'])) {
                        regenerateSession(true);
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['username'] = $admin['username'];
                        $_SESSION['role'] = $admin['role'];
                        $_SESSION['full_name'] = $admin['full_name'];
                        $_SESSION['email'] = $admin['email'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $_SESSION['last_activity'] = time();
                        $db->query("UPDATE administrators SET last_login = NOW() WHERE admin_id = ?", [$admin['admin_id']]);
                        logSecurityEvent('Admin login via member page: ' . $admin['username'], 'info');
                        header('Location: ../admin/index.php');
                        exit();
                    }
                }

                if (!$member) {
                    $login_error = 'Invalid matriculation number or password.';
                } elseif (empty($member['password_hash'])) {
                    $login_error = 'Account not properly configured. Please contact an administrator.';
                } elseif (password_verify($password, $member['password_hash'])) {
                    if ($member['membership_status'] === 'pending') {
                        $login_error = 'Your account is pending admin approval. Please wait a few hours while we verify your details.';
                    } elseif (in_array($member['membership_status'], ['inactive', 'suspended'])) {
                        $login_error = 'Your account has been deactivated. Please contact an administrator for assistance.';
                    } elseif ($member['membership_status'] === 'active') {
                        regenerateSession(true);
                        $_SESSION['member_id'] = $member['member_id'];
                        $_SESSION['member_matric_no'] = $member['matric_no'];
                        $_SESSION['member_full_name'] = $member['full_name'];
                        $_SESSION['member_role'] = $member['role'];
                        $_SESSION['member_executive_position'] = $member['executive_position'] ?? null;
                        $_SESSION['member_email'] = $member['email'];
                        $_SESSION['member_logged_in'] = true;
                        if ($member['role'] === 'admin' || $member['role'] === 'executive') {
                            header('Location: ../admin/index.php');
                        } else {
                            header('Location: dashboard.php');
                        }
                        exit();
                    } else {
                        $login_error = 'Your membership is not active. Please contact an administrator.';
                    }
                } else {
                    $login_error = 'Invalid matriculation number or password.';
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');

*{
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Montserrat', sans-serif;
}

:root{
    --nacos-main: #05963fff; /* primary NACOS teal */
    --nacos-accent: #4ab82bff; /* NACOS accent indigo */
    --nacos-bg-light: #f4f7ff;
}

body{
    background-color: var(--nacos-bg-light);
    background: linear-gradient(120deg, rgba(92,107,192,0.08), rgba(45,160,168,0.06));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    min-height: 100vh;
    padding: 24px 16px;
}

.password-wrapper{
    position: relative;
    width: 100%;
}

.password-wrapper input{
    padding-right: 40px;
    height: 44px;
    line-height: 44px;
}

.toggle-password{
    position: absolute;
    right: -5px;
    top: 33%;
    transform: translateY(-50%);
    background: transparent !important;
    border: 0 !important;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    margin: 0;
    box-shadow: none;
    display: none;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-password:focus{
    outline: none;
}

.container{
    background-color: #fff;
    border-radius: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35);
    position: relative;
    overflow: hidden;
    width: 768px;
    max-width: 100%;
    min-height: 550px;
    margin: 0 auto;
}

.container p{
    font-size: 14px;
    line-height: 20px;
    letter-spacing: 0.3px;
    margin: 20px 0;
}

.container span{
    font-size: 12px;
}

.container a{
    color: var(--nacos-main);
    font-size: 13px;
    text-decoration: none;
    margin: 15px 0 10px;
}

.container button{
    background-color: var(--nacos-main);
    color: #fff;
    font-size: 12px;
    padding: 10px 45px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-top: 10px;
    cursor: pointer;
}

.container button.hidden{
    background-color: transparent;
    border-color: #fff;
}

.container button:hover{
    background-color: var(--nacos-accent);
    border-color: var(--nacos-accent);
    border: white solid 1px;
}

.container form{
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 0 40px;
    height: 100%;
}

.container input{
    background-color: #eee;
    border: none;
    margin: 8px 0;
    padding: 10px 15px;
    font-size: 13px;
    border-radius: 8px;
    width: 100%;
    outline: none;
}

.form-container{
    position: absolute;
    top: 0;
    height: 100%;
    transition: all 0.6s ease-in-out;
}

.sign-in{
    left: 0;
    width: 50%;
    z-index: 2;
}

.container.active .sign-in{
    transform: translateX(100%);
}

.sign-up{
    left: 0;
    width: 50%;
    opacity: 0;
    z-index: 1;
}

.container.active .sign-up{
    transform: translateX(100%);
    opacity: 1;
    z-index: 5;
    animation: move 0.6s;
}

@keyframes move{
    0%, 49.99%{
        opacity: 0;
        z-index: 1;
    }
    50%, 100%{
        opacity: 1;
        z-index: 5;
    }
}

.social-icons{
    margin: 20px 0;
}

.social-icons a{
    border: 1px solid rgba(45,160,168,0.12);
    border-radius: 20%;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    margin: 0 3px;
    width: 40px;
    height: 40px;
}

.toggle-container{
    position: absolute;
    top: 0;
    left: 50%;
    width: 50%;
    height: 100%;
    overflow: hidden;
    transition: all 0.6s ease-in-out;
    border-radius: 150px 0 0 100px;
    z-index: 1000;
}

.container.active .toggle-container{
    transform: translateX(-100%);
    border-radius: 0 150px 100px 0;
}

    .toggle{
    background-color: var(--nacos-main);
    height: 100%;
    background: linear-gradient(to right, var(--nacos-accent), var(--nacos-main));
    color: #fff;
    position: relative;
    left: -100%;
    height: 100%;
    width: 200%;
    transform: translateX(0);
    transition: all 0.6s ease-in-out;
}

.container.active .toggle{
    transform: translateX(50%);
}

.toggle-panel{
    position: absolute;
    width: 50%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 0 30px;
    text-align: center;
    top: 0;
    transform: translateX(0);
    transition: all 0.6s ease-in-out;
}

.toggle-left{
    transform: translateX(-200%);
}

.container.active .toggle-left{
    transform: translateX(0);
}

.toggle-right{
    right: 0;
    transform: translateX(0);
}

.container.active .toggle-right{
    transform: translateX(200%);
}
    
    /* Small centered modal (NACOS style) */
    .nacos-modal-overlay{
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 16px;
    }
    .nacos-modal-overlay.active{ display: flex; }
    .nacos-modal{
        background: #fff;
        border-radius: 12px;
        max-width: 320px;
        width: 92%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        padding: 16px;
        box-sizing: border-box;
        text-align: left;
    }
    .nacos-modal h3{ margin: 0 0 8px 0; font-size: 18px; }
    .nacos-modal p{ margin: 0 0 12px 0; color: #333; }
    .nacos-modal .modal-actions{ display:flex; justify-content:flex-end; gap:8px; }
    .nacos-modal .btn-cancel{ background: transparent; border: 1px solid #ddd; color: #333; padding:6px 12px; border-radius:6px; cursor:pointer; }
    .nacos-modal .btn-confirm{ background: var(--nacos-main); color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; }

    @media (max-width: 900px){
        .container{ width: 100%; }
        .container form{ padding: 0 24px; }
    }

    @media (max-width: 480px){
        body{ padding: 16px 12px; }
        .container{ border-radius: 20px; }
        .container form{ padding: 0 20px; }
        .nacos-modal{ max-width: 280px; }
    }
    </style>
</head>

  <body>
        <div class="container" id="container">
            <div class="form-container sign-up">
                <form method="POST" action="login.php">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <!--  <h1>Create Account</h1>
                   <div class="social-icons">
                        <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                    </div> 
                    <span>Use your email for registration</span> -->
                    <input type="text" name="full_name" placeholder="Full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required />
                    <input type="text" name="matric_no" placeholder="Matriculation (e.g. 23/0223)" value="<?php echo htmlspecialchars($_POST['matric_no'] ?? ''); ?>" required />
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
                    <input type="text" name="phone_number" placeholder="Phone number (optional)" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" />
                    <select name="department" required style="width:100%;margin-top:8px;padding:10px;border-radius:8px">
                        <option value="">-- Select Department --</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Computer Information System">Computer Information System</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Cyber Security">Cyber Security</option>
                    </select>
                    <select name="level" required style="width:100%;margin-top:8px;padding:10px;border-radius:8px">
                        <option value="">-- Select Level --</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                        <option value="graduate">Graduate</option>
                    </select>
                    <div class="password-wrapper">
                        <input type="password" id="signup_password" name="password" placeholder="Password" required />
                        <button type="button" class="toggle-password" data-target="signup_password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" id="signup_confirm_password" name="confirm_password" placeholder="Confirm password" required />
                        <button type="button" class="toggle-password" data-target="signup_confirm_password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <button type="submit">Sign Up</button>
                </form>
            </div>
            <div class="form-container sign-in">
                <form method="POST" action="login.php">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <h1>Sign In</h1>
                     <!-- <div class="social-icons">
                        <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                        <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                    </div>-->
                    <span>Use your matriculation & password</span>
                    <?php if (!empty($login_error)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>
                    <input type="text" name="matric_no" placeholder="Matriculation number" value="<?php echo htmlspecialchars($_POST['matric_no'] ?? ''); ?>" required />
                    <div class="password-wrapper">
                        <input type="password" id="login_password" name="password" placeholder="Password" required />
                        <button type="button" class="toggle-password" data-target="login_password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <a href="forgot_password.php">Forget Your Password?</a>
                    <button type="submit">Sign In</button>
                </form>
            </div>
      <div class="toggle-container">
        <div class="toggle">
          <div class="toggle-panel toggle-left">
            <h1>Welcome Back!</h1>
            <p>Enter your personal details to use all of site features</p>
            <button class="hidden" id="login">Sign In</button>
          </div>
          <div class="toggle-panel toggle-right">
            <h1>Hello, Friend!</h1>
            <p>
              Register with your personal details to use all of site features
            </p>
            <button class="hidden" id="register">Sign Up</button>
          </div>
        </div>
      </div>
        </div>
        <!-- Centered modal overlay -->
        <div id="nacosModalOverlay" class="nacos-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
            <div class="nacos-modal" role="document">
                <h3 id="nacosModalTitle">Notice</h3>
                <p id="nacosModalMessage">This is a small centered popup. Use it for confirmations or messages.</p>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="nacosModalCancel">Cancel</button>
                    <button type="button" class="btn-confirm" id="nacosModalConfirm">OK</button>
                </div>
            </div>
        </div>

        <div style="height:80px;">&nbsp;</div>

        <script>
const container = document.getElementById("container");
const registerBtn = document.getElementById("register");
const loginBtn = document.getElementById("login");
if (registerBtn) registerBtn.addEventListener("click", () => {
    container.classList.add("active");
});
if (loginBtn) loginBtn.addEventListener("click", () => {
    container.classList.remove("active");
});

// If registration had errors or success, open the sign-up panel
<?php if (!empty($reg_error) || !empty($reg_success)): ?>
document.addEventListener('DOMContentLoaded', function(){ container.classList.add('active'); });
<?php endif; ?>

// Modal helpers
const nacosModalOverlay = document.getElementById('nacosModalOverlay');
const nacosModalCancel = document.getElementById('nacosModalCancel');
const nacosModalConfirm = document.getElementById('nacosModalConfirm');
const nacosModalTitle = document.getElementById('nacosModalTitle');
const nacosModalMessage = document.getElementById('nacosModalMessage');

function showNacosModal(title, message, showConfirm = false){
    if (!nacosModalOverlay) return;
    nacosModalTitle.textContent = title || 'Notice';
    nacosModalMessage.textContent = message || '';
    if (nacosModalConfirm) nacosModalConfirm.style.display = showConfirm ? 'inline-block' : 'none';
    nacosModalOverlay.classList.add('active');
    nacosModalOverlay.setAttribute('aria-hidden', 'false');
}

function hideNacosModal(){
    if (!nacosModalOverlay) return;
    nacosModalOverlay.classList.remove('active');
    nacosModalOverlay.setAttribute('aria-hidden', 'true');
}

if (nacosModalCancel) nacosModalCancel.addEventListener('click', hideNacosModal);
if (nacosModalOverlay) nacosModalOverlay.addEventListener('click', function(e){ if (e.target === nacosModalOverlay) hideNacosModal(); });
if (nacosModalConfirm) nacosModalConfirm.addEventListener('click', function(){ hideNacosModal(); });

// Password eye toggle
document.querySelectorAll('.toggle-password').forEach(function(btn){
    btn.addEventListener('click', function(){
        const target = document.getElementById(btn.dataset.target);
        if (!target) return;
        const isPassword = target.type === 'password';
        target.type = isPassword ? 'text' : 'password';
        btn.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });
});

document.querySelectorAll('.password-wrapper input').forEach(function(input){
    const toggle = input.parentElement.querySelector('.toggle-password');
    if (!toggle) return;
    const updateToggle = function(){
        toggle.style.display = input.value ? 'inline-flex' : 'none';
    };
    input.addEventListener('input', updateToggle);
    input.addEventListener('blur', updateToggle);
    updateToggle();
});

// Optionally auto-show modal on success (example):
<?php if (!empty($reg_success)): ?>
document.addEventListener('DOMContentLoaded', function(){ showNacosModal('Registration', <?php echo json_encode($reg_success); ?>, false); });
<?php endif; ?>
<?php if (!empty($reg_error)): ?>
document.addEventListener('DOMContentLoaded', function(){ showNacosModal('Registration Error', <?php echo json_encode($reg_error); ?>, false); });
<?php endif; ?>

        </script>
  </body>
</html>
