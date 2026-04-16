<?php
$target = 'ProviderLogin.php';
$redirect = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';

if ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)) {
    $target .= '?redirect=' . rawurlencode($redirect);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at 0 100%, rgba(43, 139, 235, 0.08), transparent 45%), #f7f9ff;
            font-family: Manrope, Arial, sans-serif;
        }
        .transition-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
        }
        .transition-card {
            width: min(520px, 100%);
            border-radius: 1.5rem;
            border: 1px solid rgba(19, 28, 37, 0.06);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 28px 60px -24px rgba(43, 139, 235, 0.28);
            padding: 1.25rem 1.25rem 1.5rem;
        }
        #lottie-transition {
            width: 100%;
            max-width: 420px;
            aspect-ratio: 7 / 5;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<main class="transition-shell">
    <section class="transition-card">
        <div id="lottie-transition" aria-hidden="true"></div>
        <p class="text-center text-sm font-semibold tracking-wide text-slate-500">Preparing secure login...</p>
    </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
    (function () {
        var destination = <?php echo json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var redirected = false;

        function go() {
            if (redirected) return;
            redirected = true;
            window.location.href = destination;
        }

        if (prefersReduced || !window.lottie) {
            setTimeout(go, 150);
            return;
        }

        try {
            var anim = window.lottie.loadAnimation({
                container: document.getElementById('lottie-transition'),
                renderer: 'svg',
                loop: false,
                autoplay: true,
                path: 'flyingteeth1.json'
            });

            anim.addEventListener('complete', go);
            setTimeout(go, 7000);
        } catch (e) {
            setTimeout(go, 150);
        }
    })();
</script>
</body>
</html>
