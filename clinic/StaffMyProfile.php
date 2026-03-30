<?php
$staff_nav_active = 'profile';
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>My Profile | Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10">
        <section class="max-w-5xl mx-auto space-y-8">
            <div class="text-center">
                <div class="relative mx-auto w-28 h-28 rounded-full bg-primary text-white flex items-center justify-center text-4xl font-bold shadow-lg shadow-primary/30 ring-4 ring-white">
                    MK
                    <button class="absolute -right-1 -bottom-1 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white hover:bg-primary/90 transition-all" type="button">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </button>
                </div>
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-on-background">Manager Koko</h1>
                <p class="mt-1 text-sm font-bold text-slate-500 uppercase tracking-wider">Staff ID: S-2026-00004</p>
            </div>

            <section class="elevated-card rounded-3xl p-8 sm:p-10">
                <div class="flex items-center gap-2.5 mb-7">
                    <span class="material-symbols-outlined text-primary text-lg">description</span>
                    <h2 class="text-2xl font-bold font-headline text-on-background">Personal Details</h2>
                </div>
                <form class="space-y-7">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">Username</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" value="manager"/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">Email Address</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="email" value="manager@drcgdental-baliwag.com"/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">First Name</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" value="Manager"/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">Last Name</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" value="Koko"/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-1">
                        <button class="px-6 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest transition-all" type="button">Cancel</button>
                        <button class="px-7 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-primary/20" type="submit">Save Changes</button>
                    </div>
                </form>
            </section>

            <section class="elevated-card rounded-3xl p-8 sm:p-10">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="material-symbols-outlined text-primary text-lg">shield</span>
                    <h2 class="text-2xl font-bold font-headline text-on-background">Security Settings</h2>
                </div>
                <p class="text-sm text-on-surface-variant mb-7">Update your password to keep your account secure.</p>
                <form class="space-y-6">
                    <div>
                        <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">Current Password</label>
                        <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" value="Password123"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">New Password</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" value="Password123"/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2">Confirm New Password</label>
                            <input class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" value="Password123"/>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-1">
                        <button class="inline-flex items-center gap-2 text-rose-500 hover:text-rose-600 text-xs font-black uppercase tracking-wider transition-colors" type="button">
                            <span class="material-symbols-outlined text-base">mark_email_unread</span>
                            Reset Password via Email
                        </button>
                        <button class="px-7 py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs font-black uppercase tracking-widest transition-all" type="submit">Update Password</button>
                    </div>
                </form>
            </section>
        </section>
    </div>
</main>
</body>
</html>
