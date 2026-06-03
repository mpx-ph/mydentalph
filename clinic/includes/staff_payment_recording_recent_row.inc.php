<?php

/**
 * Build display + receipt payload for one recent payment row (table + mobile cards).
 *
 * @return array<string, mixed>
 */
function staff_payment_recording_recent_payment_row_context(
    array $payment,
    ?PDO $pdo,
    string $tenantId,
    bool $supportsAppointmentServicesTable,
    bool $supportsAppointmentServiceTypeColumn,
    bool $supportsServiceEnableInstallmentColumn,
    string $clinicDisplayName,
    string $clinicLogoUrl
): array {
    $patientFirst = trim((string) ($payment['patient_first_name'] ?? ''));
    $patientLast = trim((string) ($payment['patient_last_name'] ?? ''));
    $patientName = trim($patientFirst . ' ' . $patientLast);
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientIdLabel = trim((string) ($payment['patient_id'] ?? ''));
    $initials = strtoupper(substr($patientFirst !== '' ? $patientFirst : $patientName, 0, 1) . substr($patientLast !== '' ? $patientLast : 'X', 0, 1));
    $amountLabel = '₱' . number_format((float) ($payment['amount'] ?? 0), 2);
    $paymentDateRaw = trim((string) ($payment['payment_date'] ?? ''));
    $paymentDateObj = staff_payment_recording_to_manila_datetime($paymentDateRaw);
    $dateLabel = $paymentDateObj instanceof DateTimeImmutable ? $paymentDateObj->format('M d, Y') : '-';
    $timeLabel = $paymentDateObj instanceof DateTimeImmutable ? $paymentDateObj->format('h:i A') : '-';
    $methodLabel = staff_payment_recording_format_payment_method_display(
        (string) ($payment['payment_method'] ?? 'cash'),
        (string) ($payment['notes'] ?? '')
    );
    $isBookingInstallmentPlan = !empty($payment['is_installment_plan']);
    $installmentNumber = (int) ($payment['installment_number'] ?? 0);
    $paymentLifecycleStatus = strtolower(trim((string) ($payment['status'] ?? '')));
    $isCompletedPayment = in_array($paymentLifecycleStatus, ['completed', 'paid'], true);
    $isExplicitInstallmentPayment = $installmentNumber > 0;
    if (!$isCompletedPayment) {
        $lifecycleLabels = [
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ];
        if (isset($lifecycleLabels[$paymentLifecycleStatus])) {
            $financialStatus = $lifecycleLabels[$paymentLifecycleStatus];
        } elseif ($paymentLifecycleStatus !== '') {
            $financialStatus = ucwords(str_replace('_', ' ', $paymentLifecycleStatus));
        } else {
            $financialStatus = 'Unknown';
        }
        $statusClasses = 'bg-slate-100 text-slate-700 border border-slate-200';
        if ($paymentLifecycleStatus === 'cancelled' || $paymentLifecycleStatus === 'canceled') {
            $statusClasses = 'bg-rose-50 text-rose-700 border border-rose-200';
        } elseif ($paymentLifecycleStatus === 'pending') {
            $statusClasses = 'bg-amber-50 text-amber-700 border border-amber-200';
        } elseif ($paymentLifecycleStatus === 'failed') {
            $statusClasses = 'bg-red-50 text-red-700 border border-red-200';
        }
    } elseif ($isBookingInstallmentPlan && !$isExplicitInstallmentPayment) {
        $appointmentTreatmentId = trim((string) ($payment['appointment_treatment_id'] ?? ''));
        $treatmentRemaining = (float) ($payment['treatment_remaining_balance'] ?? 0);
        $statusTotalCost = max(
            (float) ($payment['total_treatment_cost'] ?? 0),
            (float) ($payment['treatment_total_cost'] ?? 0)
        );
        $statusTotalPaid = max(
            (float) ($payment['booking_total_paid'] ?? 0),
            (float) ($payment['treatment_amount_paid'] ?? 0)
        );
        $financialStatus = staff_payment_recording_financial_status(
            $statusTotalCost,
            $statusTotalPaid,
            trim((string) ($payment['appointment_date'] ?? '')),
            $isBookingInstallmentPlan,
            (array) ($payment['installment_schedule'] ?? [])
        );
        if ($appointmentTreatmentId !== '' && $treatmentRemaining > 0.009 && $financialStatus === 'PAID') {
            $financialStatus = 'PARTIAL';
        }
        $statusClasses = 'bg-rose-50 text-rose-700 border border-rose-200';
        if ($financialStatus === 'PAID') {
            $statusClasses = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        } elseif ($financialStatus === 'PARTIAL') {
            $statusClasses = 'bg-amber-50 text-amber-700 border border-amber-200';
        } elseif ($financialStatus === 'UNPAID') {
            $statusClasses = 'bg-slate-100 text-slate-700 border border-slate-200';
        }
    } else {
        $statusTotalCost = max(
            (float) ($payment['total_treatment_cost'] ?? 0),
            (float) ($payment['treatment_total_cost'] ?? 0)
        );
        $statusTotalPaid = max(
            (float) ($payment['booking_total_paid'] ?? 0),
            (float) ($payment['treatment_amount_paid'] ?? 0)
        );
        $financialStatus = staff_payment_recording_financial_status(
            $statusTotalCost,
            $statusTotalPaid,
            trim((string) ($payment['appointment_date'] ?? '')),
            $isBookingInstallmentPlan,
            (array) ($payment['installment_schedule'] ?? [])
        );
        $appointmentTreatmentIdRow = trim((string) ($payment['appointment_treatment_id'] ?? ''));
        $treatmentRemainingRow = (float) ($payment['treatment_remaining_balance'] ?? 0);
        $paymentTypeKeyRow = strtolower(trim((string) ($payment['payment_type'] ?? '')));
        $isInstallmentDownpaymentRow = $isBookingInstallmentPlan && $paymentTypeKeyRow === 'downpayment';
        if ($isInstallmentDownpaymentRow && $financialStatus === 'PAID') {
            $stillUnsettled = ($appointmentTreatmentIdRow !== '' && $treatmentRemainingRow > 0.009)
                || ($statusTotalPaid + 0.009 < $statusTotalCost);
            if ($stillUnsettled) {
                $financialStatus = 'PARTIAL';
            }
        }
        $statusClasses = 'bg-rose-50 text-rose-700 border border-rose-200';
        if ($financialStatus === 'PAID') {
            $statusClasses = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        } elseif ($financialStatus === 'PARTIAL') {
            $statusClasses = 'bg-amber-50 text-amber-700 border border-amber-200';
        } elseif ($financialStatus === 'UNPAID') {
            $statusClasses = 'bg-slate-100 text-slate-700 border border-slate-200';
        }
    }
    $paymentBookingId = trim((string) ($payment['booking_id'] ?? ''));
    $patientEmail = trim((string) ($payment['patient_email'] ?? ''));
    if ($paymentBookingId !== '' && $pdo instanceof PDO) {
        $payment['regular_service_items'] = staff_payment_recording_fetch_regular_services_for_booking(
            $pdo,
            $tenantId,
            $paymentBookingId,
            $supportsAppointmentServicesTable,
            $supportsAppointmentServiceTypeColumn,
            $supportsServiceEnableInstallmentColumn
        );
        $appointmentServicesSummaryReceipt = staff_payment_recording_fetch_appointment_service_names_summary(
            $pdo,
            $tenantId,
            $paymentBookingId,
            $supportsAppointmentServicesTable
        );
    } else {
        $payment['regular_service_items'] = [];
        $appointmentServicesSummaryReceipt = '';
    }
    $receiptBreakdown = staff_payment_recording_build_transaction_breakdown($payment);
    $isRegularAddOnReceipt = strcasecmp((string) ($receiptBreakdown['service_label'] ?? ''), 'Add-on Services') === 0;
    if ($isRegularAddOnReceipt) {
        $remainingBalance = 0.0;
    } elseif (trim((string) ($payment['appointment_treatment_id'] ?? '')) !== '' && (float) ($payment['treatment_remaining_balance'] ?? 0) > 0.009) {
        $remainingBalance = max(0, (float) ($payment['treatment_remaining_balance'] ?? 0));
    } else {
        $remainingBalance = max(0, (float) ($payment['total_treatment_cost'] ?? 0) - (float) ($payment['booking_total_paid'] ?? 0));
    }
    $receiptServiceItems = isset($receiptBreakdown['service_items']) && is_array($receiptBreakdown['service_items'])
        ? $receiptBreakdown['service_items']
        : [];
    $receiptServicesTotal = (float) ($receiptBreakdown['services_total'] ?? 0);
    $serviceLabel = trim((string) ($payment['service_list'] ?? ''));
    if ($serviceLabel === '') {
        $serviceLabel = 'Dental treatment';
    }
    $referenceLabel = trim((string) ($payment['reference_number'] ?? ''));
    if ($referenceLabel === '') {
        $referenceLabel = trim((string) ($payment['payment_id'] ?? ''));
    }
    $receiptPayload = [
        'payment_id' => (string) ($payment['payment_id'] ?? ''),
        'patient_name' => $patientName,
        'patient_id' => $patientIdLabel,
        'patient_email' => $patientEmail,
        'service' => (string) ($receiptBreakdown['service_label'] ?? $serviceLabel),
        'service_items' => $receiptServiceItems,
        'services_total' => round($receiptServicesTotal, 2),
        'amount_paid' => round((float) ($payment['amount'] ?? 0), 2),
        'remaining_balance' => round($remainingBalance, 2),
        'payment_date' => $paymentDateObj instanceof DateTimeImmutable
            ? $paymentDateObj->format('Y-m-d H:i:s')
            : $paymentDateRaw,
        'payment_method' => $methodLabel,
        'reference_number' => $referenceLabel,
        'booking_id' => (string) ($payment['booking_id'] ?? ''),
        'clinic_name' => $clinicDisplayName,
        'clinic_logo' => $clinicLogoUrl,
        'appointment_services' => $appointmentServicesSummaryReceipt,
    ];

    return [
        'patient_name' => $patientName,
        'patient_id_label' => $patientIdLabel,
        'initials' => $initials,
        'amount_label' => $amountLabel,
        'date_label' => $dateLabel,
        'time_label' => $timeLabel,
        'method_label' => $methodLabel,
        'financial_status' => $financialStatus,
        'status_classes' => $statusClasses,
        'payment_lifecycle_status' => $paymentLifecycleStatus,
        'payment_id' => (string) ($payment['payment_id'] ?? ''),
        'receipt_json' => json_encode($receiptPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ];
}
