<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Test Credentials</title>
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
            --priority-amber: #d97706;
            --priority-amber-dark: #b45309;
            --priority-bg-1: #fffbeb;
            --priority-bg-2: #ffedd5;
            --priority-ring: rgba(217, 119, 6, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
        }

        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 16px 32px;
        }

        .page-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
            gap: 28px;
            align-items: start;
        }

        .main-column {
            min-width: 0;
            margin-right: auto;
            width: 100%;
            max-width: 720px;
            order: 1;
        }

        .pending-updates {
            position: sticky;
            top: 24px;
            order: 2;
        }

        .card.card-priority {
            margin-top: 14px;
            border: 2px solid var(--priority-amber);
            border-radius: 14px;
            padding: 0;
            overflow: hidden;
            background: linear-gradient(165deg, var(--priority-bg-1) 0%, #fff 42%, var(--priority-bg-2) 160%);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.6) inset,
                0 12px 40px var(--priority-ring),
                0 4px 14px rgba(17, 24, 39, 0.08);
        }

        .card-priority__banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 16px;
            background: linear-gradient(90deg, var(--priority-amber-dark) 0%, var(--priority-amber) 55%, #f59e0b 100%);
            color: #fff;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.15);
        }

        .card-priority__banner h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-priority__banner h2::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #fef3c7;
            box-shadow: 0 0 0 3px rgba(254, 243, 199, 0.45);
            animation: priority-pulse 2s ease-in-out infinite;
        }

        @keyframes priority-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(0.92); }
        }

        .card-priority__badge {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.45);
            white-space: nowrap;
        }

        .card-priority__body {
            padding: 14px 16px 16px;
        }

        .card-priority ul {
            margin: 0 0 14px;
            padding: 0;
            list-style: none;
            font-size: 14px;
            font-weight: 600;
            color: #1c1917;
        }

        .card-priority li {
            position: relative;
            margin: 0 0 12px;
            padding: 10px 12px 10px 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(217, 119, 6, 0.28);
            line-height: 1.4;
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.9) inset;
        }

        .card-priority li:last-child {
            margin-bottom: 0;
        }

        .card-priority li::before {
            content: "!";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 900;
            color: #fff;
            background: linear-gradient(145deg, var(--priority-amber-dark), var(--priority-amber));
            box-shadow: 0 2px 6px rgba(180, 83, 9, 0.35);
        }

        .card-priority .note {
            margin: 14px 0 0;
            padding: 12px 12px 12px 14px;
            font-size: 13px;
            color: #57534e;
            line-height: 1.55;
            font-weight: 500;
            background: linear-gradient(90deg, rgba(254, 243, 199, 0.55) 0%, rgba(255, 255, 255, 0.9) 100%);
            border-left: 4px solid var(--priority-amber);
            border-radius: 0 10px 10px 0;
        }

        .card-priority .note code {
            font-family: Consolas, "Courier New", monospace;
            font-size: 12px;
            font-weight: 700;
            color: var(--priority-amber-dark);
            background: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid rgba(217, 119, 6, 0.35);
        }

        @media (prefers-reduced-motion: reduce) {
            .card-priority__banner h2::before {
                animation: none;
            }
        }

        .header {
            margin-bottom: 18px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .header p {
            margin: 0;
            color: var(--muted);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-top: 14px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .card h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .new-badge {
            color: var(--success);
            font-weight: 700;
        }

        .subdesc {
            margin: -2px 0 10px;
            color: var(--muted);
            font-size: 13px;
        }

        .link-row {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .link-row a {
            color: var(--primary);
            text-decoration: none;
            word-break: break-word;
        }

        .link-row a:hover {
            text-decoration: underline;
        }

        .credential {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            background: #fbfdff;
        }

        .role {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .row {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 10px;
            align-items: center;
            margin-top: 6px;
        }

        .row.row--top {
            align-items: start;
        }

        .row.row--top .copy-btn {
            margin-top: 2px;
        }

        .row .label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .value {
            font-family: Consolas, "Courier New", monospace;
            font-size: 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 10px;
            word-break: break-all;
        }

        textarea.value {
            width: 100%;
            min-height: 92px;
            resize: vertical;
            line-height: 1.45;
            margin: 0;
        }

        button.copy-btn {
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 13px;
        }

        button.copy-btn:hover {
            background: var(--primary-hover);
        }

        button.copy-btn.copied {
            background: var(--success);
        }

        @media (max-width: 960px) {
            .page-grid {
                grid-template-columns: 1fr;
            }

            .main-column {
                max-width: none;
                order: 2;
            }

            .pending-updates {
                position: static;
                order: 1;
            }

            .card.card-priority {
                margin-top: 0;
            }
        }

        @media (max-width: 640px) {
            .row {
                grid-template-columns: 1fr;
            }

            .row .label {
                margin-bottom: -4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Developer Test Credentials</h1>
            <p>Quick copy page for test logins.</p>
        </div>

        <div class="page-grid">
        <div class="main-column">
        <section class="card">
            <h2>GIT REPO</h2>
            <div class="credential">
                <div class="role">Quick Commands</div>
                <p class="subdesc" style="margin-top: 0;">Push script is editable; reloading the page restores the default.</p>
                <div class="row row--top">
                    <div class="label">Push</div>
                    <textarea id="git-push-cmd" class="value" spellcheck="false" rows="4" aria-label="Git push commands">git add .
git commit -m "update"
git push</textarea>
                    <button type="button" class="copy-btn" data-copy-from="#git-push-cmd">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Pull</div>
                    <div class="value">git pull</div>
                    <button class="copy-btn" data-copy="git pull">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Super Admin</h2>
            <div class="link-row">
                Link:
                <a href="https://mydentalph.ct.ws/ProviderLogin.php" target="_blank">
                    https://mydentalph.ct.ws/ProviderLogin.php
                </a>
            </div>

            <div class="credential">
                <div class="role">SUPER ADMIN</div>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value">superadmin</div>
                    <button class="copy-btn" data-copy="superadmin">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123!</div>
                    <button class="copy-btn" data-copy="Admin123!">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Hotdog Family <span class="new-badge">(NEW)</span></h2>
            <div class="subdesc">Uses new subscription rule</div>
            <div class="link-row">
                Link:
                <a href="https://mydentalph.ct.ws/hotdog/" target="_blank">
                    https://mydentalph.ct.ws/hotdog/
                </a>
            </div>

            <div class="credential">
                <div class="role">TENANT</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">jamoyi6423@azucore.com</div>
                    <button class="copy-btn" data-copy="jamoyi6423@azucore.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">STAFF</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">lafem15214@bpotogo.com</div>
                    <button class="copy-btn" data-copy="lafem15214@bpotogo.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">DOCTOR</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">hajikam923@azucore.com</div>
                    <button class="copy-btn" data-copy="hajikam923@azucore.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>SISIG DENTALS <span class="new-badge">(NEW)</span></h2>
            <div class="link-row">
                Link:
                <a href="https://mydentalph.ct.ws/sisigdentals" target="_blank">
                    https://mydentalph.ct.ws/sisigdentals
                </a>
            </div>

            <div class="credential">
                <div class="role">PROVIDER</div>
                <div class="row">
                    <div class="label">Username</div>
                    <div class="value">SISIG</div>
                    <button class="copy-btn" data-copy="SISIG">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">123</div>
                    <button class="copy-btn" data-copy="123">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">STAFF</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">nelmanlapaz19@gmail.com</div>
                    <button class="copy-btn" data-copy="nelmanlapaz19@gmail.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Ilocos Empanada Dental Clinic</h2>
            <div class="link-row">
                Link:
                <a href="https://mydentalph.ct.ws/ilocosempanadadental/login" target="_blank">
                    https://mydentalph.ct.ws/ilocosempanadadental/login
                </a>
            </div>
            <div class="link-row">
                Tenant Login:
                <a href="https://mydentalph.ct.ws/ProviderLogin.php" target="_blank">
                    https://mydentalph.ct.ws/ProviderLogin.php
                </a>
            </div>

            <div class="credential">
                <div class="role">STAFF</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">b4svdtaopp@ozsaip.com</div>
                    <button class="copy-btn" data-copy="b4svdtaopp@ozsaip.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">PATIENT</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">simaham876@algarr.com</div>
                    <button class="copy-btn" data-copy="simaham876@algarr.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">DENTIST</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">teyadag807@flownue.com</div>
                    <button class="copy-btn" data-copy="teyadag807@flownue.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>

            <div class="credential">
                <div class="role">TENANT</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">ricatev198@agoalz.com</div>
                    <button class="copy-btn" data-copy="ricatev198@agoalz.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>John Pork Dental Clinic</h2>
            <div class="link-row">
                Link:
                <a href="https://mydentalph.ct.ws/ProviderLogin.php" target="_blank">
                    https://mydentalph.ct.ws/ProviderLogin.php
                </a>
            </div>

            <div class="credential">
                <div class="role">TENANT</div>
                <div class="row">
                    <div class="label">Email</div>
                    <div class="value">wokib76060@parsitv.com</div>
                    <button class="copy-btn" data-copy="wokib76060@parsitv.com">Copy</button>
                </div>
                <div class="row">
                    <div class="label">Password</div>
                    <div class="value">Admin123456_</div>
                    <button class="copy-btn" data-copy="Admin123456_">Copy</button>
                </div>
            </div>
        </section>
        </div>

        <aside class="pending-updates" aria-label="Pending updates">
            <section class="card card-priority">
                <div class="card-priority__banner">
                    <h2>Pending Updates</h2>
                    <span class="card-priority__badge">Priority</span>
                </div>
                <div class="card-priority__body">
                    <ul>
                        <li>mydentalph.ct.ws Mobile view UI fixes (handled by MP)</li>
                        <li>Create account not yet included in customization</li>
                        <li>HOTDOG FAMILY CLINIC ACCOUNT IS AUTO-LOGGING IN WHEN SISIG DENTALS WAS OPENED.</li>
                        <li>Approval of the Terms &amp; Conditions modal during plan purchases</li>
                        <li>Service discounts</li>
                        <li>Refunds (via PayMongo, if possible)</li>
                    </ul>
                    <p class="note">If you discover or fix any issues, please update them in <code>credentials.php</code>. Thanks!</p>
                </div>
            </section>
        </aside>
        </div>

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

        document.querySelectorAll(".copy-btn").forEach(function (button) {
            button.addEventListener("click", function () {
                var fromSel = button.getAttribute("data-copy-from");
                var value = fromSel
                    ? (document.querySelector(fromSel) || {}).value
                    : button.getAttribute("data-copy");
                if (value == null) {
                    value = "";
                }
                copyText(value, button);
            });
        });
    </script>
</body>
</html>
