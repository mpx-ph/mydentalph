<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['onboarding_user_id']) || empty($_SESSION['onboarding_tenant_id'])) {
    header('Location: ProviderOTP.php');
    exit;
}

$tenant_id = (string) $_SESSION['onboarding_tenant_id'];
$user_id = (string) $_SESSION['onboarding_user_id'];

// Step guard: must have verified email first.
$email_verified = false;
try {
    $stmt = $pdo->prepare('SELECT 1 FROM tbl_email_verifications WHERE tenant_id = ? AND user_id = ? AND verified_at IS NOT NULL LIMIT 1');
    $stmt->execute([$tenant_id, $user_id]);
    $email_verified = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    $email_verified = false;
}

$session_email_verified_at = $_SESSION['onboarding_email_verified_at'] ?? 0;
$session_email_verified = is_numeric($session_email_verified_at) && (int) $session_email_verified_at > 0;
$email_verified = $email_verified || $session_email_verified;

if (!$email_verified) {
    header('Location: ProviderOTP.php');
    exit;
}

// Step guard: must have uploaded/received clinic verification docs.
$has_submitted_clinic_docs = false;
try {
    $stmt = $pdo->prepare('SELECT 1 FROM tbl_tenant_verification_requests WHERE tenant_id = ? AND submitted_at IS NOT NULL LIMIT 1');
    $stmt->execute([$tenant_id]);
    $has_submitted_clinic_docs = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    $has_submitted_clinic_docs = false;
}

$session_docs_submitted_at = $_SESSION['onboarding_clinic_docs_submitted_at'] ?? 0;
$session_docs_submitted = is_numeric($session_docs_submitted_at) && (int) $session_docs_submitted_at > 0;
$has_submitted_clinic_docs = $has_submitted_clinic_docs || $session_docs_submitted;

if (!$has_submitted_clinic_docs) {
    header('Location: ProviderClinicVerif.php');
    exit;
}
?>
<!DOCTYPE html>

