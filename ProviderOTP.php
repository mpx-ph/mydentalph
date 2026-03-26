<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_signup_lib.php';
require_once 'mail_config.php';

$error = '';
$resend_message = '';
$email_for_display = $_SESSION['onboarding_email'] ?? 'your email';
$verify_button_text = 'Verify Email';
$title_text = 'Verify Your Email';
$subtitle_text = "We've sent a 6-digit verification code to your email. Please enter it below to continue.";

if (empty($_SESSION['onboarding_pending_id'])) {
    header('Location: ProviderCreate.php');
    exit;
}

$pending_id = isset($_SESSION['onboarding_pending_id']) ? (int) $_SESSION['onboarding_pending_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    if ($action === 'resend') {
        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
        $otp_expires = date('Y-m-d H:i:s', time() + 900);
        $stmt = $pdo->prepare('SELECT id FROM tbl_provider_pending_signups WHERE id = ? LIMIT 1');
        $stmt->execute([$pending_id]);
        $exists = (bool) $stmt->fetchColumn();
        if (!$exists) {
            unset($_SESSION['onboarding_pending_id']);
            $error = 'No pending registration found. Please start over.';
        } else {
            $stmt = $pdo->prepare('UPDATE tbl_provider_pending_signups SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW() WHERE id = ?');
            $stmt->execute([$otp_hash, $otp_expires, $pending_id]);
            $to_email = $_SESSION['onboarding_email'] ?? '';
            if ($to_email && send_otp_email($to_email, $otp_code)) {
                $resend_message = 'A new code has been sent to your email.';
            } else {
                $resend_message = 'We could not send the email. Please check your address or try again later.';
            }
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
            } else {
                $otp_expires_ts = strtotime((string) $row['otp_expires_at']);
                if ($otp_expires_ts === false || $otp_expires_ts < time()) {
                    $error = 'This code has expired. Please request a new one.';
                } elseif ($dev_otp !== null && $otp_code === $dev_otp) {
                    // DEV override (only allowed when code is not expired).
                    $proceed = true;
                } elseif (!password_verify($otp_code, $row['otp_hash'])) {
                    $stmt = $pdo->prepare('UPDATE tbl_provider_pending_signups SET attempts = attempts + 1 WHERE id = ?');
                    $stmt->execute([$pending_id]);
                    $error = 'Invalid verification code. Please try again.';
                } else {
                    $proceed = true;
                }
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
                    error_log('Provider signup finalize failed: ' . $e->getMessage());
                    $error = 'Could not complete registration. Please try again or contact support.';
                    $error .= ' [Debug: ' . $e->getMessage() . ']';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title><?php echo htmlspecialchars($title_text); ?> - MyDental.com</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
  <!-- Material Symbols -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#2b8beb",
            "on-surface": "#131c25",
            surface: "#ffffff",
            "surface-variant": "#f7f9ff",
            "on-surface-variant": "#404752",
            "outline-variant": "#c0c7d4",
            "surface-container-low": "#edf4ff",
            "background-light": "#f6f7f8",
            "background-dark": "#101922",

            mydental: {
              dark: "#101922",
              blue: "#2b8beb",
              light: "#f8fafc"
            }
          },
          fontFamily: {
            headline: ["Manrope", "sans-serif"],
            body: ["Manrope", "sans-serif"],
            editorial: ["Playfair Display", "serif"]
          },
          borderRadius: {
            DEFAULT: "0.25rem",
            lg: "0.5rem",
            xl: "0.75rem",
            "2xl": "1.5rem",
            "3xl": "2.5rem",
            full: "9999px"
          },
        },
      },
    }
  </script>

  <style data-purpose="layout-refinements">
    .material-symbols-outlined {
      font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
    }
    .editorial-word {
      text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
      letter-spacing: -0.02em;
    }
    .mesh-gradient {
      background-color: #ffffff;
      background-image:
        radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.05) 0px, transparent 50%),
        radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.03) 0px, transparent 50%);
    }
    .premium-card {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(43, 139, 235, 0.05);
      box-shadow: 0 40px 80px -20px rgba(43, 139, 235, 0.08);
    }

    /* Popup/reveal animation (same mechanism used on ProviderMain.php) */
    .reveal {
      opacity: 0;
      transform: translateY(34px) scale(0.985);
      filter: blur(12px);
      transition:
        opacity 900ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 900ms cubic-bezier(0.22, 1, 0.36, 1),
        filter 900ms cubic-bezier(0.22, 1, 0.36, 1);
      will-change: opacity, transform, filter;
    }
    .reveal.is-visible {
      opacity: 1;
      transform: translateY(0) scale(1);
      filter: blur(0);
    }
    @media (prefers-reduced-motion: reduce) {
      .reveal {
        opacity: 1;
        transform: none;
        filter: none;
        transition: none;
      }
    }
  </style>
