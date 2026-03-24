<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

$error = '';
$resend_message = '';
$email_for_display = $_SESSION['provider_password_reset_email'] ?? 'your email';
$verify_button_text = 'Verify Code';
$title_text = 'Verify Reset Code';
$subtitle_text = "We've sent a 6-digit verification code to your email. Enter it to continue resetting your password.";

if (
    empty($_SESSION['provider_password_reset_user_id']) ||
    empty($_SESSION['provider_password_reset_email'])
) {
    header('Location: ProviderFindAccount.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    $user_id = (string) ($_SESSION['provider_password_reset_user_id'] ?? '');
    if ($action === 'resend') {
        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
        $otp_expires = date('Y-m-d H:i:s', time() + 900);
        $_SESSION['provider_password_reset_verified'] = false;
        $stmt = $pdo->prepare(
            "SELECT id FROM tbl_email_verifications WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$user_id]);
        $otp_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otp_row) {
            $stmt = $pdo->prepare(
                "UPDATE tbl_email_verifications
                 SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW(), token_hash = NULL, token_expires_at = NULL
                 WHERE id = ?"
            );
            $stmt->execute([$otp_hash, $otp_expires, (int) $otp_row['id']]);
        } else {
            $stmt = $pdo->prepare("SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $tenant_id = (string) ($stmt->fetchColumn() ?: '');
            if ($tenant_id !== '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at)
                     VALUES (?, ?, ?, ?, 0, NOW())"
                );
                $stmt->execute([$tenant_id, $user_id, $otp_hash, $otp_expires]);
            }
        }
        if (send_otp_email((string) $_SESSION['provider_password_reset_email'], $otp_code)) {
            $resend_message = 'A new code has been sent to your email.';
        } else {
            $resend_message = 'We could not send the email. Please check your address or try again later.';
        }
    } else {
        $otp_code = trim($_POST['otp_code'] ?? '');
        if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
            $error = 'Please enter a valid 6-digit code.';
        } else {
            $dev_otp = defined('DEV_OTP') ? DEV_OTP : null;
            $stmt = $pdo->prepare(
                "SELECT id, otp_hash, otp_expires_at
                 FROM tbl_email_verifications
                 WHERE user_id = ? AND verified_at IS NULL
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $error = 'No reset request found. Please start again.';
            } elseif ($dev_otp !== null && $otp_code === $dev_otp) {
                $_SESSION['provider_password_reset_verified'] = true;
                $stmt = $pdo->prepare('UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?');
                $stmt->execute([(int) $row['id']]);
                header('Location: ProviderResetPassword.php');
                exit;
            } elseif (strtotime((string) $row['otp_expires_at']) < time()) {
                $error = 'This code has expired. Please request a new one.';
            } elseif (!password_verify($otp_code, (string) $row['otp_hash'])) {
                $stmt = $pdo->prepare('UPDATE tbl_email_verifications SET attempts = attempts + 1 WHERE id = ?');
                $stmt->execute([(int) $row['id']]);
                $error = 'Invalid verification code. Please try again.';
            } else {
                $_SESSION['provider_password_reset_verified'] = true;
                $stmt = $pdo->prepare('UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?');
                $stmt->execute([(int) $row['id']]);
                header('Location: ProviderResetPassword.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Verify Reset Code - MyDental.com</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            mydental: {
              dark: '#101922',
              blue: '#2b8beb',
              light: '#f8fafc'
            }
          }
        }
      }
    }
  </script>
<style>
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type=number] {
      -moz-appearance: textfield;
    }
    .otp-input:focus {
      border-color: #2b8beb;
      box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.2);
    }
  </style>
</head>
<body class="bg-mydental-light min-h-screen flex items-center justify-center p-4">
<main class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 md:p-12">
<header class="text-center mb-10">
<div class="mb-6 inline-flex items-center justify-center w-16 h-16 bg-blue-50 rounded-full">
<svg class="h-8 w-8 text-mydental-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<h1 class="text-3xl font-bold text-mydental-dark mb-3"><?php echo htmlspecialchars($title_text); ?></h1>
<p class="text-gray-500 text-sm leading-relaxed">
        <?php echo htmlspecialchars(str_replace('your email', (string) $email_for_display, $subtitle_text)); ?>
      </p>
</header>
<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm text-center"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($resend_message): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm text-center"><?php echo htmlspecialchars($resend_message); ?></div>
<?php endif; ?>
<form action="" class="space-y-8" id="otp-form" method="POST">
<input type="hidden" name="action" value="verify"/>
<input type="hidden" name="otp_code" id="otp-code-hidden" value=""/>
<div class="flex justify-between gap-2 md:gap-4">
<input autofocus="" class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="0" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="1" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="2" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="3" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="4" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="5" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
</div>
<div class="pt-2">
<button class="w-full py-4 bg-mydental-blue hover:bg-blue-600 text-white font-semibold rounded-xl shadow-lg shadow-blue-200 transition-colors focus:ring-4 focus:ring-blue-200" type="submit">
          <?php echo htmlspecialchars($verify_button_text); ?>
        </button>
</div>
</form>
<footer class="mt-10 text-center">
<p class="text-gray-600 text-sm">
        Didn't receive the code?
        <form method="POST" class="inline" onsubmit="this.querySelector('input[name=action]').value='resend';">
          <input type="hidden" name="action" value="resend"/>
          <button type="submit" class="text-mydental-blue font-semibold hover:underline decoration-2 underline-offset-4 ml-1 bg-none border-none cursor-pointer p-0">
            Resend Code
          </button>
        </form>
</p>
<div class="mt-8 border-t border-gray-100 pt-6">
<p class="text-xs text-gray-400">
          © 2023 MyDental.com. All rights reserved.
        </p>
</div>
</footer>
</main>
<script>
    const inputs = document.querySelectorAll('.otp-input');
    inputs.forEach((input, index) => {
      input.addEventListener('keyup', (e) => {
        if (e.key >= 0 && e.key <= 9) {
          if (index < inputs.length - 1) {
            inputs[index + 1].focus();
          }
        } else if (e.key === 'Backspace') {
          if (index > 0) {
            inputs[index - 1].focus();
          }
        }
      });
      input.addEventListener('paste', (e) => {
        const data = e.clipboardData.getData('text');
        if (data.length === 6 && /^\d+$/.test(data)) {
          inputs.forEach((item, i) => {
            item.value = data[i];
          });
          inputs[5].focus();
        }
      });
    });
    document.getElementById('otp-form').addEventListener('submit', () => {
      let code = "";
      inputs.forEach(input => code += input.value);
      document.getElementById('otp-code-hidden').value = code;
    });
  </script>
</body></html>
