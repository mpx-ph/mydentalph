<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';

// Already logged in -> redirect to home or requested redirect
// user_id can be 0 for hardcoded superadmin; empty() treats 0 as empty
if (isset($_SESSION['user_id'])) {
    $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
    if ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)) {
        $is_superadmin_path = (strpos($redirect, 'superadmin/') === 0);
        if ($is_superadmin_path && ($_SESSION['role'] ?? '') !== 'superadmin') {
            header('Location: ProviderMain.php');
        } else {
            header('Location: ' . $redirect);
        }
    } else {
        header('Location: ProviderMain.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier) || empty($password)) {
        $error = 'Please enter your email/username and password.';
    } else {
        // Hardcoded superadmin (temporary)
        if ($login_identifier === 'super' && $password === 'admin') {
            $_SESSION['user_id'] = 0;
            $_SESSION['tenant_id'] = null;
            $_SESSION['username'] = 'super';
            $_SESSION['email'] = 'super';
            $_SESSION['full_name'] = 'Super Admin';
            $_SESSION['role'] = 'superadmin';
            $_SESSION['is_owner'] = false;

            header('Location: ProviderSuperAdmin.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT user_id, tenant_id, username, email, full_name, role, password_hash FROM tbl_users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$login_identifier, $login_identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Load tenant and set owner status (owner_user_id)
                $is_owner = false;
                if (!empty($user['tenant_id'])) {
                    $stmt = $pdo->prepare("SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
                    $stmt->execute([$user['tenant_id']]);
                    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                    $is_owner = ($tenant && isset($tenant['owner_user_id']) && $tenant['owner_user_id'] === $user['user_id']);
                }
                $_SESSION['is_owner'] = $is_owner;

                $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
                if ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ProviderMain.php');
                }
                exit;
            }

            $error = 'Invalid email/username or password.';
        } catch (PDOException $e) {
            $error = 'A temporary error occurred. Please try again.';
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
                        "display": ["Manrope"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<title>Login - MyDental</title>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<!-- Navigation Header -->
<header class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 px-6 md:px-10 py-4 bg-white dark:bg-background-dark/50 backdrop-blur-md sticky top-0 z-50">
<div class="flex items-center gap-3">
<img src="MyDental%20Logo.svg" alt="MyDental Logo" class="h-10 w-auto" />
</div>
<div class="flex items-center gap-4">
<a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary transition-colors" href="#">Support</a>
<button class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold transition-all hover:bg-primary/90 shadow-sm">
<span>Help Center</span>
</button>
</div>
</header>
<!-- Main Content Section -->
<main class="flex-1 flex items-center justify-center p-6 md:p-10">
<div class="w-full max-w-[440px] flex flex-col gap-8 bg-white dark:bg-slate-900 p-8 md:p-10 rounded-xl shadow-xl shadow-primary/5 border border-slate-100 dark:border-slate-800">
<!-- Header Text -->
<div class="flex flex-col gap-2">
<h1 class="text-slate-900 dark:text-slate-100 text-3xl font-extrabold leading-tight tracking-tight">Login to MyDental</h1>
<p class="text-slate-500 dark:text-slate-400 text-base">Access your clinic dashboard.</p>
</div>
<?php if ($error): ?>
<div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-300">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>
<!-- Login Form -->
<form class="flex flex-col gap-5" method="post" action="">
<!-- Email Field -->
<div class="flex flex-col gap-2">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold leading-normal" for="login_identifier">Email / Username</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-xl">alternate_email</span>
</div>
<input id="login_identifier" name="login_identifier" value="<?php echo isset($_POST['login_identifier']) ? htmlspecialchars($_POST['login_identifier']) : ''; ?>" class="flex w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 h-12 pl-11 pr-4 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Enter your email or username" type="text" autocomplete="username"/>
</div>
</div>
<!-- Password Field -->
<div class="flex flex-col gap-2">
<div class="flex justify-between items-center">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold leading-normal" for="password">Password</label>
<a class="text-xs font-semibold text-primary hover:underline" href="#">Forgot password?</a>
</div>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-xl">lock</span>
</div>
<input id="password" name="password" class="flex w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 h-12 pl-11 pr-12 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Enter your password" type="password" autocomplete="current-password"/>
<button class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" type="button" onclick="var p=document.getElementById('password'); p.type=p.type==='password'?'text':'password';">
<span class="material-symbols-outlined text-xl">visibility</span>
</button>
</div>
</div>
<!-- Login Button -->
<div class="pt-2">
<button class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 px-5 bg-primary text-white text-base font-bold transition-all hover:bg-primary/90 shadow-md shadow-primary/20 hover:shadow-lg hover:shadow-primary/30 active:scale-[0.98]" type="submit">
<span class="material-symbols-outlined mr-2">login</span>
<span>Login</span>
</button>
</div>
</form>
<!-- Footer Info -->
<div class="border-t border-slate-100 dark:border-slate-800 pt-6 text-center">
<p class="text-sm text-slate-500 dark:text-slate-400">
                            Don't have an account? <a class="text-primary font-bold hover:underline" href="ProviderCreate.php">Create account</a>
</p>
</div>
</div>
</main>
<!-- Page Footer -->
<footer class="p-6 text-center">
<p class="text-xs text-slate-400 dark:text-slate-500">
                    © 2024 MyDental Health Systems. All rights reserved. 
                    <span class="mx-2">|</span>
<a class="hover:text-primary" href="#">Privacy Policy</a>
<span class="mx-2">|</span>
<a class="hover:text-primary" href="#">Terms of Service</a>
</p>
</footer>
</div>
</div>
<!-- Security Badge (Floating) -->
<div class="fixed bottom-6 right-6 hidden md:flex items-center gap-2 bg-white dark:bg-slate-800 px-3 py-2 rounded-full shadow-lg border border-slate-100 dark:border-slate-700">
<span class="material-symbols-outlined text-green-500 text-lg">verified_user</span>
<span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Secure AES-256 Encryption</span>
</div>
</body></html>