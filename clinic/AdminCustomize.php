<?php
/**
 * Admin Customize - Edit clinic website images and content
 * Accessible to logged-in admin. Customizes MainPageClient, AboutUsClient, RegisterClient, ContactUsClient.
 */
$pageTitle = 'Customize Website - Dental Clinic Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Customize Website</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Edit images and text shown on the clinic's public pages (Home, About Us, Register, Contact).</p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="mx-auto max-w-6xl">
<div class="flex flex-row gap-8 items-start min-h-[calc(100vh-6rem)]">

<!-- Left: controls -->
<div class="basis-[360px] shrink-0 space-y-6 pr-2">
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl px-5 py-4">
<p class="text-xs font-semibold tracking-[0.16em] text-primary uppercase mb-1">Visual Site Builder</p>
<h2 class="text-lg font-bold text-slate-900 dark:text-slate-50">Clinic appearance</h2>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Switch between areas to update logos, colors, and page content. Changes are reflected in the live preview.</p>
</div>

<!-- Tabs -->
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
    <div class="flex items-center justify-between mb-2">
        <p class="text-[10px] font-semibold tracking-[0.18em] text-slate-400 uppercase">Sections</p>
    </div>
    <div class="flex gap-1 overflow-x-auto pb-1 rounded-full bg-slate-100 dark:bg-slate-800 p-1">
        <button
            type="button"
            data-tab="logos"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap bg-white text-slate-900 shadow-sm"
        >
            Branding
        </button>
        <button
            type="button"
            data-tab="colors"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap text-slate-500 dark:text-slate-400"
        >
            Colors
        </button>
        <button
            type="button"
            data-tab="homepage"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap text-slate-500 dark:text-slate-400"
        >
            Home
        </button>
        <button
            type="button"
            data-tab="about"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap text-slate-500 dark:text-slate-400"
        >
            About
        </button>
        <button
            type="button"
            data-tab="register"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap text-slate-500 dark:text-slate-400"
        >
            Register
        </button>
        <button
            type="button"
            data-tab="contact"
            class="tab-btn inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-semibold tracking-[0.14em] uppercase whitespace-nowrap text-slate-500 dark:text-slate-400"
        >
            Contact
        </button>
    </div>
</div>

<form id="customizeForm" class="space-y-8">
<input type="hidden" name="data" id="formDataInput" value=""/>

<!-- Tab: Logos -->
<div id="panel-logos" class="tab-panel space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Site Logos</h2>
<p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Upload different logos for the navigation bar, footer/login pages, and the registration page.</p>
<div class="grid gap-6">
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Logo (header / navigation)</span>
<span class="block text-xs text-slate-400 mt-0.5">Shown in the top bar on the main website and in the admin sidebar.</span>
<div class="mt-2 flex items-center gap-4 flex-wrap">
<img id="preview_logo_nav" src="" alt="" class="h-16 w-auto object-contain rounded-xl border border-slate-200 dark:border-slate-600 hidden"/>
<input type="file" name="file_logo_nav" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/>
</div>
</label>
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Logo (footer &amp; login pages)</span>
<span class="block text-xs text-slate-400 mt-0.5">Shown in the site footer and on the admin login / create account pages.</span>
<div class="mt-2 flex items-center gap-4 flex-wrap">
<img id="preview_logo" src="" alt="" class="h-16 w-auto object-contain rounded-xl border border-slate-200 dark:border-slate-600 hidden"/>
<input type="file" name="file_logo" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/>
</div>
</label>
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Logo (register page)</span>
<span class="block text-xs text-slate-400 mt-0.5">Shown at the top of the patient registration page.</span>
<div class="mt-2 flex items-center gap-4 flex-wrap">
<img id="preview_logo_register" src="" alt="" class="h-16 w-auto object-contain rounded-xl border border-slate-200 dark:border-slate-600 hidden"/>
<input type="file" name="file_logo_register" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/>
</div>
</label>
</div>
</div>
</div>

