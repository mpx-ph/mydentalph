<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyDentalPH — Login Information</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --success: #16a34a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 36px 20px 48px;
        }

        .page-header {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .page-header img {
            display: block;
            height: 56px;
            width: auto;
            max-width: 100%;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0 0 10px;
            font-size: 28px;
            line-height: 1.25;
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            max-width: 52ch;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 22px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .card h2 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        .card-intro {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .link-row {
            margin: 0 0 14px;
            font-size: 15px;
            line-height: 1.5;
        }

        .link-row:last-child {
            margin-bottom: 0;
        }

        .link-row strong {
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
            color: var(--text);
        }

        .link-row a {
            color: var(--primary);
            word-break: break-all;
        }

        .link-row a:hover {
            text-decoration: underline;
        }

        .login-note {
            margin: 0 0 16px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            line-height: 1.5;
        }

        .credential {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-top: 14px;
            background: #fbfdff;
        }

        .credential:first-of-type {
            margin-top: 0;
        }

        .role {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .row {
            display: grid;
            grid-template-columns: 110px 1fr auto;
            gap: 12px;
            align-items: center;
            margin-top: 10px;
        }

        .row:first-of-type {
            margin-top: 0;
        }

        .row .label {
            font-size: 14px;
            color: var(--muted);
        }

        .value {
            font-family: Consolas, "Courier New", monospace;
            font-size: 15px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            word-break: break-all;
        }

        .value.empty {
            font-family: Arial, Helvetica, sans-serif;
            color: var(--muted);
            font-style: italic;
        }

        .alt-login {
            margin: 12px 0 0;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
            font-size: 14px;
            color: var(--muted);
        }

        button.copy-btn {
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            padding: 9px 14px;
            font-size: 13px;
        }

        button.copy-btn:hover {
            background: var(--primary-hover);
        }

        button.copy-btn.copied {
            background: var(--success);
        }

        button.copy-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .footer-note {
            margin-top: 8px;
            padding-top: 20px;
            font-size: 14px;
            color: var(--muted);
            text-align: center;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 640px) {
            .container {
                padding: 24px 16px 40px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .row {
                grid-template-columns: 1fr;
            }

            .row .label {
                margin-bottom: -2px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <img src="MyDental Logo.svg" alt="MyDental Philippines">
            <h1>Login Information</h1>
            <p>Test account details for MyDentalPH. Click <strong>Copy</strong> beside each username or password to paste it when signing in.</p>
        </header>

        <section class="card">
            <h2>Websites</h2>
            <p class="card-intro">Open these links in your browser.</p>

            <div class="link-row">
                <strong>Provider website</strong>
                <a href="https://mydentalph.gt.tc" target="_blank" rel="noopener noreferrer">https://mydentalph.gt.tc</a>
            </div>

            <div class="link-row">
                <strong>Dental clinic (SISIG Dentals)</strong>
                <a href="https://mydentalph.gt.tc/sisigdentals" target="_blank" rel="noopener noreferrer">https://mydentalph.gt.tc/sisigdentals</a>
            </div>
        </section>

        <section class="card">
            <h2>Account credentials</h2>
            <p class="card-intro">For Super Admin and Tenant (clinic owner).</p>

            <p class="login-note">
                <strong>Where to sign in:</strong><br>
                <a href="https://mydentalph.gt.tc/ProviderLogin.php" target="_blank" rel="noopener noreferrer">https://mydentalph.gt.tc/ProviderLogin.php</a>
            </p>

            <div class="credential">
                <div class="role">Super Admin</div>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value">superadmin</div>
                    <button type="button" class="copy-btn" data-copy="superadmin">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123!</div>
                    <button type="button" class="copy-btn" data-copy="Admin123!">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">Tenant</div>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value">SISIG</div>
                    <button type="button" class="copy-btn" data-copy="SISIG">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">123</div>
                    <button type="button" class="copy-btn" data-copy="123">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Dental clinic staff</h2>
            <p class="card-intro">For clinic staff and doctor accounts at SISIG Dentals.</p>

            <p class="login-note">
                <strong>Where to sign in:</strong><br>
                <a href="https://mydentalph.gt.tc/sisigdentals/login" target="_blank" rel="noopener noreferrer">https://mydentalph.gt.tc/sisigdentals/login</a>
            </p>

            <div class="credential">
                <div class="role">Staff</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">nelmanlapaz19@gmail.com</div>
                    <button type="button" class="copy-btn" data-copy="nelmanlapaz19@gmail.com">Copy</button>
                </div>
                <p class="alt-login">You may also sign in with this username:</p>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value">layooonel</div>
                    <button type="button" class="copy-btn" data-copy="layooonel">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">lionel123456_</div>
                    <button type="button" class="copy-btn" data-copy="lionel123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">Doctor</div>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value empty">Not provided yet</div>
                    <button type="button" class="copy-btn" disabled>Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value empty">Not provided yet</div>
                    <button type="button" class="copy-btn" disabled>Copy</button>
                </div>
            </div>
        </section>

        <p class="footer-note">For internal testing only. Please keep these details confidential.</p>
    </div>

    <script>
        function setCopiedState(button) {
            var original = button.textContent;
            button.textContent = "Copied!";
            button.classList.add("copied");
            setTimeout(function () {
                button.textContent = original;
                button.classList.remove("copied");
            }, 1200);
        }

        function copyText(value, button) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function () {
                    setCopiedState(button);
                }).catch(function () {
                    fallbackCopy(value, button);
                });
                return;
            }
            fallbackCopy(value, button);
        }

        function fallbackCopy(value, button) {
            var temp = document.createElement("textarea");
            temp.value = value;
            temp.setAttribute("readonly", "");
            temp.style.position = "absolute";
            temp.style.left = "-9999px";
            document.body.appendChild(temp);
            temp.select();
            document.execCommand("copy");
            document.body.removeChild(temp);
            setCopiedState(button);
        }

        document.querySelectorAll(".copy-btn:not([disabled])").forEach(function (button) {
            button.addEventListener("click", function () {
                var value = button.getAttribute("data-copy");
                if (value) {
                    copyText(value, button);
                }
            });
        });
    </script>
</body>
</html>
