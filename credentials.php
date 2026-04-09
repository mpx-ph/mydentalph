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
            max-width: 980px;
            margin: 32px auto;
            padding: 0 16px 32px;
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
                copyText(button.getAttribute("data-copy"), button);
            });
        });
    </script>
</body>
</html>