<html class="light scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Application Received | Aetheris OS</title>
<!-- Google Fonts: Manrope, Inter, Playfair Display -->
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "#2b8beb",
              "on-surface": "#131c25",
              "surface": "#ffffff",
              "surface-variant": "#f7f9ff",
              "on-surface-variant": "#404752",
              "outline-variant": "#c0c7d4",
              "primary-fixed": "#d4e3ff",
              "on-primary-fixed-variant": "#004883",
              "surface-container-low": "#edf4ff",
              "inverse-surface": "#131c25",
              "surface-container-lowest": "#ffffff",
              "on-primary": "#ffffff"
            },
            fontFamily: {
              "headline": ["Manrope", "sans-serif"],
              "body": ["Inter", "sans-serif"],
              "label": ["Inter", "sans-serif"],
              "editorial": ["Playfair Display", "serif"]
            },
            borderRadius: {
                "DEFAULT": "0.25rem", 
                "lg": "0.5rem", 
                "xl": "0.75rem", 
                "2xl": "1.5rem", 
                "3xl": "2.5rem", 
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
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image: 
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-body min-h-screen flex flex-col mesh-gradient">
<!-- Navigation -->
<nav class="fixed top-0 z-50 w-full bg-white/80 backdrop-blur-xl shadow-sm">
<div class="flex justify-between items-center h-20 px-8 max-w-screen-2xl mx-auto">
<div class="text-2xl font-bold tracking-tighter font-headline flex items-center gap-2">
<div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
<span class="material-symbols-outlined text-white text-lg">select_check_box</span>
</div>
                Aetheris
            </div>
<div class="hidden md:flex items-center space-x-12 text-sm font-semibold tracking-tight text-on-surface/60 font-headline">
<a class="text-primary border-b-2 border-primary pb-1" href="#">Home</a>
<a class="hover:text-primary transition-colors" href="#">Features</a>
<a class="hover:text-primary transition-colors" href="#">Pricing</a>
<a class="hover:text-primary transition-colors" href="#">Contact Us</a>
<a class="hover:text-primary transition-colors" href="#">FAQs</a>
</div>
<div class="flex items-center gap-4">
<button class="text-on-surface font-semibold text-sm hover:text-primary transition-all font-headline">Login</button>
<button class="bg-primary text-white px-6 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all active:scale-95 font-headline">
                    Get Started
                </button>
</div>
</div>
</nav>
<main class="flex-grow flex items-center justify-center px-6 pt-32 pb-16">
<!-- Main Application Card -->
<div class="max-w-3xl w-full">
<div class="bg-surface-container-lowest rounded-[3rem] p-10 md:p-20 shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)] text-center relative overflow-hidden border border-on-surface/5">
<!-- Success Icon Cluster -->
<div class="mb-10 inline-flex items-center justify-center">
<div class="relative">
<div class="absolute inset-0 bg-primary/10 rounded-full scale-150 blur-xl"></div>
<div class="relative bg-surface-container-low text-primary w-24 h-24 rounded-full flex items-center justify-center">
<span class="material-symbols-outlined !text-5xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
</div>
</div>
</div>
<!-- Header with Styled Treatment -->
<h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter leading-[1.1] text-on-surface mb-8">
                    Application <br/>
<span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Received</span>
</h1>
<!-- Message Content -->
<div class="max-w-md mx-auto mb-12">
<p class="text-on-surface-variant text-xl leading-relaxed font-medium">
                        Application received. We will review your business documents and email you a secure payment link once approved.
                    </p>
</div>
<!-- Action Section -->
<div class="flex flex-col items-center gap-8">
<button class="group relative px-12 py-5 bg-primary text-white font-bold rounded-full overflow-hidden transition-all hover:pr-16 active:scale-95 shadow-xl shadow-primary/20">
<span class="relative z-10 font-headline text-base uppercase tracking-widest">Go to Dashboard</span>
<span class="material-symbols-outlined absolute right-6 opacity-0 group-hover:opacity-100 transition-all">arrow_right_alt</span>
</button>
<!-- Status Note -->
<div class="flex items-center gap-3 px-8 py-4 bg-surface-container-low rounded-2xl border border-primary/10">
<span class="material-symbols-outlined text-primary text-2xl">schedule</span>
<p class="text-sm font-semibold text-on-surface-variant">
                            Review may take 24–48 hours. Please check your inbox regularly.
                        </p>
</div>
</div>
<!-- Decorative Detail -->
<div class="mt-20 pt-10 border-t border-on-surface/5">
<p class="text-[10px] uppercase tracking-[0.4em] font-black text-primary/60 font-headline">
                        Aetheris Systems • Precision Dental Framework
                    </p>
</div>
</div>
<!-- Contextual Help Link -->
<div class="mt-10 text-center">
<a class="text-sm font-bold text-primary hover:underline transition-all flex items-center justify-center gap-2 font-headline uppercase tracking-widest" href="#">
                    Need urgent assistance? Contact Support
                    <span class="material-symbols-outlined text-lg">open_in_new</span>
</a>
</div>
</div>
</main>
<!-- Footer -->
<footer class="w-full border-t border-slate-200 bg-slate-50 mt-auto">
<div class="flex flex-col md:flex-row justify-between items-center py-12 px-8 max-w-screen-2xl mx-auto gap-4">
<div class="text-lg font-bold text-slate-900 font-headline">Aetheris Systems</div>
<div class="flex flex-wrap justify-center gap-8 text-xs font-medium font-body text-slate-500">
<a class="hover:text-primary transition-all" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-all" href="#">Terms of Service</a>
<a class="hover:text-primary transition-all" href="#">Interoperability Standards</a>
<a class="hover:text-primary transition-all" href="#">Contact Sales</a>
</div>
<div class="text-xs text-slate-500 font-body opacity-80">
                © 2024 Clinical Precision Framework. All rights reserved.
            </div>
</div>
</footer>
</body></html>