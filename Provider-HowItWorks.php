<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Features - MyDental Practice Management</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<!-- Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-1">
<!-- Hero Section -->
<section class="px-6 lg:px-40 py-16 lg:py-24 bg-gradient-to-b from-primary/5 to-transparent">
<div class="mx-auto max-w-[1200px] text-center">
<span class="inline-block px-4 py-1.5 mb-6 text-xs font-bold tracking-widest uppercase rounded-full bg-primary/10 text-primary">Platform Features</span>
<h1 class="text-4xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-slate-50 mb-6">
                        The Operating System for <br/><span class="text-primary">Modern Dentistry</span>
</h1>
<p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto mb-10">
                        Everything you need to run a high-performance dental clinic, from clinical charting to automated patient marketing.
                    </p>
<div class="flex flex-col sm:flex-row justify-center gap-4">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-lg shadow-xl shadow-primary/20">Explore All Features</button>
<button class="px-8 py-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-xl font-bold text-lg">Watch Demo</button>
</div>
</div>
</section>
<!-- Clinic Management -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="grid lg:grid-cols-2 gap-16 items-center">
<div class="relative aspect-video rounded-2xl overflow-hidden shadow-2xl bg-slate-200" data-alt="Modern dental clinic office interior setup">
<img alt="Clinic Management" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC9wBgIIbMnO6L_S_y67nZoE5sxC3XrP_wlxvt6y63wj1LjuFdJ-uo06OVBRv7JBXaDHr94C4ymCY6RHMW6t2eCkaJmGkxlwwKmH9JHBFWj4armrFkSMpO9T9ytBREuMmV9hQbs-2vRtHCEaJ5fiRlxXAncr4TcMelpbWv1_VS0pqH2io36vn-a9ec3tVGEiEFjxqvmre-gcF7AUyB-kuGGT6Fk9KVhXYOP4O0uGBMBypFP7iUg-jPI5_P0dNOAzsfREBNN6vEvTSI"/>
<div class="absolute inset-0 bg-primary/10"></div>
</div>
<div>
<div class="flex items-center gap-3 text-primary mb-4">
<span class="material-symbols-outlined">domain</span>
<span class="font-bold tracking-wider uppercase text-sm">Operations</span>
</div>
<h2 class="text-3xl font-bold mb-6 text-slate-900 dark:text-slate-50">Clinic Management</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Centralize your practice operations with a robust infrastructure designed for scale and efficiency.</p>
<div class="grid sm:grid-cols-2 gap-6">
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">inventory_2</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Inventory Tracking</h4>
<p class="text-sm text-slate-500">Automated stock alerts and supply management.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">account_balance_wallet</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Billing &amp; Invoicing</h4>
<p class="text-sm text-slate-500">Integrated payment processing and insurance claims.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">description</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Document Vault</h4>
<p class="text-sm text-slate-500">Secure storage for HIPAA-compliant documentation.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">settings_suggest</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Workflow Automation</h4>
<p class="text-sm text-slate-500">Custom triggers for recurring administrative tasks.</p>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Appointment Management -->
<section class="px-6 lg:px-40 py-20 bg-slate-100 dark:bg-slate-900/50">
<div class="mx-auto max-w-[1200px]">
<div class="text-center max-w-3xl mx-auto mb-16">
<h2 class="text-3xl font-bold mb-4">Appointment Management</h2>
<p class="text-slate-600 dark:text-slate-400">Maximize your chair utilization with smart scheduling tools and automated patient communications.</p>
</div>
<div class="grid md:grid-cols-3 gap-8">
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">calendar_month</span>
</div>
<h3 class="text-xl font-bold mb-3">Online Booking</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Patient-facing scheduling portal that syncs in real-time with your clinic's availability.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Website Integration</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Treatment-specific Slots</li>
</ul>
</div>
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">notifications_active</span>
</div>
<h3 class="text-xl font-bold mb-3">Smart Reminders</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Reduce no-shows by 40% with automated SMS and email confirmations and reminders.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> 2-Way SMS Chat</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Custom Templates</li>
</ul>
</div>
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">dynamic_feed</span>
</div>
<h3 class="text-xl font-bold mb-3">Waitlist Management</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Instantly fill last-minute cancellations with automated waitlist notifications.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Priority Ranking</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Auto-filling slots</li>
</ul>
</div>
</div>
</div>
</section>
<!-- Patient Management -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="grid lg:grid-cols-2 gap-16 items-center">
<div class="order-2 lg:order-1">
<h2 class="text-3xl font-bold mb-6">Patient Management</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Build stronger relationships with a 360-degree view of every patient’s clinical and financial history.</p>
<div class="space-y-4">
<div class="p-4 bg-primary/5 rounded-xl border-l-4 border-primary">
<h4 class="font-bold mb-1">Interactive Clinical Charting</h4>
<p class="text-sm text-slate-500">Visual 3D tooth charting with treatment phase tracking.</p>
</div>
<div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
<h4 class="font-bold mb-1">Digital Patient Onboarding</h4>
<p class="text-sm text-slate-500">Contactless intake forms and medical history updates via mobile.</p>
</div>
<div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
<h4 class="font-bold mb-1">Patient Loyalty Portal</h4>
<p class="text-sm text-slate-500">Patients can view X-rays, treatment plans, and pay bills online.</p>
</div>
</div>
</div>
<div class="order-1 lg:order-2 bg-slate-100 rounded-2xl p-8 flex items-center justify-center min-h-[400px]" data-alt="Screenshot of a patient digital health record and charting interface">
<div class="w-full h-64 bg-white dark:bg-slate-800 rounded-lg shadow-xl flex flex-col p-4">
<div class="flex items-center gap-3 border-b pb-3 mb-3">
<div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold">JD</div>
<div>
<div class="font-bold text-sm">John Doe</div>
<div class="text-xs text-slate-400">Patient ID: #8829</div>
</div>
</div>
<div class="grid grid-cols-2 gap-4 flex-1">
<div class="bg-slate-50 dark:bg-slate-900 rounded p-2">
<div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Last Visit</div>
<div class="text-xs font-bold">Oct 12, 2023</div>
</div>
<div class="bg-slate-50 dark:bg-slate-900 rounded p-2">
<div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Treatment Plan</div>
<div class="text-xs font-bold text-primary">In Progress</div>
</div>
</div>
<div class="mt-4 flex gap-2">
<div class="flex-1 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden"><div class="w-2/3 h-full bg-primary"></div></div>
<span class="text-[10px] font-bold">66% Complete</span>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Staff and Dentist Management -->
<section class="px-6 lg:px-40 py-20 bg-background-dark text-slate-100">
<div class="mx-auto max-w-[1200px]">
<div class="flex flex-col lg:flex-row gap-12 items-end mb-16">
<div class="flex-1">
<h2 class="text-3xl font-bold mb-4">Staff &amp; Provider Management</h2>
<p class="text-slate-400">Manage schedules, commissions, and access rights for your entire clinical team from one dashboard.</p>
</div>
<div class="flex gap-4">
<div class="text-center px-6 py-4 bg-slate-800 rounded-xl">
<div class="text-2xl font-bold text-primary">99.9%</div>
<div class="text-xs uppercase tracking-widest text-slate-500">Uptime</div>
</div>
<div class="text-center px-6 py-4 bg-slate-800 rounded-xl">
<div class="text-2xl font-bold text-primary">HIPAA</div>
<div class="text-xs uppercase tracking-widest text-slate-500">Compliant</div>
</div>
</div>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">badge</span>
<h4 class="font-bold mb-2">Provider Credentialing</h4>
<p class="text-sm text-slate-400">Store and track licenses, certifications, and malpractice insurance.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">monitoring</span>
<h4 class="font-bold mb-2">Commission Tracking</h4>
<p class="text-sm text-slate-400">Automatically calculate dentist and hygienist pay based on production or collections.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">lock_person</span>
<h4 class="font-bold mb-2">Granular Permissions</h4>
<p class="text-sm text-slate-400">Control feature access per role to ensure data security and compliance.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">history</span>
<h4 class="font-bold mb-2">Audit Logs</h4>
<p class="text-sm text-slate-400">Detailed logs of every action taken within the system for accountability.</p>
</div>
</div>
</div>
</section>
<!-- Reporting and Monitoring -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="flex flex-col lg:flex-row gap-16">
<div class="w-full lg:w-1/3">
<h2 class="text-3xl font-bold mb-6">Reporting &amp; Analytics</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Data-driven insights to help you optimize clinic performance and patient outcomes.</p>
<ul class="space-y-6">
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">trending_up</span></div>
<p class="text-sm"><span class="font-bold block">Production Reports</span> Track daily, monthly, and yearly production vs. goals.</p>
</li>
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">pie_chart</span></div>
<p class="text-sm"><span class="font-bold block">Collection Analysis</span> Visualize aging accounts and collection efficiency ratios.</p>
</li>
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">groups</span></div>
<p class="text-sm"><span class="font-bold block">Referral Tracking</span> Measure the ROI of your marketing campaigns and referral sources.</p>
</li>
</ul>
</div>
<div class="flex-1 bg-slate-50 dark:bg-slate-800 p-8 rounded-3xl" data-alt="Colorful business analytics dashboard showing clinic performance charts">
<div class="h-full w-full min-h-[300px] bg-white dark:bg-slate-900 rounded-2xl shadow-inner p-6 flex flex-col gap-6">
<div class="flex justify-between items-center">
<h4 class="font-bold">Monthly Revenue Overview</h4>
<div class="flex gap-2">
<div class="w-24 h-8 bg-slate-100 dark:bg-slate-800 rounded"></div>
<div class="w-24 h-8 bg-slate-100 dark:bg-slate-800 rounded"></div>
</div>
</div>
<div class="flex-1 flex items-end gap-3 pb-4">
<div class="flex-1 bg-primary/20 rounded-t h-[40%]"></div>
<div class="flex-1 bg-primary/40 rounded-t h-[60%]"></div>
<div class="flex-1 bg-primary/30 rounded-t h-[45%]"></div>
<div class="flex-1 bg-primary/60 rounded-t h-[80%]"></div>
<div class="flex-1 bg-primary/80 rounded-t h-[70%]"></div>
<div class="flex-1 bg-primary rounded-t h-[95%]"></div>
</div>
<div class="grid grid-cols-3 gap-4 border-t pt-4">
<div class="text-center"><div class="text-xs text-slate-400">Production</div><div class="font-bold">$124,500</div></div>
<div class="text-center"><div class="text-xs text-slate-400">Collections</div><div class="font-bold">$118,200</div></div>
<div class="text-center"><div class="text-xs text-slate-400">Adjustment</div><div class="font-bold">-$6,300</div></div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Multi-Tenant System -->
<section class="px-6 lg:px-40 py-20 bg-primary/5">
<div class="mx-auto max-w-[1200px]">
<div class="bg-white dark:bg-slate-800 rounded-[2.5rem] p-8 lg:p-16 shadow-xl border border-slate-200 dark:border-slate-700">
<div class="grid lg:grid-cols-2 gap-12 items-center">
<div>
<h2 class="text-3xl font-bold mb-6">Multi-Tenant Architecture</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Designed for Solo Practices and Large DSOs alike. Manage multiple locations from a single master account with ease.</p>
<div class="space-y-6">
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">1</div>
<div>
<h4 class="font-bold">Global Reporting</h4>
<p class="text-sm text-slate-500">Roll up data from all clinics into a single corporate-level dashboard.</p>
</div>
</div>
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">2</div>
<div>
<h4 class="font-bold">Centralized Marketing</h4>
<p class="text-sm text-slate-500">Deploy brand-wide campaigns and communication templates across all branches.</p>
</div>
</div>
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">3</div>
<div>
<h4 class="font-bold">Standardized Protocols</h4>
<p class="text-sm text-slate-500">Maintain clinical standards with unified treatment plan templates and procedures.</p>
</div>
</div>
</div>
</div>
<div class="relative bg-slate-100 dark:bg-slate-900 rounded-2xl p-6 min-h-[360px]" data-alt="Abstract network diagram showing multiple clinic locations connected to a central hub">
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-24 h-24 bg-primary rounded-2xl shadow-2xl flex items-center justify-center text-white">
<span class="material-symbols-outlined text-4xl">hub</span>
</div>
<div class="absolute top-10 left-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute top-10 right-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute bottom-10 left-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute bottom-10 right-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<!-- Connecting lines visualized as dashed SVG paths -->
<svg class="absolute inset-0 w-full h-full opacity-20" viewbox="0 0 100 100">
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="20" x2="50" y1="20" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="80" x2="50" y1="20" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="20" x2="50" y1="80" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="80" x2="50" y1="80" y2="50"></line>
</svg>
</div>
</div>
</div>
</div>
</section>
<!-- CTA Section -->
<section class="px-6 lg:px-40 py-24 text-center">
<div class="mx-auto max-w-[800px]">
<h2 class="text-4xl font-bold mb-6">Ready to Modernize Your Practice?</h2>
<p class="text-lg text-slate-600 dark:text-slate-400 mb-10">Join 5,000+ dental professionals who trust MyDental for their daily operations.</p>
<div class="flex flex-col sm:flex-row justify-center gap-4">
<button class="px-10 py-4 bg-primary text-white rounded-xl font-bold text-lg hover:scale-105 transition-transform">Get Started Free</button>
<button class="px-10 py-4 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-lg">Contact Sales</button>
</div>
</div>
</section>
</main>
<!-- Footer -->
<footer class="px-6 lg:px-40 py-12 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-background-dark">
<div class="mx-auto max-w-[1200px]">
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 mb-12">
<div class="col-span-2 lg:col-span-1">
<div class="flex items-center gap-2 text-primary mb-6">
<span class="material-symbols-outlined text-2xl font-bold">dentistry</span>
<h2 class="text-slate-900 dark:text-slate-50 text-lg font-bold">MyDental</h2>
</div>
<p class="text-xs text-slate-500 leading-relaxed">The world's leading cloud-based dental practice management platform.</p>
</div>
<div>
<h4 class="font-bold text-sm mb-4">Product</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">Features</a></li>
<li><a class="hover:text-primary" href="#">Security</a></li>
<li><a class="hover:text-primary" href="#">Integrations</a></li>
<li><a class="hover:text-primary" href="#">Pricing</a></li>
</ul>
</div>
<div>
<h4 class="font-bold text-sm mb-4">Resources</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">Documentation</a></li>
<li><a class="hover:text-primary" href="#">API Ref</a></li>
<li><a class="hover:text-primary" href="#">Blog</a></li>
<li><a class="hover:text-primary" href="#">Support</a></li>
</ul>
</div>
<div>
<h4 class="font-bold text-sm mb-4">Company</h4>
<ul class="space-y-2 text-sm text-slate-500">
<li><a class="hover:text-primary" href="#">About Us</a></li>
<li><a class="hover:text-primary" href="#">Careers</a></li>
<li><a class="hover:text-primary" href="#">Contact</a></li>
<li><a class="hover:text-primary" href="#">Legal</a></li>
</ul>
</div>
</div>
<div class="pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-center gap-4">
<p class="text-xs text-slate-400">© 2024 MyDental Technologies Inc. All rights reserved.</p>
<div class="flex gap-6">
<a class="text-slate-400 hover:text-primary" href="#"><span class="material-symbols-outlined text-sm">public</span></a>
<a class="text-slate-400 hover:text-primary" href="#"><span class="material-symbols-outlined text-sm">alternate_email</span></a>
<a class="text-slate-400 hover:text-primary" href="#"><span class="material-symbols-outlined text-sm">share</span></a>
</div>
</div>
</div>
</footer>
</div>
</body></html>