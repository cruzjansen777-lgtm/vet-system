<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$regError = '';
$regSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include('dbconnect.php');

    /* ── LOGIN ── */
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $staff_id = trim($_POST['staff_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($staff_id === '' || $password === '') {
            $error = 'Please enter your Staff ID and password.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE StaffID = ? AND IsActive = 1 AND IsDeleted = 0 LIMIT 1");
            $stmt->bind_param("s", $staff_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['Password'])) {
                    $_SESSION['staff_id']  = $user['UserID'];
                    $_SESSION['first_name']= $user['FirstName'];
                    $_SESSION['last_name'] = $user['LastName'];
                    $_SESSION['full_name'] = $user['FirstName'] . ' ' . $user['LastName'];
                    $_SESSION['role']      = $user['Role'];
                    $_SESSION['staff_code']= $user['StaffID'];
                    header("Location: index.php");
                    exit;
                } else {
                    $error = 'Incorrect password. Please try again.';
                }
            } else {
                $error = 'Staff ID not found or account is inactive.';
            }
            $stmt->close();
        }
    }

    /* ── REGISTER ── */
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        include_once('dbconnect.php');

        $firstName = trim($_POST['reg_first_name'] ?? '');
        $lastName  = trim($_POST['reg_last_name']  ?? '');
        $email     = trim($_POST['reg_email']      ?? '');
        $phone     = trim($_POST['reg_phone']      ?? '');
        $role      = $_POST['reg_role']            ?? 'Veterinarian';
        $password  = $_POST['reg_password']        ?? '';
        $confirm   = $_POST['reg_confirm']         ?? '';

        if (!$firstName || !$lastName || !$email || !$password) {
            $regError = 'Please fill in all required fields.';
        } elseif ($password !== $confirm) {
            $regError = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $regError = 'Password must be at least 6 characters.';
        } elseif ($phone !== '' && !preg_match('/^[0-9]{11}$/', $phone)) {
            $regError = 'Phone number must be exactly 11 digits (e.g. 09xxxxxxxxx).';
        } else {
            // Check if email exists
            $chk = $conn->prepare("SELECT UserID FROM users WHERE Email = ? LIMIT 1");
            $chk->bind_param("s", $email);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $regError = 'That email is already registered.';
            } else {
                // Generate next StaffID
                $row = $conn->query("SELECT StaffID FROM users ORDER BY UserID DESC LIMIT 1")->fetch_assoc();
                $nextNum = $row ? intval(substr($row['StaffID'], 4)) + 1 : 1;
                $staffID = 'VET-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $conn->prepare("INSERT INTO users (StaffID,FirstName,LastName,Email,Phone,Role,Password) VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param("sssssss", $staffID, $firstName, $lastName, $email, $phone, $role, $hash);

                if ($ins->execute()) {
                    $regSuccess = "Account created! Your Staff ID is <strong>$staffID</strong>. You can now sign in.";
                } else {
                    $regError = 'Registration failed. Please try again.';
                }
                $ins->close();
            }
            $chk->close();
        }
    }
    /* ── FORGOT PASSWORD ── */
    if (isset($_POST['action']) && $_POST['action'] === 'forgot') {
        include_once('dbconnect.php');
        $fp_email = trim($_POST['fp_email'] ?? '');
        $fp_msg   = '';
        $fp_err   = '';

        if (!$fp_email) {
            $fp_err = 'Please enter your email address.';
        } elseif (!filter_var($fp_email, FILTER_VALIDATE_EMAIL)) {
            $fp_err = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("SELECT UserID, FirstName, StaffID FROM users WHERE Email = ? AND IsActive = 1 AND IsDeleted = 0 LIMIT 1");
            $stmt->bind_param("s", $fp_email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $u = $res->fetch_assoc();
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in DB (create table if needed)
                $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    UserID INT NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (token),
                    INDEX (UserID)
                )");
                // Invalidate old tokens for this user
                $conn->query("UPDATE password_resets SET used=1 WHERE UserID={$u['UserID']}");
                $ins = $conn->prepare("INSERT INTO password_resets (UserID, token, expires_at) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $u['UserID'], $token, $expires);
                $ins->execute();
                $ins->close();

                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

                // Try sending email via PHP mail()
                $to      = $fp_email;
                $subject = 'Heartside Vet — Password Reset Request';
                $body    = "Hi {$u['FirstName']},\n\nYou requested a password reset for your Heartside Vet account (Staff ID: {$u['StaffID']}).\n\nClick the link below to reset your password. This link expires in 1 hour.\n\n$resetLink\n\nIf you did not request this, you can ignore this email.\n\n— Heartside Vet";
                $headers = "From: noreply@heartsideVet.com\r\nX-Mailer: PHP/" . phpversion();
                $sent    = @mail($to, $subject, $body, $headers);

                if ($sent) {
                    $fp_msg = "A password reset link has been sent to <strong>" . htmlspecialchars($fp_email) . "</strong>. Check your inbox (and spam folder). The link expires in 1 hour.";
                } else {
                    // Email not configured — show token link directly (admin/dev fallback)
                    $fp_msg = "__TOKEN__::{$resetLink}";
                }
            } else {
                // Don't reveal if email exists — same message either way
                $fp_msg = "If that email is registered, a reset link has been sent.";
            }
            $stmt->close();
        }
    }
}
$fp_msg = $fp_msg ?? '';
$fp_err = $fp_err ?? '';
$activeTab = ($regError || $regSuccess) ? 'register' : (($fp_msg || $fp_err) ? 'forgot' : 'login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heartside Vet — Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal:      #0ea5e9;
            --teal-dark: #0284c7;
            --green:     #10b981;
            --text:      #0f172a;
            --muted:     #64748b;
            --border:    #e2e8f0;
            --bg:        #f0f4f8;
            --surface:   #ffffff;
            --radius:    14px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0fdf4 50%, #fefce8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            margin: 0;
        }

        .login-card {
            background: var(--surface);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.10), 0 4px 16px rgba(0,0,0,.06);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }

        /* ── Tab row ── */
        .role-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
        }
        .role-tab {
            flex: 1; padding: 14px 10px;
            font-size: 13.5px; font-weight: 600;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            cursor: pointer; transition: all .2s;
            color: var(--muted); background: #f8fafc;
            border: none; outline: none;
        }
        .role-tab.active {
            background: var(--surface); color: var(--teal);
            border-bottom: 2px solid var(--teal);
            margin-bottom: -1px;
        }
        .role-tab i { font-size: 15px; }

        .login-body { padding: 28px 32px 24px; }

        .login-brand {
            text-align: center; margin-bottom: 22px;
        }
        .brand-icon-wrap {
            width: 54px; height: 54px;
            background: linear-gradient(135deg, var(--teal), #0369a1);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 10px;
            box-shadow: 0 6px 20px rgba(14,165,233,.3);
        }
        .brand-icon-wrap i { font-size: 24px; color: #fff; }
        .brand-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 19px; color: var(--text); letter-spacing: -.4px; }
        .brand-sub  { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* ── Panels ── */
        .panel { display: none; }
        .panel.active { display: block; }

        /* ── Form elements ── */
        .form-label-sm {
            font-size: 11.5px; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .5px;
            margin-bottom: 5px; display: block;
        }
        .input-wrap { position: relative; margin-bottom: 14px; }
        .input-icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: 14px; pointer-events: none;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap input,
        .input-wrap select {
            width: 100%; padding: 10px 40px 10px 38px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 13.5px; color: var(--text);
            outline: none; transition: border-color .15s, box-shadow .15s;
            background: #fafbfc; appearance: none;
        }
        .input-wrap input:focus,
        .input-wrap select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(14,165,233,.12);
            background: #fff;
        }
        .pw-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--muted);
            cursor: pointer; font-size: 15px; padding: 0; margin: 0;
            width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
            transition: color .15s;
        }
        .pw-toggle:hover { color: var(--teal); }

        /* Remove native browser password-reveal eye button */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        input[type="password"]::-webkit-credentials-auto-fill-button { display: none !important; }
        input[type="password"] { -webkit-appearance: none; }

        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .remember-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 18px; font-size: 13px;
        }
        .remember-row label { display: flex; align-items: center; gap: 6px; color: var(--muted); cursor: pointer; }
        .remember-row input[type=checkbox] { accent-color: var(--teal); width: 15px; height: 15px; }
        .forgot-link { color: var(--teal); text-decoration: none; font-weight: 500; }
        .forgot-link:hover { color: var(--teal-dark); }

        .btn-signin {
            width: 100%; padding: 11px;
            background: var(--teal);
            color: #fff; border: none; border-radius: 10px;
            font-size: 14px; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; transition: opacity .15s, transform .1s;
            
        }
        .btn-signin:hover { opacity: .92; }
        .btn-signin:active { transform: scale(.98); }

        .alert-login {
            background: #fef2f2; color: #b91c1c;
            border: 1px solid #fecaca; border-radius: 10px;
            padding: 10px 14px; font-size: 13px;
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 16px;
        }
        .alert-success-login {
            background: #f0fdf4; color: #15803d;
            border: 1px solid #bbf7d0; border-radius: 10px;
            padding: 10px 14px; font-size: 13px;
            display: flex; align-items: flex-start; gap: 8px;
            margin-bottom: 16px;
        }

        .login-footer {
            text-align: center; padding: 14px;
            border-top: 1px solid var(--border);
            font-size: 12px; color: #94a3b8;
        }

        /* ── Forgot password extras ── */
        .back-to-login {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12.5px; color: var(--muted); text-decoration: none;
            margin-bottom: 18px; font-weight: 500;
            cursor: pointer; border: none; background: none; padding: 0;
        }
        .back-to-login:hover { color: var(--teal); }

        .token-fallback {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
            padding: 12px 14px; margin-bottom: 14px; font-size: 12px; color: #15803d;
        }
        .token-fallback strong { display: block; margin-bottom: 6px; font-size: 13px; }
        .token-fallback a {
            word-break: break-all; color: #0369a1; font-size: 11px;
            background: #e0f2fe; border-radius: 6px; padding: 6px 8px;
            display: block; margin-top: 6px; text-decoration: none;
        }
        .token-fallback a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">

    <div class="role-tabs">
        <button class="role-tab <?= $activeTab==='login'?'active':'' ?>" id="tab-login" onclick="switchTab('login')">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
        <button class="role-tab <?= $activeTab==='register'?'active':'' ?>" id="tab-register" onclick="switchTab('register')">
            <i class="bi bi-person-plus-fill"></i> Register
        </button>
        <button class="role-tab <?= $activeTab==='forgot'?'active':'' ?>" id="tab-forgot" onclick="switchTab('forgot')" style="display:none;">
            <i class="bi bi-key-fill"></i> Reset
        </button>
    </div>

    <!-- ── SIGN IN ── -->
    <div class="panel <?= $activeTab==='login'?'active':'' ?>" id="panel-login">
        <div class="login-body">
            <div class="login-brand">
            <div class="brand-icon-wrap" style="background:transparent;box-shadow:none;"><img src="logo1.png" alt="Logo" style="width:54px;height:54px;object-fit:contain;"></div>                
            <div class="brand-name">Heartside Vet</div>
                <div class="brand-sub">Compassionate care, all in one place</div>
            </div>

            <?php if ($error): ?>
            <div class="alert-login"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="login">

                <label class="form-label-sm">Staff ID</label>
                <div class="input-wrap">
                    <i class="bi bi-person-badge input-icon"></i>
                    <input type="text" name="staff_id" placeholder="VET-00001"
                           value="<?= htmlspecialchars($_POST['staff_id'] ?? '') ?>" required>
                </div>

                <label class="form-label-sm">Password</label>
                <div class="input-wrap">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input type="password" name="password" id="pwInput" placeholder="••••••••" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pwInput','pwEye')">
                        <i class="bi bi-eye" id="pwEye"></i>
                    </button>
                </div>

                <div class="remember-row">
                    <label><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#" class="forgot-link" onclick="switchTab('forgot'); return false;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-signin">Sign in to Clinic</button>
            </form>
        </div>
    </div>

    <!-- ── REGISTER ── -->
    <div class="panel <?= $activeTab==='register'?'active':'' ?>" id="panel-register">
        <div class="login-body">
            <div class="login-brand">
                <div class="brand-icon-wrap" style="background:transparent;box-shadow:none;"> <img src="logo1.png" alt="Logo" style="width:54px;height:54px;object-fit:contain;"></div>
                <div class="brand-name">Create Staff Account</div>
                <div class="brand-sub">Register as a clinic staff member</div>
            </div>

            <?php if ($regError): ?>
            <div class="alert-login"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($regError) ?></div>
            <?php endif; ?>
            <?php if ($regSuccess): ?>
            <div class="alert-success-login"><i class="bi bi-check-circle-fill" style="margin-top:2px;flex-shrink:0;"></i><span><?= $regSuccess ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="register">

                <div class="row-2">
                    <div>
                        <label class="form-label-sm">First Name *</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="reg_first_name" placeholder="Juan"
                                   value="<?= htmlspecialchars($_POST['reg_first_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-sm">Last Name *</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="reg_last_name" placeholder="Dela Cruz"
                                   value="<?= htmlspecialchars($_POST['reg_last_name'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <label class="form-label-sm">Email *</label>
                <div class="input-wrap">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="reg_email" placeholder="juan@heartsideVet.com"
                           value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>" required>
                </div>

                <div class="row-2">
                    <div>
                        <label class="form-label-sm">Phone</label>
                        <div class="input-wrap">
                            <i class="bi bi-telephone input-icon"></i>
                            <input type="tel" name="reg_phone" id="reg_phone" placeholder="09xxxxxxxxx"
                                   maxlength="11" inputmode="numeric"
                                   pattern="[0-9]{11}" title="Phone number must be exactly 11 digits"
                                   value="<?= htmlspecialchars($_POST['reg_phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label-sm">Role *</label>
                        <div class="input-wrap">
                            <i class="bi bi-briefcase input-icon"></i>
                            <select name="reg_role">
                                <?php foreach(['Veterinarian','Admin'] as $r): ?>
                                <option value="<?= $r ?>" <?= ($_POST['reg_role']??'Veterinarian')===$r?'selected':'' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row-2">
                    <div>
                        <label class="form-label-sm">Password *</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="reg_password" id="regPw" placeholder="Min. 6 chars" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('regPw','regPwEye')">
                                <i class="bi bi-eye" id="regPwEye"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-sm">Confirm *</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" name="reg_confirm" id="regCf" placeholder="Re-enter" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('regCf','regCfEye')">
                                <i class="bi bi-eye" id="regCfEye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-signin" style="background:var(--green);">
                    <i class="bi bi-person-check-fill me-1"></i> Create Account
                </button>
            </form>
        </div>
    </div>

    <!-- ── FORGOT PASSWORD ── -->
    <div class="panel <?= $activeTab==='forgot'?'active':'' ?>" id="panel-forgot">
        <div class="login-body">
            <div class="login-brand">
                <div class="brand-icon-wrap"><i class="bi bi-key-fill"></i></div>
                <div class="brand-name">Reset Password</div>
                <div class="brand-sub">Enter your email to receive a reset link</div>
            </div>

            <?php if ($fp_err): ?>
            <div class="alert-login"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($fp_err) ?></div>
            <?php endif; ?>

            <?php if ($fp_msg && strpos($fp_msg, '__TOKEN__::') === 0):
                $resetLink = substr($fp_msg, strlen('__TOKEN__::'));
            ?>
            <div class="token-fallback">
                <strong><i class="bi bi-info-circle-fill"></i> Email not configured — use this link directly:</strong>
                Copy and open this link in your browser to reset your password (expires in 1 hour):
                <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
            </div>
            <?php elseif ($fp_msg): ?>
            <div class="alert-success-login">
                <i class="bi bi-envelope-check-fill" style="margin-top:2px;flex-shrink:0;"></i>
                <span><?= $fp_msg ?></span>
            </div>
            <?php endif; ?>

            <?php if (!$fp_msg): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="forgot">
                <label class="form-label-sm">Registered Email Address</label>
                <div class="input-wrap">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="fp_email" placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['fp_email'] ?? '') ?>" required autofocus>
                </div>
                <button type="submit" class="btn-signin" style="background:#6366f1;">
                    <i class="bi bi-send-fill me-1"></i> Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <div style="text-align:center;margin-top:16px;">
                <button class="back-to-login" onclick="switchTab('login')">
                    <i class="bi bi-arrow-left"></i> Back to Sign In
                </button>
            </div>
        </div>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> Heartside Vet &middot; Trusted by your community
    </div>
</div>

<script>
function switchTab(tab) {
    ['login','register','forgot'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('active', t === tab);
        document.getElementById('panel-' + t).classList.toggle('active', t === tab);
    });
    // Only show forgot tab button when it's active (keeps UI clean)
    document.getElementById('tab-forgot').style.display = tab === 'forgot' ? '' : 'none';
}
// On load: show forgot tab if active server-side
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($activeTab === 'forgot'): ?>
    document.getElementById('tab-forgot').style.display = '';
    <?php endif; ?>
});
function phoneDigitsOnly(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 11);
}
document.addEventListener('DOMContentLoaded', function() {
    const ph = document.getElementById('reg_phone');
    if (ph) {
        ph.addEventListener('input', phoneDigitsOnly);
        ph.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            ph.value = (ph.value + pasted).replace(/[^0-9]/g, '').slice(0, 11);
        });
    }
});
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>