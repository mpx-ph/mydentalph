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
 * Team invite: welcome + 6-digit verification code (provider portal).
 *
 * @param string $to_email Recipient
 * @param string $recipient_name Greeting name (first name or full name)
 * @param string $otp_code Six-digit code (digits only)
 */
function send_staff_invite_verification_email(string $to_email, string $recipient_name, string $otp_code): bool
{
    $name = trim($recipient_name) !== '' ? trim($recipient_name) : 'there';
    $subject = 'Welcome to MyDental — verify your team account';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($otp_code, ENT_QUOTES, 'UTF-8');
    $body_text = "Hi {$name},\r\n\r\n"
        . "Welcome to MyDental! Your account has been initialized.\r\n"
        . "To finalize your setup, please log in to the portal and enter the verification code below when prompted:\r\n\r\n"
        . "{$otp_code}\r\n\r\n"
        . "This code expires in 15 minutes. If you did not expect this email, you can ignore it.\r\n";
    $body_html = '<p>Hi ' . $safeName . ',</p>'
        . '<p>Welcome to MyDental! Your account has been initialized.</p>'
        . '<p>To finalize your setup, please log in to the portal and enter the verification code below when prompted:</p>'
        . '<p style="font-size:1.25rem;font-weight:700;letter-spacing:0.2em;">' . $safeCode . '</p>'
        . '<p>This code expires in 15 minutes. If you did not expect this email, you can ignore it.</p>';
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

/**
 * Send subscription confirmation email with end/renewal date.
 *
 * @param string $to_email Recipient email
 * @param string $recipient_name Name of the recipient
 * @param string $plan_name Plan name (e.g., Monthly, Yearly)
 * @param string $end_date End/renewal date (e.g., June 29, 2026)
 * @param float $amount Amount paid
 * @param string $reference_number Reference number for the payment
 * @param string $billing_cycle Billing cycle (monthly/yearly)
 * @return bool True if sent, false on failure
 */
function send_subscription_confirmation_email(
    string $to_email,
    string $recipient_name,
    string $plan_name,
    string $end_date,
    float $amount,
    string $reference_number,
    string $billing_cycle
): bool {
    $name = trim($recipient_name) !== '' ? trim($recipient_name) : 'Valued Subscriber';
    $subject = 'Your MyDental Subscription is Confirmed — Active until ' . $end_date;
    
    $dashboard_url = rtrim(mydental_site_base_url(), '/') . '/ProviderTenantSubs.php';
    
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safePlanName = htmlspecialchars($plan_name, ENT_QUOTES, 'UTF-8');
    $safeEndDate = htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8');
    $safeAmount = number_format($amount, 2);
    $safeRef = htmlspecialchars($reference_number, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8');
    
    $body_text = "Hi {$name},\r\n\r\n"
        . "Thank you for subscribing to MyDental!\r\n\r\n"
        . "Your subscription to the {$plan_name} plan is now active. Your subscription will run until {$end_date}.\r\n\r\n"
        . "Payment Details:\r\n"
        . "- Plan: {$plan_name}\r\n"
        . "- Amount Paid: PHP {$safeAmount}\r\n"
        . "- Reference Number: {$reference_number}\r\n"
        . "- End/Renewal Date: {$end_date}\r\n\r\n"
        . "To avoid any service disruptions, you can pay in advance for your next billing cycle at any time by visiting your billing dashboard:\r\n"
        . "{$dashboard_url}\r\n\r\n"
        . "If you have any questions or need assistance, please feel free to reach out to our support team.\r\n\r\n"
        . "Thank you,\r\n"
        . "MyDental Philippines Team";

    // Design a beautiful HTML template matching the platform styles
    $body_html = '
    <div style="font-family: \'Manrope\', Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; color: #101922;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; border: 1px solid #cbd5e1; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <!-- Header -->
            <div style="background-color: #2b8beb; padding: 32px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">Subscription Confirmed</h1>
                <p style="color: #e0f2fe; margin: 8px 0 0 0; font-size: 14px; font-weight: 500;">Thank you for your purchase!</p>
            </div>
            
            <!-- Body Content -->
            <div style="padding: 32px;">
                <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">Hi <strong>' . $safeName . '</strong>,</p>
                <p style="font-size: 15px; line-height: 1.6; color: #404752;">Your subscription is officially active! You now have full access to MyDental Philippines premium clinic management services.</p>
                
                <!-- Plan Summary Card -->
                <div style="background-color: #f1f5f9; border-radius: 12px; padding: 20px; margin: 24px 0;">
                    <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: #404752;">Subscription Details</h3>
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="padding: 6px 0; color: #64748b; font-weight: 500;">Plan</td>
                            <td style="padding: 6px 0; font-weight: 700; text-align: right;">' . $safePlanName . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #64748b; font-weight: 500;">Amount Paid</td>
                            <td style="padding: 6px 0; font-weight: 700; text-align: right; color: #0f766e;">₱' . $safeAmount . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #64748b; font-weight: 500;">Reference Number</td>
                            <td style="padding: 6px 0; font-family: monospace; text-align: right; color: #404752;">' . $safeRef . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #e2e8f0;">
                            <td style="padding: 12px 0 6px 0; color: #64748b; font-weight: 500;">Subscription End Date</td>
                            <td style="padding: 12px 0 6px 0; font-weight: 800; text-align: right; color: #2b8beb;">' . $safeEndDate . '</td>
                        </tr>
                    </table>
                </div>

                <!-- Pay in Advance Banner -->
                <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; margin: 24px 0; text-align: center;">
                    <p style="font-size: 13px; line-height: 1.5; color: #1e3a8a; margin: 0 0 12px 0;">
                        To prevent any disruptions to your clinic services, you can pay in advance for your next billing cycle at any time.
                    </p>
                    <a href="' . $safeUrl . '" style="display: inline-block; background-color: #2b8beb; color: #ffffff; text-decoration: none; padding: 10px 20px; font-size: 13px; font-weight: 700; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Manage Subscription
                    </a>
                </div>

                <p style="font-size: 14px; line-height: 1.6; color: #404752; margin-bottom: 0;">
                    If you have any questions or need technical support, please reply to this email or contact our customer support desk.
                </p>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f8fafc; border-top: 1px solid #cbd5e1; padding: 24px; text-align: center; font-size: 12px; color: #64748b;">
                <p style="margin: 0 0 8px 0;">&copy; ' . date('Y') . ' MyDental Philippines. All rights reserved.</p>
                <p style="margin: 0;">This is an automated transaction receipt. Please do not reply directly to this message.</p>
            </div>
        </div>
    </div>
    ';
    
    return send_smtp_gmail($to_email, $subject, $body_text, $body_html);
}

