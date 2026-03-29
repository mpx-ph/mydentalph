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
<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js?render=6LfQuT4sAAAAAAxKDsMLjv-15e2km5ytXY6hJbSOY"></script>
<style>
.contact-mesh-gradient {
    background-color: #ffffff;
    background-image:
        radial-gradient(at 100% 0%, rgba(43, 140, 238, 0.08) 0px, transparent 50%),
        radial-gradient(at 0% 100%, rgba(43, 140, 238, 0.04) 0px, transparent 50%);
}
.dark .contact-mesh-gradient {
    background-color: #0f172a;
    background-image:
        radial-gradient(at 100% 0%, rgba(43, 140, 238, 0.12) 0px, transparent 50%),
        radial-gradient(at 0% 100%, rgba(43, 140, 238, 0.06) 0px, transparent 50%);
}
.contact-editorial-word {
    text-shadow: 0 0 12px rgba(43, 140, 238, 0.1);
    letter-spacing: -0.02em;
}
.contact-form-panel {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.dark .contact-form-panel {
    background: rgba(30, 41, 59, 0.85);
}
</style>
<div class="relative flex min-h-screen w-full flex-col">
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<main class="contact-mesh-gradient flex-grow w-full pt-20 md:pt-24 min-h-screen">

<section class="py-14 md:py-20 lg:py-24 text-center px-4 md:px-6 overflow-hidden">
<div class="max-w-[1800px] mx-auto">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.35em] mb-8 md:mb-10">
Reach Out
</div>
<h1 class="font-display text-[clamp(2.5rem,6vw,4.5rem)] md:text-[clamp(3rem,7vw,5.5rem)] font-extrabold tracking-[-0.05em] text-slate-900 dark:text-white mb-6 md:mb-8 leading-[0.95] text-balance max-w-4xl mx-auto">
<?php echo $cu('contact_hero_heading'); ?>
</h1>
<p class="font-body text-lg md:text-xl max-w-2xl mx-auto leading-relaxed text-slate-600 dark:text-slate-400 font-medium text-balance">
<?php echo $cu('contact_hero_subtext'); ?>
</p>
</div>
</section>