<!-- Tab: Colors -->
<div id="panel-colors" class="tab-panel hidden space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Brand Colors</h2>
<p class="text-sm text-slate-500 dark:text-slate-400 mb-6">These colors are used for buttons, links, and accents across the clinic website and admin pages.</p>
<div class="grid gap-6">
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Primary</span>
<span class="block text-xs text-slate-400 mt-0.5">Main brand color (buttons, links, highlights).</span>
<div class="mt-2 flex items-center gap-3 flex-wrap">
<input type="color" id="color_primary_picker" class="h-10 w-14 rounded border border-slate-300 dark:border-slate-600 cursor-pointer p-0.5 bg-white" value="#2b8cee"/>
<input type="text" name="color_primary" id="color_primary" maxlength="7" placeholder="#2b8cee" class="w-28 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-sm"/>
</div>
</label>
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Primary (darker)</span>
<span class="block text-xs text-slate-400 mt-0.5">Hover state for buttons and links.</span>
<div class="mt-2 flex items-center gap-3 flex-wrap">
<input type="color" id="color_primary_dark_picker" class="h-10 w-14 rounded border border-slate-300 dark:border-slate-600 cursor-pointer p-0.5 bg-white" value="#1a6cb6"/>
<input type="text" name="color_primary_dark" id="color_primary_dark" maxlength="7" placeholder="#1a6cb6" class="w-28 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-sm"/>
</div>
</label>
<label class="block">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Primary (light)</span>
<span class="block text-xs text-slate-400 mt-0.5">Light backgrounds and subtle highlights.</span>
<div class="mt-2 flex items-center gap-3 flex-wrap">
<input type="color" id="color_primary_light_picker" class="h-10 w-14 rounded border border-slate-300 dark:border-slate-600 cursor-pointer p-0.5 bg-white" value="#eef7ff"/>
<input type="text" name="color_primary_light" id="color_primary_light" maxlength="7" placeholder="#eef7ff" class="w-28 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-sm"/>
</div>
</label>
</div>
</div>
</div>

<!-- Tab: Homepage -->
<div id="panel-homepage" class="tab-panel hidden space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Hero Section</h2>
<div class="grid gap-4">
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Line 1</span><input type="text" name="main_hero_line1" id="main_hero_line1" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Line 2</span><input type="text" name="main_hero_line2" id="main_hero_line2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Line 3</span><input type="text" name="main_hero_line3" id="main_hero_line3" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
</div>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Subtext</span><textarea name="main_hero_subtext" id="main_hero_subtext" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero Image</span><div class="mt-1 flex items-center gap-4"><img id="preview_main_hero_image" src="" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 hidden"/><input type="file" name="file_main_hero_image" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/></div></label>
</div>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Doctor / Team Section</h2>
<div class="grid gap-4">
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Name</span><input type="text" name="main_doctor_name" id="main_doctor_name" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Title</span><input type="text" name="main_doctor_title" id="main_doctor_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Bio (paragraph 1)</span><textarea name="main_doctor_bio1" id="main_doctor_bio1" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Bio (paragraph 2)</span><textarea name="main_doctor_bio2" id="main_doctor_bio2" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Doctor Image</span><div class="mt-1 flex items-center gap-4"><img id="preview_main_doctor_image" src="" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 hidden"/><input type="file" name="file_main_doctor_image" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/></div></label>
<div class="grid grid-cols-3 gap-4">
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Years</span><input type="text" name="main_stats_years" id="main_stats_years" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Smiles</span><input type="text" name="main_stats_smiles" id="main_stats_smiles" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Focus</span><input type="text" name="main_stats_focus" id="main_stats_focus" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
</div>
</div>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Services Section</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Heading</span><input type="text" name="main_services_heading" id="main_services_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Title</span><input type="text" name="main_services_title" id="main_services_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Description</span><textarea name="main_services_description" id="main_services_description" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Facilities Section</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Title</span><input type="text" name="main_facilities_title" id="main_facilities_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Description</span><textarea name="main_facilities_description" id="main_facilities_description" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
</div>
</div>

