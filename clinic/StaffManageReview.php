<?php
$staff_nav_active = 'reviews';
require_once __DIR__ . '/config/config.php';
// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) {
    clinic_session_start();
}
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

$tenantId = trim((string) ($_SESSION['tenant_id'] ?? $_SESSION['public_tenant_id'] ?? ''));
$reviews = [];
$avgRating = 0.0;
$totalReviewsAll = 0;
$positivePct = 0;
$reviewsListTotal = 0;
$reviewLoadError = '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$searchQ = trim((string) ($_GET['q'] ?? ''));
$ratingFilter = trim((string) ($_GET['rating'] ?? ''));
$allowedRatings = ['', '5', '4', '3', '2', '1'];
if (!in_array($ratingFilter, $allowedRatings, true)) {
    $ratingFilter = '';
}

if ($tenantId === '') {
    $reviewLoadError = 'missing_tenant';
} else {
    try {
        $pdo = getDBConnection();
        $aggStmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avgr,
                SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS pos
             FROM tbl_reviews WHERE tenant_id = ?'
        );
        $aggStmt->execute([$tenantId]);
        $agg = $aggStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalReviewsAll = (int) ($agg['cnt'] ?? 0);
        $avgRating = $totalReviewsAll > 0 ? round((float) ($agg['avgr'] ?? 0), 1) : 0.0;
        $positivePct = $totalReviewsAll > 0
            ? (int) round(100 * (int) ($agg['pos'] ?? 0) / $totalReviewsAll)
            : 0;

        $where = 'WHERE r.tenant_id = ?';
        $listParams = [$tenantId];
        if ($ratingFilter !== '') {
            $where .= ' AND r.rating = ?';
            $listParams[] = (int) $ratingFilter;
        }
        if ($searchQ !== '') {
            $where .= ' AND (
                r.comment LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                OR r.booking_id LIKE ? OR r.review_id LIKE ?
            )';
            $term = '%' . $searchQ . '%';
            $listParams[] = $term;
            $listParams[] = $term;
            $listParams[] = $term;
            $listParams[] = $term;
            $listParams[] = $term;
        }

        $countSql = 'SELECT COUNT(DISTINCT r.id) FROM tbl_reviews r
            LEFT JOIN tbl_patients p ON r.patient_id = p.patient_id AND p.tenant_id = r.tenant_id
            ' . $where;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($listParams);
        $reviewsListTotal = (int) $countStmt->fetchColumn();

        $totalPages = $reviewsListTotal > 0 ? (int) ceil($reviewsListTotal / $perPage) : 0;
        if ($totalPages > 0) {
            $page = min($page, $totalPages);
        } else {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;

        $listSql = 'SELECT r.id, r.review_id, r.appointment_id, r.booking_id, r.patient_id, r.rating, r.comment, r.created_at,
                p.first_name, p.last_name,
                a.appointment_date, a.appointment_time,
                GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR \', \') AS service_names
            FROM tbl_reviews r
            LEFT JOIN tbl_patients p ON r.patient_id = p.patient_id AND p.tenant_id = r.tenant_id
            LEFT JOIN tbl_appointments a ON r.appointment_id = a.id AND a.tenant_id = r.tenant_id
            LEFT JOIN tbl_appointment_services aps ON a.id = aps.appointment_id AND aps.tenant_id = r.tenant_id
            LEFT JOIN tbl_services s ON aps.service_id = s.service_id AND s.tenant_id = r.tenant_id
            ' . $where . '
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute($listParams);
        $reviews = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('StaffManageReview: ' . $e->getMessage());
        $reviewLoadError = 'query_failed';
        $reviews = [];
        $reviewsListTotal = 0;
        $totalReviewsAll = 0;
        $avgRating = 0.0;
        $positivePct = 0;
    }
}

$totalPages = $reviewsListTotal > 0 ? (int) ceil($reviewsListTotal / $perPage) : 0;

$queryBase = [];
if ($searchQ !== '') {
    $queryBase['q'] = $searchQ;
}
if ($ratingFilter !== '') {
    $queryBase['rating'] = $ratingFilter;
}

/**
 * Request path as seen in the browser (e.g. /my-clinic/StaffManageReview.php).
 * Using SCRIPT_NAME breaks GET forms when rewritten “slug” URLs differ from the real file path.
 */
$staff_reviews_request_path_raw = static function () {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = $uri !== '' ? parse_url($uri, PHP_URL_PATH) : '';
    if (!is_string($path) || $path === '') {
        $path = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/StaffManageReview.php';
    }
    $path = str_replace('\\', '/', $path);
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    return $path;
};

