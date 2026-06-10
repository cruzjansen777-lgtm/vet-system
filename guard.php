<?php
// ── Route Guard ──
// Include at the very top of every protected module (before any output).
// Requires a valid login session — redirects to login.php if missing.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
