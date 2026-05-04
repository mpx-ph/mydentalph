<?php
// api/reviews.php — patient reviews (tbl_reviews) for mobile app; session via cookies optional, body/query carries user_id + tenant_id.
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/request_context.inc.php';

header('Content-Type: application/json; charset=utf-8');
api_send_no_cache_headers();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$input = array_merge($_GET, $_POST);
if ($method === 'POST' || $method === 'PUT') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$userId = trim((string) ($input['user_id'] ?? ''));
$tenantId = trim((string) ($input['tenant_id'] ?? ''));

if ($userId === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

try {
    $tenantId = api_resolve_tenant_id($pdo, $userId, $tenantId) ?? '';
    if ($tenantId === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing tenant_id']);
        exit;
    }

    /** @var PDO $pdo */
    $patientIds = reviews_collect_patient_ids($pdo, $tenantId, $userId);
    if (empty($patientIds)) {
        echo json_encode([
            'status' => 'success',
            'pending' => [],
            'my_reviews' => [],
            'message' => 'No patient record found for this user.',
        ]);
        exit;
    }

    if ($method === 'POST') {
        reviews_handle_post($pdo, $tenantId, $patientIds, $input);
        exit;
    }

    if ($method !== 'GET') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }

    $pending = reviews_fetch_pending($pdo, $tenantId, $patientIds);
    $myReviews = reviews_fetch_my_reviews($pdo, $tenantId, $patientIds);

    echo json_encode([
        'status' => 'success',
        'pending' => $pending,
        'my_reviews' => $myReviews,
    ]);
} catch (Throwable $e) {
    error_log('reviews.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}

/**
 * @return list<string>
 */
function reviews_collect_patient_ids(PDO $pdo, string $tenantId, string $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT patient_id FROM tbl_patients
         WHERE tenant_id = ? AND (owner_user_id = ? OR linked_user_id = ?)'
    );
    $stmt->execute([$tenantId, $userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $out = [];
    foreach ($rows as $v) {
        $s = trim((string) $v);
        if ($s !== '') {
            $out[] = $s;
        }
    }
    return array_values(array_unique($out));
}

/**
 * @param list<string> $patientIds
 */
function reviews_fetch_pending(PDO $pdo, string $tenantId, array $patientIds): array
{
    $ph = implode(',', array_fill(0, count($patientIds), '?'));
    $sql = "SELECT
                a.id AS appointment_id,
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                a.patient_id,
                TRIM(CONCAT(COALESCE(pat.first_name, ''), ' ', COALESCE(pat.last_name, ''))) AS patient_name,
                COALESCE(
                    NULLIF(TRIM(a.service_type), ''),
                    (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)
                ) AS service_name,
                TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name
            FROM tbl_appointments a
            LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
            LEFT JOIN tbl_patients pat ON pat.tenant_id = a.tenant_id AND pat.patient_id = a.patient_id
            WHERE a.tenant_id = ?
              AND a.patient_id IN ($ph)
              AND LOWER(TRIM(a.status)) = 'completed'
              AND NOT EXISTS (
                  SELECT 1 FROM tbl_reviews r
                  WHERE r.tenant_id = a.tenant_id AND r.appointment_id = a.id
              )
            ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $params = array_merge([$tenantId], $patientIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['appointment_id'] = (string) ($r['appointment_id'] ?? '');
        $r['service_name'] = (string) ($r['service_name'] ?? '');
        if ($r['service_name'] === '') {
            $r['service_name'] = 'Appointment';
        }
        $r['dentist_name'] = reviews_format_dentist((string) ($r['dentist_name'] ?? ''));
    }
    unset($r);
    return $rows;
}

/**
 * @param list<string> $patientIds
 */
function reviews_fetch_my_reviews(PDO $pdo, string $tenantId, array $patientIds): array
{
    $ph = implode(',', array_fill(0, count($patientIds), '?'));
    $sql = "SELECT
                r.review_id,
                r.appointment_id,
                r.rating,
                r.comment,
                r.created_at,
                r.booking_id,
                a.appointment_date,
                a.appointment_time,
                COALESCE(
                    NULLIF(TRIM(a.service_type), ''),
                    (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)
                ) AS service_name,
                TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name
            FROM tbl_reviews r
            INNER JOIN tbl_appointments a
                ON a.tenant_id = r.tenant_id AND a.id = r.appointment_id
            LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
            WHERE r.tenant_id = ?
              AND r.patient_id IN ($ph)
            ORDER BY r.created_at DESC";

    $params = array_merge([$tenantId], $patientIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['appointment_id'] = (string) ($r['appointment_id'] ?? '');
        $r['service_name'] = (string) ($r['service_name'] ?? '');
        if ($r['service_name'] === '') {
            $r['service_name'] = 'Appointment';
        }
        $r['dentist_name'] = reviews_format_dentist((string) ($r['dentist_name'] ?? ''));
    }
    unset($r);
    return $rows;
}

function reviews_format_dentist(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return '';
    }
    return 'DR. ' . strtoupper($t);
}

function reviews_generate_review_id(PDO $pdo, string $tenantId): string
{
    $prefix = 'RV';
    $year = date('Y');
    $pattern = $prefix . '-' . $year . '-%';
    $stmt = $pdo->prepare(
        'SELECT review_id FROM tbl_reviews WHERE review_id LIKE ? AND tenant_id = ? ORDER BY review_id DESC LIMIT 1'
    );
    $stmt->execute([$pattern, $tenantId]);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last !== false && $last !== null && $last !== '') {
        $parts = explode('-', (string) $last);
        $next = (int) ($parts[2] ?? 0) + 1;
    }
    return $prefix . '-' . $year . '-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

/**
 * @param list<string> $patientIds
 * @param array<string,mixed> $input
 */
function reviews_handle_post(PDO $pdo, string $tenantId, array $patientIds, array $input): void
{
    $appointmentId = (int) ($input['appointment_id'] ?? 0);
    $rating = (int) ($input['rating'] ?? 0);
    $comment = trim((string) ($input['comment'] ?? ''));

    if ($appointmentId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid appointment ID.']);
        return;
    }
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['status' => 'error', 'message' => 'Rating must be between 1 and 5.']);
        return;
    }

    $ph = implode(',', array_fill(0, count($patientIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT a.id, a.booking_id, a.patient_id, a.status
         FROM tbl_appointments a
         WHERE a.id = ?
           AND a.tenant_id = ?
           AND a.patient_id IN ($ph)
           AND LOWER(TRIM(a.status)) = 'completed'"
    );
    $params = array_merge([$appointmentId, $tenantId], $patientIds);
    $stmt->execute($params);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
        echo json_encode(['status' => 'error', 'message' => 'Appointment not found or not eligible for review.']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM tbl_reviews WHERE appointment_id = ? AND tenant_id = ?');
    $stmt->execute([$appointmentId, $tenantId]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'You have already reviewed this appointment.']);
        return;
    }

    $reviewId = reviews_generate_review_id($pdo, $tenantId);
    $bookingId = trim((string) ($appt['booking_id'] ?? ''));
    $patientId = trim((string) ($appt['patient_id'] ?? ''));

    try {
        $ins = $pdo->prepare(
            'INSERT INTO tbl_reviews (tenant_id, review_id, appointment_id, booking_id, patient_id, rating, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$tenantId, $reviewId, $appointmentId, $bookingId, $patientId, $rating, $comment]);
    } catch (PDOException $e) {
        error_log('reviews insert: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit review.']);
        return;
    }

    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => 'Review submitted successfully.',
        'review_id' => $reviewId,
        'appointment_id' => (string) $appointmentId,
    ]);
}
