<?php
// api/verify_otp.php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$user_id = trim($input['user_id'] ?? '');
$otp_code = trim($input['otp_code'] ?? '');

if (empty($user_id) || empty($otp_code)) {
    die(json_encode(["status" => "error", "message" => "User ID and OTP required"]));
}

if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
    die(json_encode(["status" => "error", "message" => "Invalid 6-digit code"]));
}

try {
    // Look for a pending unused OTP for this user
    $stmt = $pdo->prepare("SELECT id, otp_hash, otp_expires_at, attempts FROM tbl_email_verifications WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die(json_encode(["status" => "error", "message" => "No pending reset request found. Proceed back and request a new code."]));
    }

    // Check expiration
    if (strtotime($row['otp_expires_at']) < time()) {
        die(json_encode(["status" => "error", "message" => "This verification code has expired. Please request a new one."]));
    }

    // Check attempt limit to prevent brute force
    if ($row['attempts'] >= 5) {
        die(json_encode(["status" => "error", "message" => "Too many incorrect attempts. Please request a new code."]));
    }

    // Verify Hash
    if (!password_verify($otp_code, $row['otp_hash'])) {
        // Increment attempt
        $stmt = $pdo->prepare("UPDATE tbl_email_verifications SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$row['id']]);
        die(json_encode(["status" => "error", "message" => "Incorrect verification code. Please try again."]));
    }

    // Success! Mark as verified
    $stmt = $pdo->prepare("UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?");
    $stmt->execute([$row['id']]);

    echo json_encode([
        "status" => "success",
        "message" => "Verification successful. Proceeding to reset password."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
