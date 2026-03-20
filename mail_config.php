<?php
/**
 * Gmail SMTP config for OTP emails (InfinityFree + phpMyAdmin).
 *
 * EASIEST SETUP:
 * 1. Use a Gmail account and enable 2-Step Verification:
 *    https://myaccount.google.com/security
 * 2. Create an App Password (16-character):
 *    https://myaccount.google.com/apppasswords
 * 3. Set SMTP_GMAIL_USER and SMTP_GMAIL_APP_PASSWORD below.
 * 4. Upload this project to InfinityFree. Port 587 (TLS) usually works on free hosts.
 */

// --- Edit these (use Gmail App Password, not your normal password) ---
define('SMTP_GMAIL_USER', 'manlapazlion4@gmail.com');
define('SMTP_GMAIL_APP_PASSWORD', 'leil pqmy cdvn vthd');
define('SMTP_FROM_NAME', 'MyDental Philippines');

// Last SMTP error (set when send fails); useful for test_mail.php
$GLOBALS['smtp_last_error'] = '';

/**
 * Send OTP email via Gmail SMTP (TLS on port 587).
 * No Composer/PHPMailer needed – works on InfinityFree.
 *
 * @param string $to_email Recipient email
 * @param string $otp_code 6-digit OTP
 * @return bool True if sent, false on failure
 */
function send_otp_email($to_email, $otp_code) {
    $from_email = SMTP_GMAIL_USER;
    $subject = 'Your MyDental.com verification code';
    $body_text = "Your verification code is: {$otp_code}. It expires in 15 minutes. If you didn't request this, please ignore.";
    $body_html = '<p>Your verification code is: <strong>' . htmlspecialchars($otp_code) . '</strong>.</p><p>It expires in 15 minutes.</p><p>If you didn\'t request this, please ignore this email.</p>';
    return send_smtp_gmail($to_email, $subject, $body_text, $body_html);
}

/**
 * Send email via Gmail SMTP (smtp.gmail.com:587, STARTTLS).
 */
function send_smtp_gmail($to_email, $subject, $body_text, $body_html = null) {
    $user = SMTP_GMAIL_USER;
    // Gmail App Password: strip spaces (Google shows "xxxx xxxx xxxx xxxx")
    $pass = str_replace(' ', '', SMTP_GMAIL_APP_PASSWORD);
    $from_email = $user;
    $from_name = SMTP_FROM_NAME;

    if (empty($user) || $user === 'your-email@gmail.com' || empty($pass) || $pass === 'your-16-char-app-password') {
        error_log('mail_config.php: Set SMTP_GMAIL_USER and SMTP_GMAIL_APP_PASSWORD before sending email.');
        return false;
    }

    $host = 'smtp.gmail.com';

    // Read one full SMTP reply (handles 250-line1, 250-line2, 250 OK multi-line)
    $read_reply = function ($fp) {
        $out = '';
        while (true) {
            $line = @fgets($fp, 512);
            if ($line === false) return false;
            $out .= $line;
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') break;
        }
        return $out;
    };
    $write = function ($fp, $data) {
        return fwrite($fp, $data . "\r\n");
    };

    // Dot-stuff for SMTP DATA: any line starting with . must become ..
    $dot_stuff = function ($data) {
        return str_replace("\r\n.", "\r\n..", $data);
    };

    $fp = null;
    $last_error = '';

    // Try port 587 (STARTTLS) first, then 465 (SSL) - some hosts block 587
    foreach ([[587, 'tcp', true], [465, 'ssl', false]] as $try) {
        list($port, $scheme, $use_starttls) = $try;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$fp) {
            $last_error = "Connect {$port}: {$errstr} ({$errno})";
            continue;
        }

        $read_reply($fp);

        if ($use_starttls) {
            $write($fp, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $read_reply($fp);
            $write($fp, 'STARTTLS');
            $read_reply($fp);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $last_error = 'STARTTLS failed';
                fclose($fp);
                $fp = null;
                continue;
            }
        }

        $write($fp, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $read_reply($fp);

        $write($fp, 'AUTH LOGIN');
        $read_reply($fp);
        $write($fp, base64_encode($user));
        $read_reply($fp);
        $write($fp, base64_encode($pass));
        $auth = $read_reply($fp);
        if (strpos($auth, '235') === false) {
            $last_error = 'Auth failed (use App Password, not normal password): ' . trim(preg_replace('/\s+/', ' ', $auth));
            fclose($fp);
            $fp = null;
            continue;
        }

        break;
    }

    if (!$fp) {
        $GLOBALS['smtp_last_error'] = $last_error ?: 'Could not connect or authenticate';
        error_log('SMTP: ' . $GLOBALS['smtp_last_error']);
        return false;
    }

    $write($fp, 'MAIL FROM:<' . $from_email . '>');
    $read_reply($fp);
    $write($fp, 'RCPT TO:<' . $to_email . '>');
    $read_reply($fp);
    $write($fp, 'DATA');
    $read_reply($fp);

    $boundary = 'bound_' . bin2hex(random_bytes(8));
    $from_header = trim($from_name) !== '' ? '"' . str_replace('"', '\\"', $from_name) . '" <' . $from_email . '>' : $from_email;
    $headers = "From: {$from_header}\r\n";
    $headers .= "To: " . $to_email . "\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    if ($body_html !== null) {
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $body_text . "\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $body_html . "\r\n";
        $body .= "--{$boundary}--";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body = $body_text;
    }
    $payload = $dot_stuff($headers . $body);
    $write($fp, $payload);
    $write($fp, '.');
    $data_resp = $read_reply($fp);
    $write($fp, 'QUIT');
    fclose($fp);

    if (strpos($data_resp, '250') === false) {
        $GLOBALS['smtp_last_error'] = 'DATA rejected: ' . trim($data_resp);
        error_log('SMTP DATA failed: ' . trim($data_resp));
        return false;
    }
    $GLOBALS['smtp_last_error'] = '';
    return true;
}
