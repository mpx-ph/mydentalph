<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>FAQ - MyDental</title>
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
                        "display": ["Manrope"]
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
        body {
            font-family: 'Manrope', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<!-- Navigation Bar -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-1">
<div class="px-6 lg:px-40 flex flex-1 justify-center py-12">
<div class="layout-content-container flex flex-col max-w-[800px] flex-1">
<div class="flex flex-col gap-4 mb-10 text-center md:text-left">
<h1 class="text-slate-900 dark:text-white text-4xl md:text-5xl font-black leading-tight tracking-[-0.033em]">Frequently Asked Questions</h1>
<p class="text-slate-600 dark:text-slate-400 text-lg font-normal max-w-2xl">
                            Everything you need to know about the MyDental platform and how it can transform your clinical operations.
                        </p>
</div>
<div class="flex flex-col gap-4">
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow" open="">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">What is MyDental?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                MyDental is a comprehensive, cloud-based dental practice management platform. We provide tools for patient scheduling, electronic health records, billing automation, and clinical imaging, all designed to streamline operations and enhance the patient experience through modern digital interfaces.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">How do clinics create accounts?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Registering your clinic is simple. Click on the "Get Started" button on our homepage, provide your clinic's basic information and license details, and our team will verify your credentials within 24 hours. Once verified, you can immediately begin setting up your team profiles and patient database.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">Will each clinic have its own dashboard?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Yes, absolutely. Every clinic registered on MyDental receives a private, secure, and fully customizable dashboard. This dashboard provides real-time analytics on patient visits, revenue tracking, inventory management, and staff performance, ensuring you have total visibility into your practice's health.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">Can multiple clinics use the platform?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Yes, MyDental is built for scale. Whether you are a single private practice or a large Dental Service Organization (DSO) with hundreds of locations, our platform supports multi-site management. You can switch between locations seamlessly and generate consolidated reports for the entire organization.
                            </div>
</details>
</div>
<div class="mt-16 p-8 rounded-2xl bg-primary/5 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-6">
<div class="text-center md:text-left">
<h2 class="text-slate-900 dark:text-white text-[22px] font-bold leading-tight tracking-[-0.015em]">Still have questions?</h2>
<p class="text-slate-600 dark:text-slate-400 text-base font-normal mt-2">
                                If you cannot find the answer you are looking for, please contact our support team.
                            </p>
</div>
<div class="flex gap-3">
<button class="flex min-w-[120px] cursor-pointer items-center justify-center rounded-lg h-12 px-6 bg-primary text-white text-sm font-bold shadow-lg shadow-primary/20 hover:opacity-90 transition-all">
                                Contact Support
                            </button>
<button class="flex min-w-[120px] cursor-pointer items-center justify-center rounded-lg h-12 px-6 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
                                View Docs
                            </button>
</div>
</div>
</div>
</div>
</main>
<footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 px-6 lg:px-40 py-10">
<div class="flex flex-col md:flex-row justify-between items-center gap-8">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-2xl">dentistry</span>
<span class="text-slate-900 dark:text-white font-bold text-lg">MyDental</span>
</div>
<div class="flex gap-8 text-slate-500 dark:text-slate-400 text-sm">
<a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="hover:text-primary transition-colors" href="#">Cookies</a>
</div>
<div class="text-slate-400 text-sm">
                    © 2024 MyDental Inc. All rights reserved.
                </div>
</div>
</footer>
</div>
</div>
</body></html>