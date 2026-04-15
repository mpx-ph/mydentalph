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
<body class="min-h-screen bg-background-light font-body text-on-surface antialiased">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-12">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(43,139,235,0.12),transparent_42%),radial-gradient(circle_at_bottom_left,rgba(43,139,235,0.08),transparent_40%)]"></div>
        <section class="relative w-full max-w-3xl rounded-3xl border border-primary/15 bg-white/90 p-8 shadow-[0_35px_90px_-35px_rgba(43,139,235,0.45)] backdrop-blur sm:p-12">
            <div class="mb-8 flex items-center justify-center">
                <img src="MyDental Logo.svg" alt="MyDental logo" class="h-20 w-auto sm:h-24" />
            </div>
            <div class="text-center">
                <p class="mb-3 font-headline text-xs font-bold uppercase tracking-[0.28em] text-primary">Access Control</p>
                <h1 class="font-headline text-4xl font-extrabold tracking-tight sm:text-5xl">
                    403 <span class="font-editorial italic font-normal text-primary editorial-word -skew-x-6 inline-block">Forbidden</span>
                </h1>
                <p class="mx-auto mt-6 max-w-xl text-lg font-medium leading-relaxed text-on-surface-variant">
                    You do not have permission to access this page.
                </p>
            </div>
        </section>
    </main>
</body>
</html>
