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
            --primary-soft: #eff6ff;
            --success: #16a34a;
            --accent: #0ea5e9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 640px;
            margin: 0 auto;
            padding: 40px 20px 48px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.02em;
            box-shadow: 0 4px 14px rgba(29, 78, 216, 0.25);
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .header p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        .section-label {
            margin: 28px 0 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            margin-top: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .card h2 {
            margin: 0 0 4px;
            font-size: 17px;
            font-weight: 700;
        }

        .card-desc {
            margin: 0 0 14px;
            font-size: 13px;
            color: var(--muted);
        }

        .site-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            margin-top: 8px;
            background: var(--primary-soft);
            border: 1px solid rgba(29, 78, 216, 0.12);
            border-radius: 10px;
            text-decoration: none;
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            word-break: break-all;
            transition: background 0.15s, border-color 0.15s;
        }

        .site-link:hover {
            background: #dbeafe;
            border-color: rgba(29, 78, 216, 0.22);
        }

        .site-link span:last-child {
            flex-shrink: 0;
            font-size: 18px;
            opacity: 0.7;
        }

        .login-hint {
            margin: 0 0 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .login-hint a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .login-hint a:hover {
            text-decoration: underline;
        }

        .credential {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-top: 10px;
            background: #fafbfc;
        }

        .credential:first-of-type {
            margin-top: 0;
        }

        .role {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .row {
            display: grid;
            grid-template-columns: 88px 1fr auto;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
        }

        .row:first-of-type {
            margin-top: 0;
        }

        .row .label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .value {
            font-size: 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 11px;
            word-break: break-all;
        }

        .value.empty {
            color: var(--muted);
            font-style: italic;
        }

        .value-or {
            margin: 6px 0 0;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        button.copy-btn {
            border: 0;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            transition: background 0.15s;
        }

        button.copy-btn:hover {
            background: var(--primary-hover);
        }

        button.copy-btn.copied {
            background: var(--success);
        }

        button.copy-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .footer-note {
            margin-top: 32px;
            padding: 14px 16px;
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 520px) {
            .container {
                padding: 28px 16px 40px;
            }

            .row {
                grid-template-columns: 1fr;
            }

            .row .label {
                margin-bottom: -4px;
            }

            button.copy-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">MD</div>
            <div class="header">
                <h1>Login Information</h1>
                <p>MyDentalPH test accounts — tap Copy to use each value.</p>
            </div>
        </div>

        <p class="section-label">Websites</p>

        <section class="card">
            <h2>Provider website</h2>
            <p class="card-desc">Main platform entry point.</p>
            <a class="site-link" href="https://mydentalph.gt.tc" target="_blank" rel="noopener noreferrer">
                <span>https://mydentalph.gt.tc</span>
                <span aria-hidden="true">↗</span>
            </a>
        </section>

        <section class="card">
            <h2>Dental clinic</h2>
            <p class="card-desc">SISIG Dentals clinic page.</p>
            <a class="site-link" href="https://mydentalph.gt.tc/sisigdentals" target="_blank" rel="noopener noreferrer">
                <span>https://mydentalph.gt.tc/sisigdentals</span>
                <span aria-hidden="true">↗</span>
            </a>
        </section>

        <p class="section-label">Account credentials</p>
        <p class="login-hint">
            Sign in at
            <a href="https://mydentalph.gt.tc/ProviderLogin.php" target="_blank" rel="noopener noreferrer">Provider Login</a>
        </p>

        <section class="card">
            <h2>Platform accounts</h2>
            <p class="card-desc">Super admin and tenant (clinic owner) access.</p>

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

        <p class="section-label">Dental clinic staff</p>
        <p class="login-hint">
            Staff and doctor accounts also use
            <a href="https://mydentalph.gt.tc/ProviderLogin.php" target="_blank" rel="noopener noreferrer">Provider Login</a>
        </p>

        <section class="card">
            <h2>SISIG Dentals — Staff &amp; Doctor</h2>

            <div class="credential">
                <div class="role">Staff</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">nelmanlapaz19@gmail.com</div>
                    <button type="button" class="copy-btn" data-copy="nelmanlapaz19@gmail.com">Copy</button>
                </div>
                <p class="value-or">or username</p>
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

        <p class="footer-note">For internal testing only. Keep these details confidential.</p>
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