<section class="max-w-[1800px] mx-auto px-4 sm:px-6 md:px-10 mb-16 md:mb-24">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-start">
<div class="space-y-6">
<div class="bg-white dark:bg-slate-800 p-8 md:p-12 rounded-[2.5rem] border border-slate-100 dark:border-slate-700 shadow-[0_20px_50px_-15px_rgba(43,140,238,0.08)] space-y-10">
<div>
<div class="text-primary font-bold text-xs uppercase mb-8 md:mb-10 flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> Contact Information
</div>
<div class="space-y-8 md:space-y-10">
<div class="flex items-start gap-5 md:gap-6 group">
<div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-primary-light dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110 shrink-0">
<span class="material-symbols-outlined text-2xl md:text-3xl font-light">location_on</span>
</div>
<div>
<p class="font-display font-extrabold text-slate-900 dark:text-white text-lg md:text-xl mb-1 tracking-tight">Visit Us</p>
<p class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed"><?php echo nl2br($cu('contact_address')); ?></p>
<a class="inline-flex items-center gap-1 text-primary text-sm font-bold mt-3 hover:gap-2 transition-all" href="<?php echo $cu('contact_map_link'); ?>" target="_blank" rel="noopener noreferrer">Get directions <span class="material-symbols-outlined text-base">arrow_forward</span></a>
</div>
</div>
<div class="flex items-start gap-5 md:gap-6 group">
<div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-primary-light dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110 shrink-0">
<span class="material-symbols-outlined text-2xl md:text-3xl font-light">call</span>
</div>
<div>
<p class="font-display font-extrabold text-slate-900 dark:text-white text-lg md:text-xl mb-1 tracking-tight">Phone</p>
<a class="text-slate-600 dark:text-slate-400 font-medium hover:text-primary transition-colors" href="tel:<?php echo preg_replace('/\s+/', '', $cu('contact_phone')); ?>"><?php echo $cu('contact_phone'); ?></a>
</div>
</div>
<div class="flex items-start gap-5 md:gap-6 group">
<div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-primary-light dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110 shrink-0">
<span class="material-symbols-outlined text-2xl md:text-3xl font-light">mail</span>
</div>
<div>
<p class="font-display font-extrabold text-slate-900 dark:text-white text-lg md:text-xl mb-1 tracking-tight">Email</p>
<a class="text-slate-600 dark:text-slate-400 font-medium hover:text-primary transition-colors break-all" href="mailto:<?php echo $cu('contact_email'); ?>"><?php echo $cu('contact_email'); ?></a>
</div>
</div>
<div class="flex items-start gap-5 md:gap-6 group">
<div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-primary-light dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110 shrink-0">
<span class="material-symbols-outlined text-2xl md:text-3xl font-light">schedule</span>
</div>
<div class="flex-1">
<p class="font-display font-extrabold text-slate-900 dark:text-white text-lg md:text-xl mb-3 tracking-tight">Hours</p>
<div class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
<div class="flex justify-between gap-4"><span>Mon - Fri</span><span class="font-bold text-slate-900 dark:text-slate-200"><?php echo $cu('contact_hours_mon_fri'); ?></span></div>
<div class="flex justify-between gap-4"><span>Saturday</span><span class="font-bold text-slate-900 dark:text-slate-200"><?php echo $cu('contact_hours_sat'); ?></span></div>
<div class="flex justify-between gap-4"><span>Sunday</span><span class="font-bold text-red-600 dark:text-red-400"><?php echo $cu('contact_hours_sun'); ?></span></div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="contact-form-panel rounded-[2.5rem] p-8 md:p-12 shadow-[0_40px_100px_-30px_rgba(43,140,238,0.18)] border border-primary/15 dark:border-primary/25 border-t-4 border-t-primary relative overflow-hidden">
<div id="formMessage" class="hidden mb-6 p-4 rounded-xl border font-medium text-sm"></div>
<div class="mb-8 relative z-10">
<h2 class="font-display text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2">Send us a message</h2>
<p class="text-slate-500 dark:text-slate-400 text-sm md:text-base">We typically reply within 24 hours.</p>
</div>
<form class="space-y-6 md:space-y-8 relative z-10" id="contactForm" onsubmit="handleFormSubmit(event);">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
<div class="space-y-2">
<label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 text-primary/80" for="name">Full name</label>
<div class="relative">
<input class="w-full bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 focus:border-primary/40 focus:ring-4 focus:ring-primary/10 transition-all px-5 py-4 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="name" placeholder="John Doe" type="text"/>
</div>
</div>
<div class="space-y-2">
<label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 text-primary/80" for="email">Email</label>
<input class="w-full bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 focus:border-primary/40 focus:ring-4 focus:ring-primary/10 transition-all px-5 py-4 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="email" placeholder="you@example.com" type="email"/>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
<div class="space-y-2">
<label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 text-primary/80" for="phone">Phone</label>
<input class="w-full bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 focus:border-primary/40 focus:ring-4 focus:ring-primary/10 transition-all px-5 py-4 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none" id="phone" placeholder="(555) 123-4567" type="tel"/>
</div>
<div class="space-y-2">
<label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 text-primary/80" for="concern">Subject</label>
<select class="w-full bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 focus:border-primary/40 focus:ring-4 focus:ring-primary/10 transition-all px-5 py-4 rounded-2xl text-slate-900 dark:text-white font-medium outline-none cursor-pointer appearance-none" id="concern">
<option disabled="" selected="" value="">Select reason…</option>
<option value="appointment">New Appointment</option>
<option value="general">General Inquiry</option>
<option value="billing">Billing Question</option>
<option value="emergency">Emergency</option>
</select>
</div>
</div>
<div class="space-y-2">
<label class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400 ml-1 text-primary/80" for="message">Message</label>
<textarea class="w-full bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 focus:border-primary/40 focus:ring-4 focus:ring-primary/10 transition-all px-5 py-4 rounded-2xl text-slate-900 dark:text-white font-medium placeholder:text-slate-400 outline-none resize-none" id="message" placeholder="How can we help?" rows="5"></textarea>
</div>
<div class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
<span class="leading-relaxed">Protected by reCAPTCHA. Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Terms</a> apply.</span>
</div>
<button class="w-full bg-primary hover:bg-primary-dark text-white font-display font-black text-sm uppercase tracking-[0.2em] py-5 md:py-6 rounded-2xl shadow-[0_20px_40px_-10px_rgba(43,140,238,0.45)] hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3 group" type="submit">
Send message <span class="material-symbols-outlined transition-transform group-hover:translate-x-1 text-xl">arrow_forward</span>
</button>
</form>
</div>
</div>
</section>

<section class="max-w-[1800px] mx-auto px-4 sm:px-6 md:px-10 mb-20 md:mb-28">
<div class="relative w-full h-[320px] sm:h-[400px] md:h-[520px] lg:h-[600px] rounded-[2.5rem] md:rounded-[4rem] overflow-hidden bg-slate-200 dark:bg-slate-800 shadow-2xl border border-slate-100 dark:border-slate-700">
<iframe
    class="w-full h-full border-0"
    src="<?php echo $cu('contact_map_embed'); ?>"
    allowfullscreen=""
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    title="Clinic location">
</iframe>
<div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-slate-900/70 to-transparent p-6 md:p-10 pointer-events-none">
<div class="pointer-events-auto max-w-lg">
<p class="text-white/90 text-xs font-bold uppercase tracking-widest mb-2">Locate us</p>
<a class="text-white font-bold text-lg md:text-xl hover:text-primary transition-colors inline-flex items-center gap-2" href="<?php echo $cu('contact_map_link'); ?>" target="_blank" rel="noopener noreferrer">Open in Google Maps <span class="material-symbols-outlined">open_in_new</span></a>
</div>
</div>
</div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
        const RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_SITE_KEY';
        (function(){
            emailjs.init("yF8aTwk2JYrSOIn02");
        })();

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
            submitButton.innerHTML = '<span class="flex items-center gap-2 justify-center"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Sending…</span>';
            try {
                let recaptchaToken = '';
                if (RECAPTCHA_SITE_KEY && RECAPTCHA_SITE_KEY !== 'YOUR_RECAPTCHA_SITE_KEY') {
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
                await emailjs.send('service_q99148g', 'template_1w5v9oe', templateParams);
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
