<?php
$staff_nav_active = 'block_schedule';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/availability.php';

if (session_status() === PHP_SESSION_NONE) {
    clinic_session_start();
}

$pdo = getDBConnection();
$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$selectedDate = isset($_GET['selected_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['selected_date'])
    ? (string) $_GET['selected_date']
    : date('Y-m-d');
$selectedUserId = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$currentUserType = strtolower(trim((string) ($_SESSION['user_type'] ?? '')));
$isManagerUser = ($currentUserType === 'manager');

$addBreakErrorMessage = '';
$addBreakSuccessMessage = '';
$openAddBreakModal = false;
$addBreakForm = [
    'user_id' => $selectedUserId,
    'block_date' => $selectedDate,
    'start_time' => '',
    'end_time' => '',
    'notes' => '',
    'apply_mode' => 'date',
];

if (isset($_GET['break_status']) && (string) $_GET['break_status'] === 'success') {
    $addBreakSuccessMessage = 'Break schedule added successfully';
}

$editShiftErrorMessage = '';
$editShiftSuccessMessage = '';
$openEditShiftModal = false;
$editShiftForm = [
    'user_id' => $selectedUserId,
    'block_date' => $selectedDate,
    'start_time' => '',
    'end_time' => '',
    'apply_mode' => 'date',
];

if (isset($_GET['shift_status']) && (string) $_GET['shift_status'] === 'success') {
    $editShiftSuccessMessage = 'Shift schedule updated successfully';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['form_action'] ?? '') === 'add_break') {
    $postedUserId = trim((string) ($_POST['user_id'] ?? ''));
    $postedDate = trim((string) ($_POST['block_date'] ?? ''));
    $postedStartTime = trim((string) ($_POST['start_time'] ?? ''));
    $postedEndTime = trim((string) ($_POST['end_time'] ?? ''));
    $postedNotes = trim((string) ($_POST['notes'] ?? ''));
    $postedApplyMode = ((string) ($_POST['apply_mode'] ?? 'date') === 'recurring') ? 'recurring' : 'date';

    $addBreakForm = [
        'user_id' => $postedUserId,
        'block_date' => $postedDate,
        'start_time' => $postedStartTime,
        'end_time' => $postedEndTime,
        'notes' => $postedNotes,
        'apply_mode' => $postedApplyMode,
    ];

    $selectedUserId = $postedUserId;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $selectedDate = $postedDate;
    }

    if ($tenantId === '') {
        $addBreakErrorMessage = 'Unable to resolve clinic tenant.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $addBreakErrorMessage = 'Invalid date.';
    } else {
        $startTimeObj = DateTime::createFromFormat('H:i', $postedStartTime) ?: DateTime::createFromFormat('H:i:s', $postedStartTime);
        $endTimeObj = DateTime::createFromFormat('H:i', $postedEndTime) ?: DateTime::createFromFormat('H:i:s', $postedEndTime);
        if (!$startTimeObj || !$endTimeObj) {
            $addBreakErrorMessage = 'Invalid time range';
        } else {
            $startTimeDb = $startTimeObj->format('H:i:s');
            $endTimeDb = $endTimeObj->format('H:i:s');
            if ($startTimeDb >= $endTimeDb) {
                $addBreakErrorMessage = 'Invalid time range';
            } else {
                $targetDayOfWeek = date('l', strtotime($postedDate));
                $userCheckStmt = $pdo->prepare("
                    SELECT user_id
                    FROM tbl_users
                    WHERE tenant_id = ?
                      AND user_id = ?
                      AND status = 'active'
                      AND role IN ('staff', 'dentist')
                    LIMIT 1
                ");
                $userCheckStmt->execute([$tenantId, $postedUserId]);
                $matchedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                if (!$matchedUser) {
                    $addBreakErrorMessage = 'Invalid staff/dentist selection.';
                } else {
                    $overlapSql = "
                        SELECT schedule_block_id
                        FROM tbl_schedule_blocks
                        WHERE tenant_id = ?
                          AND user_id = ?
                          AND is_active = 1
                          AND start_time < ?
                          AND end_time > ?
                    ";
                    $overlapParams = [$tenantId, $postedUserId, $endTimeDb, $startTimeDb];
                    if ($postedApplyMode === 'recurring') {
                        $overlapSql .= " AND day_of_week = ? AND (block_date IS NULL OR block_date = '0000-00-00')";
                        $overlapParams[] = $targetDayOfWeek;
                    } else {
                        $overlapSql .= " AND block_date = ?";
                        $overlapParams[] = $postedDate;
                    }
                    $overlapSql .= " LIMIT 1";
                    $overlapStmt = $pdo->prepare($overlapSql);
                    $overlapStmt->execute($overlapParams);
                    if ($overlapStmt->fetch(PDO::FETCH_ASSOC)) {
                        $addBreakErrorMessage = 'Break overlaps with existing schedule';
                    } else {
                        $appointmentOverlapSql = "
                            SELECT a.id
                            FROM tbl_appointments a
                            INNER JOIN tbl_dentists d
                                ON d.tenant_id = a.tenant_id
                               AND d.dentist_id = a.dentist_id
                            WHERE a.tenant_id = ?
                              AND d.user_id = ?
                              AND LOWER(COALESCE(a.status, 'pending')) <> 'cancelled'
                              AND a.appointment_time < ?
                              AND ADDTIME(a.appointment_time, '01:00:00') > ?
                        ";
                        $appointmentOverlapParams = [$tenantId, $postedUserId, $endTimeDb, $startTimeDb];
                        if ($postedApplyMode === 'recurring') {
                            $appointmentOverlapSql .= " AND DAYNAME(a.appointment_date) = ?";
                            $appointmentOverlapParams[] = $targetDayOfWeek;
                        } else {
                            $appointmentOverlapSql .= " AND a.appointment_date = ?";
                            $appointmentOverlapParams[] = $postedDate;
                        }
                        $appointmentOverlapSql .= " LIMIT 1";
                        $appointmentOverlapStmt = $pdo->prepare($appointmentOverlapSql);
                        $appointmentOverlapStmt->execute($appointmentOverlapParams);
                        if ($appointmentOverlapStmt->fetch(PDO::FETCH_ASSOC)) {
                            $addBreakErrorMessage = 'Break overlaps with existing schedule';
                        } else {
                            $createdBy = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
                            if ($createdBy === '') {
                                $createdBy = null;
                            }
                            $insertBreakStmt = $pdo->prepare("
                                INSERT INTO tbl_schedule_blocks (
                                    tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, notes, created_by, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, 'break', 1, ?, ?, NOW())
                            ");
                            $insertBreakStmt->execute([
                                $tenantId,
                                $postedUserId,
                                $postedApplyMode === 'recurring' ? null : $postedDate,
                                $postedApplyMode === 'recurring' ? $targetDayOfWeek : null,
                                $startTimeDb,
                                $endTimeDb,
                                $postedNotes !== '' ? $postedNotes : null,
                                $createdBy,
                            ]);
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?selected_date=' . urlencode($postedDate) . '&user_id=' . urlencode($postedUserId) . '&break_status=success');
                            exit;
                        }
                    }
                }
            }
        }
    }

    if ($addBreakErrorMessage !== '') {
        $openAddBreakModal = true;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['form_action'] ?? '') === 'edit_shift') {
    $postedUserId = trim((string) ($_POST['user_id'] ?? ''));
    $postedDate = trim((string) ($_POST['block_date'] ?? ''));
    $postedStartTime = trim((string) ($_POST['start_time'] ?? ''));
    $postedEndTime = trim((string) ($_POST['end_time'] ?? ''));
    $postedApplyMode = ((string) ($_POST['apply_mode'] ?? 'date') === 'recurring') ? 'recurring' : 'date';

    $editShiftForm = [
        'user_id' => $postedUserId,
        'block_date' => $postedDate,
        'start_time' => $postedStartTime,
        'end_time' => $postedEndTime,
        'apply_mode' => $postedApplyMode,
    ];

    $selectedUserId = $postedUserId;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $selectedDate = $postedDate;
    }

    if (!$isManagerUser) {
        $editShiftErrorMessage = 'Only managers can edit shifts.';
    } elseif ($tenantId === '') {
        $editShiftErrorMessage = 'Unable to resolve clinic tenant.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $editShiftErrorMessage = 'Invalid date.';
    } else {
        $startTimeObj = DateTime::createFromFormat('H:i', $postedStartTime) ?: DateTime::createFromFormat('H:i:s', $postedStartTime);
        $endTimeObj = DateTime::createFromFormat('H:i', $postedEndTime) ?: DateTime::createFromFormat('H:i:s', $postedEndTime);
        if (!$startTimeObj || !$endTimeObj) {
            $editShiftErrorMessage = 'Invalid time range';
        } else {
            $startTimeDb = $startTimeObj->format('H:i:s');
            $endTimeDb = $endTimeObj->format('H:i:s');
            if ($startTimeDb >= $endTimeDb) {
                $editShiftErrorMessage = 'Invalid time range';
            } else {
                $targetDayOfWeek = date('l', strtotime($postedDate));
                $userCheckStmt = $pdo->prepare("
                    SELECT user_id
                    FROM tbl_users
                    WHERE tenant_id = ?
                      AND user_id = ?
                      AND status = 'active'
                      AND role IN ('staff', 'dentist')
                    LIMIT 1
                ");
                $userCheckStmt->execute([$tenantId, $postedUserId]);
                $matchedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                if (!$matchedUser) {
                    $editShiftErrorMessage = 'Invalid staff/dentist selection.';
                } else {
                    $outsideAppointmentSql = "
                        SELECT a.id
                        FROM tbl_appointments a
                        INNER JOIN tbl_dentists d
                            ON d.tenant_id = a.tenant_id
                           AND d.dentist_id = a.dentist_id
                        WHERE a.tenant_id = ?
                          AND d.user_id = ?
                          AND LOWER(COALESCE(a.status, 'pending')) <> 'cancelled'
                          AND (
                              a.appointment_time < ?
                              OR ADDTIME(a.appointment_time, '01:00:00') > ?
                          )
                    ";
                    $outsideAppointmentParams = [$tenantId, $postedUserId, $startTimeDb, $endTimeDb];
                    if ($postedApplyMode === 'recurring') {
                        $outsideAppointmentSql .= " AND DAYNAME(a.appointment_date) = ?";
                        $outsideAppointmentParams[] = $targetDayOfWeek;
                    } else {
                        $outsideAppointmentSql .= " AND a.appointment_date = ?";
                        $outsideAppointmentParams[] = $postedDate;
                    }
                    $outsideAppointmentSql .= " LIMIT 1";
                    $outsideAppointmentStmt = $pdo->prepare($outsideAppointmentSql);
                    $outsideAppointmentStmt->execute($outsideAppointmentParams);
                    if ($outsideAppointmentStmt->fetch(PDO::FETCH_ASSOC)) {
                        $editShiftErrorMessage = 'Shift conflicts with existing appointments';
                    } else {
                        $outsideBreakSql = "
                            SELECT schedule_block_id
                            FROM tbl_schedule_blocks
                            WHERE tenant_id = ?
                              AND user_id = ?
                              AND is_active = 1
                              AND LOWER(block_type) = 'break'
                              AND (
                                  start_time < ?
                                  OR end_time > ?
                              )
                        ";
                        $outsideBreakParams = [$tenantId, $postedUserId, $startTimeDb, $endTimeDb];
                        if ($postedApplyMode === 'recurring') {
                            $outsideBreakSql .= " AND day_of_week = ? AND (block_date IS NULL OR block_date = '0000-00-00')";
                            $outsideBreakParams[] = $targetDayOfWeek;
                        } else {
                            $outsideBreakSql .= " AND block_date = ?";
                            $outsideBreakParams[] = $postedDate;
                        }
                        $outsideBreakSql .= " LIMIT 1";
                        $outsideBreakStmt = $pdo->prepare($outsideBreakSql);
                        $outsideBreakStmt->execute($outsideBreakParams);
                        if ($outsideBreakStmt->fetch(PDO::FETCH_ASSOC)) {
                            $editShiftErrorMessage = 'Shift conflicts with existing breaks';
                        } else {
                            $existingWorkSql = "
                                SELECT schedule_block_id
                                FROM tbl_schedule_blocks
                                WHERE tenant_id = ?
                                  AND user_id = ?
                                  AND is_active = 1
                                  AND LOWER(block_type) IN ('work', 'shift')
                            ";
                            $existingWorkParams = [$tenantId, $postedUserId];
                            if ($postedApplyMode === 'recurring') {
                                $existingWorkSql .= " AND day_of_week = ? AND (block_date IS NULL OR block_date = '0000-00-00')";
                                $existingWorkParams[] = $targetDayOfWeek;
                            } else {
                                $existingWorkSql .= " AND block_date = ?";
                                $existingWorkParams[] = $postedDate;
                            }
                            $existingWorkSql .= " ORDER BY schedule_block_id ASC";
                            $existingWorkStmt = $pdo->prepare($existingWorkSql);
                            $existingWorkStmt->execute($existingWorkParams);
                            $workRows = $existingWorkStmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($workRows)) {
                                $primaryWorkId = (int) $workRows[0]['schedule_block_id'];
                                $updateWorkStmt = $pdo->prepare("
                                    UPDATE tbl_schedule_blocks
                                    SET start_time = ?, end_time = ?, block_type = 'work', is_active = 1, updated_at = NOW()
                                    WHERE schedule_block_id = ?
                                      AND tenant_id = ?
                                      AND user_id = ?
                                ");
                                $updateWorkStmt->execute([$startTimeDb, $endTimeDb, $primaryWorkId, $tenantId, $postedUserId]);

                                if (count($workRows) > 1) {
                                    $extraIds = array_map(static function ($row) {
                                        return (int) ($row['schedule_block_id'] ?? 0);
                                    }, array_slice($workRows, 1));
                                    $extraIds = array_values(array_filter($extraIds, static function ($id) {
                                        return $id > 0;
                                    }));
                                    if (!empty($extraIds)) {
                                        $placeholders = implode(',', array_fill(0, count($extraIds), '?'));
                                        $deactivateExtraStmt = $pdo->prepare("
                                            UPDATE tbl_schedule_blocks
                                            SET is_active = 0, updated_at = NOW()
                                            WHERE tenant_id = ?
                                              AND user_id = ?
                                              AND schedule_block_id IN ($placeholders)
                                        ");
                                        $deactivateExtraStmt->execute(array_merge([$tenantId, $postedUserId], $extraIds));
                                    }
                                }
                            } else {
                                $createdBy = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
                                if ($createdBy === '') {
                                    $createdBy = null;
                                }
                                $insertWorkStmt = $pdo->prepare("
                                    INSERT INTO tbl_schedule_blocks (
                                        tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, created_by, created_at, updated_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, 'work', 1, ?, NOW(), NOW())
                                ");
                                $insertWorkStmt->execute([
                                    $tenantId,
                                    $postedUserId,
                                    $postedApplyMode === 'recurring' ? null : $postedDate,
                                    $postedApplyMode === 'recurring' ? $targetDayOfWeek : null,
                                    $startTimeDb,
                                    $endTimeDb,
                                    $createdBy,
                                ]);
                            }

                            header('Location: ' . $_SERVER['PHP_SELF'] . '?selected_date=' . urlencode($postedDate) . '&user_id=' . urlencode($postedUserId) . '&shift_status=success');
                            exit;
                        }
                    }
                }
            }
        }
    }

    if ($editShiftErrorMessage !== '') {
        $openEditShiftModal = true;
    }
}

$staffUsers = [];
$scheduleBlocks = [];
$appointments = [];
$timelineEntries = [];
$usingFallbackRecurring = false;

if ($tenantId !== '') {
    $usersStmt = $pdo->prepare("
        SELECT user_id, full_name, role
        FROM tbl_users
        WHERE tenant_id = ?
          AND status = 'active'
          AND role IN ('staff', 'dentist')
        ORDER BY role ASC, full_name ASC
    ");
    $usersStmt->execute([$tenantId]);
    $staffUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($staffUsers)) {
        $requestedUserId = $selectedUserId;
        $validUserIds = array_column($staffUsers, 'user_id');
        $selectedUserId = in_array($requestedUserId, $validUserIds, true) ? $requestedUserId : $staffUsers[0]['user_id'];
        if ($addBreakForm['user_id'] === '') {
            $addBreakForm['user_id'] = $selectedUserId;
        }
        if ($addBreakForm['block_date'] === '') {
            $addBreakForm['block_date'] = $selectedDate;
        }
        if ($editShiftForm['user_id'] === '') {
            $editShiftForm['user_id'] = $selectedUserId;
        }
        if ($editShiftForm['block_date'] === '') {
            $editShiftForm['block_date'] = $selectedDate;
        }

        $existingWorkSql = "
            SELECT start_time, end_time
            FROM tbl_schedule_blocks
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND LOWER(block_type) IN ('work', 'shift')
        ";
        $existingWorkParams = [$tenantId, $selectedUserId];
        if (($editShiftForm['apply_mode'] ?? 'date') === 'recurring') {
            $existingWorkSql .= " AND day_of_week = ? AND (block_date IS NULL OR block_date = '0000-00-00')";
            $existingWorkParams[] = date('l', strtotime($selectedDate));
        } else {
            $existingWorkSql .= " AND block_date = ?";
            $existingWorkParams[] = $selectedDate;
        }
        $existingWorkSql .= " ORDER BY schedule_block_id ASC LIMIT 1";
        $existingWorkShiftStmt = $pdo->prepare($existingWorkSql);
        $existingWorkShiftStmt->execute($existingWorkParams);
        $existingWorkShift = $existingWorkShiftStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingWorkShift) {
            if ($editShiftForm['start_time'] === '' && isset($existingWorkShift['start_time'])) {
                $editShiftForm['start_time'] = substr((string) $existingWorkShift['start_time'], 0, 5);
            }
            if ($editShiftForm['end_time'] === '' && isset($existingWorkShift['end_time'])) {
                $editShiftForm['end_time'] = substr((string) $existingWorkShift['end_time'], 0, 5);
            }
        }

        $dateSpecificStmt = $pdo->prepare("
            SELECT start_time, end_time, block_type, block_date, day_of_week
            FROM tbl_schedule_blocks
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND block_date = ?
            ORDER BY start_time ASC
        ");
        $dateSpecificStmt->execute([$tenantId, $selectedUserId, $selectedDate]);
        $scheduleBlocks = $dateSpecificStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($scheduleBlocks)) {
            $dayOfWeek = date('l', strtotime($selectedDate));
            $recurringStmt = $pdo->prepare("
                SELECT start_time, end_time, block_type, block_date, day_of_week
                FROM tbl_schedule_blocks
                WHERE tenant_id = ?
                  AND user_id = ?
                  AND is_active = 1
                  AND day_of_week = ?
                  AND (block_date IS NULL OR block_date = '0000-00-00')
                ORDER BY start_time ASC
            ");
            $recurringStmt->execute([$tenantId, $selectedUserId, $dayOfWeek]);
            $scheduleBlocks = $recurringStmt->fetchAll(PDO::FETCH_ASSOC);
            $usingFallbackRecurring = !empty($scheduleBlocks);
        }

        $appointmentsStmt = $pdo->prepare("
            SELECT
                a.appointment_time AS start_time,
                ADDTIME(a.appointment_time, '01:00:00') AS end_time,
                a.status,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''), a.patient_id, 'Patient') AS patient_name
            FROM tbl_appointments a
            INNER JOIN tbl_dentists d
                ON d.tenant_id = a.tenant_id
               AND d.dentist_id = a.dentist_id
            LEFT JOIN tbl_patients p
                ON p.tenant_id = a.tenant_id
               AND p.patient_id = a.patient_id
            WHERE a.tenant_id = ?
              AND d.user_id = ?
              AND a.appointment_date = ?
              AND LOWER(COALESCE(a.status, 'pending')) IN ('pending', 'confirmed')
            ORDER BY a.appointment_time ASC
        ");
        $appointmentsStmt->execute([$tenantId, $selectedUserId, $selectedDate]);
        $appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($scheduleBlocks as $block) {
            $timelineEntries[] = [
                'entry_type' => 'schedule',
                'start_time' => (string) ($block['start_time'] ?? ''),
                'end_time' => (string) ($block['end_time'] ?? ''),
                'block_type' => strtolower((string) ($block['block_type'] ?? '')),
                'patient_name' => null,
                'is_recurring' => empty($block['block_date']) && !empty($block['day_of_week']),
            ];
        }

        foreach ($appointments as $appointment) {
            $timelineEntries[] = [
                'entry_type' => 'appointment',
                'start_time' => (string) ($appointment['start_time'] ?? ''),
                'end_time' => (string) ($appointment['end_time'] ?? ''),
                'block_type' => 'appointment',
                'patient_name' => (string) ($appointment['patient_name'] ?? ''),
                'is_recurring' => false,
            ];
        }

        if (!empty($timelineEntries)) {
            $appointmentRanges = [];
            foreach ($timelineEntries as $entry) {
                if (($entry['entry_type'] ?? '') !== 'appointment') {
                    continue;
                }
                $appointmentRanges[] = [
                    'start' => (string) ($entry['start_time'] ?? ''),
                    'end' => (string) ($entry['end_time'] ?? ''),
                ];
            }

            $timelineEntries = array_values(array_filter($timelineEntries, function ($entry) use ($appointmentRanges) {
                $entryType = (string) ($entry['entry_type'] ?? '');
                $entryBlockType = strtolower((string) ($entry['block_type'] ?? ''));
                if ($entryType === 'appointment') {
                    return true;
                }
                if (!in_array($entryBlockType, ['work', 'shift'], true)) {
                    return true;
                }
                $entryStart = (string) ($entry['start_time'] ?? '');
                $entryEnd = (string) ($entry['end_time'] ?? '');
                foreach ($appointmentRanges as $range) {
                    if ($entryStart < $range['end'] && $entryEnd > $range['start']) {
                        return false;
                    }
                }
                return true;
            }));

            usort($timelineEntries, function ($a, $b) {
                return strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
            });
        }
    }
}

function formatScheduleTime($timeValue)
{
    $dateTime = DateTime::createFromFormat('H:i:s', (string) $timeValue);
    if (!$dateTime) {
        $dateTime = DateTime::createFromFormat('H:i', (string) $timeValue);
    }
    return $dateTime ? $dateTime->format('h:i A') : (string) $timeValue;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Schedule Management - Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: "Manrope", sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
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
        .schedule-input {
            border: none;
            background: #f8fafc;
            border-radius: 0.9rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #0f172a;
            transition: box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .schedule-input:focus {
            outline: none;
            background: #f1f5f9;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.18);
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10 space-y-8">
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> SCHEDULE MANAGEMENT
            </div>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Schedule <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Manage staff and dentist shifts and breaks.
                    </p>
                </div>
            </div>
        </section>

        <section class="elevated-card p-7 rounded-3xl">
            <div class="flex items-center justify-between gap-4 mb-5">
                <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Top Controls</h2>
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Live Schedule Filter</div>
            </div>
            <form method="get" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Select Staff / Dentist</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
                        <select name="user_id" class="schedule-input w-full py-3 pl-10 pr-10 appearance-none">
                            <?php if (empty($staffUsers)): ?>
                                <option value="">No active staff/dentist</option>
                            <?php else: ?>
                                <?php foreach ($staffUsers as $user): ?>
                                    <?php
                                    $isSelected = $selectedUserId === (string) $user['user_id'];
                                    $roleLabel = ((string) $user['role'] === 'dentist') ? 'Dr.' : 'Staff';
                                    ?>
                                    <option value="<?php echo htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($roleLabel . ' ' . (string) $user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none">expand_more</span>
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_today</span>
                        <input type="date" name="selected_date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 pl-10 pr-4"/>
                    </div>
                </div>
                <div class="lg:col-span-5 flex flex-wrap items-end gap-3">
                    <button type="submit" class="px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:border-primary/30 hover:text-primary transition-colors">
                        Apply
                    </button>
                    <a href="?selected_date=<?php echo urlencode(date('Y-m-d')); ?>&user_id=<?php echo urlencode($selectedUserId); ?>" class="px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:border-primary/30 hover:text-primary transition-colors">
                        Today
                    </a>
                    <button type="button" data-open-modal="addBreakModal" class="px-5 py-3 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        Add Break
                    </button>
                    <button type="button" <?php echo $isManagerUser ? 'data-open-modal="editShiftModal"' : 'disabled'; ?> class="px-5 py-3 rounded-xl font-bold text-xs uppercase tracking-widest transition-colors shadow-sm <?php echo $isManagerUser ? 'bg-primary/90 hover:bg-primary text-white' : 'bg-slate-200 text-slate-500 cursor-not-allowed'; ?>">
                        Edit Shift
                    </button>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 border border-amber-200">
                        Manager Only
                    </span>
                </div>
            </form>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-4 gap-6 items-start">
            <div class="xl:col-span-3 elevated-card rounded-3xl p-7">
                <div class="flex items-center justify-between gap-4 mb-6">
                    <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Daily Timeline</h3>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <?php echo $usingFallbackRecurring ? 'Recurring Schedule' : 'Date-Specific Schedule'; ?>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php if (empty($timelineEntries)): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-600">
                            No schedule available for this date
                        </div>
                    <?php else: ?>
                        <?php foreach ($timelineEntries as $entry): ?>
                            <?php
                            $normalizedType = strtolower((string) ($entry['block_type'] ?? ''));
                            $isBreakBlock = ($normalizedType === 'break');
                            $isWorkBlock = ($normalizedType === 'work' || $normalizedType === 'shift');
                            $isAppointment = ($normalizedType === 'appointment');
                            if ($isBreakBlock) {
                                $styleClass = 'border-rose-200 bg-rose-50 text-rose-700';
                                $typeLabel = 'Break';
                            } elseif ($isAppointment) {
                                $styleClass = 'border-blue-200 bg-blue-50 text-blue-700';
                                $typeLabel = 'Booked';
                            } elseif ($isWorkBlock) {
                                $styleClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
                                $typeLabel = 'Available';
                            } else {
                                $styleClass = 'border-slate-200 bg-slate-50 text-slate-700';
                                $typeLabel = ucfirst($normalizedType !== '' ? $normalizedType : 'Block');
                            }
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 md:gap-4 items-center">
                                <div class="md:col-span-2 text-sm font-bold text-slate-700">
                                    <?php echo htmlspecialchars(formatScheduleTime($entry['start_time']) . ' - ' . formatScheduleTime($entry['end_time']), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="md:col-span-4 rounded-xl border px-4 py-3 font-bold text-xs uppercase tracking-[0.15em] <?php echo $styleClass; ?>">
                                    <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($entry['is_recurring'])): ?>
                                        <span class="ml-2 normal-case tracking-normal text-[11px] font-semibold">(Recurring)</span>
                                    <?php endif; ?>
                                    <?php if ($isAppointment && trim((string) ($entry['patient_name'] ?? '')) !== ''): ?>
                                        <span class="ml-2 normal-case tracking-normal text-[11px] font-semibold">
                                            <?php echo htmlspecialchars((string) $entry['patient_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="elevated-card rounded-3xl p-6">
                <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] mb-5">Color Legend</h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Available</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Break</span>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                        <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                        <span class="text-sm font-semibold text-slate-700">Booked</span>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</main>

<div id="addBreakModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/45">
    <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Add Break</h3>
            <button type="button" data-close-modal="addBreakModal" class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form method="post">
            <input type="hidden" name="form_action" value="add_break"/>
            <div class="p-6 space-y-4">
                <p class="text-sm text-slate-500">Add a break block to the selected schedule.</p>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Staff / Dentist</label>
                    <select name="user_id" class="schedule-input w-full py-3 px-4" required>
                        <?php foreach ($staffUsers as $user): ?>
                            <?php
                            $isFormSelected = $addBreakForm['user_id'] === (string) $user['user_id'];
                            $roleLabel = ((string) $user['role'] === 'dentist') ? 'Dr.' : 'Staff';
                            ?>
                            <option value="<?php echo htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isFormSelected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($roleLabel . ' ' . (string) $user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
                        <input type="date" name="block_date" value="<?php echo htmlspecialchars((string) $addBreakForm['block_date'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" required/>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Start Time</label>
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars((string) $addBreakForm['start_time'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" required/>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">End Time</label>
                    <input type="time" name="end_time" value="<?php echo htmlspecialchars((string) $addBreakForm['end_time'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" required/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Apply</label>
                    <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="radio" name="apply_mode" value="date" <?php echo ($addBreakForm['apply_mode'] ?? 'date') === 'recurring' ? '' : 'checked'; ?>/>
                            Apply to this date only
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="radio" name="apply_mode" value="recurring" <?php echo ($addBreakForm['apply_mode'] ?? 'date') === 'recurring' ? 'checked' : ''; ?>/>
                            Apply weekly
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="3" class="schedule-input w-full py-3 px-4 resize-y"><?php echo htmlspecialchars((string) $addBreakForm['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" data-close-modal="addBreakModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider">Close</button>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs uppercase tracking-wider">Save Break</button>
            </div>
        </form>
    </div>
</div>

<div id="editShiftModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/45">
    <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Edit Shift</h3>
            <button type="button" data-close-modal="editShiftModal" class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form method="post">
            <input type="hidden" name="form_action" value="edit_shift"/>
            <div class="p-6 space-y-4">
                <?php if (!$isManagerUser): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                        Manager access required to edit shift schedules.
                    </div>
                <?php endif; ?>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Staff / Dentist</label>
                    <select name="user_id" class="schedule-input w-full py-3 px-4" <?php echo $isManagerUser ? '' : 'disabled'; ?> required>
                        <?php foreach ($staffUsers as $user): ?>
                            <?php
                            $isFormSelected = $editShiftForm['user_id'] === (string) $user['user_id'];
                            $roleLabel = ((string) $user['role'] === 'dentist') ? 'Dr.' : 'Staff';
                            ?>
                            <option value="<?php echo htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isFormSelected ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($roleLabel . ' ' . (string) $user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
                        <input type="date" name="block_date" value="<?php echo htmlspecialchars((string) $editShiftForm['block_date'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" <?php echo $isManagerUser ? '' : 'disabled'; ?> required/>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Start Time</label>
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars((string) $editShiftForm['start_time'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" <?php echo $isManagerUser ? '' : 'disabled'; ?> required/>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Apply</label>
                    <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="radio" name="apply_mode" value="date" <?php echo ($editShiftForm['apply_mode'] ?? 'date') === 'recurring' ? '' : 'checked'; ?> <?php echo $isManagerUser ? '' : 'disabled'; ?>/>
                            Apply to this date only
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="radio" name="apply_mode" value="recurring" <?php echo ($editShiftForm['apply_mode'] ?? 'date') === 'recurring' ? 'checked' : ''; ?> <?php echo $isManagerUser ? '' : 'disabled'; ?>/>
                            Apply weekly
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">End Time</label>
                    <input type="time" name="end_time" value="<?php echo htmlspecialchars((string) $editShiftForm['end_time'], ENT_QUOTES, 'UTF-8'); ?>" class="schedule-input w-full py-3 px-4" <?php echo $isManagerUser ? '' : 'disabled'; ?> required/>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" data-close-modal="editShiftModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider">Close</button>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-wider disabled:opacity-60 disabled:cursor-not-allowed" <?php echo $isManagerUser ? '' : 'disabled'; ?>>Save Shift</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.getAttribute('data-open-modal'));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('[id$="Modal"]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    <?php if ($openAddBreakModal): ?>
        openModal('addBreakModal');
    <?php endif; ?>
    <?php if ($openEditShiftModal): ?>
        openModal('editShiftModal');
    <?php endif; ?>

    <?php if ($addBreakErrorMessage !== ''): ?>
        window.alert(<?php echo json_encode($addBreakErrorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    <?php endif; ?>

    <?php if ($addBreakSuccessMessage !== ''): ?>
        window.alert(<?php echo json_encode($addBreakSuccessMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    <?php endif; ?>
    <?php if ($editShiftErrorMessage !== ''): ?>
        window.alert(<?php echo json_encode($editShiftErrorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    <?php endif; ?>
    <?php if ($editShiftSuccessMessage !== ''): ?>
        window.alert(<?php echo json_encode($editShiftSuccessMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    <?php endif; ?>
</script>
</body>
</html>
