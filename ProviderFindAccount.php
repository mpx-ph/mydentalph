<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ProviderMain.php');
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, tenant_id, email FROM tbl_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'No account found for that email.';
            } else {
                $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
                $otp_expires = date('Y-m-d H:i:s', time() + 900);
                // Clear signup onboarding state to avoid OTP mode collision.
                unset(
                    $_SESSION['onboarding_pending_id'],
                    $_SESSION['onboarding_email'],
                    $_SESSION['onboarding_plan'],
                    $_SESSION['onboarding_full_name'],
                    $_SESSION['onboarding_username'],
                    $_SESSION['onboarding_user_id'],
                    $_SESSION['onboarding_tenant_id']
                );
                $_SESSION['provider_password_reset_user_id'] = (string) $user['user_id'];
                $_SESSION['provider_password_reset_email'] = (string) $user['email'];
                $_SESSION['provider_password_reset_otp_hash'] = $otp_hash;
                $_SESSION['provider_password_reset_otp_expires_at'] = time() + 900;
                $_SESSION['provider_password_reset_verified'] = false;

                $stmt = $pdo->prepare(
                    "SELECT id FROM tbl_email_verifications WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([(string) $user['user_id']]);
                $otp_row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($otp_row) {
                    $stmt = $pdo->prepare(
                        "UPDATE tbl_email_verifications
                         SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW(), token_hash = NULL, token_expires_at = NULL
                         WHERE id = ?"
                    );
                    $stmt->execute([$otp_hash, $otp_expires, (int) $otp_row['id']]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at)
                         VALUES (?, ?, ?, ?, 0, NOW())"
                    );
                    $stmt->execute([(string) $user['tenant_id'], (string) $user['user_id'], $otp_hash, $otp_expires]);
                }

                if (send_otp_email((string) $user['email'], $otp_code)) {
                    header('Location: ResetPasswordOTP.php');
                    exit;
                }
                $error = 'Could not send verification code right now. Please try again.';
            }
        } catch (Throwable $e) {
            $error = 'A temporary error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Find Your Account - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet"/>
</head>
<body class="bg-slate-50 font-[Manrope] text-slate-900 antialiased">
<main class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl border border-slate-200 shadow-lg p-8">
        <h1 class="text-2xl font-extrabold">Find your account</h1>
        <p class="mt-2 text-sm text-slate-500">Enter your account email and we will send a 6-digit verification code.</p>

        <?php if ($error): ?>
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-5">
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700">Email Address</label>
                <input id="email" name="email" type="email" required class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
            </div>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700 transition-colors">Send Verification Code</button>
        </form>

        <div class="mt-6 text-center text-sm">
            <a href="ProviderLogin.php" class="font-semibold text-blue-600 hover:underline">Back to Login</a>
        </div>
    </div>
</main>
</body>
</html>