<!-- Tab: About Us -->
<div id="panel-about" class="tab-panel hidden space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">About Intro</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Heading</span><input type="text" name="about_intro_heading" id="about_intro_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Intro Text</span><textarea name="about_intro_text" id="about_intro_text" rows="3" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero caption title</span><input type="text" name="about_hero_caption_title" id="about_hero_caption_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero caption text</span><input type="text" name="about_hero_caption_text" id="about_hero_caption_text" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero Image</span><div class="mt-1 flex items-center gap-4"><img id="preview_about_hero_image" src="" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 hidden"/><input type="file" name="file_about_hero_image" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/></div></label>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Trusted Care & Stats</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Trusted Care Title</span><input type="text" name="about_trusted_title" id="about_trusted_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Trusted Care Text</span><textarea name="about_trusted_text" id="about_trusted_text" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Years Number</span><input type="text" name="about_years_number" id="about_years_number" class="mt-1 w-full max-w-[120px] px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Years Text</span><input type="text" name="about_years_text" id="about_years_text" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Why Choose Us</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Heading</span><input type="text" name="about_why_heading" id="about_why_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Subtext</span><input type="text" name="about_why_subtext" id="about_why_subtext" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<div class="grid gap-4">
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 1 Title</span><input type="text" name="about_why_1_title" id="about_why_1_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 1 Text</span><textarea name="about_why_1_text" id="about_why_1_text" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 2 Title</span><input type="text" name="about_why_2_title" id="about_why_2_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 2 Text</span><textarea name="about_why_2_text" id="about_why_2_text" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 3 Title</span><input type="text" name="about_why_3_title" id="about_why_3_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Feature 3 Text</span><textarea name="about_why_3_text" id="about_why_3_text" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
</div>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Team – Doctor 1</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Name</span><input type="text" name="about_team_doctor1_name" id="about_team_doctor1_name" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Title</span><input type="text" name="about_team_doctor1_title" id="about_team_doctor1_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Bio</span><textarea name="about_team_doctor1_bio" id="about_team_doctor1_bio" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tags (comma-separated)</span><input type="text" name="about_team_doctor1_tags" id="about_team_doctor1_tags" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white" placeholder="Prosthodontics, Implants"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Image (URL or upload)</span><div class="mt-1 flex items-center gap-4"><img id="preview_about_team_doctor1_image" src="" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 hidden"/><input type="file" name="file_about_team_doctor1_image" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/><input type="url" name="about_team_doctor1_image" id="about_team_doctor1_image" placeholder="Or paste image URL" class="flex-1 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></div></label>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Team – Doctor 2</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Name</span><input type="text" name="about_team_doctor2_name" id="about_team_doctor2_name" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Title</span><input type="text" name="about_team_doctor2_title" id="about_team_doctor2_title" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Bio</span><textarea name="about_team_doctor2_bio" id="about_team_doctor2_bio" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tags (comma-separated)</span><input type="text" name="about_team_doctor2_tags" id="about_team_doctor2_tags" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white" placeholder="Orthodontics, Pediatric"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Image (URL or upload)</span><div class="mt-1 flex items-center gap-4"><img id="preview_about_team_doctor2_image" src="" alt="" class="h-24 w-24 object-cover rounded-xl border border-slate-200 dark:border-slate-600 hidden"/><input type="file" name="file_about_team_doctor2_image" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/><input type="url" name="about_team_doctor2_image" id="about_team_doctor2_image" placeholder="Or paste image URL" class="flex-1 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></div></label>
</div>
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">CTA Section</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Heading</span><input type="text" name="about_cta_heading" id="about_cta_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Subtext</span><input type="text" name="about_cta_subtext" id="about_cta_subtext" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Button labels</span><div class="mt-1 flex gap-4"><input type="text" name="about_cta_book_text" id="about_cta_book_text" placeholder="Book Appointment" class="flex-1 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/><input type="text" name="about_cta_contact_text" id="about_cta_contact_text" placeholder="Contact Us Now" class="flex-1 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></div></label>
</div>
</div>

