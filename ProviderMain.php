<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental PH - Modern Dental Clinic Management</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
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
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased">
<!-- Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main>
<!-- Hero Section -->
<section class="relative overflow-hidden py-20 lg:py-32">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:items-center">
<div class="flex flex-col gap-8">
<h1 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-6xl">
                            cute me <span class="text-primary">Efficiently</span> with MyDental
                        </h1>
<p class="text-lg leading-8 text-slate-600 dark:text-slate-400">
                            MyDental is a multi-tenant dental clinic management platform designed to streamline operations, enhance patient care, and coordinate staff across multiple locations effortlessly.
                        </p>
<div class="flex flex-wrap gap-4">
<a href="Provider-Plans.php" class="rounded-xl bg-primary px-8 py-4 text-base font-bold text-white shadow-xl shadow-primary/30 hover:bg-primary/90 hover:scale-[1.02] transition-all">
                                View Plans
                            </a>
</div>
</div>
<div class="relative">
<div class="aspect-video w-full rounded-2xl bg-gradient-to-tr from-primary/20 to-primary/5 p-2 ring-1 ring-slate-200 dark:ring-slate-800">
<div class="h-full w-full overflow-hidden rounded-xl bg-slate-200 dark:bg-slate-800" data-alt="Modern dental clinic office interior with medical equipment" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBDpSRWXg-YjyWE72N5LZpQ6cjCT5W3uvqOQ5JY03SE6CWLISOnFW4zesrQVmCx-Zb_-hL2J0K35B8n7NZfrnUUBCs27GiQ6mVUyEENiio-IXH1LTeKw8o97BoT8WBq_T0R3m_krJcpe5HEiBRbwQ6oZFQ9mH71POo36KFNMDQ_puMGRkoefTpnXs_cg55Eweu_7LvKLa-rgwaj054NQE_a1FvCiU7Hnp18jmh27j_tRhBDkxjxn5mHtyQuVyLdJ5V9k3yr0mllJrM'); background-size: cover; background-position: center;">
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Introduction -->
<section class="py-16 bg-white dark:bg-slate-900/50">
<div class="mx-auto max-w-3xl px-4 text-center">
<h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">Efficiency Redefined for Modern Practices</h2>
<p class="text-lg text-slate-600 dark:text-slate-400 leading-relaxed">
                    Empower your dental practice with cutting-edge tools. MyDental provides a comprehensive suite of features tailored for modern clinics to ensure administrative excellence and superior patient experiences.
                </p>
