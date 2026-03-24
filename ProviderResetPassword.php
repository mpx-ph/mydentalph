<?php
session_start();
require_once __DIR__ . '/db.php';

if (
    empty($_SESSION['provider_password_reset_user_id']) ||
    empty($_SESSION['provider_password_reset_email']) ||
    empty($_SESSION['provider_password_reset_verified'])
) {
    header('Location: ProviderFindAccount.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE tbl_users SET password_hash = ? WHERE user_id = ? LIMIT 1");
            $stmt->execute([
                $password_hash,
                (string) $_SESSION['provider_password_reset_user_id']
            ]);

            unset($_SESSION['provider_password_reset_user_id']);
            unset($_SESSION['provider_password_reset_email']);
            unset($_SESSION['provider_password_reset_otp_hash']);
            unset($_SESSION['provider_password_reset_otp_expires_at']);
            unset($_SESSION['provider_password_reset_verified']);

            header('Location: ProviderMain.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Could not update password right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Create New Password - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet"/>
</head>
<body class="bg-slate-50 font-[Manrope] text-slate-900 antialiased">
<main class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-xl border border-slate-200 shadow-lg p-8">
        <h1 class="text-2xl font-extrabold">Create New Password</h1>
        <p class="mt-2 text-sm text-slate-500">Set a new password for <?php echo htmlspecialchars((string) $_SESSION['provider_password_reset_email']); ?>.</p>

        <?php if ($error): ?>
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-5">
            <div>
                <label for="new_password" class="block text-sm font-semibold text-slate-700">New Password</label>
                <input id="new_password" name="new_password" type="password" required minlength="8" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter new password"/>
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-semibold text-slate-700">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required minlength="8" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Confirm new password"/>
            </div>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700 transition-colors">Update Password</button>
        </form>
    </div>
</main>
</body>
</html>
