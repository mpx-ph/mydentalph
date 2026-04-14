<?php
/**
 * Shared final CTA for provider marketing pages (matches ProviderMain).
 * Expects Tailwind + .reveal styles; pages should run the provider reveal script on [data-reveal="section"].
 */
?>
<!-- Final CTA: Ready to evolve your practice? (shared with ProviderMain) -->
<section
    class="bg-primary reveal overflow-hidden pt-6 pb-16 sm:pb-20 lg:grid lg:grid-cols-2 lg:gap-0 lg:items-stretch lg:px-0 lg:pt-6 lg:pb-0"
    data-reveal="section">
    <div
        class="relative z-10 w-full flex flex-col justify-start px-6 sm:px-8 lg:px-10 xl:px-14 2xl:px-16 py-10 sm:py-12 lg:py-14 lg:pb-16">
        <div class="w-full max-w-md sm:max-w-lg lg:max-w-xl mx-auto text-center lg:text-left">
            <h2
                class="font-headline text-4xl font-extrabold text-white tracking-tighter leading-[0.95] md:text-5xl mb-6 lg:mb-7">
                Ready to evolve your practice?</h2>
            <p class="text-white/75 text-lg md:text-xl leading-relaxed mb-8 lg:mb-10">Join hundreds of
                dental clinics streamlining their clinical operations through the MyDental ecosystem.</p>
            <div class="flex justify-center lg:justify-start">
                <a href="Provider-Plans.php"
                    class="bg-white text-primary px-7 py-3 sm:px-9 sm:py-3.5 rounded-full font-bold text-xs sm:text-sm uppercase tracking-[0.12em] hover:scale-[1.02] transition-transform duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 inline-block">
                    Start Your Subscription
                </a>
            </div>
        </div>
    </div>
    <div
        class="relative w-full min-h-[14rem] aspect-[4/3] lg:aspect-auto lg:min-h-0 -mx-6 sm:-mx-10 lg:mx-0 lg:h-full">
        <img src="Banner2.webp" alt="Dental professionals collaborating with modern clinic software"
            class="absolute inset-x-0 top-0 bottom-0 w-full h-full object-cover object-center lg:top-5 lg:bottom-0"
            loading="lazy" decoding="async" />
    </div>
</section>
