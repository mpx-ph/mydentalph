<?php
declare(strict_types=1);

require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';

$tenantId = (string) ($tenant_id ?? ($_SESSION['tenant_id'] ?? ''));
$userId = (string) ($user_id ?? ($_SESSION['user_id'] ?? ''));
$flashError = '';
$flashSuccess = '';

if ($tenantId === '' || $userId === '') {
    header('Location: ProviderLogin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim((string) ($_POST['message'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $receiverId = trim((string) ($_POST['receiver_id'] ?? ''));

    if ($content === '') {
        $flashError = 'Message cannot be empty.';
    } else {
        try {
            if ($receiverId === '') {
                $saStmt = $pdo->prepare("
                    SELECT user_id
                    FROM tbl_users
                    WHERE role = 'superadmin'
                      AND status = 'active'
                    ORDER BY user_id ASC
                    LIMIT 1
                ");
                $saStmt->execute();
                $receiverId = (string) ($saStmt->fetchColumn() ?: '');
            } else {
                $verifyStmt = $pdo->prepare("
                    SELECT user_id
                    FROM tbl_users
                    WHERE user_id = ?
                      AND role = 'superadmin'
                    LIMIT 1
                ");
                $verifyStmt->execute([$receiverId]);
                $receiverId = (string) ($verifyStmt->fetchColumn() ?: '');
            }

            if ($receiverId === '') {
                $flashError = 'No active super admin account is available yet.';
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO tbl_messages (
                        tenant_id, sender_id, receiver_id, subject, message, is_read, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 0, 'sent', NOW())
                ");
                $insert->execute([
                    $tenantId,
                    $userId,
                    $receiverId,
                    $subject !== '' ? $subject : null,
                    $content,
                ]);
                $flashSuccess = 'Message sent to super admin.';
            }
        } catch (Throwable $e) {
            $flashError = 'Unable to send message right now.';
        }
    }
}

$superAdmins = [];
$activeSuperadminId = '';
try {
    $saList = $pdo->query("
        SELECT user_id, full_name, email, status
        FROM tbl_users
        WHERE role = 'superadmin'
        ORDER BY status = 'active' DESC, full_name ASC, user_id ASC
    ");
    $superAdmins = $saList ? $saList->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($superAdmins as $saRow) {
        if (strtolower((string) ($saRow['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $activeSuperadminId = (string) ($saRow['user_id'] ?? '');
        if ($activeSuperadminId !== '') {
            break;
        }
    }
    if ($activeSuperadminId === '' && isset($superAdmins[0]['user_id'])) {
        $activeSuperadminId = (string) $superAdmins[0]['user_id'];
    }
} catch (Throwable $e) {
    $superAdmins = [];
}

$selectedSuperadmin = $activeSuperadminId;
if (isset($_GET['with'])) {
    $requested = trim((string) $_GET['with']);
    foreach ($superAdmins as $row) {
        if ((string) ($row['user_id'] ?? '') === $requested) {
            $selectedSuperadmin = $requested;
            break;
        }
    }
}

$conversations = [];
try {
    $convStmt = $pdo->prepare("
        SELECT
            CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS partner_id,
            MAX(m.created_at) AS last_message_at
        FROM tbl_messages m
        INNER JOIN tbl_users su
            ON su.user_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.tenant_id = ?
          AND (m.sender_id = ? OR m.receiver_id = ?)
          AND su.role = 'superadmin'
        GROUP BY partner_id
        ORDER BY last_message_at DESC
    ");
    $convStmt->execute([$userId, $userId, $tenantId, $userId, $userId]);
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $conversations = [];
}

$conversationMap = [];
foreach ($conversations as $row) {
    $partnerId = (string) ($row['partner_id'] ?? '');
    if ($partnerId !== '') {
        $conversationMap[$partnerId] = $row;
    }
}
foreach ($superAdmins as $saRow) {
    $sid = (string) ($saRow['user_id'] ?? '');
    if ($sid !== '' && !isset($conversationMap[$sid])) {
        $conversationMap[$sid] = ['partner_id' => $sid, 'last_message_at' => null];
    }
}
$conversations = array_values($conversationMap);

usort($conversations, static function (array $a, array $b): int {
    $ta = (string) ($a['last_message_at'] ?? '');
    $tb = (string) ($b['last_message_at'] ?? '');
    return strcmp($tb, $ta);
});

$userById = [];
foreach ($superAdmins as $sa) {
    $sid = (string) ($sa['user_id'] ?? '');
    if ($sid !== '') {
        $userById[$sid] = $sa;
    }
}

$messages = [];
if ($selectedSuperadmin !== '') {
    try {
        $msgStmt = $pdo->prepare("
            SELECT id, sender_id, receiver_id, subject, message, is_read, status, created_at
            FROM tbl_messages
            WHERE tenant_id = ?
              AND (
                (sender_id = ? AND receiver_id = ?)
                OR
                (sender_id = ? AND receiver_id = ?)
              )
            ORDER BY created_at ASC, id ASC
        ");
        $msgStmt->execute([$tenantId, $userId, $selectedSuperadmin, $selectedSuperadmin, $userId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $markStmt = $pdo->prepare("
            UPDATE tbl_messages
            SET is_read = 1, status = 'seen'
            WHERE tenant_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $markStmt->execute([$tenantId, $selectedSuperadmin, $userId]);
    } catch (Throwable $e) {
        $messages = [];
    }
}

$provider_nav_active = 'messages';
$clinic_display_name = isset($clinic_name) && trim((string) $clinic_name) !== '' ? (string) $clinic_name : 'Clinic Workspace';
$user_email_display = trim((string) ($_SESSION['email'] ?? ''));
require_once __DIR__ . '/provider_tenant_header_context.inc.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MyDental | Tenant Messages</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#2b8beb',
                        'on-background': '#101922',
                        'on-surface-variant': '#4b5563',
                        'surface-container-low': '#edf4ff'
                    },
                    fontFamily: {
                        headline: ['Manrope', 'sans-serif'],
                        body: ['Manrope', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24; }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @media (max-width: 1023.98px) {
            .provider-top-header {
                left: 0 !important;
                min-height: 5rem;
            }
            #provider-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
                background: #ffffff;
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                border-right: 1px solid #e2e8f0;
            }
            body.provider-mobile-sidebar-open #provider-sidebar {
                transform: translateX(0);
            }
            #provider-mobile-sidebar-toggle {
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #provider-mobile-sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(19, 28, 37, 0.45);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 55;
                opacity: 0;
                pointer-events: none;
                transition: opacity 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>

<button id="provider-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="provider-sidebar" aria-expanded="false" aria-label="Open navigation menu">
    <span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="provider-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>

<main class="ml-0 lg:ml-64 pt-[4.75rem] sm:pt-20 min-h-screen">
    <div class="px-6 lg:px-10 py-8">
        <section class="mb-6">
            <h2 class="text-3xl font-extrabold font-headline tracking-tight">Secure Messages</h2>
            <p class="text-sm text-on-surface-variant mt-1">Send and receive messages with the super admin team.</p>
        </section>

        <?php if ($flashError !== ''): ?>
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess !== ''): ?>
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="rounded-3xl border border-slate-200/70 bg-white/85 backdrop-blur-xl shadow-sm overflow-hidden min-h-[70vh] grid grid-cols-1 xl:grid-cols-[20rem,1fr]">
            <aside class="border-r border-slate-200/70 p-4">
                <div class="mb-3">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-primary font-extrabold">Conversations</p>
                    <p class="text-xs text-on-surface-variant mt-1"><?php echo count($conversations); ?> contact(s)</p>
                </div>
                <div class="space-y-2 max-h-[58vh] overflow-y-auto no-scrollbar pr-1">
                    <?php if ($conversations === []): ?>
                        <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-sm text-on-surface-variant">No conversations yet.</div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv):
                            $pid = (string) ($conv['partner_id'] ?? '');
                            $isActive = $pid !== '' && $pid === $selectedSuperadmin;
                            $person = $userById[$pid] ?? [];
                            $name = trim((string) ($person['full_name'] ?? 'Super Admin'));
                            if ($name === '') { $name = 'Super Admin'; }
                            $preview = trim((string) ($person['email'] ?? ''));
                        ?>
                            <a href="?with=<?php echo rawurlencode($pid); ?>" class="block rounded-2xl border px-3 py-3 transition-all <?php echo $isActive ? 'border-primary/35 bg-primary/5' : 'border-slate-200 hover:border-primary/25 hover:bg-slate-50'; ?>">
                                <p class="text-sm font-bold text-on-background truncate"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-on-surface-variant truncate mt-1"><?php echo htmlspecialchars($preview !== '' ? $preview : 'Super Admin Team', ENT_QUOTES, 'UTF-8'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="flex flex-col min-h-[70vh]">
                <div class="border-b border-slate-200/70 px-5 py-4">
                    <?php
                    $activeUser = $selectedSuperadmin !== '' ? ($userById[$selectedSuperadmin] ?? []) : [];
                    $activeName = trim((string) ($activeUser['full_name'] ?? 'Super Admin'));
                    if ($activeName === '') { $activeName = 'Super Admin'; }
                    ?>
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-extrabold">Thread</p>
                    <h3 class="text-lg font-extrabold mt-1"><?php echo htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>

                <div id="thread-box" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-gradient-to-b from-white to-slate-50/50">
                    <?php if ($selectedSuperadmin === ''): ?>
                        <div class="h-full flex items-center justify-center text-sm text-on-surface-variant">No super admin account found.</div>
                    <?php elseif ($messages === []): ?>
                        <div class="h-full flex items-center justify-center text-sm text-on-surface-variant">No messages yet. Start the conversation below.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $m):
                            $mine = (string) ($m['sender_id'] ?? '') === $userId;
                        ?>
                            <div class="flex <?php echo $mine ? 'justify-end' : 'justify-start'; ?>">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 shadow-sm <?php echo $mine ? 'bg-primary text-white' : 'bg-white border border-slate-200 text-on-background'; ?>">
                                    <?php if (!$mine && trim((string) ($m['subject'] ?? '')) !== ''): ?>
                                        <p class="text-[11px] font-bold uppercase tracking-wide opacity-80 mb-1"><?php echo htmlspecialchars((string) $m['subject'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words"><?php echo htmlspecialchars((string) ($m['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-[11px] mt-2 <?php echo $mine ? 'text-white/80' : 'text-on-surface-variant'; ?>">
                                        <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string) ($m['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="post" class="border-t border-slate-200/70 p-4 bg-white/90">
                    <input type="hidden" name="receiver_id" value="<?php echo htmlspecialchars($selectedSuperadmin, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <div class="grid grid-cols-1 md:grid-cols-[1fr,auto] gap-3">
                        <input
                            type="text"
                            name="subject"
                            maxlength="255"
                            placeholder="Subject (optional)"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-primary focus:ring-primary/30"
                        />
                        <button type="submit" class="hidden md:inline-flex items-center justify-center rounded-xl bg-primary px-5 text-white text-sm font-bold hover:brightness-110 transition">
                            Send
                        </button>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <textarea
                            name="message"
                            rows="3"
                            required
                            placeholder="Write a secure message to super admin..."
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-primary focus:ring-primary/30 resize-none"
                        ></textarea>
                        <button type="submit" class="md:hidden inline-flex items-center justify-center self-end rounded-xl bg-primary px-4 h-11 text-white text-sm font-bold">Send</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
<script>
    (function () {
        var box = document.getElementById('thread-box');
        if (box) { box.scrollTop = box.scrollHeight; }

        var body = document.body;
        var sidebar = document.getElementById('provider-sidebar');
        var mobileToggle = document.getElementById('provider-mobile-sidebar-toggle');
        var mobileBackdrop = document.getElementById('provider-mobile-sidebar-backdrop');
        var desktopQuery = window.matchMedia('(min-width: 1024px)');

        if (!body || !sidebar || !mobileToggle || !mobileBackdrop) {
            return;
        }

        function setMobileSidebar(open) {
            body.classList.toggle('provider-mobile-sidebar-open', open);
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            var icon = mobileToggle.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = open ? 'close' : 'menu';
            }
        }

        function closeOnDesktop() {
            if (desktopQuery.matches) {
                setMobileSidebar(false);
            }
        }

        mobileToggle.addEventListener('click', function () {
            setMobileSidebar(!body.classList.contains('provider-mobile-sidebar-open'));
        });
        mobileBackdrop.addEventListener('click', function () {
            setMobileSidebar(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.classList.contains('provider-mobile-sidebar-open')) {
                setMobileSidebar(false);
            }
        });
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!desktopQuery.matches) {
                    setMobileSidebar(false);
                }
            });
        });
        if (typeof desktopQuery.addEventListener === 'function') {
            desktopQuery.addEventListener('change', closeOnDesktop);
        } else if (typeof desktopQuery.addListener === 'function') {
            desktopQuery.addListener(closeOnDesktop);
        }

        setMobileSidebar(false);
    })();
</script>
</body>
</html>
