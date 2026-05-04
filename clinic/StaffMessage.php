<?php
declare(strict_types=1);

$pageTitle = 'Patient Messages';
$staff_nav_active = 'messages';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/tenant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedTypes = ['admin', 'staff', 'doctor', 'manager'];
if (empty($_SESSION['user_id']) || empty($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowedTypes, true)) {
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffMessage.php';
    if (is_string($reqPath) && $reqPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($reqPath, '/')), 'strlen'));
        $scriptIdx = array_search($scriptBase, $segments, true);
        if ($scriptIdx !== false && $scriptIdx > 0) {
            $slugFromPath = strtolower(trim((string) $segments[$scriptIdx - 1]));
            if ($slugFromPath !== '' && preg_match('/^[a-z0-9\-]+$/', $slugFromPath)) {
                $_GET['clinic_slug'] = $slugFromPath;
            }
        }
    }
}

$clinicSlugBoot = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinicSlugBoot !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinicSlugBoot))) {
    $_GET['clinic_slug'] = strtolower($clinicSlugBoot);
    require_once __DIR__ . '/tenant_bootstrap.php';
    if (!isset($currentTenantSlug) || trim((string) $currentTenantSlug) === '') {
        $currentTenantSlug = strtolower($clinicSlugBoot);
    }
} else {
    $currentTenantSlug = '';
}

requireClinicTenantId();

$pdo = getDBConnection();
$tenantId = trim((string) getClinicTenantId());
$staffUserId = trim((string) ($_SESSION['user_id'] ?? ''));

$buildStaffMessageHref = function (array $query = []) use (&$currentTenantSlug): string {
    $page = 'StaffMessage.php';
    $slug = isset($currentTenantSlug) ? trim((string) $currentTenantSlug) : '';
    if ($slug === '' && function_exists('getClinicTenantSlug')) {
        $gs = getClinicTenantSlug();
        if (is_string($gs) && $gs !== '') {
            $slug = $gs;
        }
    }
    $q = $query === [] ? '' : ('?' . http_build_query($query));
    if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($slug))) {
        return '/' . rawurlencode(strtolower($slug)) . '/' . $page . $q;
    }
    $merged = $query;
    if ($slug !== '') {
        $merged['clinic_slug'] = strtolower($slug);
    }
    return $page . ($merged === [] ? '' : ('?' . http_build_query($merged)));
};

$flashError = '';
$flashSuccess = '';

