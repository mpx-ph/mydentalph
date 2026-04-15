<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>403 Forbidden | MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap"
        rel="stylesheet" />
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
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    }
                }
            }
        };
    </script>
    <style>
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="bg-background-light font-body text-on-surface antialiased">
    <main>
        <section class="relative overflow-hidden bg-surface-variant">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(43,139,235,0.12),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(43,139,235,0.08),transparent_42%)]"></div>
            <div class="relative mx-auto flex min-h-[78vh] w-full max-w-[1400px] items-center justify-center px-6 py-16 sm:px-10 lg:px-16">
                <div class="w-full max-w-3xl text-center">
                    <img src="MyDental Logo.svg" alt="MyDental logo" class="mx-auto mb-10 h-16 w-auto sm:h-20" />
                    <p class="mb-4 font-headline text-xs font-bold uppercase tracking-[0.28em] text-primary">Access Control</p>
                    <h1 class="font-headline text-[clamp(2.4rem,6.6vw,5.2rem)] font-extrabold leading-[0.95] tracking-tight text-on-surface">
                        403
                        <span class="font-editorial italic font-normal text-primary editorial-word -skew-x-6 inline-block">
                            Forbidden
                        </span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg font-medium leading-relaxed text-on-surface-variant sm:text-xl">
                        You do not have permission to access this page.
                    </p>
                    <div class="mt-10">
                        <a href="ProviderMain.php"
                            class="group relative inline-flex items-center justify-center rounded-full bg-primary px-9 py-4 font-headline text-sm font-bold uppercase tracking-[0.15em] text-white transition-all hover:pr-12 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                            <span>Back to Home</span>
                            <span class="ml-2 translate-x-0 opacity-80 transition-all group-hover:translate-x-1 group-hover:opacity-100">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php require_once __DIR__ . '/provider_marketing_footer.inc.php'; ?>
</body>
</html>
