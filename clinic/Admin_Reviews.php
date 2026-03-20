<?php
/**
 * Admin Reviews Page
 * Requires admin authentication
 */
$pageTitle = 'Patient Reviews - Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';

// Get database connection
try {
    $pdo = getDBConnection();
    
    // Check if reviews table exists
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
        $tableExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    // Get filter parameters
    $filterRating = isset($_GET['rating']) ? intval($_GET['rating']) : null;
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Initialize variables
    $reviews = [];
    $totalReviews = 0;
    $averageRating = 0;
    $ratingBreakdown = [
        5 => 0,
        4 => 0,
        3 => 0,
        2 => 0,
        1 => 0
    ];
    
    if ($tableExists) {
        // Test query to verify reviews exist and have ratings
        try {
            $testStmt = $pdo->query("SELECT COUNT(*) as count, COUNT(rating) as rating_count FROM reviews");
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            error_log('Reviews in DB: ' . ($testResult['count'] ?? 0) . ', Reviews with rating: ' . ($testResult['rating_count'] ?? 0));
            
            // Get a sample review
            $sampleStmt = $pdo->query("SELECT * FROM reviews LIMIT 1");
            $sampleReview = $sampleStmt->fetch(PDO::FETCH_ASSOC);
            if ($sampleReview) {
                error_log('Sample review data: ' . print_r($sampleReview, true));
            }
        } catch (Exception $e) {
            error_log('Error testing reviews table: ' . $e->getMessage());
        }
        
        // Build query for reviews
        // Only use fields from reviews table in WHERE clause since we're not using JOINs
        $whereConditions = [];
        $params = [];
        
        if ($filterRating !== null && $filterRating > 0) {
            $whereConditions[] = "r.rating = ?";
            $params[] = $filterRating;
        }
        
        if (!empty($searchQuery)) {
            // Search only in reviews table fields for now (we'll filter by patient name after fetching)
            $whereConditions[] = "(r.booking_id LIKE ? OR r.comment LIKE ? OR 
                                  EXISTS (SELECT 1 FROM appointment_services aps_search 
                                          LEFT JOIN services s_search ON aps_search.service_id = s_search.id 
                                          WHERE aps_search.appointment_id = r.appointment_id 
                                          AND s_search.service_name LIKE ?))";
            $searchTerm = "%{$searchQuery}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // Get reviews with patient and service information
        // Query reviews first, then get related data separately to avoid JOIN issues
        $sql = "
            SELECT r.id, r.review_id, r.appointment_id, r.booking_id, r.patient_id, 
                   r.rating, r.comment, r.created_at, r.updated_at
            FROM reviews r
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        // Add limit and offset to params
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            // First, test if we can get reviews at all with a simple query
            $testStmt = $pdo->query("SELECT COUNT(*) as count FROM reviews");
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            $totalReviewsInDB = intval($testResult['count'] ?? 0);
            error_log('Total reviews in database: ' . $totalReviewsInDB);
            
            // If no reviews in DB, skip everything
            if ($totalReviewsInDB === 0) {
                $reviews = [];
                error_log('No reviews found in database');
            } else {
                // Test simple query without WHERE to verify data exists
                $simpleStmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC LIMIT 10");
                $simpleReviews = $simpleStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log('Simple query returned: ' . count($simpleReviews) . ' reviews');
                if (!empty($simpleReviews)) {
                    error_log('Sample review from simple query: ' . print_r($simpleReviews[0], true));
                }
                
                // Try the main query
                error_log('Executing main query: ' . $sql);
                error_log('With params: ' . print_r($params, true));
                error_log('Where clause: ' . ($whereClause ?: 'NONE'));
                
                $stmt = $pdo->prepare($sql);
                if (!$stmt) {
                    error_log('PDO Prepare failed: ' . print_r($pdo->errorInfo(), true));
                    throw new Exception('Failed to prepare query');
                }
                
                $execResult = $stmt->execute($params);
                if (!$execResult) {
                    error_log('PDO Execute failed: ' . print_r($stmt->errorInfo(), true));
                    throw new Exception('Failed to execute query');
                }
                
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Debug: Log query and results
                error_log('Admin Reviews Query executed successfully');
                error_log('Admin Reviews Count: ' . count($reviews));
                
                // If main query returns 0 but simple query works, use simple query results
                if (empty($reviews) && !empty($simpleReviews)) {
                    error_log('Main query returned 0 results, using simple query results instead');
                    // Apply filters manually to simple query results
                    $filteredSimple = $simpleReviews;
                    
                    // Apply rating filter
                    if ($filterRating !== null && $filterRating > 0) {
                        $filteredSimple = array_filter($filteredSimple, function($r) use ($filterRating) {
                            return intval($r['rating'] ?? 0) === $filterRating;
                        });
                    }
                    
                    // Apply pagination
                    $reviews = array_slice($filteredSimple, $offset, $limit);
                    error_log('After filtering simple query results: ' . count($reviews) . ' reviews');
                }
            }
            
            // Now fetch related data for each review
            $filteredReviews = [];
            foreach ($reviews as &$review) {
                // Debug: Log review data before enrichment
                error_log('Processing review: ' . print_r($review, true));
                
                // Get patient info
                if (!empty($review['patient_id'])) {
                    error_log('Looking up patient with patient_id: ' . $review['patient_id']);
                    
                    // First, check if patient exists at all
                    $checkPatientStmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients WHERE patient_id = ?");
                    $checkPatientStmt->execute([$review['patient_id']]);
                    $patientExists = $checkPatientStmt->fetch(PDO::FETCH_ASSOC);
                    error_log('Patient exists check: ' . ($patientExists['count'] ?? 0) . ' records found');
                    
                    // Also check all patient_ids to see what's in the database
                    $allPatientsStmt = $pdo->query("SELECT patient_id, first_name, last_name FROM patients LIMIT 5");
                    $allPatients = $allPatientsStmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log('Sample patients in DB: ' . print_r($allPatients, true));
                    
                    $patientStmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ? LIMIT 1");
                    $patientStmt->execute([$review['patient_id']]);
                    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
                    if ($patient) {
                        $review['first_name'] = $patient['first_name'];
                        $review['last_name'] = $patient['last_name'];
                        error_log('Found patient: ' . $patient['first_name'] . ' ' . $patient['last_name']);
                    } else {
                        error_log('Patient not found for patient_id: ' . $review['patient_id']);
                        // Try alternative lookup using booking_id to get patient_id from appointments
                        if (!empty($review['booking_id'])) {
                            error_log('Trying to get patient_id from appointment with booking_id: ' . $review['booking_id']);
                            $aptStmt = $pdo->prepare("SELECT patient_id FROM appointments WHERE booking_id = ? LIMIT 1");
                            $aptStmt->execute([$review['booking_id']]);
                            $aptPatient = $aptStmt->fetch(PDO::FETCH_ASSOC);
                            if ($aptPatient && !empty($aptPatient['patient_id'])) {
                                error_log('Found patient_id from appointment: ' . $aptPatient['patient_id']);
                                $patientStmt2 = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ? LIMIT 1");
                                $patientStmt2->execute([$aptPatient['patient_id']]);
                                $patient2 = $patientStmt2->fetch(PDO::FETCH_ASSOC);
                                if ($patient2) {
                                    $review['first_name'] = $patient2['first_name'];
                                    $review['last_name'] = $patient2['last_name'];
                                    error_log('Found patient via appointment: ' . $patient2['first_name'] . ' ' . $patient2['last_name']);
                                } else {
                                    $review['first_name'] = '';
                                    $review['last_name'] = '';
                                }
                            } else {
                                $review['first_name'] = '';
                                $review['last_name'] = '';
                            }
                        } else {
                            $review['first_name'] = '';
                            $review['last_name'] = '';
                        }
                    }
                } else {
                    error_log('Review has no patient_id');
                    $review['first_name'] = '';
                    $review['last_name'] = '';
                }
                
                // Get appointment info and services
                if (!empty($review['appointment_id'])) {
                    error_log('Looking up appointment with id: ' . $review['appointment_id']);
                    $aptStmt = $pdo->prepare("SELECT appointment_date, booking_id FROM appointments WHERE id = ? LIMIT 1");
                    $aptStmt->execute([$review['appointment_id']]);
                    $apt = $aptStmt->fetch(PDO::FETCH_ASSOC);
                    if ($apt) {
                        $review['appointment_date'] = $apt['appointment_date'];
                        error_log('Found appointment date: ' . $apt['appointment_date']);
                        
                        // Get service names using booking_id (appointment_services uses booking_id, not appointment_id!)
                        $bookingIdForServices = $review['booking_id'] ?? $apt['booking_id'] ?? null;
                        if ($bookingIdForServices) {
                            $serviceStmt = $pdo->prepare("
                                SELECT GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') as service_names
                                FROM appointment_services aps
                                LEFT JOIN services s ON aps.service_id = s.service_id
                                WHERE aps.booking_id = ?
                            ");
                            $serviceStmt->execute([$bookingIdForServices]);
                            $serviceResult = $serviceStmt->fetch(PDO::FETCH_ASSOC);
                            $review['service_names'] = $serviceResult['service_names'] ?? 'N/A';
                            error_log('Service names from booking_id: ' . ($review['service_names'] ?? 'N/A') . ' (booking_id: ' . $bookingIdForServices . ')');
                        } else {
                            $review['service_names'] = 'N/A';
                            error_log('No booking_id available for service lookup');
                        }
                    } else {
                        error_log('Appointment not found with id: ' . $review['appointment_id']);
                        $review['appointment_date'] = null;
                        $review['service_names'] = 'N/A';
                    }
                } elseif (!empty($review['booking_id'])) {
                    // Try using booking_id instead
                    error_log('Trying to get services using booking_id: ' . $review['booking_id']);
                    $aptStmt = $pdo->prepare("SELECT id, appointment_date FROM appointments WHERE booking_id = ? LIMIT 1");
                    $aptStmt->execute([$review['booking_id']]);
                    $apt = $aptStmt->fetch(PDO::FETCH_ASSOC);
                    if ($apt) {
                        $review['appointment_date'] = $apt['appointment_date'];
                        // Store booking_id from appointment if not already in review
                        if (empty($review['booking_id']) && !empty($apt['booking_id'])) {
                            $review['booking_id'] = $apt['booking_id'];
                        }
                        
                        // Get service names using booking_id (appointment_services uses booking_id, not appointment_id!)
                        $bookingIdForServices = $review['booking_id'] ?? $apt['booking_id'] ?? null;
                        if ($bookingIdForServices) {
                            $serviceStmt = $pdo->prepare("
                                SELECT GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') as service_names
                                FROM appointment_services aps
                                LEFT JOIN services s ON aps.service_id = s.service_id
                                WHERE aps.booking_id = ?
                            ");
                            $serviceStmt->execute([$bookingIdForServices]);
                            $serviceResult = $serviceStmt->fetch(PDO::FETCH_ASSOC);
                            $review['service_names'] = $serviceResult['service_names'] ?? 'N/A';
                            error_log('Service names from booking_id lookup: ' . ($review['service_names'] ?? 'N/A') . ' (booking_id: ' . $bookingIdForServices . ')');
                        } else {
                            $review['service_names'] = 'N/A';
                            error_log('No booking_id available for service lookup');
                        }
                    } else {
                        error_log('Appointment not found with booking_id: ' . $review['booking_id']);
                        $review['appointment_date'] = null;
                        $review['service_names'] = 'N/A';
                    }
                } else {
                    error_log('Review has no appointment_id or booking_id');
                    $review['appointment_date'] = null;
                    $review['service_names'] = 'N/A';
                }
                
                // Apply patient name search filter if search query exists
                if (!empty($searchQuery)) {
                    $patientName = strtolower(trim($review['first_name'] . ' ' . $review['last_name']));
                    $searchLower = strtolower($searchQuery);
                    if (strpos($patientName, $searchLower) !== false) {
                        $filteredReviews[] = $review;
                    }
                } else {
                    // No search query, include all reviews
                    $filteredReviews[] = $review;
                }
            }
            unset($review);
            
            // Use filtered reviews if search was applied, otherwise use all reviews
            $reviews = !empty($searchQuery) ? $filteredReviews : $reviews;
            
            if (!empty($reviews)) {
                error_log('First Review Sample after enrichment: ' . print_r($reviews[0], true));
            }
        } catch (PDOException $e) {
            error_log('Error fetching reviews: ' . $e->getMessage());
            error_log('SQL: ' . $sql);
            error_log('Params: ' . print_r($params, true));
            error_log('PDO Error Info: ' . print_r($pdo->errorInfo(), true));
            
            // Fallback: Try simple query without WHERE clause
            try {
                error_log('Attempting fallback with simple query...');
                $fallbackStmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset));
                $reviews = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log('Fallback query returned: ' . count($reviews) . ' reviews');
            } catch (PDOException $e2) {
                error_log('Fallback query also failed: ' . $e2->getMessage());
                $reviews = [];
            }
        }
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total
            FROM reviews r
            LEFT JOIN patients p ON r.patient_id = p.patient_id
            LEFT JOIN appointments a ON r.appointment_id = a.id
            {$whereClause}
        ";
        $countParams = array_slice($params, 0, -2); // Remove limit and offset
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalReviews = $totalResult['total'] ?? 0;
        
        // Get statistics (all reviews, not filtered)
        $statsSql = "
            SELECT 
                AVG(r.rating) as avg_rating,
                COUNT(*) as total_count,
                SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM reviews r
        ";
        $statsStmt = $pdo->query($statsSql);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            $averageRating = round($stats['avg_rating'] ?? 0, 1);
            $totalReviews = intval($stats['total_count'] ?? 0);
            $ratingBreakdown[5] = intval($stats['rating_5'] ?? 0);
            $ratingBreakdown[4] = intval($stats['rating_4'] ?? 0);
            $ratingBreakdown[3] = intval($stats['rating_3'] ?? 0);
            $ratingBreakdown[2] = intval($stats['rating_2'] ?? 0);
            $ratingBreakdown[1] = intval($stats['rating_1'] ?? 0);
        }
    }
    
    $totalPages = ceil($totalReviews / $limit);
    
} catch (Exception $e) {
    error_log('Error in Admin_Reviews.php: ' . $e->getMessage());
    $reviews = [];
    $totalReviews = 0;
    $averageRating = 0;
    $totalPages = 0;
}
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .material-symbols-outlined.filled {
        font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .dark ::-webkit-scrollbar-thumb {
        background: #334155;
    }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
    <header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-6 lg:px-10 sticky top-0 z-10 shrink-0">
        <div>
            <h1 class="text-2xl font-bold">Patient Reviews</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Feedback and ratings from our valued patients.</p>
        </div>
        <div class="flex items-center gap-6">
            <div class="relative hidden md:block">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" style="font-size: 20px;">search</span>
                <form method="GET" action="" class="inline">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                        class="pl-10 pr-4 py-2 w-64 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all text-sm" 
                        placeholder="Search reviews..."
                    />
                    <?php if ($filterRating): ?>
                        <input type="hidden" name="rating" value="<?php echo $filterRating; ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto p-6 lg:p-10">
        <div class="max-w-[1600px] mx-auto w-full">
            <?php if (!$tableExists): ?>
                <!-- No Reviews Table Message -->
                <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="size-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <span class="material-symbols-outlined text-4xl text-slate-400">rate_review</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Reviews System Not Configured</h3>
                            <p class="text-slate-500 dark:text-slate-400 max-w-md">
                                The reviews table has not been created yet. Please create the reviews table in your database to start collecting patient feedback.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Average Rating Card -->
                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 lg:col-span-1 flex flex-col items-center justify-center text-center">
                        <span class="text-6xl font-extrabold text-slate-900 dark:text-white mb-2"><?php echo $averageRating; ?></span>
                        <div class="flex text-amber-400 mb-2">
                            <?php
                            $fullStars = floor($averageRating);
                            $hasHalfStar = ($averageRating - $fullStars) >= 0.5;
                            for ($i = 0; $i < 5; $i++) {
                                if ($i < $fullStars) {
                                    echo '<span class="material-symbols-outlined filled" style="font-size: 24px;">star</span>';
                                } elseif ($i == $fullStars && $hasHalfStar) {
                                    echo '<span class="material-symbols-outlined filled" style="font-size: 24px;">star_half</span>';
                                } else {
                                    echo '<span class="material-symbols-outlined" style="font-size: 24px; opacity: 0.3;">star</span>';
                                }
                            }
                            ?>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Average Patient Rating</p>
                        <p class="text-slate-400 dark:text-slate-500 text-xs mt-1">Based on <?php echo number_format($totalReviews); ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <!-- Rating Breakdown Card -->
                    <div class="bg-white dark:bg-surface-dark p-8 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-6">Rating Breakdown</h3>
                        <div class="space-y-4">
                            <?php
                            $maxCount = max($ratingBreakdown);
                            foreach ([5, 4, 3, 2, 1] as $rating):
                                $count = $ratingBreakdown[$rating];
                                $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                                $totalPercentage = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                            ?>
                            <div class="flex items-center gap-4">
                                <span class="text-sm font-medium text-slate-600 dark:text-slate-400 w-12"><?php echo $rating; ?> Star</span>
                                <div class="flex-1 h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary transition-all duration-500" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 w-16 text-right"><?php echo $count; ?> (<?php echo number_format($totalPercentage, 1); ?>%)</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="flex flex-wrap gap-2 mb-8">
                    <a href="?search=<?php echo urlencode($searchQuery); ?>" 
                       class="px-5 py-2.5 rounded-full font-medium text-sm transition-all shadow-md <?php echo $filterRating === null ? 'bg-primary text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'; ?>">
                        All Reviews
                    </a>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <a href="?rating=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>" 
                           class="px-5 py-2.5 rounded-full font-medium text-sm flex items-center gap-2 transition-all <?php echo $filterRating === $i ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700'; ?>">
                            <?php echo $i; ?> <span class="material-symbols-outlined text-amber-400" style="font-size: 18px;">star</span>
                        </a>
                    <?php endfor; ?>
                </div>

                <!-- Reviews Table -->
                <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-slate-50 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-800/50">
                                    <th class="px-6 lg:px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Booking ID</th>
                                    <th class="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Patient</th>
                                    <th class="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Service</th>
                                    <th class="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Rating</th>
                                    <th class="px-6 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider">Comment</th>
                                    <th class="px-6 lg:px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-700/50">
                                <?php 
                                // Debug output
                                $debugInfo = [
                                    'reviews_count' => count($reviews),
                                    'reviews_is_array' => is_array($reviews),
                                    'reviews_empty' => empty($reviews),
                                    'table_exists' => $tableExists,
                                    'first_review_keys' => !empty($reviews) ? array_keys($reviews[0]) : []
                                ];
                                error_log('DEBUG INFO: ' . print_r($debugInfo, true));
                                ?>
                                <?php if (empty($reviews)): ?>
                                    <tr>
                                        <td colspan="6" class="px-8 py-12 text-center">
                                            <div class="flex flex-col items-center gap-3">
                                                <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600">rate_review</span>
                                                <p class="text-slate-400 dark:text-slate-500 font-medium">No reviews found</p>
                                                <p class="text-xs text-red-400">Debug: Count=<?php echo count($reviews); ?>, IsArray=<?php echo is_array($reviews) ? 'Yes' : 'No'; ?>, TableExists=<?php echo $tableExists ? 'Yes' : 'No'; ?></p>
                                                <?php if ($filterRating || !empty($searchQuery)): ?>
                                                    <a href="?" class="text-primary hover:underline text-sm">Clear filters</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reviews as $review): 
                                        // Debug: Log review data
                                        error_log('Review data: ' . print_r($review, true));
                                        
                                        $bookingId = $review['booking_id'] ?? 'N/A';
                                        $patientName = trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''));
                                        $patientName = !empty($patientName) ? $patientName : 'Anonymous';
                                        $serviceName = $review['service_names'] ?? 'N/A';
                                        $comment = $review['comment'] ?? '';
                                        
                                        // Get rating - try multiple ways to ensure we get it
                                        $rating = 0;
                                        
                                        // Check all possible ways the rating might be stored
                                        if (isset($review['rating']) && $review['rating'] !== null && $review['rating'] !== '') {
                                            $rating = intval($review['rating']);
                                        } elseif (isset($review['RATING']) && $review['RATING'] !== null && $review['RATING'] !== '') {
                                            $rating = intval($review['RATING']);
                                        }
                                        
                                        // If still 0, try to get it directly from the array
                                        if ($rating === 0 && isset($review['rating'])) {
                                            $rawVal = $review['rating'];
                                            if (is_numeric($rawVal)) {
                                                $rating = (int)$rawVal;
                                            }
                                        }
                                        
                                        // Debug: Log rating value and all keys
                                        error_log('Review keys: ' . implode(', ', array_keys($review)));
                                        error_log('Rating value for review ' . ($review['review_id'] ?? 'unknown') . ': ' . $rating . ' (raw: ' . var_export($review['rating'] ?? 'NOT SET', true) . ', type: ' . gettype($review['rating'] ?? 'N/A') . ')');
                                        
                                        $reviewDate = $review['created_at'] ?? $review['appointment_date'] ?? '';
                                        if ($reviewDate) {
                                            $dateObj = new DateTime($reviewDate);
                                            $formattedDate = $dateObj->format('M d, Y');
                                        } else {
                                            $formattedDate = 'N/A';
                                        }
                                    ?>
                                    <tr class="group hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 lg:px-8 py-6">
                                            <span class="text-sm font-mono font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($bookingId); ?></span>
                                        </td>
                                        <td class="px-6 py-6">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($patientName); ?></span>
                                        </td>
                                        <td class="px-6 py-6">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-primary">
                                                <?php echo htmlspecialchars($serviceName); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-6 text-amber-400">
                                            <?php 
                                            // Debug: Show raw rating value temporarily
                                            $rawRating = $review['rating'] ?? 'NOT SET';
                                            ?>
                                            <?php if ($rating > 0): ?>
                                                <div class="flex gap-0.5 items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="material-symbols-outlined <?php echo $i <= $rating ? 'filled' : ''; ?>" style="font-size: 18px; <?php echo $i > $rating ? 'opacity: 0.3;' : ''; ?>">star</span>
                                                    <?php endfor; ?>
                                                    <span class="text-xs text-slate-500 ml-2">(<?php echo $rating; ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex flex-col">
                                                    <span class="text-xs text-red-400">No rating (raw: <?php echo htmlspecialchars(var_export($rawRating, true)); ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-6">
                                            <span class="text-sm <?php echo !empty($comment) ? 'text-slate-600 dark:text-slate-300' : 'italic text-slate-400 dark:text-slate-500'; ?>">
                                                <?php echo !empty($comment) ? htmlspecialchars($comment) : '""'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 lg:px-8 py-6 text-right">
                                            <span class="text-xs font-mono text-slate-400 dark:text-slate-500 uppercase"><?php echo $formattedDate; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="px-6 lg:px-8 py-5 border-t border-slate-50 dark:border-slate-700/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Showing <?php echo $totalReviews > 0 ? (($page - 1) * $limit + 1) : 0; ?> to <?php echo min($page * $limit, $totalReviews); ?> of <?php echo number_format($totalReviews); ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?>
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $filterRating ? '&rating=' . $filterRating : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                                   class="p-2 border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 transition-colors">
                                    <span class="material-symbols-outlined text-sm" style="font-size: 18px;">chevron_left</span>
                                </a>
                            <?php else: ?>
                                <span class="p-2 border border-slate-200 dark:border-slate-700 rounded text-slate-300 dark:text-slate-600 cursor-not-allowed">
                                    <span class="material-symbols-outlined text-sm" style="font-size: 18px;">chevron_left</span>
                                </span>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filterRating ? '&rating=' . $filterRating : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                                   class="px-3 py-1 border rounded text-sm font-medium transition-colors <?php echo $i == $page ? 'border-primary bg-primary text-white' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $filterRating ? '&rating=' . $filterRating : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                                   class="p-2 border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 transition-colors">
                                    <span class="material-symbols-outlined text-sm" style="font-size: 18px;">chevron_right</span>
                                </a>
                            <?php else: ?>
                                <span class="p-2 border border-slate-200 dark:border-slate-700 rounded text-slate-300 dark:text-slate-600 cursor-not-allowed">
                                    <span class="material-symbols-outlined text-sm" style="font-size: 18px;">chevron_right</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Auto-submit search form on input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length === 0 || this.value.length >= 2) {
                    this.closest('form').submit();
                }
            }, 500);
        });
        
        // Submit on Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }
});
</script>

</body>
</html>
