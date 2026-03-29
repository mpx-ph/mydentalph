<!DOCTYPE html>

<html class="light scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
              "surface-container": "#e6effc",
              "on-primary": "#ffffff",
            },
            fontFamily: {
              "headline": ["Manrope", "sans-serif"],
              "body": ["Inter", "sans-serif"],
              "editorial": ["Playfair Display", "serif"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px"},
          },
        },
      }
    </script>
<style>
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
        h1, h2, h3, h4 { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .service-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(43, 139, 235, 0.15);
        }
    </style>
</head>
<body class="text-on-surface overflow-x-hidden font-body bg-white">
<!-- Navigation -->
<nav class="fixed top-0 z-50 w-full bg-white/90 backdrop-blur-xl border-b border-primary/10">
<div class="flex justify-between items-center h-20 px-8 max-w-screen-2xl mx-auto">
<div class="text-2xl font-bold tracking-tighter font-headline flex items-center gap-2 text-primary">
<div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-white text-lg">dentistry</span>
</div> 
                Your Logo Here
            </div>
<div class="hidden md:flex items-center space-x-12 text-sm font-bold tracking-tight text-on-surface/70 font-headline uppercase">
<a class="hover:text-primary transition-colors" href="#">Home</a>
<a class="text-primary border-b-2 border-primary pb-1" href="#">Services</a>
<a class="hover:text-primary transition-colors" href="#">About Us</a>
<a class="hover:text-primary transition-colors" href="#">Contact Us</a>
</div>
<div class="flex items-center gap-6">
<button class="text-on-surface font-bold text-sm hover:text-primary transition-all uppercase tracking-wider">Login</button>
<button class="bg-primary text-white px-8 py-3 rounded-full font-bold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all active:scale-95 uppercase tracking-wider">
                    Download Our App
                </button>
</div>
</div>
</nav>
<main>
<!-- Hero Section -->
<section class="max-w-7xl mx-auto px-8 pt-44 pb-16 text-center">
<div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-[11px] font-extrabold uppercase tracking-[0.2em] mb-8">
                Clinically Proven Care
            </div>
<h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tight text-on-surface mb-6">
                Our Specialized <span class="text-primary">Services</span>
</h1>
<p class="text-on-surface-variant max-w-2xl mx-auto text-xl font-medium leading-relaxed">
                Elevating dental wellness through clinical mastery and curated patient experiences. Discover our full spectrum of elite treatments.
            </p>
</section>
<!-- Vertical Services List -->
<section class="max-w-5xl mx-auto px-8 pb-20 space-y-6">
<!-- Service Card 1 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white border border-primary/10 rounded-3xl shadow-sm gap-8">
<div class="w-16 h-16 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-4xl">medical_services</span>
</div>
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-headline">General Dentistry</h3>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed">Preventative care, professional cleanings, and precise digital diagnostics designed to maintain your peak oral health and wellness for a lifetime.</p>
</div>
<div class="shrink-0">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-primary/90 transition-all flex items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</button>
</div>
</div>
<!-- Service Card 2 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white border border-primary/10 rounded-3xl shadow-sm gap-8">
<div class="w-16 h-16 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-4xl">auto_awesome</span>
</div>
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-headline">Cosmetic Dentistry</h3>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed">Artistic smile transformations using premium porcelain veneers, professional whitening, and digital smile design for natural-looking perfection.</p>
</div>
<div class="shrink-0">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-primary/90 transition-all flex items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</button>
</div>
</div>
<!-- Service Card 3 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white border border-primary/10 rounded-3xl shadow-sm gap-8">
<div class="w-16 h-16 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-4xl">align_horizontal_center</span>
</div>
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-headline">Orthodontics</h3>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed">Advanced alignment solutions including clear aligners and modern braces tailored for both adults and teenagers to achieve a confident bite.</p>
</div>
<div class="shrink-0">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-primary/90 transition-all flex items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</button>
</div>
</div>
<!-- Service Card 4 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white border border-primary/10 rounded-3xl shadow-sm gap-8">
<div class="w-16 h-16 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-4xl">biotech</span>
</div>
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-headline">Oral Surgery</h3>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed">Specialized procedures including wisdom tooth extraction and dental implants performed with surgical precision and optimal patient comfort.</p>
</div>
<div class="shrink-0">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-primary/90 transition-all flex items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</button>
</div>
</div>
<!-- Service Card 5 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white border border-primary/10 rounded-3xl shadow-sm gap-8">
<div class="w-16 h-16 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary text-4xl">child_care</span>
</div>
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-headline">Pediatric Dentistry</h3>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed">Gentle, fun-focused dental care for our youngest patients, building a foundation for healthy smiles that last a lifetime in a warm environment.</p>
</div>
<div class="shrink-0">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-sm uppercase tracking-widest hover:bg-primary/90 transition-all flex items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</button>
</div>
</div>
</section>
<!-- Final CTA Section -->
<section class="max-w-7xl mx-auto px-8 pb-32">
<div class="rounded-3xl bg-primary p-12 md:p-20 text-center text-white shadow-xl shadow-primary/20">
<h2 class="font-headline text-4xl md:text-5xl font-extrabold tracking-tight mb-6">Ready to start your journey?</h2>
<p class="text-white/80 text-lg md:text-xl max-w-xl mx-auto mb-10 font-medium">Join thousands of happy patients who trust us with their oral health and aesthetic transformations.</p>
<button class="bg-white text-primary px-12 py-5 rounded-full font-extrabold text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-lg active:scale-95">
                    Book Your Consultation
                </button>
</div>
</section>
</main>
<!-- Footer -->
<footer class="w-full border-t border-primary/10 bg-surface">
<div class="flex flex-col md:flex-row justify-between items-center py-12 px-8 max-w-screen-2xl mx-auto gap-8">
<div class="text-lg font-bold text-primary font-headline flex items-center gap-2">
<div class="w-6 h-6 bg-primary rounded-md flex items-center justify-center">
<span class="material-symbols-outlined text-white text-[10px]">dentistry</span>
</div> 
                Your Logo Here
            </div>
<div class="flex flex-wrap justify-center gap-8 text-xs font-bold uppercase tracking-widest text-on-surface/50 font-headline">
<a class="hover:text-primary transition-all" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-all" href="#">Patient Forms</a>
<a class="hover:text-primary transition-all" href="#">Emergency Care</a>
<a class="hover:text-primary transition-all" href="#">Contact Us</a>
</div>
<div class="text-xs text-on-surface/40 font-bold uppercase tracking-widest">
                © 2024 Your Clinic Name. All rights reserved.
            </div>
</div>
</footer>
</body></html>