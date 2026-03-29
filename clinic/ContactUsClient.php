<?php
/**
 * Contact Us Page
 */
$pageTitle = 'Contact Us - Dental Care Plus';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/header.php';
$cu = function($k) use ($CLINIC) { return isset($CLINIC[$k]) ? htmlspecialchars($CLINIC[$k], ENT_QUOTES, 'UTF-8') : ''; };
?>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" rel="stylesheet"/>
<style>
.font-headline { font-family: Manrope, ui-sans-serif, system-ui, sans-serif; }
.font-body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.font-editorial { font-family: "Playfair Display", Georgia, serif; }
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}
.mesh-gradient {
    background-color: #ffffff;
    background-image:
        radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
        radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
}
.dark .mesh-gradient {
    background-color: rgb(15 23 42);
    background-image:
        radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.15) 0px, transparent 50%),
        radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.08) 0px, transparent 50%);
}
.editorial-word {
    text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
    letter-spacing: -0.02em;
}
.contact-hero {
    background: linear-gradient(180deg, #ffffff 0%, #f3f8fd 45%, #eef5fb 100%);
}
.dark .contact-hero {
    background: linear-gradient(180deg, rgb(15 23 42) 0%, rgb(17 24 39) 50%, rgb(15 23 42) 100%);
}
.contact-hero-badge {
    background-color: #e3f0fa;
    color: #5c9bd1;
    letter-spacing: 0.35em;
}
.dark .contact-hero-badge {
    background-color: rgba(92, 155, 209, 0.15);
    color: #7eb8e0;
}
.contact-hero-title {
    color: #1a1a1b;
}
.dark .contact-hero-title {
    color: #f8fafc;
}
.contact-hero-editorial {
    color: #2185d5;
}
.dark .contact-hero-editorial {
    color: #4da3e6;
}
.contact-hero-sub {
    color: #5c6670;
}
.dark .contact-hero-sub {
    color: #94a3b8;
}
</style>
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<main class="mesh-gradient pt-24 flex-grow w-full min-h-screen">
<!-- Hero Section -->
<section class="contact-hero py-20 md:py-28 lg:py-32 text-center px-4 sm:px-6 overflow-hidden">
<div class="max-w-3xl mx-auto flex flex-col items-center">
<div class="inline-flex items-center justify-center px-4 py-2 rounded-full contact-hero-badge text-[10px] font-black uppercase mb-8 sm:mb-10 font-headline">
                    Reach out to excellence
                </div>
<h1 class="font-headline text-[clamp(2.75rem,7vw,4.75rem)] font-extrabold tracking-[-0.04em] mb-6 sm:mb-8 leading-[1.05]">
<span class="contact-hero-title">Get in </span><span class="font-editorial italic font-normal contact-hero-editorial editorial-word transform -skew-x-6 inline-block">Touch.</span>
</h1>
<p class="font-body text-lg sm:text-xl max-w-2xl mx-auto leading-relaxed font-medium contact-hero-sub">
                    <?php echo $cu('contact_hero_subtext'); ?>
                </p>
</div>
</section>
<!-- Form & Info Section -->
<section class="max-w-[1800px] mx-auto px-10 mb-24">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
<!-- Left Column: Contact Information -->
<div class="space-y-6">
<div class="bg-white dark:bg-slate-800 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 shadow-[0_20px_50px_-15px_rgba(43,139,235,0.05)] space-y-12">
<div>
<div class="text-primary font-bold text-xs uppercase mb-10 flex items-center gap-4 tracking-[0.3em] font-headline">
<span class="w-12 h-[1.5px] bg-primary"></span> Contact Information
                            </div>
<div class="space-y-10">
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">location_on</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight">Office Address</p>
<p class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body"><?php echo nl2br($cu('contact_address')); ?></p>
<?php if (trim($CLINIC['contact_map_link'] ?? '')): ?>
<a class="text-primary text-sm font-bold mt-2 inline-flex items-center gap-1 hover:underline font-headline" href="<?php echo $cu('contact_map_link'); ?>" target="_blank" rel="noopener noreferrer">Get directions <span class="material-symbols-outlined text-base">arrow_forward</span></a>
<?php endif; ?>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">call</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight">Phone</p>
<a class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body hover:text-primary" href="tel:<?php echo preg_replace('/\s+/', '', $cu('contact_phone')); ?>"><?php echo $cu('contact_phone'); ?></a>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">mail</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight">Email</p>
<a class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body hover:text-primary break-all" href="mailto:<?php echo $cu('contact_email'); ?>"><?php echo $cu('contact_email'); ?></a>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">schedule</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight">Hours</p>
<p class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body">
<span class="block">Mon - Fri: <?php echo $cu('contact_hours_mon_fri'); ?></span>
<span class="block">Saturday: <?php echo $cu('contact_hours_sat'); ?></span>
<span class="block">Sunday: <?php echo $cu('contact_hours_sun'); ?></span>
</p>
</div>
</div>
</div>
</div>
</div>
</div>
<!-- Right Column: Contact Form -->
<div class="glass-card dark:bg-slate-800/80 p-12 rounded-[2.5rem] shadow-[0_40px_100px_-30px_rgba(43,139,235,0.15)] border border-primary/10 border-t-4 border-t-primary relative overflow-hidden">
<div id="formMessage" class="hidden mb-6 p-4 rounded-xl border font-medium text-sm"></div>
<form class="space-y-8" id="contactForm" onsubmit="handleFormSubmit(event);">
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 font-headline text-primary/70" for="name">Full Name</label>
<div class="relative">
<input class="w-full bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-6 py-5 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="name" placeholder="John Doe" type="text"/>
</div>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 font-headline text-primary/70" for="email">Email</label>
<input class="w-full bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-6 py-5 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="email" placeholder="john@example.com" type="email"/>
</div>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 font-headline text-primary/70" for="phone">Phone</label>
<input class="w-full bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-6 py-5 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="phone" placeholder="(555) 123-4567" type="tel"/>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 font-headline text-primary/70" for="concern">Subject</label>
<select class="w-full bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-6 py-5 rounded-2xl text-slate-900 dark:text-white font-medium outline-none cursor-pointer appearance-none" id="concern">
<option disabled="" selected="" value="">Select reason...</option>
<option value="appointment">New Appointment</option>
<option value="general">General Inquiry</option>
<option value="billing">Billing Question</option>
<option value="emergency">Emergency</option>
</select>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 font-headline text-primary/70" for="message">Message</label>
<textarea class="w-full bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-6 py-5 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none resize-none" id="message" placeholder="How can we help you today?" rows="5"></textarea>
</div>
<div class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
<span class="leading-relaxed font-body">This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Terms of Service</a> apply.</span>
</div>
<button class="w-full bg-primary text-white font-headline font-black text-sm uppercase tracking-[0.2em] py-6 rounded-2xl shadow-[0_20px_40px_-10px_rgba(43,139,235,0.4)] hover:shadow-[0_25px_50px_-12px_rgba(43,139,235,0.6)] hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3 group" type="submit">
<span>Send Message</span><span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
</button>
</form>
</div>
</div>
</section>
<!-- Map Section -->
<?php if (trim($CLINIC['contact_map_embed'] ?? '')): ?>
<section class="max-w-[1800px] mx-auto px-10 mb-24">
<div class="relative w-full h-[400px] md:h-[500px] lg:h-[600px] rounded-[4rem] overflow-hidden bg-slate-100 dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700">
<iframe
    class="w-full h-full border-0"
    src="<?php echo $cu('contact_map_embed'); ?>"
    allowfullscreen=""
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    title="Clinic Location">
</iframe>
</div>
</section>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js?render=6LfQuT4sAAAAAAxKDsMLjv-15e2km5ytXY6hJbSOY"></script>
<script>
        const RECAPTCHA_SITE_KEY = '6LfQuT4sAAAAAAxKDsMLjv-15e2km5ytXY6hJbSOY';

        (function(){
            emailjs.init("yF8aTwk2JYrSOIn02");
        })();

        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const mobileMenu = document.getElementById('mobileMenu');
            const menuIcon = document.getElementById('menuIcon');

            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    const isHidden = mobileMenu.classList.contains('hidden');

                    if (isHidden) {
                        mobileMenu.classList.remove('hidden');
                        menuIcon.textContent = 'close';
                    } else {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    }
                });

                const mobileMenuLinks = mobileMenu.querySelectorAll('a');
                mobileMenuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    });
                });

                document.addEventListener('click', function(event) {
                    if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                        if (!mobileMenu.classList.contains('hidden')) {
                            mobileMenu.classList.add('hidden');
                            menuIcon.textContent = 'menu';
                        }
                    }
                });
            }
        });

        async function handleFormSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('contactForm');
            const messageDiv = document.getElementById('formMessage');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;

            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const concern = document.getElementById('concern').value;
            const message = document.getElementById('message').value.trim();

            messageDiv.classList.add('hidden');

            if (!name || !email || !phone || !concern || !message) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Sending...</span>';

            try {
                let recaptchaToken = '';
                if (RECAPTCHA_SITE_KEY) {
                    try {
                        recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' });
                    } catch (recaptchaError) {
                        console.error('reCAPTCHA Error:', recaptchaError);
                    }
                }

                const templateParams = {
                    user_name: name,
                    user_email: email,
                    phone_number: phone,
                    concern_type: concern,
                    user_message: message,
                    recaptcha_token: recaptchaToken
                };

                await emailjs.send(
                    'service_q99148g',
                    'template_1w5v9oe',
                    templateParams
                );

                showMessage('Thank you! Your message has been sent successfully. We\'ll get back to you within 24 hours.', 'success');
                form.reset();

            } catch (error) {
                console.error('EmailJS Error:', error);
                showMessage('Sorry, there was an error sending your message. Please try again or contact us directly at hello@drcgdental.com', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }

        function showMessage(text, type) {
            const messageDiv = document.getElementById('formMessage');
            messageDiv.textContent = text;
            messageDiv.classList.remove('hidden');

            if (type === 'success') {
                messageDiv.className = 'mb-6 p-4 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-medium text-sm';
            } else {
                messageDiv.className = 'mb-6 p-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-medium text-sm';
            }

            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
