<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>403 Forbidden | MyDental</title>
    <meta name="description" content="You do not have permission to access this page on MyDental." />
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
            text-shadow: 0 0 14px rgba(43, 139, 235, 0.14);
            letter-spacing: -0.02em;
        }

        #deniedAnimation {
            overflow: hidden;
        }

        #deniedAnimation svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
    </style>
</head>
<body class="bg-background-light font-body text-on-surface antialiased">
    <main>
        <section class="relative overflow-hidden bg-surface-variant">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(43,139,235,0.14),transparent_42%),radial-gradient(circle_at_bottom_left,rgba(43,139,235,0.08),transparent_45%)]"></div>
            <div class="relative mx-auto grid min-h-[82vh] w-full max-w-[1500px] grid-cols-1 items-center gap-10 px-6 py-14 sm:px-10 lg:grid-cols-[1fr_1.35fr] lg:gap-10 lg:px-16">
                <div class="order-2 lg:order-1">
                    <img src="MyDental Logo.svg" alt="MyDental logo" class="mb-8 h-14 w-auto sm:h-16" />
                    <p class="mb-4 font-headline text-xs font-bold uppercase tracking-[0.28em] text-primary">Access Control</p>
                    <h1 class="font-headline text-[clamp(2rem,5.2vw,4.8rem)] font-extrabold leading-[0.95] tracking-tight text-on-surface">
                        403
                        <span class="inline-block font-editorial italic font-normal text-primary editorial-word -skew-x-6">
                            Forbidden
                        </span>
                    </h1>
                    <p class="mt-6 max-w-xl text-base font-medium leading-relaxed text-on-surface-variant sm:text-lg">
                        You do not have permission to view this page.
                        If you think this is a mistake, please contact your account administrator.
                    </p>

                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a href="ProviderMain.php"
                            class="group inline-flex items-center justify-center rounded-full bg-primary px-8 py-3.5 font-headline text-xs font-bold uppercase tracking-[0.16em] text-white transition-all hover:pr-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                            <span>Back to Home</span>
                            <span class="ml-2 translate-x-0 opacity-80 transition-all group-hover:translate-x-1 group-hover:opacity-100">→</span>
                        </a>
                    </div>
                </div>

                <div class="order-1 flex items-center justify-center lg:order-2">
                    <div class="w-full max-w-[1200px]">
                        <div id="deniedAnimation" class="aspect-[16/8] w-full bg-transparent"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php require_once __DIR__ . '/provider_marketing_footer.inc.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function () {
            if (typeof lottie === "undefined") return;

            var container = document.getElementById("deniedAnimation");
            if (!container) return;

            lottie.loadAnimation({
                container: container,
                renderer: "svg",
                loop: true,
                autoplay: true,
                path: "denied.json",
                rendererSettings: {
                    preserveAspectRatio: "xMidYMid slice"
                }
            });
        })();
    </script>
</body>
</html>
