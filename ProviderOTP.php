<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_signup_lib.php';
require_once 'mail_config.php';

$error = '';
$resend_message = '';

if (empty($_SESSION['onboarding_pending_id'])) {
    header('Location: ProviderCreate.php');
    exit;
}

$pending_id = (int) $_SESSION['onboarding_pending_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    if ($action === 'resend') {
        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
        $otp_expires = date('Y-m-d H:i:s', time() + 900);
        $stmt = $pdo->prepare('UPDATE tbl_provider_pending_signups SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW() WHERE id = ?');
        $stmt->execute([$otp_hash, $otp_expires, $pending_id]);
        $to_email = $_SESSION['onboarding_email'] ?? '';
        if ($to_email && send_otp_email($to_email, $otp_code)) {
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
            $stmt = $pdo->prepare('SELECT id, otp_hash, otp_expires_at FROM tbl_provider_pending_signups WHERE id = ?');
            $stmt->execute([$pending_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $proceed = false;
            if (!$row) {
                unset($_SESSION['onboarding_pending_id']);
                $error = 'No pending registration found. Please start over.';
            } elseif ($dev_otp !== null && $otp_code === $dev_otp) {
                $proceed = true;
            } elseif (strtotime($row['otp_expires_at']) < time()) {
                $error = 'This code has expired. Please request a new one.';
            } elseif (!password_verify($otp_code, $row['otp_hash'])) {
                $stmt = $pdo->prepare('UPDATE tbl_provider_pending_signups SET attempts = attempts + 1 WHERE id = ?');
                $stmt->execute([$pending_id]);
                $error = 'Invalid verification code. Please try again.';
            } else {
                $proceed = true;
            }
            if ($proceed) {
                try {
                    $ids = provider_signup_finalize_from_pending($pdo, $pending_id);
                    unset($_SESSION['onboarding_pending_id']);
                    $_SESSION['onboarding_user_id'] = $ids['user_id'];
                    $_SESSION['onboarding_tenant_id'] = $ids['tenant_id'];
                    header('Location: ProviderClinicSetup.php');
                    exit;
                } catch (Throwable $e) {
                    $error = 'Could not complete registration. Please try again or contact support.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Verify Email - MyDental.com</title>
<!-- BEGIN: Scripts and Configuration -->
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
<!-- END: Scripts and Configuration -->
<!-- BEGIN: Custom Styles -->
<style data-purpose="layout-refinements">
    /* Remove arrows/spinners from number inputs */
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
<!-- END: Custom Styles -->
</head>
<body class="bg-mydental-light min-h-screen flex items-center justify-center p-4">
<!-- BEGIN: Verification Card -->
<main class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 md:p-12" data-purpose="otp-verification-container">
<!-- BEGIN: Header Section -->
<header class="text-center mb-10">
<div class="mb-6 inline-flex items-center justify-center w-16 h-16 bg-blue-50 rounded-full">
<!-- Brand Icon Representation -->
<svg class="h-8 w-8 text-mydental-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<h1 class="text-3xl font-bold text-mydental-dark mb-3">Verify Your Email</h1>
<p class="text-gray-500 text-sm leading-relaxed">
        We've sent a 6-digit verification code to <?php echo htmlspecialchars($_SESSION['onboarding_email'] ?? 'your email'); ?>. Please enter it below to continue.
      </p>
</header>
<!-- END: Header Section -->
<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm text-center"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($resend_message): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm text-center"><?php echo htmlspecialchars($resend_message); ?></div>
<?php endif; ?>
<!-- BEGIN: OTP Form -->
<form action="" class="space-y-8" id="otp-form" method="POST">
<input type="hidden" name="action" value="verify"/>
<input type="hidden" name="otp_code" id="otp-code-hidden" value=""/>
<!-- Input Group -->
<div class="flex justify-between gap-2 md:gap-4" data-purpose="otp-inputs-group">
<input autofocus="" class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="0" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="1" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="2" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="3" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="4" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
<input class="otp-input w-12 h-14 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg focus:outline-none transition-all" data-index="5" maxlength="1" required="" type="text" inputmode="numeric" pattern="[0-9]*"/>
</div>
<!-- Action Button -->
<div class="pt-2">
<button class="w-full py-4 bg-mydental-blue hover:bg-blue-600 text-white font-semibold rounded-xl shadow-lg shadow-blue-200 transition-colors focus:ring-4 focus:ring-blue-200" type="submit">
          Verify Email
        </button>
</div>
</form>
<!-- END: OTP Form -->
<!-- BEGIN: Footer Links -->
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
<!-- END: Footer Links -->
</main>
<!-- END: Verification Card -->
<!-- BEGIN: Interactive Behavior -->
<script data-purpose="otp-input-handling">
    const inputs = document.querySelectorAll('.otp-input');
    
    inputs.forEach((input, index) => {
      // Auto-focus next input on keyup
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

      // Handle pasting
      input.addEventListener('paste', (e) => {
        const data = e.clipboardData.getData('text');
        if (data.length === 6 && /^\d+$/.test(data)) {
          inputs.forEach((input, i) => {
            input.value = data[i];
          });
          inputs[5].focus();
        }
      });
    });

    // Handle Form Submit: collect digits into hidden input so PHP receives otp_code
    document.getElementById('otp-form').addEventListener('submit', (e) => {
      let code = "";
      inputs.forEach(input => code += input.value);
      document.getElementById('otp-code-hidden').value = code;
    });
  </script>
<!-- END: Interactive Behavior -->
</body></html>