$staffReviewsRequestPath = $staff_reviews_request_path_raw();

$buildReviewsPageUrl = function ($targetPage) use ($queryBase, $staffReviewsRequestPath) {
    $q = $queryBase;
    if ($targetPage > 1) {
        $q['page'] = (string) $targetPage;
    }
    $qs = http_build_query($q);
    return $staffReviewsRequestPath . ($qs !== '' ? '?' . $qs : '');
};

$esc = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$starsHtml = static function ($rating) {
    $rating = max(0, min(5, (int) $rating));
    $buf = '';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $i <= $rating ? 'text-primary' : 'text-slate-200';
        $buf .= '<span class="material-symbols-outlined ' . $cls . ' text-xl" style="font-variation-settings: \'FILL\' 1;">star</span>';
    }
    return $buf;
};

$patientLabel = static function (array $row) use ($esc) {
    $fn = trim((string) ($row['first_name'] ?? ''));
    $ln = trim((string) ($row['last_name'] ?? ''));
    $name = trim($fn . ' ' . $ln);
    return $name !== '' ? $esc($name) : 'Patient';
};

$patientInitials = static function (array $row) {
    $fn = trim((string) ($row['first_name'] ?? ''));
    $ln = trim((string) ($row['last_name'] ?? ''));
    $ini = '';
    if ($fn !== '') {
        $ini .= strtoupper(substr($fn, 0, 1));
    }
    if ($ln !== '') {
        $ini .= strtoupper(substr($ln, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
};

$formatReviewDate = static function ($row) {
    $raw = $row['created_at'] ?? '';
    if ($raw === '') {
        return '';
    }
    $t = strtotime((string) $raw);
    if ($t === false) {
        return $raw;
    }
    return date('M j, Y', $t);
};
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Patient Reviews</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    },
                },
            },
        }
    </script>
<style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<?php if ($reviewLoadError === 'missing_tenant') { ?>
<div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
    No clinic workspace in your session. Sign in again from the provider portal so reviews can load for your tenant.
</div>
<?php } elseif ($reviewLoadError === 'query_failed') { ?>
<div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
    Reviews could not be loaded. Check the database connection or server logs.
</div>
<?php } ?>
<!-- Page Header (High-contrast typography) -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> PATIENT REVIEWS
                </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                            Patient <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Reviews</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                            View and manage feedback from patients
                        </p>
