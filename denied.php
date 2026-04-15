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
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
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
                        "outline-variant": "#c0c7d4",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                    },
                    boxShadow: {
                        "soft-card": "0 30px 70px -35px rgba(19, 28, 37, 0.35)",
                    },
                    borderRadius: {
                        "3xl": "2.5rem",
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .mesh-gradient {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.14) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.08) 0px, transparent 55%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
    </style>
</head>

<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
    <main class="mesh-gradient min-h-screen px-6 py-10 sm:px-8 lg:px-10">
        <div class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-5xl items-center justify-center">
            <section class="glass-card shadow-soft-card w-full max-w-2xl rounded-3xl p-8 sm:p-12">
                <div
                    class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-3xl">gpp_bad</span>
                </div>
                <p class="mb-4 text-xs font-extrabold uppercase tracking-[0.3em] text-primary">
                    Access Control
                </p>
                <h1 class="font-headline mb-4 text-4xl font-extrabold tracking-tight sm:text-5xl">
                    403 Forbidden
                </h1>
                <p class="text-on-surface-variant text-lg leading-relaxed font-medium">
                    You do not have permission to access this page.
                </p>
            </section>
        </div>
    </main>
</body>

</html>
