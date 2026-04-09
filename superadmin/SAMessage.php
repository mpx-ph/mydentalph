<?php
declare(strict_types=1);

require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

$currentSuperadminId = (string) ($_SESSION['user_id'] ?? '');
$flashError = '';
$flashSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantId = trim((string) ($_POST['tenant_id'] ?? ''));
    $receiverId = trim((string) ($_POST['receiver_id'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $content = trim((string) ($_POST['message'] ?? ''));

    if ($tenantId === '' || $receiverId === '') {
        $flashError = 'Choose a tenant conversation first.';
    } elseif ($content === '') {
        $flashError = 'Message cannot be empty.';
    } elseif ($currentSuperadminId === '') {
        $flashError = 'Your super admin session is missing a user ID.';
    } else {
        try {
            $receiverStmt = $pdo->prepare("
                SELECT user_id
                FROM tbl_users
                WHERE user_id = ?
                  AND tenant_id = ?
                  AND role IN ('tenant_owner', 'manager', 'staff', 'dentist')
                LIMIT 1
            ");
            $receiverStmt->execute([$receiverId, $tenantId]);
            $verifiedReceiver = (string) ($receiverStmt->fetchColumn() ?: '');

            if ($verifiedReceiver === '') {
                $flashError = 'This tenant contact is no longer available.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO tbl_messages (
                        tenant_id, sender_id, receiver_id, subject, message, is_read, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 0, 'sent', NOW())
                ");
                $ins->execute([
                    $tenantId,
                    $currentSuperadminId,
                    $verifiedReceiver,
                    $subject !== '' ? $subject : null,
                    $content
                ]);
                $flashSuccess = 'Reply sent.';
            }
        } catch (Throwable $e) {
            $flashError = 'Unable to send reply at the moment.';
        }
    }
}

$tenantConversations = [];
try {
    $convStmt = $pdo->prepare("
        SELECT
            m.tenant_id,
            CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS partner_id,
            MAX(m.created_at) AS last_message_at
        FROM tbl_messages m
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY m.tenant_id, partner_id
        ORDER BY last_message_at DESC
    ");
    $convStmt->execute([$currentSuperadminId, $currentSuperadminId, $currentSuperadminId]);
    $tenantConversations = $convStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenantConversations = [];
}

$selectedTenantId = isset($_GET['tenant']) ? trim((string) $_GET['tenant']) : '';
$selectedPartnerId = isset($_GET['with']) ? trim((string) $_GET['with']) : '';
if ($selectedTenantId === '' || $selectedPartnerId === '') {
    if (isset($tenantConversations[0])) {
        $selectedTenantId = (string) ($tenantConversations[0]['tenant_id'] ?? '');
        $selectedPartnerId = (string) ($tenantConversations[0]['partner_id'] ?? '');
    }
}

$tenantMap = [];
foreach ($tenantConversations as $row) {
    $tid = (string) ($row['tenant_id'] ?? '');
    $pid = (string) ($row['partner_id'] ?? '');
    if ($tid === '' || $pid === '') {
        continue;
    }
    $tenantMap[$tid . '|' . $pid] = $row;
}
$tenantConversations = array_values($tenantMap);

$tenantIds = [];
$partnerIds = [];
foreach ($tenantConversations as $row) {
    $tid = (string) ($row['tenant_id'] ?? '');
    $pid = (string) ($row['partner_id'] ?? '');
    if ($tid !== '') {
        $tenantIds[$tid] = true;
    }
    if ($pid !== '') {
        $partnerIds[$pid] = true;
    }
}

$tenantInfo = [];
if ($tenantIds !== []) {
    $in = implode(',', array_fill(0, count($tenantIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT tenant_id, clinic_name FROM tbl_tenants WHERE tenant_id IN ($in)");
        $stmt->execute(array_keys($tenantIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tenantInfo[(string) $row['tenant_id']] = $row;
        }
    } catch (Throwable $e) {
    }
}

$userInfo = [];
if ($partnerIds !== []) {
    $in = implode(',', array_fill(0, count($partnerIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT user_id, full_name, email, role FROM tbl_users WHERE user_id IN ($in)");
        $stmt->execute(array_keys($partnerIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userInfo[(string) $row['user_id']] = $row;
        }
    } catch (Throwable $e) {
    }
}

$messages = [];
if ($selectedTenantId !== '' && $selectedPartnerId !== '' && $currentSuperadminId !== '') {
    try {
        $msgStmt = $pdo->prepare("
            SELECT id, tenant_id, sender_id, receiver_id, subject, message, is_read, status, created_at
            FROM tbl_messages
            WHERE tenant_id = ?
              AND (
                  (sender_id = ? AND receiver_id = ?)
                  OR
                  (sender_id = ? AND receiver_id = ?)
              )
            ORDER BY created_at ASC, id ASC
        ");
        $msgStmt->execute([$selectedTenantId, $currentSuperadminId, $selectedPartnerId, $selectedPartnerId, $currentSuperadminId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $markStmt = $pdo->prepare("
            UPDATE tbl_messages
            SET is_read = 1, status = 'seen'
            WHERE tenant_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $markStmt->execute([$selectedTenantId, $selectedPartnerId, $currentSuperadminId]);
    } catch (Throwable $e) {
        $messages = [];
    }
}

$superadmin_nav = 'dashboard';
$superadmin_header_center = '<div class="text-sm font-semibold text-on-surface-variant">Super Admin · Messages</div>';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Super Admin | Messages</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#0066ff',
                        'on-surface': '#131c25',
                        'on-surface-variant': '#475569'
                    },
                    fontFamily: {
                        headline: ['Plus Jakarta Sans', 'sans-serif'],
                        body: ['Plus Jakarta Sans', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 430, 'GRAD' 0, 'opsz' 24; }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface min-h-screen">
<?php require __DIR__ . '/superadmin_sidebar.php'; ?>
<?php require __DIR__ . '/superadmin_header.php'; ?>

<main class="ml-64 pt-20 min-h-screen">
    <div class="px-8 py-8">
        <section class="mb-6">
            <h2 class="text-3xl font-extrabold font-headline tracking-tight">Tenant Messages</h2>
            <p class="text-sm text-on-surface-variant mt-1">Communicate directly with provider tenant teams.</p>
        </section>

        <?php if ($flashError !== ''): ?>
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess !== ''): ?>
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <section class="rounded-3xl border border-slate-200/70 bg-white/85 backdrop-blur-xl shadow-sm overflow-hidden min-h-[72vh] grid grid-cols-1 xl:grid-cols-[21rem,1fr]">
            <aside class="border-r border-slate-200/70 p-4">
                <p class="text-[11px] uppercase tracking-[0.18em] text-primary font-extrabold">Tenant Threads</p>
                <p class="text-xs text-on-surface-variant mt-1"><?php echo count($tenantConversations); ?> active conversation(s)</p>
                <div class="mt-3 space-y-2 max-h-[60vh] overflow-y-auto no-scrollbar pr-1">
                    <?php if ($tenantConversations === []): ?>
                        <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-sm text-on-surface-variant">No tenant messages yet.</div>
                    <?php else: ?>
                        <?php foreach ($tenantConversations as $row):
                            $tid = (string) ($row['tenant_id'] ?? '');
                            $pid = (string) ($row['partner_id'] ?? '');
                            $active = ($tid === $selectedTenantId && $pid === $selectedPartnerId);
                            $tenantName = trim((string) ($tenantInfo[$tid]['clinic_name'] ?? $tid));
                            $partner = $userInfo[$pid] ?? [];
                            $partnerName = trim((string) ($partner['full_name'] ?? 'Tenant user'));
                            if ($partnerName === '') { $partnerName = 'Tenant user'; }
                        ?>
                            <a href="?tenant=<?php echo rawurlencode($tid); ?>&with=<?php echo rawurlencode($pid); ?>" class="block rounded-2xl border px-3 py-3 transition-all <?php echo $active ? 'border-primary/35 bg-primary/5' : 'border-slate-200 hover:border-primary/25 hover:bg-slate-50'; ?>">
                                <p class="text-sm font-bold truncate"><?php echo htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-on-surface-variant truncate mt-1"><?php echo htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="flex flex-col min-h-[72vh]">
                <div class="border-b border-slate-200/70 px-5 py-4">
                    <?php
                    $tenantName = trim((string) ($tenantInfo[$selectedTenantId]['clinic_name'] ?? 'Select a conversation'));
                    $partner = $userInfo[$selectedPartnerId] ?? [];
                    $partnerName = trim((string) ($partner['full_name'] ?? 'Tenant contact'));
                    if ($partnerName === '') { $partnerName = 'Tenant contact'; }
                    ?>
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-extrabold">Thread</p>
                    <h3 class="text-lg font-extrabold mt-1"><?php echo htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-xs text-on-surface-variant mt-1"><?php echo htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <div id="thread-box" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-gradient-to-b from-white to-slate-50/50">
                    <?php if ($selectedTenantId === '' || $selectedPartnerId === ''): ?>
                        <div class="h-full flex items-center justify-center text-sm text-on-surface-variant">Select a tenant thread to view messages.</div>
                    <?php elseif ($messages === []): ?>
                        <div class="h-full flex items-center justify-center text-sm text-on-surface-variant">No messages in this thread yet.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $m):
                            $mine = (string) ($m['sender_id'] ?? '') === $currentSuperadminId;
                        ?>
                            <div class="flex <?php echo $mine ? 'justify-end' : 'justify-start'; ?>">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 shadow-sm <?php echo $mine ? 'bg-primary text-white' : 'bg-white border border-slate-200 text-on-surface'; ?>">
                                    <?php if (trim((string) ($m['subject'] ?? '')) !== ''): ?>
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
                    <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($selectedTenantId, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <input type="hidden" name="receiver_id" value="<?php echo htmlspecialchars($selectedPartnerId, ENT_QUOTES, 'UTF-8'); ?>"/>
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
                            placeholder="Reply to this tenant..."
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
    })();
</script>
</body>
</html>
