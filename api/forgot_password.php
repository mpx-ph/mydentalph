<?php
// api/forgot_password.php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(["status" => "error", "message" => "Please enter a valid email address"]));
}

try {
    // Check if user exists in tbl_users
    $stmt = $pdo->prepare("SELECT user_id, tenant_id FROM tbl_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        die(json_encode(["status" => "error", "message" => "No account found with that email address."]));
    }

    $user_id  = (string) $userRow['user_id'];
    $tenant_id = (string) $userRow['tenant_id'];

    // Generate 6-digit OTP (zero-padded)
    $otp_code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash   = password_hash($otp_code, PASSWORD_DEFAULT);
    $otp_expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes from now

    // Invalidate any old pending OTP requests for this user
    $stmt = $pdo->prepare("UPDATE tbl_email_verifications SET verified_at = NOW() WHERE user_id = ? AND verified_at IS NULL");
    $stmt->execute([$user_id]);

    // Insert new OTP record
    $stmt = $pdo->prepare(
        "INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([$tenant_id, $user_id, $otp_hash, $otp_expires]);

    // ✅ Return OTP in response for testing (REMOVE test_otp in production!)
    echo json_encode([
        "status"   => "success",
        "message"  => "Verification code ready. Check the DEV toast for the code.",
        "user_id"  => $user_id,
        "test_otp" => $otp_code
    ]);

} catch (Exception $e) {
    // Return the real database error message for debugging
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
