<?php
// api/forgot_password.php
require_once '../db.php';
// require_once '../mail_config.php'; // Uncomment when ready to actually send emails

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$email = $input['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(["status" => "error", "message" => "Valid email required"]));
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id, tenant_id FROM tbl_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        // Return a generic success message even if email doesn't exist for security reasons (prevents enumeration)
        // But for development, returning error is easier. We will return generic success.
        die(json_encode(["status" => "success", "message" => "If the email is registered, a verification code has been sent."]));
    }

    $user_id = $userRow['user_id'];
    $tenant_id = $userRow['tenant_id'];

    // Generate 6-digit OTP
    $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
    $otp_expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes

    // Store in tbl_email_verifications
    $stmt = $pdo->prepare("INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$tenant_id, $user_id, $otp_hash, $otp_expires]);

    // TODO: Actually send the email here using your mail_config logic.
    // send_otp_email($email, $otp_code);

    echo json_encode([
        "status" => "success",
        "message" => "A verification code has been sent to your email.",
        "test_otp" => $otp_code, // REMOVE THIS IN PRODUCTION! (For easy testing only)
        "user_id" => $user_id
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
