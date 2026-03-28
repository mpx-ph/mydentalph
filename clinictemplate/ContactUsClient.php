<?php
/**
 * Contact Us Page
 */
$pageTitle = 'Contact Us - Dental Care Plus';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<!-- Replace YOUR_RECAPTCHA_SITE_KEY with your reCAPTCHA v3 Site Key from https://www.google.com/recaptcha/admin -->
<script src="https://www.google.com/recaptcha/api.js?render=6LfQuT4sAAAAAAxKDsMLjv-15e2km5ytXY6hJbSOY"></script>
<style>
        .form-input-transition {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen flex flex-col overflow-x-hidden selection:bg-primary/20 selection:text-primary">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main class="flex-grow w-full">
<section class="relative bg-gradient-to-b from-blue-50 to-transparent dark:from-slate-800/20 dark:to-transparent pt-16 pb-12 lg:pt-24 lg:pb-16 px-4 sm:px-6 lg:px-10 overflow-hidden">
<div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-full pointer-events-none z-0">
<div class="absolute top-20 right-20 w-72 h-72 bg-primary/10 rounded-full blur-3xl"></div>
<div class="absolute top-40 left-10 w-96 h-96 bg-blue-400/10 rounded-full blur-3xl"></div>
</div>
<div class="max-w-3xl mx-auto text-center relative z-10 flex flex-col items-center gap-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold uppercase tracking-wider border border-primary/20">
<span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                    Contact Support
                </span>
<h1 class="text-slate-900 dark:text-white text-4xl lg:text-6xl font-black leading-[1.1] tracking-tight">
                    hotdoggg test
                </h1>
<p class="text-slate-600 dark:text-slate-300 text-lg lg:text-xl font-medium leading-relaxed max-w-2xl">
                    Whether you have a question about our services, pricing, or need to book an emergency appointment, our team is ready to answer all your questions.
                </p>
</div>
</section>
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 pb-16 lg:pb-24">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
<div class="lg:col-span-5 flex flex-col gap-6">
<div class="grid gap-5">
<div class="group bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-soft hover:shadow-lg hover:border-primary/20 transition-all duration-300">
<div class="flex items-start gap-5">
<div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-slate-800 flex items-center justify-center text-primary group-hover:scale-110 group-hover:bg-primary group-hover:text-white transition-all duration-300 shrink-0">
<span class="material-symbols-outlined text-2xl">location_on</span>
</div>
<div>
<h3 class="text-slate-900 dark:text-white font-bold text-lg mb-2">Visit Us</h3>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                                        Dr. Gonzales St., <br/>
                                        Tibag, Baliwag City, Bulacan
                                    </p>
<a class="inline-flex items-center gap-1 text-primary text-sm font-bold mt-3 hover:gap-2 transition-all" href="https://maps.app.goo.gl/6hEegE4yZtBpxeWC6" target="_blank" rel="noopener noreferrer">
                                        Get Directions <span class="material-symbols-outlined text-base">arrow_forward</span>
</a>
</div>
</div>
</div>
<div class="group bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-soft hover:shadow-lg hover:border-primary/20 transition-all duration-300">
<div class="flex items-start gap-5">
<div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-slate-800 flex items-center justify-center text-primary group-hover:scale-110 group-hover:bg-primary group-hover:text-white transition-all duration-300 shrink-0">
<span class="material-symbols-outlined text-2xl">chat_bubble</span>
</div>
<div class="flex-1">
<h3 class="text-slate-900 dark:text-white font-bold text-lg mb-2">Chat with us</h3>
<div class="space-y-3">
<div class="flex flex-col">
<span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Phone</span>
<a class="text-slate-900 dark:text-slate-200 font-medium hover:text-primary transition-colors" href="tel:+15551234567">0998 585 7970</a>
</div>
<div class="flex flex-col">
<span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Email</span>
<a class="text-slate-900 dark:text-slate-200 font-medium hover:text-primary transition-colors" href="mailto:hello@dentalcareplus.com">hello@drcgdental.com</a>
</div>
</div>
</div>
</div>
</div>
<div class="group bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-soft hover:shadow-lg hover:border-primary/20 transition-all duration-300">
<div class="flex items-start gap-5">
<div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-slate-800 flex items-center justify-center text-primary group-hover:scale-110 group-hover:bg-primary group-hover:text-white transition-all duration-300 shrink-0">
<span class="material-symbols-outlined text-2xl">schedule</span>
</div>
<div class="flex-1">
<h3 class="text-slate-900 dark:text-white font-bold text-lg mb-3">Working Hours</h3>
<div class="space-y-2.5">
<div class="flex justify-between items-center text-sm">
<span class="text-slate-500 dark:text-slate-400 font-medium">Mon - Fri</span>
<span class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">8:00 AM - 6:00 PM</span>
</div>
<div class="flex justify-between items-center text-sm">
<span class="text-slate-500 dark:text-slate-400 font-medium">Saturday</span>
<span class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">9:00 AM - 2:00 PM</span>
</div>
<div class="flex justify-between items-center text-sm">
<span class="text-slate-500 dark:text-slate-400 font-medium">Sunday</span>
<span class="px-2 py-1 rounded bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-bold text-xs">Closed</span>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="relative w-full h-[350px] sm:h-[380px] md:h-[400px] lg:h-[420px] rounded-2xl overflow-hidden shadow-md border border-slate-200 dark:border-slate-800 group">
<iframe 
    class="w-full h-full border-0 rounded-2xl" 
    src="https://www.google.com/maps?q=Dr.+Gonzales+St.,+Tibag,+Baliwag+City,+Bulacan&output=embed"
    allowfullscreen="" 
    loading="lazy" 
    referrerpolicy="no-referrer-when-downgrade"
    title="Clinic Location">
</iframe>
<div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-slate-900/60 to-transparent p-3 sm:p-4 md:p-5 pointer-events-none">
<div class="text-white">
<p class="text-xs sm:text-sm font-medium opacity-90">Locate us on map</p>
<a class="font-bold text-base sm:text-lg hover:text-primary transition-colors pointer-events-auto inline-flex items-center gap-1" href="https://maps.app.goo.gl/6hEegE4yZtBpxeWC6" target="_blank" rel="noopener noreferrer">
Open in Google Maps <span class="material-symbols-outlined text-sm sm:text-base">open_in_new</span>
</a>
</div>
</div>
</div>
</div>
<div class="lg:col-span-7 h-full">
<div class="bg-surface-light dark:bg-surface-dark rounded-3xl p-6 md:p-10 shadow-xl border border-slate-100 dark:border-slate-800 relative overflow-hidden h-full flex flex-col justify-center">
<div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-primary via-blue-400 to-primary"></div>
<div class="mb-8 relative z-10">
<h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2">Send us a Message</h2>
<p class="text-slate-500 dark:text-slate-400">Fill in the form below and our team will get back to you within 24 hours.</p>
</div>
<div id="formMessage" class="hidden mb-6 p-4 rounded-xl border font-medium text-sm"></div>
<form class="space-y-6 relative z-10" id="contactForm" onsubmit="handleFormSubmit(event);">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="name">Full Name</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="name" placeholder="John Doe" type="text"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">person</span>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="email">Email Address</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="email" placeholder="john@example.com" type="email"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">mail</span>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="phone">Phone Number</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="phone" placeholder="(555) 123-4567" type="tel"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">call</span>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="concern">Subject</label>
<div class="relative">
<select class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none appearance-none cursor-pointer" id="concern">
<option disabled="" selected="" value="">Select reason...</option>
<option value="appointment">New Appointment</option>
<option value="general">General Inquiry</option>
<option value="billing">Billing Question</option>
<option value="emergency">Emergency</option>
</select>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-500">
<span class="material-symbols-outlined text-[20px]">expand_more</span>
</div>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="message">Your Message</label>
<textarea class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none resize-none" id="message" placeholder="How can we help you today?" rows="4"></textarea>
</div>
<div class="pt-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-6">
<div class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2 flex-1 sm:flex-initial">
<span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
<span class="leading-relaxed">This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Terms of Service</a> apply.</span>
</div>
<button class="w-full sm:w-auto px-8 h-12 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2 group shrink-0" type="submit">
<span>Send Message</span>
<span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">send</span>
</button>
</div>
</form>
</div>
</div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script>
        // Mobile Menu Toggle
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
                
                // Close mobile menu when clicking on a link
                const mobileMenuLinks = mobileMenu.querySelectorAll('a');
                mobileMenuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    });
                });
                
                // Close mobile menu when clicking outside
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

        // Replace 'YOUR_RECAPTCHA_SITE_KEY' with your actual reCAPTCHA v3 Site Key
        const RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_SITE_KEY';
        
        // Initialize EmailJS
        (function(){
            emailjs.init("yF8aTwk2JYrSOIn02");
        })();

        async function handleFormSubmit(event) {
            event.preventDefault();
            
            const form = document.getElementById('contactForm');
            const messageDiv = document.getElementById('formMessage');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Get form values
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const concern = document.getElementById('concern').value;
            const message = document.getElementById('message').value.trim();
            
            // Hide previous messages
            messageDiv.classList.add('hidden');
            
            // Validation
            if (!name || !email || !phone || !concern || !message) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Sending...</span>';
            
            try {
                // Execute reCAPTCHA v3
                let recaptchaToken = '';
                if (RECAPTCHA_SITE_KEY && RECAPTCHA_SITE_KEY !== 'YOUR_RECAPTCHA_SITE_KEY') {
                    try {
                        recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' });
                    } catch (recaptchaError) {
                        console.error('reCAPTCHA Error:', recaptchaError);
                        // Continue without reCAPTCHA if it fails (for development)
                    }
                }
                
                // Prepare email template parameters
                const templateParams = {
                    user_name: name,
                    user_email: email,
                    phone_number: phone,
                    concern_type: concern,
                    user_message: message,
                    recaptcha_token: recaptchaToken // Include reCAPTCHA token in email (optional)
                };
                
                // Send email via EmailJS
                const response = await emailjs.send(
                    'service_q99148g',
                    'template_1w5v9oe',
                    templateParams
                );
                
                // Success
                showMessage('Thank you! Your message has been sent successfully. We\'ll get back to you within 24 hours.', 'success');
                form.reset();
                
            } catch (error) {
                console.error('EmailJS Error:', error);
                showMessage('Sorry, there was an error sending your message. Please try again or contact us directly at hello@drcgdental.com', 'error');
            } finally {
                // Re-enable submit button
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
            
            // Scroll to message
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
</body></html>