<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';
require_once 'mail_config.php';

$error = '';
$success = '';
$chosen_plan = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : '';
$allowed_plans = ['starter', 'professional', 'enterprise'];
if (!in_array($chosen_plan, $allowed_plans, true)) {
    $chosen_plan = 'professional';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $country_region = trim($_POST['country_region'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $plan = isset($_POST['plan']) ? strtolower(trim($_POST['plan'])) : $chosen_plan;
    if (!in_array($plan, $allowed_plans, true)) {
        $plan = 'professional';
    }

    if (empty($clinic_name) || empty($country_region) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {

            // Confirmed accounts only; pending OTP lives in tbl_provider_pending_signups (no tenant/user yet).
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username or email already exists.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM tbl_provider_pending_signups WHERE username = ? AND email != ?');
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'Username is already reserved by another pending registration.';
                } else {
                    try {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $full_name = trim($_POST['full_name'] ?? '') !== '' ? trim($_POST['full_name']) : $username;
                        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
                        $otp_expires = date('Y-m-d H:i:s', time() + 900);

                        $stmt = $pdo->prepare('SELECT id FROM tbl_provider_pending_signups WHERE email = ?');
                        $stmt->execute([$email]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            $stmt = $pdo->prepare('UPDATE tbl_provider_pending_signups SET clinic_name = ?, country_region = ?, full_name = ?, username = ?, password_hash = ?, plan = ?, otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW() WHERE id = ?');
                            $stmt->execute([$clinic_name, $country_region, $full_name, $username, $password_hash, $plan, $otp_hash, $otp_expires, $existing['id']]);
                            $pending_id = (int) $existing['id'];
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO tbl_provider_pending_signups (clinic_name, country_region, full_name, username, email, password_hash, plan, otp_hash, otp_expires_at, attempts, last_sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
                            $stmt->execute([$clinic_name, $country_region, $full_name, $username, $email, $password_hash, $plan, $otp_hash, $otp_expires]);
                            $pending_id = (int) $pdo->lastInsertId();
                        }

                        send_otp_email($email, $otp_code);

                        $_SESSION['onboarding_pending_id'] = $pending_id;
                        $_SESSION['onboarding_email'] = $email;
                        $_SESSION['onboarding_plan'] = $plan;
                        $_SESSION['onboarding_full_name'] = $full_name;
                        $_SESSION['onboarding_username'] = $username;
                        unset($_SESSION['onboarding_user_id'], $_SESSION['onboarding_tenant_id']);
                        // Prevent OTP page from accidentally entering reset flow.
                        unset(
                            $_SESSION['provider_password_reset_user_id'],
                            $_SESSION['provider_password_reset_email'],
                            $_SESSION['provider_password_reset_otp_hash'],
                            $_SESSION['provider_password_reset_otp_expires_at'],
                            $_SESSION['provider_password_reset_verified']
                        );
                        header('Location: ProviderOTP.php');
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Create Account - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "on-surface": "#131c25",
                        "surface": "#ffffff",
                        "surface-variant": "#f7f9ff",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "primary-fixed": "#d4e3ff",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-low": "#edf4ff",
                        "inverse-surface": "#131c25",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1.5px solid rgba(43, 139, 235, 0.3);
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface mesh-gradient min-h-screen flex flex-col selection:bg-primary/20 antialiased">
<?php include 'ProviderNavbar.php'; ?>

<main class="flex-grow flex items-center justify-center pt-16 pb-24 px-6">
    <div class="w-full max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">
                Clinic Onboarding
            </div>
            <h1 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter text-on-surface mb-6 leading-[1.1]">
                Create An <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Account</span>
            </h1>
            <p class="font-headline text-on-surface-variant text-lg font-medium max-w-md mx-auto">
                Secure your clinic management dashboard with architectural precision.
            </p>
        </div>

        <!-- Registration Card -->
        <div class="glass-card rounded-3xl p-8 md:p-12 shadow-[0_50px_100px_-20px_rgba(43,139,235,0.15)]">
            <?php if ($error): ?>
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-8">
                <input type="hidden" name="plan" value="<?php echo htmlspecialchars($chosen_plan); ?>"/>

                <!-- Clinic Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="clinic_name">Company/Clinic Name</label>
                        <input
                            id="clinic_name"
                            name="clinic_name"
                            class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                            placeholder="e.g. North Dental"
                            type="text"
                            required
                            value="<?php echo isset($_POST['clinic_name']) ? htmlspecialchars($_POST['clinic_name']) : ''; ?>"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="country_region">Country/Region</label>
                        <div class="relative">
                            <?php $posted_country = isset($_POST['country_region']) ? (string) $_POST['country_region'] : ''; ?>
                            <select
                                id="country_region"
                                name="country_region"
                                required
                                class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all appearance-none outline-none font-headline font-semibold text-sm"
                            >
                                <option value="" disabled <?php echo $posted_country === '' ? 'selected' : ''; ?>>Select Region</option>
                                <option value="PH" <?php echo $posted_country === 'PH' ? 'selected' : ''; ?>>Philippines</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-primary/40 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="full_name">Full Name</label>
                        <input
                            id="full_name"
                            name="full_name"
                            class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                            placeholder="John Doe"
                            type="text"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="username">Username</label>
                        <input
                            id="username"
                            name="username"
                            class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                            placeholder="johndoe_dental"
                            type="text"
                            required
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                        placeholder="owner@clinic.com"
                        type="email"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    />
                </div>

                <!-- Security -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="password">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                                placeholder="••••••••"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition-colors" type="button" onclick="togglePassword('password', this)">
                                <span class="material-symbols-outlined text-lg">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black uppercase tracking-widest text-on-surface/60 font-headline ml-1" for="confirm_password">Confirm Password</label>
                        <div class="relative">
                            <input
                                id="confirm_password"
                                name="confirm_password"
                                class="w-full px-5 py-4 bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-1 focus:ring-primary rounded-2xl transition-all placeholder:text-outline-variant outline-none font-headline font-semibold text-sm"
                                placeholder="••••••••"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition-colors" type="button" onclick="togglePassword('confirm_password', this)">
                                <span class="material-symbols-outlined text-lg">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="pt-2 space-y-6 text-center">
                    <button class="w-full py-5 px-8 bg-primary text-white font-black text-sm uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-primary/20 hover:shadow-primary/40 hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3 font-headline" type="submit">
                        Sign Up
                        <span class="material-symbols-outlined text-xl">arrow_forward</span>
                    </button>

                    <div class="rounded-2xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-left">
                        <p class="text-sm text-yellow-800 font-semibold flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">warning</span>
                            This account will be the Administrator Account if you purchase a plan.
                        </p>
                    </div>

                    <p class="font-headline text-sm font-semibold text-on-surface-variant">
                        Already have an account?
                        <a class="text-primary hover:underline ml-1" href="ProviderLogin.php">Log in</a>
                    </p>

                    <p class="text-xs text-on-surface-variant/80 font-medium leading-relaxed">
                        By clicking "Sign Up", you agree to MyDental's
                        <a class="text-primary hover:underline" href="#">Terms of Service</a> and
                        <a class="text-primary hover:underline" href="#">Privacy Policy</a>.
                    </p>
                </div>
            </form>
        </div>

        <!-- Trust Badges -->
        <div class="mt-16 flex flex-wrap justify-center items-center gap-10 opacity-30 grayscale hover:opacity-100 hover:grayscale-0 transition-all duration-500">
            <div class="flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-xl">verified_user</span>
                <span class="text-[10px] font-black uppercase tracking-[0.2em] font-headline">HIPAA COMPLIANT</span>
            </div>
            <div class="flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-xl">lock</span>
                <span class="text-[10px] font-black uppercase tracking-[0.2em] font-headline">AES-256 ENCRYPTION</span>
            </div>
            <div class="flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-xl">cloud_done</span>
                <span class="text-[10px] font-black uppercase tracking-[0.2em] font-headline">MYDENTAL CLOUD</span>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="w-full border-t border-slate-200 bg-slate-50 mt-auto">
    <div class="flex flex-col md:flex-row justify-between items-center py-12 px-8 max-w-screen-2xl mx-auto gap-4">
        <div class="text-lg font-bold text-slate-900 font-headline">MyDental</div>
        <div class="flex flex-wrap justify-center gap-8 text-xs font-headline font-semibold text-slate-500">
            <a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
            <a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
            <a class="hover:text-primary transition-colors" href="#">Contact Sales</a>
        </div>
        <div class="text-xs text-slate-500 font-headline opacity-80 font-medium">
            © 2024 MyDental. All rights reserved.
        </div>
    </div>
</footer>

<script>
    function togglePassword(inputId, btn) {
        var input = document.getElementById(inputId);
        if (!input) return;
        input.type = (input.type === 'password') ? 'text' : 'password';
        var icon = btn && btn.querySelector ? btn.querySelector('.material-symbols-outlined') : null;
        if (icon) icon.textContent = (input.type === 'password') ? 'visibility' : 'visibility_off';
    }
</script>
</body>
</html>