if ($tenantId === '' || $staffUserId === '') {
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

/** @return array{0: string, 1: string} [display, subtitle] */
$patientDisplayFromRow = static function (array $row): array {
    $pf = trim((string) ($row['patient_first'] ?? ''));
    $pl = trim((string) ($row['patient_last'] ?? ''));
    $fromPatient = trim($pf . ' ' . $pl);
    $full = trim((string) ($row['full_name'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $name = $fromPatient !== '' ? $fromPatient : ($full !== '' ? $full : 'Patient');
    $sub = $fromPatient !== '' && $email !== '' ? $email : ($email !== '' ? $email : ($full !== '' && $fromPatient === '' ? '' : ''));
    if ($sub === '' && $fromPatient !== '' && $full !== '' && strcasecmp($full, $fromPatient) !== 0) {
        $sub = $full;
    }
    return [$name, $sub];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim((string) ($_POST['message'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $receiverId = trim((string) ($_POST['receiver_id'] ?? ''));

    if ($content === '') {
        $flashError = 'Message cannot be empty.';
    } elseif ($receiverId === '') {
        $flashError = 'Choose a patient to message.';
    } else {
        try {
            $verifyStmt = $pdo->prepare("
                SELECT user_id
                FROM tbl_users
                WHERE user_id = ?
                  AND tenant_id = ?
                  AND role = 'client'
                  AND status = 'active'
                LIMIT 1
            ");
            $verifyStmt->execute([$receiverId, $tenantId]);
            $verified = (string) ($verifyStmt->fetchColumn() ?: '');

            if ($verified === '') {
                $flashError = 'That patient account is not available for messaging.';
            } else {
                $staffStmt = $pdo->prepare("
                    SELECT user_id
                    FROM tbl_users
                    WHERE user_id = ?
                      AND tenant_id = ?
                      AND role IN ('tenant_owner', 'manager', 'staff', 'dentist')
                      AND status = 'active'
                    LIMIT 1
                ");
                $staffStmt->execute([$staffUserId, $tenantId]);
                if ((string) ($staffStmt->fetchColumn() ?: '') === '') {
                    $flashError = 'Your account cannot send clinic messages.';
                } else {
                    $insert = $pdo->prepare("
                        INSERT INTO tbl_messages (
                            tenant_id, sender_id, receiver_id, subject, message, is_read, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, 0, 'sent', NOW())
                    ");
                    $insert->execute([
                        $tenantId,
                        $staffUserId,
                        $verified,
                        $subject !== '' ? $subject : null,
                        $content,
                    ]);
                    $flashSuccess = 'Message sent.';
                }
            }
        } catch (Throwable $e) {
            $flashError = 'Unable to send message right now.';
        }
    }
}

$patientClients = [];
try {
    $listStmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.email,
            u.full_name,
            p.first_name AS patient_first,
            p.last_name AS patient_last,
            p.patient_id
        FROM tbl_users u
        LEFT JOIN tbl_patients p
            ON p.tenant_id = u.tenant_id
           AND p.linked_user_id = u.user_id
        WHERE u.tenant_id = ?
          AND u.role = 'client'
          AND u.status = 'active'
        ORDER BY
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''),
                     u.full_name,
                     u.email) ASC
    ");
    $listStmt->execute([$tenantId]);
    $patientClients = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $patientClients = [];
}

$conversations = [];
try {
    $convStmt = $pdo->prepare("
        SELECT
            CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS partner_id,
            MAX(m.created_at) AS last_message_at
        FROM tbl_messages m
        INNER JOIN tbl_users pu
            ON pu.user_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.tenant_id = ?
          AND (m.sender_id = ? OR m.receiver_id = ?)
          AND pu.tenant_id = ?
          AND pu.role = 'client'
        GROUP BY partner_id
    ");
    $convStmt->execute([$staffUserId, $staffUserId, $tenantId, $staffUserId, $staffUserId, $tenantId]);
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $conversations = [];
}

$conversationMap = [];
foreach ($conversations as $row) {
    $pid = (string) ($row['partner_id'] ?? '');
    if ($pid !== '') {
        $conversationMap[$pid] = $row;
    }
}
foreach ($patientClients as $pRow) {
    $uid = (string) ($pRow['user_id'] ?? '');
    if ($uid !== '' && !isset($conversationMap[$uid])) {
        $conversationMap[$uid] = ['partner_id' => $uid, 'last_message_at' => null];
    }
}
$conversations = array_values($conversationMap);

usort($conversations, static function (array $a, array $b): int {
    $ta = (string) ($a['last_message_at'] ?? '');
    $tb = (string) ($b['last_message_at'] ?? '');
    if ($ta !== $tb) {
        return strcmp($tb, $ta);
    }
    return strcmp((string) ($a['partner_id'] ?? ''), (string) ($b['partner_id'] ?? ''));
});

$userById = [];
foreach ($patientClients as $pRow) {
    $uid = (string) ($pRow['user_id'] ?? '');
    if ($uid !== '') {
        $userById[$uid] = $pRow;
    }
}

$missingPartnerIds = [];
foreach (array_keys($conversationMap) as $pid) {
    if ($pid !== '' && !isset($userById[$pid])) {
        $missingPartnerIds[] = $pid;
    }
}
if ($missingPartnerIds !== []) {
    $placeholders = implode(',', array_fill(0, count($missingPartnerIds), '?'));
    try {
        $extraStmt = $pdo->prepare("
            SELECT
                u.user_id,
                u.email,
                u.full_name,
                p.first_name AS patient_first,
                p.last_name AS patient_last,
                p.patient_id
            FROM tbl_users u
            LEFT JOIN tbl_patients p
                ON p.tenant_id = u.tenant_id
               AND p.linked_user_id = u.user_id
            WHERE u.tenant_id = ?
              AND u.user_id IN ($placeholders)
              AND u.role = 'client'
        ");
        $extraStmt->execute(array_merge([$tenantId], $missingPartnerIds));
        foreach ($extraStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ex) {
            $eid = (string) ($ex['user_id'] ?? '');
            if ($eid !== '') {
                $userById[$eid] = $ex;
            }
        }
    } catch (Throwable $e) {
    }
}

$staffMessageNavBase = $buildStaffMessageHref([]);
$staffMessageNavSep = strpos($staffMessageNavBase, '?') !== false ? '&' : '?';

$selectedPatientId = '';
if (isset($_GET['with'])) {
    $requested = trim((string) $_GET['with']);
    if ($requested !== '' && isset($userById[$requested])) {
        $selectedPatientId = $requested;
    }
}
if ($selectedPatientId === '' && $conversations !== []) {
    $selectedPatientId = (string) ($conversations[0]['partner_id'] ?? '');
}

$switchPatientRows = $patientClients;
if ($switchPatientRows === [] && $conversations !== []) {
    foreach ($conversations as $cRow) {
        $spid = (string) ($cRow['partner_id'] ?? '');
        if ($spid !== '' && isset($userById[$spid])) {
            $switchPatientRows[] = $userById[$spid];
        }
    }
}

$showPatientMessaging = $patientClients !== [] || $conversations !== [];

$selectedPatientCanReply = false;
if ($selectedPatientId !== '') {
    foreach ($patientClients as $pRow) {
        if ((string) ($pRow['user_id'] ?? '') === $selectedPatientId) {
            $selectedPatientCanReply = true;
            break;
        }
    }
}

$messages = [];
if ($selectedPatientId !== '') {
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
        $msgStmt->execute([$tenantId, $staffUserId, $selectedPatientId, $selectedPatientId, $staffUserId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $markStmt = $pdo->prepare("
            UPDATE tbl_messages
            SET is_read = 1, status = 'seen'
            WHERE tenant_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $markStmt->execute([$tenantId, $selectedPatientId, $staffUserId]);
    } catch (Throwable $e) {
        $messages = [];
    }
}

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#2b8beb',
                        background: '#f8fafc',
                        surface: '#ffffff',
                        'on-background': '#101922',
                        'on-surface-variant': '#404752',
                        'surface-container-low': '#edf4ff'
                    },
                    fontFamily: {
                        headline: ['Manrope', 'sans-serif'],
                        body: ['Manrope', 'sans-serif'],
                        editorial: ['Playfair Display', 'serif']
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%), radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

<div class="p-6 sm:p-10 space-y-8">
    <section class="flex flex-col gap-4">
        <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
            <span class="w-12 h-[1.5px] bg-primary"></span> PATIENT CARE
        </div>
        <div>
            <h2 class="font-headline text-4xl sm:text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                Patient <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Messages</span>
            </h2>
            <p class="font-body text-base sm:text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                Secure two-way messaging with registered patient accounts for this clinic.
            </p>
        </div>
    </section>

    <?php if ($flashError !== ''): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-semibold"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$showPatientMessaging): ?>
        <section class="elevated-card rounded-3xl p-10 text-center text-on-surface-variant">
            <p class="font-semibold text-on-background">No patient conversations yet</p>
            <p class="text-sm mt-2 max-w-md mx-auto">Patient messaging uses portal accounts with the <strong class="font-bold text-on-background">client</strong> role. Add patients from Patients management; when they have an account, you can message them here.</p>
        </section>
    <?php else: ?>
        <section class="elevated-card rounded-3xl overflow-hidden min-h-[65vh] grid grid-cols-1 xl:grid-cols-[19rem,1fr]">
            <aside class="border-b xl:border-b-0 xl:border-r border-slate-200/80 p-4 sm:p-5 bg-white/60">
                <p class="text-[11px] uppercase tracking-[0.18em] text-primary font-extrabold">Conversations</p>
                <p class="text-xs text-on-surface-variant mt-1"><?php echo count($conversations); ?> patient account(s)</p>
                <div class="mt-3 space-y-2 max-h-[42vh] xl:max-h-[58vh] overflow-y-auto no-scrollbar pr-1">
                    <?php foreach ($conversations as $conv):
                        $pid = (string) ($conv['partner_id'] ?? '');
                        $isActive = $pid !== '' && $pid === $selectedPatientId;
                        $person = $userById[$pid] ?? [];
                        [$pName, $pSub] = $patientDisplayFromRow($person + ['user_id' => $pid]);
                        if ($person === []) {
                            $pName = 'Patient';
                            $pSub = '';
                        }
                    ?>
                        <a href="<?php echo htmlspecialchars($buildStaffMessageHref(['with' => $pid]), ENT_QUOTES, 'UTF-8'); ?>" class="block rounded-2xl border px-3 py-3 transition-all <?php echo $isActive ? 'border-primary/40 bg-primary/8 shadow-sm shadow-primary/10' : 'border-slate-200/90 hover:border-primary/25 hover:bg-slate-50'; ?>">
                            <p class="text-sm font-bold text-on-background truncate"><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-on-surface-variant truncate mt-1"><?php echo htmlspecialchars($pSub !== '' ? $pSub : 'Patient portal', ENT_QUOTES, 'UTF-8'); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="flex flex-col min-h-[50vh] xl:min-h-[65vh] bg-white/40">
                <div class="border-b border-slate-200/80 px-5 py-4 bg-white/80">
                    <?php
                    $activeRow = $selectedPatientId !== '' ? ($userById[$selectedPatientId] ?? []) : [];
                    [$threadTitle, $threadSub] = $patientDisplayFromRow($activeRow + ['user_id' => $selectedPatientId]);
                    if ($activeRow === []) {
                        $threadTitle = 'Patient';
                        $threadSub = '';
                    }
                    ?>
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-extrabold">Thread</p>
                    <h3 class="text-lg font-extrabold font-headline mt-1"><?php echo htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <?php if ($threadSub !== ''): ?>
                        <p class="text-xs text-on-surface-variant mt-1"><?php echo htmlspecialchars($threadSub, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <div id="thread-box" class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-3 bg-gradient-to-b from-white/90 to-slate-50/60">
                    <?php if ($selectedPatientId === ''): ?>
                        <div class="h-full min-h-[12rem] flex items-center justify-center text-sm text-on-surface-variant">Select a patient from the list.</div>
                    <?php elseif ($messages === []): ?>
                        <div class="h-full min-h-[12rem] flex items-center justify-center text-sm text-on-surface-variant">No messages yet. Send an introduction below.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $m):
                            $mine = (string) ($m['sender_id'] ?? '') === $staffUserId;
                        ?>
                            <div class="flex <?php echo $mine ? 'justify-end' : 'justify-start'; ?>">
                                <div class="max-w-[90%] sm:max-w-[82%] rounded-2xl px-4 py-3 shadow-sm <?php echo $mine ? 'bg-primary text-white' : 'bg-white border border-slate-200 text-on-background'; ?>">
                                    <?php if (trim((string) ($m['subject'] ?? '')) !== ''): ?>
                                        <p class="text-[11px] font-bold uppercase tracking-wide opacity-90 mb-1"><?php echo htmlspecialchars((string) $m['subject'], ENT_QUOTES, 'UTF-8'); ?></p>
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

                <?php if ($selectedPatientId !== '' && !$selectedPatientCanReply): ?>
                    <div class="border-t border-slate-200/80 p-4 sm:p-5 bg-amber-50/90 text-sm text-amber-900">
                        This patient account is <strong>inactive</strong>. You can read the thread above; new messages cannot be sent until the account is active again.
                    </div>
                <?php elseif ($selectedPatientId !== ''): ?>
                <form method="post" class="border-t border-slate-200/80 p-4 sm:p-5 bg-white/95">
                    <input type="hidden" name="receiver_id" value="<?php echo htmlspecialchars($selectedPatientId, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <label class="block text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.16em] mb-2 md:hidden">Switch patient</label>
                    <?php if ($switchPatientRows !== []): ?>
                    <select class="w-full md:hidden mb-3 rounded-xl border-slate-200 text-sm font-semibold focus:border-primary focus:ring-primary/25" aria-label="Switch patient" id="staff-msg-patient-switch">
                        <?php foreach ($switchPatientRows as $opt):
                            $oid = (string) ($opt['user_id'] ?? '');
                            [$oname] = $patientDisplayFromRow($opt);
                        ?>
                            <option value="<?php echo htmlspecialchars($oid, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $oid === $selectedPatientId ? ' selected' : ''; ?>><?php echo htmlspecialchars($oname, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <script>
                        (function () {
                            var sel = document.getElementById('staff-msg-patient-switch');
                            if (!sel) return;
                            var base = <?php echo json_encode($staffMessageNavBase, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES); ?>;
                            var sep = <?php echo json_encode($staffMessageNavSep, JSON_HEX_TAG | JSON_HEX_APOS); ?>;
                            sel.addEventListener('change', function () {
                                if (!this.value) return;
                                window.location.href = base + sep + 'with=' + encodeURIComponent(this.value);
                            });
                        })();
                    </script>
                    <div class="grid grid-cols-1 md:grid-cols-[1fr,auto] gap-3">
                        <input
                            type="text"
                            name="subject"
                            maxlength="255"
                            placeholder="Subject (optional)"
                            class="w-full rounded-xl border-slate-200 text-sm focus:border-primary focus:ring-primary/25"
                        />
                        <button type="submit" class="hidden md:inline-flex items-center justify-center rounded-xl bg-primary px-6 text-white text-sm font-bold hover:bg-primary/90 transition shadow-md shadow-primary/20">
                            Send
                        </button>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <textarea
                            name="message"
                            rows="3"
                            required
                            placeholder="Write a message to the patient..."
                            class="w-full rounded-xl border-slate-200 text-sm focus:border-primary focus:ring-primary/25 resize-none"
                        ></textarea>
                        <button type="submit" class="md:hidden inline-flex items-center justify-center self-end rounded-xl bg-primary px-4 h-11 text-white text-sm font-bold shrink-0">Send</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
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