</div>
</section>
<!-- Platform Highlights -->
<section class="py-24">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
<div class="mb-16">
<h2 class="text-3xl font-extrabold text-slate-900 dark:text-white">Platform Highlights</h2>
<p class="mt-4 text-lg text-slate-600 dark:text-slate-400">Everything you need to run a successful multi-tenant dental practice.</p>
</div>
<div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
<!-- Feature 1 -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">calendar_month</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Appointment Scheduling</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Effortlessly manage bookings, automatic reminders, and cancellations through our intuitive drag-and-drop calendar interface.</p>
</div>
<!-- Feature 2 -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">folder_shared</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Patient Record Management</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Securely store and access complete patient medical histories, X-rays, and treatment plans from any authorized device.</p>
</div>
<!-- Feature 3 -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">group_work</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Staff Coordination</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Coordinate shifts, assign tasks, and manage internal communications across your clinical and administrative teams.</p>
</div>
<!-- Feature 4 -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">dashboard</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Centralized Dashboard</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Real-time insights into your clinic performance with key metrics, financial reports, and patient satisfaction data.</p>
</div>
<!-- Feature 5 -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">domain_add</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Multi-Tenant Architecture</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Manage multiple clinic branches seamlessly from one master account with role-based access controls for every location.</p>
</div>
<!-- Feature 6 (Generic Support) -->
<div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 hover:border-primary/50 transition-all">
<div class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all">
<span class="material-symbols-outlined">verified_user</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Compliance &amp; Security</h3>
<p class="mt-3 text-slate-600 dark:text-slate-400">Full HIPAA compliance and enterprise-grade encryption to ensure your patient data remains private and protected.</p>
</div>
</div>
</div>
</section>
<!-- How It Works -->
<section class="py-24 bg-white dark:bg-slate-900">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
<div class="text-center mb-16">
<h2 class="text-3xl font-extrabold text-slate-900 dark:text-white">How It Works</h2>
<p class="mt-4 text-lg text-slate-600 dark:text-slate-400">Get your clinic up and running in five simple steps</p>
</div>
<div class="relative grid grid-cols-1 gap-12 md:grid-cols-5">
<!-- Step 1 -->
<div class="flex flex-col items-center text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-white text-2xl font-black">1</div>
<h3 class="font-bold text-slate-900 dark:text-white">Sign Up</h3>
<p class="mt-2 text-sm text-slate-500">Create your administrative account.</p>
</div>
<!-- Step 2 -->
<div class="flex flex-col items-center text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-white text-2xl font-black">2</div>
<h3 class="font-bold text-slate-900 dark:text-white">Configure Clinics</h3>
<p class="mt-2 text-sm text-slate-500">Add your locations and branches.</p>
</div>
<!-- Step 3 -->
<div class="flex flex-col items-center text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-white text-2xl font-black">3</div>
<h3 class="font-bold text-slate-900 dark:text-white">Invite Team</h3>
<p class="mt-2 text-sm text-slate-500">Add doctors, staff, and admins.</p>
</div>
<!-- Step 4 -->
<div class="flex flex-col items-center text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-white text-2xl font-black">4</div>
<h3 class="font-bold text-slate-900 dark:text-white">Import Data</h3>
<p class="mt-2 text-sm text-slate-500">Migrate your existing patient records.</p>
</div>
<!-- Step 5 -->
<div class="flex flex-col items-center text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary text-white text-2xl font-black">5</div>
<h3 class="font-bold text-slate-900 dark:text-white">Start Operating</h3>
<p class="mt-2 text-sm text-slate-500">Go live and manage everything.</p>
</div>
</div>
</div>
</section>
<!-- CTA Section -->
<section class="py-24">
<div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
<div class="relative overflow-hidden rounded-3xl bg-primary px-8 py-16 shadow-2xl sm:px-16 text-center">
<div class="relative z-10 mx-auto max-w-2xl">
<h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Ready to transform your clinic?</h2>
<p class="mt-6 text-lg text-white/90">
                            Join over 500+ dental clinics that trust MyDental to manage their daily operations and provide better patient care.
                        </p>
<div class="mt-10 flex flex-wrap items-center justify-center gap-6">
<button class="rounded-xl bg-white px-8 py-4 text-base font-bold text-primary shadow-lg hover:bg-slate-50 transition-all">
                                Get Started
                            </button>
</div>
</div>
<!-- Abstract Background Shape -->
<div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
<div class="absolute -left-20 -bottom-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
</div>
</div>
</section>
</main>
<!-- Footer -->
<footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-background-dark py-12">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-5">
<div class="col-span-2 lg:col-span-2">
<div class="flex items-center gap-2 mb-6">
<div class="flex h-8 w-8 items-center justify-center rounded bg-primary text-white">
<span class="material-symbols-outlined text-sm">dentistry</span>
</div>
<span class="text-lg font-bold text-slate-900 dark:text-white">MyDental</span>
</div>
<p class="text-sm text-slate-500 max-w-xs">The world's most comprehensive management system for growing dental practices and multi-location clinics.</p>
</div>
<div>
<h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4">Product</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">Features</a></li>
<li><a class="hover:text-primary" href="#">Security</a></li>
<li><a class="hover:text-primary" href="#">Pricing</a></li>
<li><a class="hover:text-primary" href="#">Roadmap</a></li>
</ul>
</div>
<div>
<h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4">Company</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">About Us</a></li>
<li><a class="hover:text-primary" href="#">Careers</a></li>
<li><a class="hover:text-primary" href="#">Blog</a></li>
<li><a class="hover:text-primary" href="#">Contact</a></li>
</ul>
</div>
<div>
<h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4">Legal</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">Privacy</a></li>
<li><a class="hover:text-primary" href="#">Terms</a></li>
<li><a class="hover:text-primary" href="#">HIPAA</a></li>
</ul>
</div>
</div>
<div class="mt-12 border-t border-slate-100 dark:border-slate-800 pt-8 text-center text-sm text-slate-500">
                © 2024 MyDental Inc. All rights reserved.
            </div>
</div>
</footer>
</body></html>