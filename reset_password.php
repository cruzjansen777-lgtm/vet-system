<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect already-logged-in users
if (isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

include('dbconnect.php');

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$user    = null;

// ── Validate token ──
if ($token) {
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

    $stmt = $conn->prepare("
        SELECT pr.id, pr.UserID, pr.expires_at, u.FirstName, u.StaffID
        FROM password_resets pr
        JOIN users u ON pr.UserID = u.UserID
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $valid = true;
        $user  = $res->fetch_assoc();
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
    $stmt->close();
} else {
    $error = 'No reset token provided.';
}

// ── Handle new password submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $newPass = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        // Update password
        $upd = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
        $upd->bind_param("si", $hash, $user['UserID']);
        $upd->execute();
        $upd->close();

        // Mark token as used
        $conn->query("UPDATE password_resets SET used = 1 WHERE token = '" . $conn->real_escape_string($token) . "'");

        $success = true;
        $valid   = false; // hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Heartside Vet — Reset Password</title>
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
        --surface:   #ffffff;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body {
        font-family: 'DM Sans', sans-serif;
        background: linear-gradient(135deg, #e0f2fe 0%, #f0fdf4 50%, #fefce8 100%);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        padding: 24px; margin: 0;
    }
    .card-wrap {
        background: var(--surface);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,.10), 0 4px 16px rgba(0,0,0,.06);
        width: 100%; max-width: 420px;
        overflow: hidden;
    }
    .card-header-bar {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        padding: 22px 28px 18px;
        color: #fff;
    }
    .card-header-bar .icon-wrap {
        width: 46px; height: 46px;
        background: rgba(255,255,255,.2);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 10px;
    }
    .card-header-bar .icon-wrap i { font-size: 22px; }
    .card-header-bar h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 18px; margin: 0; }
    .card-header-bar p  { font-size: 12px; opacity: .8; margin: 4px 0 0; }
    .card-body-wrap { padding: 26px 28px 24px; }

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
    .input-wrap input {
        width: 100%; padding: 10px 40px 10px 38px;
        border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 13.5px; color: var(--text);
        outline: none; transition: border-color .15s, box-shadow .15s;
        background: #fafbfc;
    }
    .input-wrap input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        background: #fff;
    }
    .pw-toggle {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: var(--muted);
        cursor: pointer; font-size: 15px; padding: 0;
        width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    }
    .pw-toggle:hover { color: #6366f1; }
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear { display: none; }
    input[type="password"] { -webkit-appearance: none; }

    /* Password strength bar */
    .strength-bar-wrap { height: 4px; background: #e2e8f0; border-radius: 2px; margin: -8px 0 14px; overflow: hidden; }
    .strength-bar { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }

    .btn-reset {
        width: 100%; padding: 11px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff; border: none; border-radius: 10px;
        font-size: 14px; font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        cursor: pointer; transition: opacity .15s, transform .1s;
        margin-top: 4px;
    }
    .btn-reset:hover { opacity: .92; }
    .btn-reset:active { transform: scale(.98); }

    .alert-err {
        background: #fef2f2; color: #b91c1c;
        border: 1px solid #fecaca; border-radius: 10px;
        padding: 10px 14px; font-size: 13px;
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 16px;
    }
    .alert-ok {
        background: #f0fdf4; color: #15803d;
        border: 1px solid #bbf7d0; border-radius: 10px;
        padding: 16px; font-size: 13px;
        text-align: center;
        margin-bottom: 16px;
    }
    .alert-ok .check-icon {
        font-size: 32px; display: block; margin-bottom: 8px;
    }
    .alert-ok strong { display: block; font-size: 15px; margin-bottom: 4px; }

    .back-link {
        display: block; text-align: center; margin-top: 16px;
        font-size: 13px; color: var(--muted); text-decoration: none;
    }
    .back-link:hover { color: var(--teal); }

    .hint-text { font-size: 11px; color: var(--muted); margin: -8px 0 14px; }

    .card-footer-bar {
        text-align: center; padding: 12px;
        border-top: 1px solid var(--border);
        font-size: 12px; color: #94a3b8;
    }
</style>
</head>
<body>

<div class="card-wrap">
    <div class="card-header-bar">
        <div class="icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
        <h1>Reset Your Password</h1>
        <p>Heartside Vet · Staff Portal</p>
    </div>

    <div class="card-body-wrap">

    <?php if ($success): ?>
        <div class="alert-ok">
            <i class="bi bi-check-circle-fill check-icon"></i>
            <strong>Password updated successfully!</strong>
            Your password has been changed. You can now sign in with your new password.
        </div>
        <a href="login.php" class="btn-reset" style="display:block;text-align:center;text-decoration:none;line-height:1.8;">
            <i class="bi bi-box-arrow-in-right me-1"></i> Go to Sign In
        </a>

    <?php elseif (!$valid): ?>
        <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i> Back to Sign In
        </a>

    <?php else: ?>
        <?php if ($error): ?>
        <div class="alert-err"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">
            Hi <strong style="color:var(--text);"><?= htmlspecialchars($user['FirstName']) ?></strong>
            (<?= htmlspecialchars($user['StaffID']) ?>), enter your new password below.
        </p>

        <form method="post" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label class="form-label-sm">New Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock-fill input-icon"></i>
                <input type="password" name="new_password" id="newPw" placeholder="Min. 6 characters"
                       required oninput="checkStrength(this.value)">
                <button type="button" class="pw-toggle" onclick="togglePw('newPw','newPwEye')">
                    <i class="bi bi-eye" id="newPwEye"></i>
                </button>
            </div>
            <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
            <div class="hint-text" id="strengthLabel">Enter a password to check strength</div>

            <label class="form-label-sm">Confirm New Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="confirm_password" id="cfPw" placeholder="Re-enter password" required>
                <button type="button" class="pw-toggle" onclick="togglePw('cfPw','cfPwEye')">
                    <i class="bi bi-eye" id="cfPwEye"></i>
                </button>
            </div>

            <button type="submit" class="btn-reset">
                <i class="bi bi-shield-check me-1"></i> Update Password
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i> Back to Sign In
        </a>
    <?php endif; ?>

    </div>
    <div class="card-footer-bar">&copy; <?= date('Y') ?> Heartside Vet &middot; Trusted by your community</div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ic  = document.getElementById(iconId);
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    ic.className = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}
function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    if (!val) { bar.style.width = '0'; label.textContent = 'Enter a password to check strength'; return; }
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { w:'20%',  bg:'#ef4444', txt:'Weak' },
        { w:'40%',  bg:'#f97316', txt:'Fair' },
        { w:'60%',  bg:'#f59e0b', txt:'Good' },
        { w:'80%',  bg:'#84cc16', txt:'Strong' },
        { w:'100%', bg:'#10b981', txt:'Very Strong' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width      = l.w;
    bar.style.background = l.bg;
    label.textContent    = l.txt;
    label.style.color    = l.bg;
}
</script>
</body>
</html>