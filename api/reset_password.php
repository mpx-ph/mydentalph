<?php
// api/reset_password.php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$user_id = $input['user_id'] ?? '';
$otp_code = $input['otp_code'] ?? '';
$new_password = $input['new_password'] ?? '';

if (empty($user_id) || empty($otp_code) || empty($new_password)) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

if (strlen($new_password) < 6) {
    die(json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]));
}

try {
    // Look up the most recent unverified OTP request for this user
    $stmt = $pdo->prepare(
        "SELECT id, otp_hash, otp_expires_at, attempts 
         FROM tbl_email_verifications 
         WHERE user_id = ? AND verified_at IS NULL 
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die(json_encode(["status" => "error", "message" => "No active password reset request found."]));
    }

    if ($row['attempts'] >= 5) {
        die(json_encode(["status" => "error", "message" => "Too many incorrect attempts. Please request a new code."]));
    }

    if (strtotime($row['otp_expires_at']) < time()) {
        die(json_encode(["status" => "error", "message" => "Code has expired. Please request a new one."]));
    }

    if (!password_verify($otp_code, $row['otp_hash'])) {
        // Increment attempt counter
        $stmt = $pdo->prepare("UPDATE tbl_email_verifications SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$row['id']]);
        die(json_encode(["status" => "error", "message" => "Invalid verification code."]));
    }

    // OTP IS VALID! Process the password reset.
    $pdo->beginTransaction();

    // Mark OTP as verified
    $stmt = $pdo->prepare("UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?");
    $stmt->execute([$row['id']]);

    // Update user's password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE tbl_users SET password = ? WHERE user_id = ?");
    $stmt->execute([$new_hash, $user_id]);

    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Password successfully reset."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
