<?php
/**
 * Shared footer for public provider marketing pages (ProviderMain, HowItWorks, Plans, Contact, FAQs).
 * Pages should include the reveal scroll script so [data-reveal="section"] animates.
 */
?>
<!-- Footer -->
<footer class="w-full border-t border-slate-200 bg-slate-50 reveal" data-reveal="section">
    <div class="flex flex-col md:flex-row justify-between items-center py-12 px-8 max-w-screen-2xl mx-auto gap-4">
        <div class="flex items-center">
            <img src="MyDental%20Logo.svg" alt="My Dental Logo" class="h-8 w-auto" />
        </div>
        <div class="flex flex-wrap justify-center gap-8 text-xs font-inter text-slate-500">
            <a class="hover:text-blue-500 hover:underline transition-all transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded"
                href="privacy.php">Privacy Policy</a>
            <a class="hover:text-blue-500 hover:underline transition-all transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded"
                href="tos.php">Terms of Service</a>
        </div>
        <div class="text-xs text-slate-500 font-inter opacity-80 hover:opacity-100">
            © <?php echo (int) date('Y'); ?> MyDental Philippines Incorporated. All rights reserved.
        </div>
    </div>
</footer>
