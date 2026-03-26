<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinic Approvals | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-error": "#ffffff",
                        "on-tertiary-fixed-variant": "#6e3900",
                        "on-surface": "#131c25",
                        "primary-fixed-dim": "#a4c9ff",
                        "on-secondary-fixed": "#001c39",
                        "surface-container-high": "#e0e9f6",
                        "on-background": "#131c25",
                        "inverse-on-surface": "#e8f1ff",
                        "tertiary-container": "#b25f00",
                        "surface-bright": "#f7f9ff",
                        "secondary-fixed-dim": "#adc8f3",
                        "surface-variant": "#dae3f0",
                        "on-tertiary": "#ffffff",
                        "outline": "#717784",
                        "inverse-surface": "#28313b",
                        "on-primary-container": "#fdfcff",
                        "inverse-primary": "#a4c9ff",
                        "secondary-container": "#b8d3fe",
                        "error-container": "#ffdad6",
                        "primary-container": "#0076d2",
                        "on-secondary-container": "#405b80",
                        "surface": "#f7f9ff",
                        "on-secondary": "#ffffff",
                        "on-primary": "#ffffff",
                        "on-primary-fixed": "#001c39",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed-dim": "#ffb77e",
                        "surface-dim": "#d2dbe8",
                        "on-tertiary-container": "#fffbff",
                        "on-error-container": "#93000a",
                        "background": "#f7f9ff",
                        "surface-tint": "#0060ac",
                        "surface-container": "#e6effc",
                        "tertiary": "#8e4a00",
                        "primary-fixed": "#d4e3ff",
                        "on-tertiary-fixed": "#2f1500",
                        "surface-container-low": "#edf4ff",
                        "tertiary-fixed": "#ffdcc3",
                        "primary": "#0066ff",
                        "surface-container-highest": "#dae3f0",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "secondary": "#456085",
                        "secondary-fixed": "#d4e3ff",
                        "on-secondary-fixed-variant": "#2c486c"
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
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
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface selection:bg-primary/10 min-h-screen">
<?php
$superadmin_nav = 'superadmin_approval';
$superadmin_header_center = '<h2 class="text-2xl font-headline font-extrabold text-[#131c25] tracking-tight">Clinic Approvals</h2>';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<!-- Main Content Canvas -->
<main class="ml-64 flex-grow flex flex-col min-h-screen">
<!-- Content Split View -->
<div class="pt-20 flex flex-grow overflow-hidden relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Left Side: Pending List (60%) -->
<section class="w-3/5 p-10 overflow-y-auto space-y-8 no-scrollbar">
<div class="flex items-center justify-between mb-2">
<div class="flex gap-2 p-1.5 bg-white/40 backdrop-blur-md rounded-2xl border border-white/60">
<button class="px-5 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-lg shadow-primary/20">Pending (12)</button>
<button class="px-5 py-2 rounded-xl text-on-surface-variant text-xs font-bold hover:bg-white/50 transition-colors">Approved</button>
<button class="px-5 py-2 rounded-xl text-on-surface-variant text-xs font-bold hover:bg-white/50 transition-colors">Rejected</button>
</div>
<button class="flex items-center gap-2 text-primary text-xs font-bold hover:opacity-80 transition-opacity px-4 py-2 bg-white/60 rounded-xl border border-white">
<span class="material-symbols-outlined text-lg" data-icon="filter_list">filter_list</span>
                        Sort by Date
                    </button>
