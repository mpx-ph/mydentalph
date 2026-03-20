<?php
/**
 * One-off test: run this in the browser to see if Gmail SMTP works.
 * Example: https://yoursite.infinityfreeapp.com/test_mail.php
 * DELETE or protect this file in production (it can expose errors).
 */
require_once 'mail_config.php';

$test_to = isset($_GET['to']) ? trim($_GET['to']) : (SMTP_GMAIL_USER ?? '');
if ($test_to === '') {
    die('Add ?to=your@email.com to the URL to send a test OTP email.');
}

$otp = '123456';
$ok = send_otp_email($test_to, $otp);

header('Content-Type: text/plain; charset=UTF-8');
if ($ok) {
    echo "OK: Test email sent to {$test_to}. Check inbox (and spam). Code: {$otp}\n";
} else {
    $err = isset($GLOBALS['smtp_last_error']) ? $GLOBALS['smtp_last_error'] : 'Unknown error';
    echo "FAIL: Email was not sent.\nError: " . htmlspecialchars($err) . "\n";
}
