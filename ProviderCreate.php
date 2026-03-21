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

            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists.";
            } else {
                $pdo->beginTransaction();

                // 1) Create tenant first (no owner_user_id yet)
                $stmt = $pdo->query("SELECT MAX(tenant_id) FROM tbl_tenants");
                $max_tenant = $stmt->fetchColumn();
                if ($max_tenant) {
                    $num = intval(substr($max_tenant, 4)) + 1;
                } else {
                    $num = 1;
                }
                $tenant_id = 'TNT_' . str_pad($num, 5, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO tbl_tenants (tenant_id, clinic_name, country_region) VALUES (?, ?, ?)");
                $stmt->execute([$tenant_id, $clinic_name, $country_region]);

                // 2) Create tenant owner user (role = tenant_owner)
                $stmt = $pdo->query("SELECT MAX(user_id) FROM tbl_users");
                $max_user = $stmt->fetchColumn();
                if ($max_user) {
                    $unum = intval(substr($max_user, 5)) + 1;
                } else {
                    $unum = 1;
                }
                $user_id = 'USER_' . str_pad($unum, 5, '0', STR_PAD_LEFT);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $full_name = trim($_POST['full_name'] ?? '') !== '' ? trim($_POST['full_name']) : $username;
                $stmt = $pdo->prepare("INSERT INTO tbl_users (user_id, tenant_id, username, email, full_name, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'tenant_owner')");
                $stmt->execute([$user_id, $tenant_id, $username, $email, $full_name, $password_hash]);

                // 3) Set tenant's official owner
                $stmt = $pdo->prepare("UPDATE tbl_tenants SET owner_user_id = ? WHERE tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);

                // 4) Email verification OTP (6 digits, expires in 15 min)
                $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
                $otp_expires = date('Y-m-d H:i:s', time() + 900);
                $stmt = $pdo->prepare("INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$tenant_id, $user_id, $otp_hash, $otp_expires]);

                $pdo->commit();

                // Send OTP email (Gmail SMTP)
                send_otp_email($email, $otp_code);

                // Store onboarding session and redirect to OTP (do not log in yet)
                $_SESSION['onboarding_user_id'] = $user_id;
                $_SESSION['onboarding_tenant_id'] = $tenant_id;
                $_SESSION['onboarding_email'] = $email;
                $_SESSION['onboarding_plan'] = $plan;
                $_SESSION['onboarding_full_name'] = isset($full_name) ? $full_name : $username;
                $_SESSION['onboarding_username'] = $username;
                header('Location: ProviderOTP.php');
                exit;
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

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8cee",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<title>Create Clinic Admin Account - MyDental.com</title>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased">
<div class="relative flex h-auto min-h-screen w-full flex-col overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<!-- Header / Navbar -->
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-slate-800 px-6 md:px-20 py-4 bg-white dark:bg-slate-900">
<div class="flex items-center gap-3 text-primary">
<img src="MyDental%20Logo.svg" alt="MyDental Logo" class="h-10 w-auto" />
</div>
<div class="hidden md:flex items-center gap-6">
<span class="text-sm text-slate-500 dark:text-slate-400">Already have an account?</span>
<a class="text-primary font-semibold text-sm hover:underline" href="ProviderLogin.php">Log in</a>
</div>
</header>
<!-- Main Content Section -->
<main class="flex-1 flex items-center justify-center p-6 md:p-12">
<div class="w-full max-w-[540px] bg-white dark:bg-slate-900 shadow-xl rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800">
<div class="p-8 md:p-12">
<!-- Heading Section -->
<div class="mb-10">
<h1 class="text-slate-900 dark:text-slate-100 text-3xl font-extrabold leading-tight tracking-tight mb-3">Create An Account</h1>
<p class="text-slate-500 dark:text-slate-400 text-base">Enter the details below to secure your clinic management dashboard.</p>
</div>
<!-- Form -->
<?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-lg text-sm text-center">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-lg text-sm text-center">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<form method="POST" action="" class="space-y-6">
<input type="hidden" name="plan" value="<?php echo htmlspecialchars($chosen_plan); ?>"/>
<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">apartment</span>
                                    Company/Clinic Name
                                </label>
<input name="clinic_name" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="e.g. MyTheDental Clinic" type="text" required value="<?php echo isset($_POST['clinic_name']) ? htmlspecialchars($_POST['clinic_name']) : ''; ?>" />
</div>

<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">public</span>
                                    Country/Region
                                </label>
<div class="relative">
<select name="country_region" required class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none appearance-none">
<option value="" disabled selected>Select your country/region</option>
<option value="PH">Philippines</option>
</select>
<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
<span class="material-symbols-outlined text-xl">expand_more</span>
</div>
</div>
</div>

<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">person</span>
                                    Full name (owner)
                                </label>
<input name="full_name" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="e.g. Juan Dela Cruz" type="text" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" />
</div>
<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">badge</span>
                                    Username
                                </label>
<input name="username" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="e.g. admin_central" type="text" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
</div>
<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">mail</span>
                                    Email
                                </label>
<input name="email" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="e.g. admin@mydentalclinic.com" type="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">lock</span>
                                        Password
                                    </label>
<div class="relative">
<input name="password" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="••••••••" type="password" required />
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" type="button">
<span class="material-symbols-outlined text-xl">visibility</span>
</button>
</div>
</div>
<div class="space-y-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-sm">verified_user</span>
                                        Confirm Password
                                    </label>
<input name="confirm_password" class="w-full px-4 py-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="••••••••" type="password" required />
</div>
</div>
<div class="pt-4">
<button class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 px-6 rounded-lg shadow-lg shadow-primary/30 transition-all flex items-center justify-center gap-2 group" type="submit">
                                    Create an Account
                                    <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
</button>
</div>
<div class="pt-6 border-t border-slate-100 dark:border-slate-800 mt-6 text-center">
<div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700/50 rounded-lg text-left">
<p class="text-sm text-yellow-800 dark:text-yellow-400 font-medium flex items-center gap-2">
<span class="material-symbols-outlined text-base">warning</span>
                                        Warning: This account will be the 'Administrator Account' if you decided to purchase our plans.
                                    </p>
</div>
<p class="text-xs text-slate-400 dark:text-slate-500 leading-relaxed">
                                    By clicking "Create an Account", you agree to MyDental.com's 
                                    <a class="text-primary hover:underline" href="#">Terms of Service</a> and 
                                    <a class="text-primary hover:underline" href="#">Privacy Policy</a>.
                                </p>
</div>
</form>
</div>
</div>
</main>
<!-- Footer -->
<footer class="px-6 md:px-20 py-8 text-center bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
<div class="flex flex-col md:flex-row items-center justify-between gap-4 max-w-7xl mx-auto">
<p class="text-sm text-slate-500 dark:text-slate-400">© 2024 MyDental.com. All rights reserved.</p>
<div class="flex items-center gap-6">
<div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 text-sm">
<span class="material-symbols-outlined text-base">shield</span>
                            Secure encrypted portal
                        </div>
<div class="flex items-center gap-1 text-slate-500 dark:text-slate-400 text-sm">
<span class="material-symbols-outlined text-base">support_agent</span>
                            24/7 Admin Support
                        </div>
</div>
</div>
</footer>
</div>
</div>
</body></html>