</div>
<!-- Clinic Card List -->
<div class="space-y-6">
<!-- Selected Card -->
<div class="bg-white/80 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border-l-[6px] border-primary transition-all cursor-pointer group active-glow">
<div class="flex items-start justify-between">
<div class="flex gap-5">
<div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-primary group-hover:scale-105 transition-transform">
<span class="material-symbols-outlined text-3xl" data-icon="dentistry">dentistry</span>
</div>
<div>
<h3 class="font-headline font-extrabold text-on-surface text-lg">Bright Smiles Dental Hub</h3>
<p class="text-on-surface-variant text-xs font-medium mt-1">Owner: Dr. Helena Vance</p>
<div class="flex items-center gap-4 mt-4 text-[11px] text-on-surface-variant/70 font-bold uppercase tracking-widest">
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="mail">mail</span> helena.v@brightsmiles.com</span>
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="calendar_today">calendar_today</span> Oct 24, 2023</span>
</div>
</div>
</div>
<span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-[10px] font-extrabold uppercase tracking-widest border border-amber-100">Pending Review</span>
</div>
</div>
<!-- Other Cards -->
<div class="bg-white/40 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border border-white/60 hover:bg-white/60 transition-all cursor-pointer group">
<div class="flex items-start justify-between">
<div class="flex gap-5">
<div class="w-14 h-14 rounded-2xl bg-surface-container-high/50 flex items-center justify-center text-on-surface-variant/60">
<span class="material-symbols-outlined text-3xl" data-icon="medical_services">medical_services</span>
</div>
<div>
<h3 class="font-headline font-extrabold text-on-surface text-lg">Metro Health Specialist Clinic</h3>
<p class="text-on-surface-variant text-xs font-medium mt-1">Owner: Michael Chen, MBA</p>
<div class="flex items-center gap-4 mt-4 text-[11px] text-on-surface-variant/70 font-bold uppercase tracking-widest">
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="mail">mail</span> admin@metrohealth.ph</span>
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="calendar_today">calendar_today</span> Oct 22, 2023</span>
</div>
</div>
</div>
<span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-[10px] font-extrabold uppercase tracking-widest border border-amber-100">Pending Review</span>
</div>
</div>
<div class="bg-white/40 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border border-white/60 hover:bg-white/60 transition-all cursor-pointer group">
<div class="flex items-start justify-between">
<div class="flex gap-5">
<div class="w-14 h-14 rounded-2xl bg-surface-container-high/50 flex items-center justify-center text-on-surface-variant/60">
<span class="material-symbols-outlined text-3xl" data-icon="radiology">radiology</span>
</div>
<div>
<h3 class="font-headline font-extrabold text-on-surface text-lg">Zenith Imaging Center</h3>
<p class="text-on-surface-variant text-xs font-medium mt-1">Owner: Roberto San Diego</p>
<div class="flex items-center gap-4 mt-4 text-[11px] text-on-surface-variant/70 font-bold uppercase tracking-widest">
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="mail">mail</span> imaging@zenith.com</span>
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="calendar_today">calendar_today</span> Oct 21, 2023</span>
</div>
</div>
</div>
<span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-[10px] font-extrabold uppercase tracking-widest border border-amber-100">Pending Review</span>
</div>
</div>
<div class="bg-white/40 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border border-white/60 hover:bg-white/60 transition-all cursor-pointer group">
<div class="flex items-start justify-between">
<div class="flex gap-5">
<div class="w-14 h-14 rounded-2xl bg-surface-container-high/50 flex items-center justify-center text-on-surface-variant/60">
<span class="material-symbols-outlined text-3xl" data-icon="eye_tracking">eye_tracking</span>
</div>
<div>
<h3 class="font-headline font-extrabold text-on-surface text-lg">Visionary Optometry</h3>
<p class="text-on-surface-variant text-xs font-medium mt-1">Owner: Dr. Sarah Lopez</p>
<div class="flex items-center gap-4 mt-4 text-[11px] text-on-surface-variant/70 font-bold uppercase tracking-widest">
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="mail">mail</span> contact@visionary.ph</span>
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base" data-icon="calendar_today">calendar_today</span> Oct 20, 2023</span>
</div>
</div>
</div>
<span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 text-[10px] font-extrabold uppercase tracking-widest border border-amber-100">Pending Review</span>
</div>
</div>
</div>
</section>
<!-- Right Side: Details Panel (40%) -->
<aside class="w-2/5 border-l border-white/40 bg-white/30 backdrop-blur-md p-10 overflow-y-auto no-scrollbar">
<div class="space-y-10">
<!-- Header -->
<div class="space-y-6">
<div class="flex items-center justify-between">
<h4 class="font-headline font-extrabold text-2xl text-on-surface">Review Details</h4>
<span class="text-[10px] font-extrabold text-primary px-3 py-1.5 bg-blue-50 rounded-xl border border-blue-100 uppercase tracking-widest">REF: #CL-8829</span>
</div>
<div class="p-8 bg-gradient-to-br from-primary via-[#1a80ff] to-[#0052cc] rounded-[2rem] text-white shadow-xl shadow-primary/20 relative overflow-hidden group">
<div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-[40px] group-hover:bg-white/20 transition-all duration-700"></div>
<h3 class="font-headline font-extrabold text-xl relative z-10">Bright Smiles Dental Hub</h3>
<p class="text-blue-100 text-sm font-medium opacity-90 relative z-10 mt-1">Dental and Cosmetic Surgery</p>
<div class="mt-8 space-y-4 relative z-10">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-xl opacity-80" data-icon="location_on">location_on</span>
<span class="text-xs font-medium leading-relaxed opacity-90">Suite 402, High Street Corporate Plaza, Bonifacio Global City, Taguig, 1634</span>
</div>
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-xl opacity-80" data-icon="call">call</span>
<span class="text-xs font-medium opacity-90">+63 917 123 4567</span>
</div>
</div>
</div>
</div>
<!-- Document Review -->
<div class="space-y-6">
<h5 class="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-[0.2em] opacity-60">Submitted Documents</h5>
<div class="grid grid-cols-2 gap-4">
<div class="group relative aspect-[4/3] rounded-2xl overflow-hidden editorial-shadow bg-white/40 border border-white cursor-pointer">
<img alt="SEC Registration" class="w-full h-full object-cover opacity-60 group-hover:scale-110 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD_ZKWL1ueYY6THrElRatbQMvqTecvxMZTv0t5QrncejqK3v8rc7Wc2z4peKdMcIV7vRyOI9vX59XYGngHILXgg337L_en3EwLxx-86zAEThi5FyuuUMchEQU-9UiJEtrVGxgC5xvy77OaOfUPOFxF06ZXQ2vapc0AyG4N_0HmH-lUL4qyAIgGAbJKIPg8L9gegZcgpMuIoLmP5-edRf-RstYBL1DfmFAxwQf9hLfBzmPEqtiDjC3E_k9sBI5jokBudhhC3qI35Kzw"/>
<div class="absolute inset-0 bg-primary/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
<span class="material-symbols-outlined text-white text-3xl" data-icon="zoom_in">zoom_in</span>
<span class="text-white text-[10px] font-extrabold tracking-widest">VIEW SEC</span>
</div>
<div class="absolute bottom-3 left-3 px-2 py-1 bg-white/90 backdrop-blur-md rounded-lg text-[9px] font-bold text-on-surface border border-white">SEC_CERT.PDF</div>
</div>
<div class="group relative aspect-[4/3] rounded-2xl overflow-hidden editorial-shadow bg-white/40 border border-white cursor-pointer">
<img alt="DTI Permit" class="w-full h-full object-cover opacity-60 group-hover:scale-110 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBpoP5Sf-5igvvUPsWsXpj7CLC1maP_7NJomEYDj4MgRYOoivTaubGOvKLmsSxwTOT2blQ2ucALqzcT1KzWAdkxMy4fMX765Qdjpb1QkNyi2jt3Jt0v8S-kqgiFhPKoewFHqS0Uu9NSU2tJe3_jWrCjHqYOO4ONqSmB9g2qXdvdEjHvA7Nn3muCGzSsDjHS3oKSg5Y8VlJ0RI7fWFHiWlke8k7pwSNh1nhwsl8JMhj4EkliuQ0szwbUwhimbXQCgYxuL0nO495fEjw"/>
<div class="absolute inset-0 bg-primary/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
<span class="material-symbols-outlined text-white text-3xl" data-icon="zoom_in">zoom_in</span>
<span class="text-white text-[10px] font-extrabold tracking-widest">VIEW DTI</span>
</div>
<div class="absolute bottom-3 left-3 px-2 py-1 bg-white/90 backdrop-blur-md rounded-lg text-[9px] font-bold text-on-surface border border-white">DTI_PERMIT.JPG</div>
</div>
<div class="group relative aspect-[4/3] rounded-2xl overflow-hidden editorial-shadow bg-white/40 border border-white cursor-pointer">
<img alt="BIR Form 2303" class="w-full h-full object-cover opacity-60 group-hover:scale-110 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA2f06kbyH9dtNuUtwfNMkszWEjjAMv_jOuCjRQgTbk19c5QQxw3FsOaanJdr-0Kxx75P4FGF5eXemmsnK19vYeyuWQoCSV8N5V4rNHvG2-r9mp9w1LIh54x48Y_g_0LtojzxHvyiBm95z6u3tpu2jlh1Exg-1UWumgXBG4rT3TgCsKajg8oZBEl0_YUUN0-2QmKDMw_wiKKnODsKds4Ug8jbdX2nG-rouA1BxWLW8OsAfXXXqgfHHrFKSEPBO9StQq0_nG9DmOogc"/>
<div class="absolute inset-0 bg-primary/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
<span class="material-symbols-outlined text-white text-3xl" data-icon="zoom_in">zoom_in</span>
<span class="text-white text-[10px] font-extrabold tracking-widest">VIEW BIR</span>
</div>
<div class="absolute bottom-3 left-3 px-2 py-1 bg-white/90 backdrop-blur-md rounded-lg text-[9px] font-bold text-on-surface border border-white">BIR_2303.PDF</div>
</div>
<div class="group relative aspect-[4/3] rounded-2xl overflow-hidden editorial-shadow bg-white/40 border border-white cursor-pointer">
<img alt="Mayor's Permit" class="w-full h-full object-cover opacity-60 group-hover:scale-110 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC-k_x1zBWgzsV71xot0NrKvP3aEojv12ZtAfPqnyc8WQKlLBg4oyxABahErcXBwVYxCnzvqssv05wMnHuSVk_KW5JWIRkat_jTSTiTJGLtVljmniu45ePtaMe7lqotz2y2oJVj1uzcGf2XnZq_mn6WUAmCmM1R2MwL7qMoGwQl4rao-o3SN7P_OgZxh_4Y7DzbqSFAUT5qx0auKnDHmRIZpW-wsAfByBoBc-O62q24UcnVEvDxFolUZXaO5RcZR9Cs6oSEkNxFd_w"/>
<div class="absolute inset-0 bg-primary/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
<span class="material-symbols-outlined text-white text-3xl" data-icon="zoom_in">zoom_in</span>
<span class="text-white text-[10px] font-extrabold tracking-widest">VIEW PERMIT</span>
</div>
<div class="absolute bottom-3 left-3 px-2 py-1 bg-white/90 backdrop-blur-md rounded-lg text-[9px] font-bold text-on-surface border border-white">MAYOR_2023.PDF</div>
</div>
</div>
</div>
<!-- Verification Checklist -->
<div class="bg-white/60 backdrop-blur-md rounded-[2rem] p-8 editorial-shadow space-y-5">
<div class="flex items-center justify-between border-b border-on-surface/5 pb-4">
<span class="text-[11px] font-extrabold text-on-surface-variant uppercase tracking-widest">Background Check</span>
<span class="text-[10px] text-primary font-bold bg-blue-50 px-2 py-1 rounded-lg">AUTO-VERIFIED</span>
</div>
<div class="space-y-4">
<div class="flex items-center gap-4 group">
<div class="w-2.5 h-2.5 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.4)] transition-transform group-hover:scale-125"></div>
<span class="text-xs font-semibold text-on-surface/80">No duplicate TIN found in system</span>
</div>
<div class="flex items-center gap-4 group">
<div class="w-2.5 h-2.5 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.4)] transition-transform group-hover:scale-125"></div>
<span class="text-xs font-semibold text-on-surface/80">License #PRC-00921 is active</span>
</div>
</div>
</div>
<!-- Action Buttons -->
<div class="pt-10 border-t border-white/60 flex flex-col gap-4">
<button class="w-full bg-primary text-white font-headline font-extrabold py-5 rounded-[2rem] primary-glow flex items-center justify-center gap-3 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-2xl" data-icon="check_circle">check_circle</span>
                            Approve Clinic Access
                        </button>
<button class="w-full bg-white/80 border border-error/20 text-error font-headline font-extrabold py-4.5 rounded-[2rem] hover:bg-error/5 transition-all editorial-shadow flex items-center justify-center gap-3 active:scale-[0.98]">
<span class="material-symbols-outlined text-2xl" data-icon="cancel">cancel</span>
                            Reject Registration
                        </button>
<p class="text-[10px] text-center text-on-surface-variant/60 mt-2 font-bold uppercase tracking-widest leading-relaxed">
                            Final approval will trigger automated onboarding emails to clinic administrator.
                        </p>
</div>
</div>
</aside>
</div>
</main>
</body></html>