</div>
</div>
</section>
<!-- Stats Grid — from tbl_reviews -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">All time</span>
</div>
<div>
<div class="flex items-baseline gap-2">
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo $esc($totalReviewsAll > 0 ? (string) $avgRating : '—'); ?></p>
<p class="text-sm font-bold text-on-surface-variant/40">/ 5.0</p>
</div>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Average Rating</p>
</div>
</div>
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">forum</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Clinic total</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo (int) $totalReviewsAll; ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Reviews</p>
</div>
</div>
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">recommend</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">4★ &amp; up</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo $totalReviewsAll > 0 ? (int) $positivePct : '—'; ?><?php echo $totalReviewsAll > 0 ? '%' : ''; ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Positive Feedback</p>
</div>
</div>
</section>
<!-- Filters -->
<form method="get" class="elevated-card p-5 rounded-2xl flex flex-wrap items-end gap-4" action="<?php echo $esc($staffReviewsRequestPath); ?>">
<div class="flex-1 relative min-w-[300px]">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">search</span>
<input name="q" value="<?php echo $esc($searchQ); ?>" class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Search name, comment, booking ID…" type="text"/>
</div>
<div class="flex flex-wrap gap-3 items-center">
<label class="sr-only" for="rating-filter">Rating</label>
<select id="rating-filter" name="rating" class="bg-slate-50 border-none rounded-xl py-2.5 px-5 pr-10 text-xs font-bold uppercase tracking-wider text-slate-600 focus:ring-2 focus:ring-primary/20 cursor-pointer">
<option value=""<?php echo $ratingFilter === '' ? ' selected' : ''; ?>>All ratings</option>
<option value="5"<?php echo $ratingFilter === '5' ? ' selected' : ''; ?>>5 stars</option>
<option value="4"<?php echo $ratingFilter === '4' ? ' selected' : ''; ?>>4 stars</option>
<option value="3"<?php echo $ratingFilter === '3' ? ' selected' : ''; ?>>3 stars</option>
<option value="2"<?php echo $ratingFilter === '2' ? ' selected' : ''; ?>>2 stars</option>
<option value="1"<?php echo $ratingFilter === '1' ? ' selected' : ''; ?>>1 star</option>
</select>
<button type="submit" class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Apply
</button>
<?php if ($searchQ !== '' || $ratingFilter !== '') { ?>
<a class="px-5 py-2.5 text-[10px] font-bold uppercase tracking-widest text-primary hover:underline" href="<?php echo $esc($staffReviewsRequestPath); ?>">Reset</a>
<?php } ?>
</div>
</form>
<!-- Reviews list -->
<section class="elevated-card rounded-3xl overflow-hidden divide-y divide-slate-100">
<?php if ($reviewLoadError !== '' && $reviewLoadError !== 'missing_tenant') { ?>
<div class="p-12 text-center text-on-surface-variant">Unable to load reviews.</div>
<?php } elseif ($tenantId !== '' && $reviewLoadError === '' && count($reviews) === 0) { ?>
<div class="p-12 text-center text-on-surface-variant">
<?php if ($totalReviewsAll === 0) { ?>
<p class="font-headline font-bold text-lg text-on-background">No reviews yet</p>
<p class="mt-2 text-sm max-w-md mx-auto">When patients submit ratings after appointments, they will appear here.</p>
<?php } else { ?>
<p class="font-headline font-bold text-lg text-on-background">No matching reviews</p>
<p class="mt-2 text-sm max-w-md mx-auto">Try changing search or rating filters.</p>
<?php } ?>
</div>
<?php } else { foreach ($reviews as $row) {
    $svc = trim((string) ($row['service_names'] ?? ''));
    if ($svc === '') {
        $svc = 'Services (not linked)';
    }
    $commentRaw = trim((string) ($row['comment'] ?? ''));
?>
<div class="p-8 hover:bg-slate-50/30 transition-colors group">
<div class="flex justify-between items-start mb-6 flex-wrap gap-4">
<div class="flex gap-4 items-center">
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-headline font-bold text-sm ring-2 ring-primary/5"><?php echo $esc($patientInitials($row)); ?></div>
<div>
<h4 class="font-headline font-extrabold text-lg group-hover:text-primary transition-colors"><?php echo $patientLabel($row); ?></h4>
<p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?php echo $esc($formatReviewDate($row)); ?> · <?php echo $esc((string) ($row['review_id'] ?? '')); ?></p>
</div>
</div>
<div class="flex gap-0.5"><?php echo $starsHtml((int) ($row['rating'] ?? 0)); ?></div>
</div>
<div class="mb-6">
<span class="inline-block px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-widest mb-3"><?php echo $esc($svc); ?></span>
<?php if ($commentRaw !== '') { ?>
<p class="text-on-surface-variant font-medium leading-relaxed italic text-base">“<?php echo nl2br($esc($commentRaw)); ?>”</p>
<?php } else { ?>
<p class="text-slate-400 font-medium italic text-sm">No written comment.</p>
<?php } ?>
</div>
<div class="flex flex-wrap items-center justify-between gap-3 pt-6 border-t border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
<span>Booking <?php echo $esc((string) ($row['booking_id'] ?? '')); ?> · Appt #<?php echo (int) ($row['appointment_id'] ?? 0); ?></span>
</div>
</div>
<?php } } ?>
</section>
<!-- Pagination -->
<div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
<?php if ($totalPages > 1) { ?>
<p class="text-xs text-on-surface-variant font-medium">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?> (<?php echo (int) $reviewsListTotal; ?> reviews)</p>
<div class="flex gap-2">
<?php if ($page > 1) { ?>
<a class="px-6 py-3 bg-primary/10 text-primary font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-primary hover:text-white transition-all" href="<?php echo $esc($buildReviewsPageUrl($page - 1)); ?>">Previous</a>
<?php } ?>
<?php if ($page < $totalPages) { ?>
<a class="px-6 py-3 bg-primary/10 text-primary font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-primary hover:text-white transition-all" href="<?php echo $esc($buildReviewsPageUrl($page + 1)); ?>">Next</a>
<?php } ?>
</div>
<?php } elseif ($reviewsListTotal > 0) { ?>
<p class="text-xs text-on-surface-variant font-medium"><?php echo (int) $reviewsListTotal; ?> review<?php echo $reviewsListTotal === 1 ? '' : 's'; ?> total</p>
<?php } ?>
</div>
</div>
</main>
</body></html>