<!-- Tab: Register -->
<div id="panel-register" class="tab-panel hidden space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Register Page</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Register page logo</span><div class="mt-1 flex items-center gap-4"><input type="file" name="file_logo_register" accept="image/*" class="text-sm text-slate-600 dark:text-slate-400"/></div><span class="block text-xs text-slate-400 mt-1">Preview and upload also available in the Logos tab.</span></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Heading</span><input type="text" name="register_heading" id="register_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Subtext</span><textarea name="register_subtext" id="register_subtext" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Footer Text</span><textarea name="register_footer_text" id="register_footer_text" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
</div>
</div>

<!-- Tab: Contact -->
<div id="panel-contact" class="tab-panel hidden space-y-6">
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Contact Page</h2>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero Heading</span><input type="text" name="contact_hero_heading" id="contact_hero_heading" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Hero Subtext</span><textarea name="contact_hero_subtext" id="contact_hero_subtext" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Address</span><textarea name="contact_address" id="contact_address" rows="2" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"></textarea></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Map Link (Google Maps URL)</span><input type="url" name="contact_map_link" id="contact_map_link" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Map Embed URL (iframe src)</span><input type="url" name="contact_map_embed" id="contact_map_embed" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Phone</span><input type="text" name="contact_phone" id="contact_phone" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block mb-4"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Email</span><input type="email" name="contact_email" id="contact_email" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Mon–Fri Hours</span><input type="text" name="contact_hours_mon_fri" id="contact_hours_mon_fri" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Saturday</span><input type="text" name="contact_hours_sat" id="contact_hours_sat" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
<label class="block"><span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Sunday</span><input type="text" name="contact_hours_sun" id="contact_hours_sun" class="mt-1 w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"/></label>
</div>
</div>
</div>
</form>

<div class="flex justify-end gap-4 pt-4">
<button type="button" id="saveBtn" class="px-6 py-3 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl shadow-lg flex items-center gap-2">
<span class="material-symbols-outlined">save</span> Save changes
</button>
</div>
</div><!-- /Left: controls -->

<!-- Right: live preview -->
<aside class="flex-1 min-w-[420px] self-start sticky top-24">
<div class="w-full bg-surface-light dark:bg-surface-dark rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden flex flex-col">
<div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-900/60">
<div class="flex items-center gap-2">
<span class="inline-flex h-2.5 w-2.5 rounded-full bg-rose-400"></span>
<span class="inline-flex h-2.5 w-2.5 rounded-full bg-amber-400"></span>
<span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
<span class="ml-4 text-xs font-medium text-slate-500 dark:text-slate-400 truncate max-w-[160px]" id="previewClinicUrl">patientportal.local/clinic</span>
</div>
<span class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">Patient Portal — Preview</span>
</div>

<!-- Nav -->
<div class="px-8 py-4 flex items-center justify-between bg-white dark:bg-slate-900/90 border-b border-slate-200 dark:border-slate-800">
<div class="flex items-center gap-3">
<div class="h-8 w-8 rounded-full bg-primary/10 border border-primary/40 flex items-center justify-center text-xs font-bold text-primary">DC</div>
<div>
<p class="text-xs font-semibold text-slate-900 dark:text-slate-50 leading-tight" id="previewClinicName">Smile Makers Dental</p>
<p class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight">Patient-facing website</p>
</div>
</div>
<nav class="hidden md:flex items-center gap-6 text-xs font-medium text-slate-600 dark:text-slate-300">
<span class="text-primary">Home</span>
<span>Services</span>
<span>Our Dentists</span>
<span>Contact</span>
<button class="ml-4 px-4 py-1.5 rounded-full bg-primary text-white text-[11px] font-semibold shadow-sm">Register Now</button>
</nav>
</div>

<!-- Top preview page switcher -->
<div class="px-8 pt-4 pb-2 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/70 backdrop-blur">
<div class="flex items-center gap-2 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
<span class="uppercase tracking-[0.16em] text-[10px] text-slate-400">Previewing page</span>
<span id="previewPageLabel" class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200">Home</span>
</div>
<div class="flex gap-1 text-[11px] font-medium">
<button type="button" data-preview-page="home" class="preview-page-btn px-3 py-1 rounded-full bg-primary text-white shadow-sm">Home</button>
<button type="button" data-preview-page="about" class="preview-page-btn px-3 py-1 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/80">About</button>
<button type="button" data-preview-page="contact" class="preview-page-btn px-3 py-1 rounded-full text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/80">Contact</button>
</div>
</div>

<!-- HOME PREVIEW -->
<section data-preview="home" class="flex-1 flex flex-col">
<div class="flex-1 bg-gradient-to-r from-primary via-sky-500 to-emerald-400 text-white px-8 md:px-12 py-10 md:py-16 flex flex-col justify-center items-start gap-5">
<p class="text-xs font-semibold tracking-[0.2em] uppercase opacity-80">Welcome to</p>
<h2 class="text-2xl md:text-3xl lg:text-4xl font-extrabold leading-tight max-w-xl" id="previewHeroHeadline">Welcome to Our Dental Clinic</h2>
<p class="max-w-xl text-sm md:text-base text-slate-100/90" id="previewHeroSubtext">We deliver trusted, modern dental care with patient-first service and a calm, welcoming environment.</p>
<div class="mt-3 flex flex-wrap gap-3">
<button class="px-5 py-2.5 rounded-full bg-white text-primary text-sm font-semibold shadow-lg shadow-sky-900/20" type="button">Set Appointment</button>
<button class="px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/15 border border-white/40 text-sm font-semibold text-white backdrop-blur" type="button">View Services</button>
</div>
</div>
<div class="bg-white dark:bg-slate-900 px-8 py-6 md:py-8 border-t border-slate-200 dark:border-slate-800">
<h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50 mb-1" id="previewAboutHeading">About Our Clinic</h3>
<p class="text-xs text-slate-500 dark:text-slate-400 max-w-xl" id="previewAboutText">This is a quick snapshot of how your About section will feel for patients visiting your website.</p>
</div>
</section>

<!-- ABOUT PREVIEW -->
<section data-preview="about" class="hidden flex-1 flex-col bg-surface-light dark:bg-background-dark">
<div class="flex-1 px-8 md:px-10 py-10 md:py-14">
<div class="max-w-xl">
<p class="text-xs font-semibold tracking-[0.18em] text-primary uppercase mb-2">About Page</p>
<h2 class="text-2xl md:text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white leading-tight mb-3" id="previewAboutIntroHeading">Our Story &amp; Promise</h2>
<p class="text-sm md:text-base text-slate-600 dark:text-slate-300 leading-relaxed" id="previewAboutIntroText">Preview of your About page introduction as patients will read it.</p>
</div>
<div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-5">
<div class="md:col-span-2 rounded-2xl bg-cover bg-center min-h-[160px] relative overflow-hidden border border-slate-200 dark:border-slate-700">
<div class="absolute inset-0 bg-gradient-to-t from-slate-900/55 to-transparent"></div>
<div class="absolute bottom-4 left-4 right-4 text-white">
<p class="text-xs font-semibold tracking-wide uppercase opacity-80" id="previewAboutHeroCaptionTitle">Trusted Dental Care</p>
<p class="text-sm md:text-base font-semibold" id="previewAboutHeroCaptionText">A warm, modern clinic focused on your comfort.</p>
</div>
<div class="w-full h-full bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.15),_transparent_55%),linear-gradient(to_right,_rgba(15,23,42,0.88),_rgba(15,23,42,0.6))]"></div>
</div>
<div class="space-y-4">
<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-4">
<p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Trusted Care</p>
<p class="text-xs text-slate-600 dark:text-slate-300" id="previewAboutTrustedText">Highlight the reassurance and quality of care your clinic offers.</p>
</div>
<div class="rounded-2xl bg-slate-900 text-white p-4 flex flex-col items-start justify-center">
<p class="text-3xl font-bold leading-none" id="previewAboutYearsNumber">10+</p>
<p class="text-xs mt-1 opacity-90" id="previewAboutYearsText">Years of combined clinical experience caring for patients.</p>
</div>
</div>
</div>
</div>
</section>

<!-- CONTACT PREVIEW -->
<section data-preview="contact" class="hidden flex-1 flex-col bg-gradient-to-b from-blue-50 to-transparent dark:from-slate-800/40 dark:to-transparent">
<div class="px-8 md:px-10 py-10 md:py-14">
<p class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold uppercase tracking-wider border border-primary/20 mb-4">
<span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
Contact Support
</p>
<h2 class="text-2xl md:text-3xl lg:text-4xl font-black text-slate-900 dark:text-white leading-tight mb-3" id="previewContactHeroHeading">We’re here to help</h2>
<p class="text-sm md:text-base text-slate-600 dark:text-slate-300 max-w-xl mb-6" id="previewContactHeroSubtext">Preview of how your contact page invites patients to reach out.</p>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<div class="space-y-4">
<div class="rounded-2xl bg-white dark:bg-surface-dark border border-slate-200 dark:border-slate-800 p-4">
<p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Visit us</p>
<p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed" id="previewContactAddress">123 Smile Street, Dental City</p>
</div>
<div class="rounded-2xl bg-white dark:bg-surface-dark border border-slate-200 dark:border-slate-800 p-4 space-y-2">
<p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Phone</p>
<p class="text-sm font-medium text-slate-900 dark:text-slate-100" id="previewContactPhone">(555) 123-4567</p>
<p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mt-2">Email</p>
<p class="text-sm font-medium text-slate-900 dark:text-slate-100" id="previewContactEmail">hello@clinic.com</p>
</div>
</div>
<div class="rounded-2xl bg-white dark:bg-surface-dark border border-slate-200 dark:border-slate-800 p-4">
<p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Working hours</p>
<div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
<div class="flex justify-between"><span>Mon – Fri</span><span id="previewContactHoursMF">9:00 AM – 6:00 PM</span></div>
<div class="flex justify-between"><span>Saturday</span><span id="previewContactHoursSat">9:00 AM – 1:00 PM</span></div>
<div class="flex justify-between"><span>Sunday</span><span id="previewContactHoursSun">Closed</span></div>
</div>
</div>
</div>
</div>
</section>
</div>
</aside>
</div>
</div>
</main>

<script>
(function() {
    const BASE_URL = '<?php echo addslashes(BASE_URL); ?>';
    const API_URL = BASE_URL + 'api/clinic_customization.php';

    const form = document.getElementById('customizeForm');
    const saveBtn = document.getElementById('saveBtn');
    const previewPageBtns = document.querySelectorAll('.preview-page-btn');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.tab-panel');

    function setPreviewPage(page) {
        const sections = document.querySelectorAll('[data-preview]');
        const label = document.getElementById('previewPageLabel');
        const validPages = ['home', 'about', 'contact'];
        if (validPages.indexOf(page) === -1) page = 'home';
        sections.forEach(s => {
            if (s.getAttribute('data-preview') === page) s.classList.remove('hidden');
            else s.classList.add('hidden');
        });
        if (label) {
            label.textContent = page.charAt(0).toUpperCase() + page.slice(1);
        }
        previewPageBtns.forEach(btn => {
            if (btn.getAttribute('data-preview-page') === page) {
                btn.classList.add('bg-primary', 'text-white', 'shadow-sm');
                btn.classList.remove('text-slate-600', 'dark:text-slate-300');
            } else {
                btn.classList.remove('bg-primary', 'text-white', 'shadow-sm');
                btn.classList.add('text-slate-600', 'dark:text-slate-300');
            }
        });
    }

    function showPanel(id) {
        panels.forEach(p => { p.classList.add('hidden'); });
        tabBtns.forEach(b => {
            b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
            b.classList.add('text-slate-500', 'dark:text-slate-400');
        });
        const panel = document.getElementById('panel-' + id);
        const btn = document.querySelector('[data-tab="' + id + '"]');
        if (panel) panel.classList.remove('hidden');
        if (btn) {
            btn.classList.remove('text-slate-500', 'dark:text-slate-400');
            btn.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
        }

        // Couple content tabs to preview page
        if (id === 'homepage') setPreviewPage('home');
        if (id === 'about') setPreviewPage('about');
        if (id === 'contact') setPreviewPage('contact');
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showPanel(this.getAttribute('data-tab'));
        });
    });

    function imageUrl(val) {
        if (!val) return '';
        if (val.startsWith('http')) return val;
        return BASE_URL.replace(/\/$/, '') + '/' + val.replace(/^\//, '');
    }
    function toHex(v) {
        if (!v) return '';
        v = String(v).trim().replace(/^#/, '');
        return v.length === 6 && /^[0-9a-fA-F]+$/.test(v) ? '#' + v : (v.startsWith('#') ? v : '#' + v);
    }

    function updatePreviewFromForm() {
        const clinicNameEl = document.getElementById('previewClinicName');
        const heroHeadlineEl = document.getElementById('previewHeroHeadline');
        const heroSubtextEl = document.getElementById('previewHeroSubtext');
        const aboutHeadingEl = document.getElementById('previewAboutHeading');
        const aboutTextEl = document.getElementById('previewAboutText');
        const aboutIntroHeadingEl = document.getElementById('previewAboutIntroHeading');
        const aboutIntroTextEl = document.getElementById('previewAboutIntroText');
        const aboutHeroCaptionTitleEl = document.getElementById('previewAboutHeroCaptionTitle');
        const aboutHeroCaptionTextEl = document.getElementById('previewAboutHeroCaptionText');
        const aboutTrustedTextEl = document.getElementById('previewAboutTrustedText');
        const aboutYearsNumberEl = document.getElementById('previewAboutYearsNumber');
        const aboutYearsTextEl = document.getElementById('previewAboutYearsText');

        const contactHeroHeadingEl = document.getElementById('previewContactHeroHeading');
        const contactHeroSubtextEl = document.getElementById('previewContactHeroSubtext');
        const contactAddressEl = document.getElementById('previewContactAddress');
        const contactPhoneEl = document.getElementById('previewContactPhone');
        const contactEmailEl = document.getElementById('previewContactEmail');
        const contactHoursMFEl = document.getElementById('previewContactHoursMF');
        const contactHoursSatEl = document.getElementById('previewContactHoursSat');
        const contactHoursSunEl = document.getElementById('previewContactHoursSun');

        if (clinicNameEl) {
            const clinicName = (document.getElementById('clinic_name') && document.getElementById('clinic_name').value) || clinicNameEl.dataset.default || 'Smile Makers Dental';
            clinicNameEl.textContent = clinicName || 'Smile Makers Dental';
        }

        if (heroHeadlineEl) {
            const l1 = (document.getElementById('main_hero_line1') || {}).value || '';
            const l2 = (document.getElementById('main_hero_line2') || {}).value || '';
            const l3 = (document.getElementById('main_hero_line3') || {}).value || '';
            const combined = [l1, l2, l3].filter(Boolean).join(' ');
            heroHeadlineEl.textContent = combined || 'Welcome to Our Dental Clinic';
        }

        if (heroSubtextEl) {
            const sub = (document.getElementById('main_hero_subtext') || {}).value || '';
            heroSubtextEl.textContent = sub || 'We deliver trusted, modern dental care with patient-first service and a calm, welcoming environment.';
        }

        if (aboutHeadingEl) {
            const ah = (document.getElementById('about_intro_heading') || {}).value || '';
            aboutHeadingEl.textContent = ah || 'About Our Clinic';
        }

        if (aboutTextEl) {
            const at = (document.getElementById('about_intro_text') || {}).value || '';
            aboutTextEl.textContent = at || 'This is a quick snapshot of how your About section will feel for patients visiting your website.';
        }

        if (aboutIntroHeadingEl) {
            const ah = (document.getElementById('about_intro_heading') || {}).value || '';
            aboutIntroHeadingEl.textContent = ah || 'Our Story & Promise';
        }

        if (aboutIntroTextEl) {
            const at = (document.getElementById('about_intro_text') || {}).value || '';
            aboutIntroTextEl.textContent = at || 'Preview of your About page introduction as patients will read it.';
        }

        if (aboutHeroCaptionTitleEl) {
            const v = (document.getElementById('about_hero_caption_title') || {}).value || '';
            aboutHeroCaptionTitleEl.textContent = v || 'Trusted Dental Care';
        }

        if (aboutHeroCaptionTextEl) {
            const v = (document.getElementById('about_hero_caption_text') || {}).value || '';
            aboutHeroCaptionTextEl.textContent = v || 'A warm, modern clinic focused on your comfort.';
        }

        if (aboutTrustedTextEl) {
            const v = (document.getElementById('about_trusted_text') || {}).value || '';
            aboutTrustedTextEl.textContent = v || 'Highlight the reassurance and quality of care your clinic offers.';
        }

        if (aboutYearsNumberEl) {
            const v = (document.getElementById('about_years_number') || {}).value || '';
            aboutYearsNumberEl.textContent = v || '10+';
        }

        if (aboutYearsTextEl) {
            const v = (document.getElementById('about_years_text') || {}).value || '';
            aboutYearsTextEl.textContent = v || 'Years of combined clinical experience caring for patients.';
        }

        if (contactHeroHeadingEl) {
            const v = (document.getElementById('contact_hero_heading') || {}).value || '';
            contactHeroHeadingEl.textContent = v || 'We’re here to help';
        }

        if (contactHeroSubtextEl) {
            const v = (document.getElementById('contact_hero_subtext') || {}).value || '';
            contactHeroSubtextEl.textContent = v || 'Preview of how your contact page invites patients to reach out.';
        }

        if (contactAddressEl) {
            const v = (document.getElementById('contact_address') || {}).value || '';
            contactAddressEl.textContent = v || '123 Smile Street, Dental City';
        }

        if (contactPhoneEl) {
            const v = (document.getElementById('contact_phone') || {}).value || '';
            contactPhoneEl.textContent = v || '(555) 123-4567';
        }

        if (contactEmailEl) {
            const v = (document.getElementById('contact_email') || {}).value || '';
            contactEmailEl.textContent = v || 'hello@clinic.com';
        }

        if (contactHoursMFEl) {
            const v = (document.getElementById('contact_hours_mon_fri') || {}).value || '';
            contactHoursMFEl.textContent = v || '9:00 AM – 6:00 PM';
        }

        if (contactHoursSatEl) {
            const v = (document.getElementById('contact_hours_sat') || {}).value || '';
            contactHoursSatEl.textContent = v || '9:00 AM – 1:00 PM';
        }

        if (contactHoursSunEl) {
            const v = (document.getElementById('contact_hours_sun') || {}).value || '';
            contactHoursSunEl.textContent = v || 'Closed';
        }
    }

    function loadData(data) {
        const defaults = [
            'main_hero_line1', 'main_hero_line2', 'main_hero_line3', 'main_hero_subtext', 'main_hero_image',
            'main_doctor_name', 'main_doctor_title', 'main_doctor_bio1', 'main_doctor_bio2', 'main_doctor_image',
            'main_stats_years', 'main_stats_smiles', 'main_stats_focus',
            'main_services_heading', 'main_services_title', 'main_services_description',
            'main_facilities_title', 'main_facilities_description',
            'about_intro_heading', 'about_intro_text', 'about_hero_image', 'about_hero_caption_title', 'about_hero_caption_text', 'about_trusted_title', 'about_trusted_text',
            'about_years_number', 'about_years_text', 'about_why_heading', 'about_why_subtext',
            'about_why_1_title', 'about_why_1_text', 'about_why_2_title', 'about_why_2_text', 'about_why_3_title', 'about_why_3_text',
            'about_team_doctor1_name', 'about_team_doctor1_title', 'about_team_doctor1_image', 'about_team_doctor1_bio', 'about_team_doctor1_tags',
            'about_team_doctor2_name', 'about_team_doctor2_title', 'about_team_doctor2_image', 'about_team_doctor2_bio', 'about_team_doctor2_tags',
            'about_cta_heading', 'about_cta_subtext', 'about_cta_book_text', 'about_cta_contact_text',
            'register_heading', 'register_subtext', 'register_footer_text', 'logo', 'logo_nav', 'logo_register',
            'contact_hero_heading', 'contact_hero_subtext', 'contact_address', 'contact_map_link', 'contact_map_embed',
            'contact_phone', 'contact_email', 'contact_hours_mon_fri', 'contact_hours_sat', 'contact_hours_sun', 'clinic_name',
            'color_primary', 'color_primary_dark', 'color_primary_light'
        ];
        defaults.forEach(key => {
            const el = document.getElementById(key) || document.querySelector('[name="' + key + '"]');
            if (!el || data[key] === undefined) return;
            let val = data[key] || '';
            if (key.startsWith('color_')) {
                val = toHex(val);
                el.value = val;
                const picker = document.getElementById(key + '_picker');
                if (picker && val) picker.value = val;
            } else {
                el.value = val;
            }
        });
        ['main_hero_image', 'main_doctor_image', 'about_hero_image', 'about_team_doctor1_image', 'about_team_doctor2_image', 'logo', 'logo_nav', 'logo_register'].forEach(key => {
            const preview = document.getElementById('preview_' + key);
            if (!preview) return;
            const val = data[key];
            if (val) {
                preview.src = imageUrl(val);
                preview.classList.remove('hidden');
            } else preview.classList.add('hidden');
        });

        // Update preview when initial data loads
        updatePreviewFromForm();
    }

    saveBtn.addEventListener('click', function() {
        const formEl = document.getElementById('customizeForm');
        const fd = new FormData(formEl);
        const data = {};
        const skip = ['data', 'file_main_hero_image', 'file_main_doctor_image', 'file_about_hero_image', 'file_about_team_doctor1_image', 'file_about_team_doctor2_image', 'file_logo', 'file_logo_nav', 'file_logo_register'];
        for (const [k, v] of fd.entries()) {
            if (skip.indexOf(k) >= 0 || k.startsWith('file_')) continue;
            let val = v;
            if (k.startsWith('color_') && typeof val === 'string') val = val.replace(/^#/, '').trim().toLowerCase();
            data[k] = val;
        }
        fd.set('data', JSON.stringify(data));
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Saving...';
        fetch(API_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    saveBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Saved';
                    setTimeout(() => { saveBtn.innerHTML = '<span class="material-symbols-outlined">save</span> Save changes'; }, 2000);
                } else {
                    alert(res.message || 'Save failed.');
                    saveBtn.innerHTML = '<span class="material-symbols-outlined">save</span> Save changes';
                }
            })
            .catch(() => {
                alert('Network error.');
                saveBtn.innerHTML = '<span class="material-symbols-outlined">save</span> Save changes';
            })
            .finally(() => { saveBtn.disabled = false; });
    });

    // Sync color pickers and text inputs
        ['color_primary', 'color_primary_dark', 'color_primary_light'].forEach(key => {
            const picker = document.getElementById(key + '_picker');
            const text = document.getElementById(key);
            if (picker && text) {
                picker.addEventListener('input', function() { text.value = this.value; });
                text.addEventListener('input', function() {
                    const v = toHex(this.value);
                    if (v) picker.value = v;
                });
            }
        });

        // Live preview bindings
        [
            'main_hero_line1',
            'main_hero_line2',
            'main_hero_line3',
            'main_hero_subtext',
            'about_intro_heading',
            'about_intro_text',
            'about_hero_caption_title',
            'about_hero_caption_text',
            'about_trusted_text',
            'about_years_number',
            'about_years_text',
            'contact_hero_heading',
            'contact_hero_subtext',
            'contact_address',
            'contact_phone',
            'contact_email',
            'contact_hours_mon_fri',
            'contact_hours_sat',
            'contact_hours_sun',
            'clinic_name'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updatePreviewFromForm);
            }
        });

        // Allow switching preview pages from inside preview header buttons
        previewPageBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                setPreviewPage(this.getAttribute('data-preview-page'));
            });
        });

        fetch(API_URL, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    loadData(res.data);
                    // Fallback clinic name / URL in preview if provided
                    const urlEl = document.getElementById('previewClinicUrl');
                    if (urlEl && res.data.clinic_name) {
                        urlEl.textContent = (res.data.clinic_name || 'clinic') + '.patientportal';
                    }
                }
            })
            .catch(() => {});

        // Ensure default preview state
        setPreviewPage('home');
    })();
</script>
</body>
</html>
