<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

$errors = [];
$success = '';

$form = [
    'username' => '',
    'full_name' => '',
    'email' => '',
    'role' => 'admin',
];

function adddevs_generate_user_id(PDO $pdo): string
{
    $maxAttempts = 8;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(user_id, 6) AS UNSIGNED)), 0) FROM tbl_users WHERE user_id REGEXP '^USER_[0-9]+$'");
        $next = (int) $stmt->fetchColumn() + 1;
        $userId = 'USER_' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        $check = $pdo->prepare('SELECT user_id FROM tbl_users WHERE user_id = ? LIMIT 1');
        $check->execute([$userId]);
        if (!$check->fetchColumn()) {
            return $userId;
        }
    }

    throw new RuntimeException('Could not allocate a unique user ID.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $form['role'] = strtolower(trim((string) ($_POST['role'] ?? 'admin')));

    if ($form['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($form['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($form['role'] !== 'admin') {
        $errors[] = 'Invalid role selected.';
    }

    if (!$errors) {
        // "admin" option maps to canonical app role "superadmin".
        $finalRole = 'superadmin';
        $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));

        if ($tenantId === '') {
            $currentUserId = trim((string) ($_SESSION['user_id'] ?? ''));
            if ($currentUserId !== '') {
                $stmt = $pdo->prepare('SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1');
                $stmt->execute([$currentUserId]);
                $tenantId = trim((string) $stmt->fetchColumn());
            }
        }

        if ($tenantId === '') {
            $errors[] = 'Unable to determine tenant context for the new account.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND username = ? LIMIT 1');
                $stmt->execute([$tenantId, $form['username']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Username is already taken for this tenant.';
                }

                $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND email = ? LIMIT 1');
                $stmt->execute([$tenantId, $form['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email is already registered for this tenant.';
                }

                if (!$errors) {
                    $userId = adddevs_generate_user_id($pdo);
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_users (
                            user_id, tenant_id, username, email, full_name, password_hash, role, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $userId,
                        $tenantId,
                        $form['username'],
                        $form['email'],
                        $form['full_name'],
                        $passwordHash,
                        $finalRole,
                    ]);

                    $success = 'New account created successfully.';
                    $form = [
                        'username' => '',
                        'full_name' => '',
                        'email' => '',
                        'role' => 'admin',
                    ];
                }
            } catch (Throwable $e) {
                error_log('superadmin/adddevs create account error: ' . $e->getMessage());
                $errors[] = 'Unable to create account right now. Please try again.';
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
<title>Clinical Precision | Add Developer Account</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "on-surface": "#131c25",
                "on-surface-variant": "#404752",
                "surface-container-low": "#edf4ff",
                "surface-container-high": "#e0e9f6",
                "surface": "#f7f9ff",
                "primary": "#0066ff",
                "error": "#ba1a1a"
            },
            fontFamily: {
                "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                "body": ["Plus Jakarta Sans", "Inter", "sans-serif"]
            }
        }
    }
}
</script>
<style>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.sidebar-glass {
    background: rgba(252, 253, 255, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-right: 1px solid rgba(224, 233, 246, 0.5);
}
.editorial-shadow {
    box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.8);
}
.mesh-bg {
    background-color: #f7f9ff;
    background-image:
        radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%),
        radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%),
        radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
}
@media (max-width: 1023px) {
    #superadmin-sidebar {
        transform: translateX(-100%);
        transition: transform 220ms ease;
        z-index: 60;
    }
    body.sa-mobile-sidebar-open #superadmin-sidebar {
        transform: translateX(0);
    }
    .sa-top-header {
        left: 0;
        width: 100% !important;
        padding-left: 4.25rem;
        padding-right: 1rem;
    }
    #sa-mobile-sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(19, 28, 37, 0.45);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        z-index: 55;
        opacity: 0;
        pointer-events: none;
        transition: opacity 220ms ease;
    }
    body.sa-mobile-sidebar-open #sa-mobile-sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
    }
}
</style>
</head>
<body class="mesh-bg font-body text-on-surface selection:bg-primary/10 min-h-screen">
<?php
$superadmin_nav = 'adddevs';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
    <span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
    <div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16">
        <section class="max-w-3xl">
            <h1 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Add Developer Account</h1>
            <p class="text-on-surface-variant mt-2 font-medium">Create a new user in <code>tbl_users</code> with superadmin privileges.</p>
        </section>

        <section class="mt-8 max-w-3xl bg-white/70 backdrop-blur-xl p-5 sm:p-8 rounded-[2rem] editorial-shadow">
            <?php if ($success !== ''): ?>
                <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-error">
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="adddevs.php" class="space-y-5" method="post" novalidate>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="username">Username</label>
                    <input
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary"
                        id="username"
                        name="username"
                        required
                        type="text"
                        value="<?php echo htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8'); ?>"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="full_name">Full Name</label>
                    <input
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary"
                        id="full_name"
                        name="full_name"
                        required
                        type="text"
                        value="<?php echo htmlspecialchars($form['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="email">Email</label>
                    <input
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary"
                        id="email"
                        name="email"
                        required
                        type="email"
                        value="<?php echo htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8'); ?>"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="password">Password</label>
                    <input
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary"
                        id="password"
                        minlength="8"
                        name="password"
                        required
                        type="password"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="role">Role</label>
                    <select
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary"
                        id="role"
                        name="role"
                        required
                    >
                        <option value="admin" <?php echo $form['role'] === 'admin' ? 'selected' : ''; ?>>ADMIN</option>
                    </select>
                    <p class="mt-2 text-xs text-on-surface-variant">ADMIN maps to <code>superadmin</code> in the database.</p>
                </div>

                <div class="pt-2">
                    <button class="inline-flex w-full sm:w-auto justify-center items-center gap-2 rounded-2xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:brightness-110 transition-all" type="submit">
                        <span class="material-symbols-outlined text-lg">person_add</span>
                        Create Account
                    </button>
                </div>
            </form>
        </section>
    </div>
</main>
<script>
(function () {
    var toggleBtn = document.getElementById('sa-mobile-sidebar-toggle');
    var backdrop = document.getElementById('sa-mobile-sidebar-backdrop');
    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    if (!toggleBtn || !backdrop) return;

    function setOpen(isOpen) {
        document.body.classList.toggle('sa-mobile-sidebar-open', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleBtn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        var icon = toggleBtn.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggleBtn.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('sa-mobile-sidebar-open'));
    });
    backdrop.addEventListener('click', function () {
        setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sa-mobile-sidebar-open')) {
            setOpen(false);
        }
    });

    function closeOnDesktop() {
        if (mqDesktop.matches) setOpen(false);
    }
    if (typeof mqDesktop.addEventListener === 'function') {
        mqDesktop.addEventListener('change', closeOnDesktop);
    } else if (typeof mqDesktop.addListener === 'function') {
        mqDesktop.addListener(closeOnDesktop);
    }
})();
</script>
</body>
</html>