</head>

<body class="bg-background-light text-on-surface font-body dark:bg-background-dark dark:text-surface antialiased overflow-hidden flex flex-col h-screen mesh-gradient">
  <!-- Navbar (consistent with ProviderMain.php via shared component) -->
  <?php include 'ProviderNavbar.php'; ?>

  <!-- Main Content Area -->
  <main class="h-[calc(100vh-64px)] flex-grow flex items-center justify-center px-4 py-6">
    <div class="w-full max-w-md">
      <div class="premium-card rounded-3xl p-6 md:p-8 reveal" data-purpose="otp-verification-container" data-reveal="section">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/5 text-primary mb-6 transition-transform hover:scale-105 duration-500">
            <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">mark_email_read</span>
          </div>

          <h1 class="font-headline text-3xl md:text-4xl font-extrabold tracking-tighter leading-[1.1] text-on-surface mb-4">
            <?php echo htmlspecialchars($title_text); ?><br/>
            <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">OTP</span>
          </h1>

          <p class="font-body text-on-surface-variant text-base font-medium leading-relaxed max-w-md mx-auto mb-6">
            <?php echo htmlspecialchars(str_replace('your email', (string) $email_for_display, $subtitle_text)); ?>
          </p>

          <div class="inline-flex items-center gap-3 px-5 py-2 rounded-full bg-primary/5 text-primary font-bold text-xs uppercase tracking-[0.2em] border border-primary/10">
            <span class="material-symbols-outlined text-sm">alternate_email</span>
            <?php echo htmlspecialchars((string) $email_for_display); ?>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm text-center font-semibold">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        <?php if ($resend_message): ?>
          <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl text-sm text-center font-semibold">
            <?php echo htmlspecialchars($resend_message); ?>
          </div>
        <?php endif; ?>

        <form action="" class="space-y-8" id="otp-form" method="POST" autocomplete="off">
          <input type="hidden" name="action" value="verify"/>
          <input type="hidden" name="otp_code" id="otp-code-hidden" value=""/>

          <div class="flex justify-between gap-2 md:gap-3" data-purpose="otp-inputs-group">
            <input autofocus class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="0" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"/>
            <input class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="1" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*"/>
            <input class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="2" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*"/>
            <input class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="3" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*"/>
            <input class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="4" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*"/>
            <input class="otp-input w-10 h-12 md:w-11 md:h-14 text-center font-headline text-2xl md:text-3xl font-extrabold bg-surface-container-low border-2 border-slate-200 focus:border-primary focus:bg-white focus:ring-0 rounded-2xl transition-all text-on-surface" data-index="5" maxlength="1" placeholder="•" required type="text" inputmode="numeric" pattern="[0-9]*"/>
          </div>

          <div class="space-y-8">
            <button class="w-full py-4 rounded-2xl bg-primary text-white font-headline font-black text-sm uppercase tracking-[0.2em] shadow-[0_20px_40px_-10px_rgba(43,139,235,0.4)] hover:scale-[1.01] active:scale-95 transition-all flex items-center justify-center gap-3" type="submit">
              <?php echo htmlspecialchars($verify_button_text); ?>
              <span class="material-symbols-outlined">arrow_right_alt</span>
            </button>

            <div class="flex flex-col items-center gap-6">
              <div class="flex items-center gap-3 text-on-surface-variant font-bold text-[11px] uppercase tracking-widest">
                <span class="material-symbols-outlined text-primary text-lg">timer</span>
                <span>OTP expires in <span class="text-primary tabular-nums" id="otp-timer">15:00</span></span>
              </div>
              <div class="h-px w-16 bg-outline-variant/30"></div>
              <div class="flex flex-wrap justify-center items-center gap-10">
                <button class="text-primary font-black text-xs uppercase tracking-[0.2em] hover:opacity-70 transition-opacity" type="button" id="resend-otp-btn">
                  Resend Code
                </button>
              </div>
            </div>
          </div>
        </form>

        <form method="POST" action="" class="hidden" id="resend-form">
          <input type="hidden" name="action" value="resend"/>
        </form>
      </div>

      <div class="mt-6 flex flex-wrap items-center justify-center gap-6 opacity-40 hover:opacity-60 transition-opacity duration-500">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">shield_lock</span>
          <span class="text-[10px] font-black tracking-[0.3em] uppercase">Secure Verification</span>
        </div>
        <div class="w-1 h-1 rounded-full bg-on-surface/20 hidden md:block"></div>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">verified_user</span>
          <span class="text-[10px] font-black tracking-[0.3em] uppercase">Privacy Ready</span>
        </div>
      </div>
    </div>
  </main>

  <div class="fixed inset-0 -z-10 pointer-events-none overflow-hidden">
    <div class="absolute -top-[5%] -left-[5%] w-[30%] h-[30%] bg-primary/3 rounded-full blur-[120px]"></div>
    <div class="absolute top-[60%] -right-[10%] w-[40%] h-[40%] bg-primary/5 rounded-full blur-[150px]"></div>
  </div>

  <script data-purpose="otp-input-handling">
    const inputs = document.querySelectorAll('.otp-input');

    function clampToDigit(el) {
      const v = (el.value || "").replace(/\D/g, "");
      el.value = v.slice(0, 1);
    }

    inputs.forEach((input, index) => {
      input.addEventListener('input', () => {
        clampToDigit(input);
        if (input.value && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
          inputs[index - 1].focus();
        }
      });

      input.addEventListener('paste', (e) => {
        const data = (e.clipboardData || window.clipboardData).getData('text');
        const cleaned = (data || "").replace(/\D/g, "");
        if (cleaned.length === 6) {
          inputs.forEach((inp, i) => {
            inp.value = cleaned[i] || "";
          });
          inputs[5].focus();
          e.preventDefault();
        }
      });
    });

    document.getElementById('otp-form').addEventListener('submit', () => {
      let code = "";
      inputs.forEach(input => code += (input.value || ""));
      document.getElementById('otp-code-hidden').value = code;
    });

    const resendBtn = document.getElementById('resend-otp-btn');
    const resendForm = document.getElementById('resend-form');
    if (resendBtn && resendForm) {
      resendBtn.addEventListener('click', () => resendForm.submit());
    }

    // Lightweight client-side timer (UI only). Server-side expiry is enforced in PHP.
    (function startOtpCountdown() {
      const el = document.getElementById('otp-timer');
      if (!el) return;
      let remaining = 15 * 60;
      const tick = () => {
        const mm = String(Math.floor(remaining / 60)).padStart(2, '0');
        const ss = String(remaining % 60).padStart(2, '0');
        el.textContent = `${mm}:${ss}`;
        if (remaining > 0) remaining -= 1;
      };
      tick();
      setInterval(tick, 1000);
    })();
  </script>

  <!-- Popup/reveal animation wiring (copied from ProviderMain.php) -->
  <script>
    (function () {
      var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var elements = document.querySelectorAll('[data-reveal="section"]');

      if (!elements || !elements.length) return;
      if (prefersReduced || !('IntersectionObserver' in window)) {
        elements.forEach(function (el) { el.classList.add('is-visible'); });
        return;
      }

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
          } else {
            entry.target.classList.remove('is-visible');
          }
        });
      }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });

      elements.forEach(function (el) { observer.observe(el); });
    })();
  </script>
</body>
</html>