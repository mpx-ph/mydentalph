<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Contact Us - MyDental.com</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&amp;display=swap" rel="stylesheet"/>
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
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<!-- Navigation Bar -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-1 max-w-7xl mx-auto w-full px-6 md:px-20 lg:px-40 py-12">
<!-- Hero Header Section -->
<div class="flex flex-col gap-4 mb-12">
<h1 class="text-slate-900 dark:text-slate-100 text-5xl font-extrabold leading-tight tracking-tight">Contact Us</h1>
<p class="text-slate-600 dark:text-slate-400 text-lg max-w-2xl">We're here to help you with your dental needs. Reach out to us through any of the channels below or send us a message directly.</p>
</div>
<!-- Contact Info Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
<!-- Email -->
<div class="flex flex-col gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm hover:shadow-md transition-shadow">
<div class="w-12 h-12 flex items-center justify-center rounded-lg bg-primary/10 text-primary">
<span class="material-symbols-outlined text-3xl">mail</span>
</div>
<div class="flex flex-col gap-1">
<h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold">Email</h2>
<p class="text-slate-500 dark:text-slate-400 text-sm font-medium">support@mydental.com</p>
</div>
</div>
<!-- Phone -->
<div class="flex flex-col gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm hover:shadow-md transition-shadow">
<div class="w-12 h-12 flex items-center justify-center rounded-lg bg-primary/10 text-primary">
<span class="material-symbols-outlined text-3xl">call</span>
</div>
<div class="flex flex-col gap-1">
<h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold">Phone</h2>
<p class="text-slate-500 dark:text-slate-400 text-sm font-medium">+63 912 345 6789</p>
</div>
</div>
<!-- Address -->
<div class="flex flex-col gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm hover:shadow-md transition-shadow">
<div class="w-12 h-12 flex items-center justify-center rounded-lg bg-primary/10 text-primary">
<span class="material-symbols-outlined text-3xl">location_on</span>
</div>
<div class="flex flex-col gap-1">
<h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold">Address</h2>
<p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Quezon City, Philippines</p>
</div>
</div>
</div>
<!-- Main Content Area: Form & Map -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
<!-- Contact Form -->
<div class="flex flex-col gap-8 bg-white dark:bg-slate-900 p-8 md:p-10 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
<div class="flex flex-col gap-2">
<h3 class="text-slate-900 dark:text-slate-100 text-2xl font-bold">Send us a Message</h3>
<p class="text-slate-500 dark:text-slate-400">Fill out the form and our team will get back to you within 24 hours.</p>
</div>
<form class="flex flex-col gap-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<label class="flex flex-col gap-2">
<span class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Name</span>
<input class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-background-light dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" placeholder="Your full name" type="text"/>
</label>
<label class="flex flex-col gap-2">
<span class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Email</span>
<input class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-background-light dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" placeholder="email@example.com" type="email"/>
</label>
</div>
<label class="flex flex-col gap-2">
<span class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Message</span>
<textarea class="w-full px-4 py-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-background-light dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all resize-none placeholder:text-slate-400 dark:placeholder:text-slate-500" placeholder="How can we help you?" rows="5"></textarea>
</label>
<button class="flex w-full cursor-pointer items-center justify-center rounded-lg h-14 px-8 bg-primary text-white text-base font-bold tracking-wide shadow-lg shadow-primary/30 hover:bg-primary/90 hover:translate-y-[-1px] active:translate-y-[1px] transition-all" type="button">
                                Send Message
                                <span class="material-symbols-outlined ml-2 text-xl">send</span>
</button>
</form>
</div>
<!-- Map/Visual Section -->
<div class="h-full min-h-[400px] lg:min-h-full flex flex-col gap-6">
<div class="flex-1 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 relative shadow-sm group">
<img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" data-alt="Modern minimalist dental clinic office interior" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBqT18nJIuvKA9jj4o6gMwhwVHBIBoPIRtE3knoZu6dYjVdr_QJ1pJxFrItM_3DLo29hQKmT04Zm17EUWXc7GcobVFgxDBvmf3_mmTpqPRMS_T5dimmUjdl-A4S_KJyR-1GOgjuovxk9PaZ1SWaIUvAxwu-o6tiY5ZyuujpEou0iwtT6OhJyAE9dGW6B51TtYTQXtzF2xT3U6hWDKOj4YYZ62_nv8LlCNmJIOHKoyL8_lvrWb9hzI87l8Z8Hs3PXa__aeSEDSfR8hk"/>
<div class="absolute inset-0 bg-gradient-to-t from-primary/60 to-transparent flex items-end p-8">
<div class="text-white">
<h4 class="text-xl font-bold mb-1 italic">Quality Care</h4>
<p class="text-white/80 text-sm">Experience the best dental technology in Quezon City.</p>
</div>
</div>
</div>
<div class="h-48 rounded-2xl bg-slate-200 dark:bg-slate-800 relative overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800">
<img class="w-full h-full object-cover opacity-80" data-alt="Map view of Quezon City Philippines" data-location="Quezon City, Philippines" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDmuRnpHDN0l1GKV3bjZA2eFsnywZnZgNzJJPez3RBauOs9x8GorFMQQ7voLJ-K0FYrEhASp52h-XbyDq1AQ_0-5CcelEHktkP8moqBOfjhvB8KxjdBfabLH242rgXLn1zuJ5Upvb746SaysM9F11h1SbFiIKus-wCraraogHAmcYHNvmPOLzfIDy2dmtBSo0BOKcdkksKiwgxfDV5Sq7oslMKVoMXcUMofeKX9V8d8MgtX1A8tGRm6FkzJIUWorK9Lqcmmpfd3t8I"/>
<div class="absolute inset-0 flex items-center justify-center">
<div class="bg-white/90 dark:bg-slate-900/90 px-4 py-2 rounded-full shadow-lg flex items-center gap-2 border border-primary/20">
<span class="material-symbols-outlined text-primary">location_on</span>
<span class="text-sm font-bold text-slate-800 dark:text-slate-100">Our Main Office</span>
</div>
</div>
</div>
</div>
</div>
</main>
<!-- Footer -->
<footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 py-12 px-6 md:px-20 lg:px-40 mt-12">
<div class="flex flex-col md:flex-row justify-between items-center gap-8 max-w-7xl mx-auto w-full">
<div class="flex items-center gap-3 text-primary opacity-80">
<div class="size-6">
<svg class="w-full h-full" fill="none" viewbox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
<path d="M13.8261 30.5736C16.7203 29.8826 20.2244 29.4783 24 29.4783C27.7756 29.4783 31.2797 29.8826 34.1739 30.5736C36.9144 31.2278 39.9967 32.7669 41.3563 33.8352L24.8486 7.36089C24.4571 6.73303 23.5429 6.73303 23.1514 7.36089L6.64374 33.8352C8.00331 32.7669 11.0856 31.2278 13.8261 30.5736Z" fill="currentColor"></path>
</svg>
</div>
<h2 class="text-slate-600 dark:text-slate-400 text-sm font-bold">MyDental.com</h2>
</div>
<p class="text-slate-500 dark:text-slate-500 text-sm">© 2024 MyDental Services. All rights reserved.</p>
<div class="flex gap-6">
<a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-symbols-outlined">social_leaderboard</span></a>
<a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-symbols-outlined">share_reviews</span></a>
<a class="text-slate-400 hover:text-primary transition-colors" href="#"><span class="material-symbols-outlined">alternate_email</span></a>
</div>
</div>
</footer>
</div>
</div>
</body></html>