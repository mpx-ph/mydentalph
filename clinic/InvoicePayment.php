<?php
/**
 * Payment Invoice Page
 * Displays invoice for a specific payment transaction
 * Can be printed or downloaded as PDF
 */
$pageTitle = 'Payment Invoice - DentalPro';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Require login - exclusive to manager, doctor, and staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    logout();
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Verify user type is valid (manager, doctor, or staff only)
$userType = $_SESSION['user_type'] ?? null;
if (!in_array($userType, ['manager', 'doctor', 'staff'])) {
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/clinic_customization.php';

// Get payment ID from query parameter
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$paymentId) {
    header('Location: ' . clinicPageUrl('AdminPaymentRecording.php'));
    exit;
}

// Fetch payment details
$pdo = getDBConnection();
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.payment_id, p.patient_id, p.booking_id, p.amount, p.payment_method, p.payment_date,
               p.reference_number, p.notes, p.status, p.created_by, p.created_at, p.updated_at,
               pt.first_name as patient_first_name, pt.last_name as patient_last_name,
               pt.contact_number as patient_mobile,
               pt.patient_id as patient_display_id,
               u_patient.email as patient_email,
               a.appointment_date, a.appointment_time, a.service_type,
               CONCAT(pt_creator.first_name, ' ', pt_creator.last_name) as created_by_name
        FROM payments p
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN tbl_users u_patient ON pt.linked_user_id = u_patient.user_id AND pt.owner_user_id = u_patient.user_id
        LEFT JOIN appointments a ON p.booking_id = a.booking_id
        LEFT JOIN patients pt_creator ON (pt_creator.linked_user_id = p.created_by AND pt_creator.owner_user_id = p.created_by)
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: ' . clinicPageUrl('AdminPaymentRecording.php'));
        exit;
    }
    
    // Access is restricted to manager, doctor, and staff only (already validated above)
    
} catch (Exception $e) {
    error_log('Invoice error: ' . $e->getMessage());
    header('Location: ' . clinicPageUrl('AdminPaymentRecording.php'));
    exit;
}

$invoiceClinicName = trim((string) ($CLINIC['clinic_name'] ?? ''));
if ($invoiceClinicName === '') {
    $invoiceClinicName = '(Business Name) Dental Clinic';
}
$invoiceThankYouMessage = 'Thank you for choosing ' . $invoiceClinicName . '!';
$invoiceThankYouJs = json_encode($invoiceThankYouMessage, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Format dates
$paymentDate = $payment['payment_date'] ? date('F d, Y', strtotime($payment['payment_date'])) : 'N/A';
$paymentTime = $payment['payment_date'] ? date('h:i A', strtotime($payment['payment_date'])) : 'N/A';
$appointmentDate = $payment['appointment_date'] ? date('F d, Y', strtotime($payment['appointment_date'])) : 'N/A';
$appointmentTime = $payment['appointment_time'] ? date('h:i A', strtotime($payment['appointment_time'])) : 'N/A';

// Format payment method
function formatPaymentMethod($method) {
    $methods = [
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'gcash' => 'GCash',
        'paymaya' => 'PayMaya',
        'bank_transfer' => 'Bank Transfer',
        'bank' => 'Bank Transfer',
        'check' => 'Check'
    ];
    return $methods[strtolower($method)] ?? ucfirst($method);
}

// Format status
function formatStatus($status) {
    $statuses = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled'
    ];
    return $statuses[strtolower($status)] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: #1e293b;
            line-height: 1.6;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 50px 60px;
        }
        
        .clinic-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .clinic-logo {
            height: 80px;
            width: auto;
        }
        
        .receipt-title {
            text-align: center;
            margin: 30px 0;
        }
        
        .receipt-title h1 {
            font-size: 36px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .receipt-divider {
            height: 3px;
            background: #7c3aed;
            margin: 20px 0 30px;
        }
        
        .receipt-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        
        .meta-item {
            flex: 1;
        }
        
        .meta-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .meta-value {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .section {
            margin-bottom: 35px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #7c3aed;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 12px 20px;
        }
        
        .info-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 700;
        }
        
        .payment-summary {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }
        
        .summary-label {
            color: #64748b;
        }
        
        .summary-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            font-size: 24px;
        }
        
        .total-label {
            color: #7c3aed;
            font-weight: 700;
        }
        
        .total-amount {
            color: #7c3aed;
            font-weight: 700;
        }
        
        .status-paid {
            color: #16a34a;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-pending {
            color: #d97706;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-refunded {
            color: #2563eb;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-cancelled {
            color: #dc2626;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .invoice-footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .footer-message {
            font-size: 15px;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .footer-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
        }
        
        .footer-timestamp {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 12px;
        }
        
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 12px;
            z-index: 1000;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: #7c3aed;
            color: white;
        }
        
        .btn-primary:hover {
            background: #6d28d9;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background: white;
            color: #475569;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .invoice-container {
                padding: 40px 50px;
                box-shadow: none;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                padding: 30px 20px;
            }
            
            
            .receipt-meta {
                flex-direction: column;
                gap: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .action-buttons {
                position: relative;
                top: auto;
                right: auto;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="action-buttons">
        <button class="btn btn-primary" onclick="window.print()">
            <span class="material-icons-outlined" style="font-size: 18px;">print</span>
            Print
        </button>
        <button class="btn btn-secondary" onclick="downloadPDF()">
            <span class="material-icons-outlined" style="font-size: 18px;">download</span>
            Download PDF
        </button>
    </div>
    
    <div class="invoice-container">
        <!-- Clinic Header with Logo -->
        <div class="clinic-header">
            <img src="<?php echo BASE_URL; ?>DRCGLogo2.png" alt="Clinic Logo" class="clinic-logo" onerror="this.style.display='none'">
        </div>
        
        <!-- Receipt Title -->
        <div class="receipt-title">
            <h1>PAYMENT RECEIPT</h1>
        </div>
        <div class="receipt-divider"></div>
        
        <!-- Receipt Number and Date -->
        <div class="receipt-meta">
            <div class="meta-item">
                <div class="meta-label">Receipt Number</div>
                <div class="meta-value"><?php echo htmlspecialchars($payment['payment_id']); ?></div>
            </div>
            <div class="meta-item" style="text-align: right;">
                <div class="meta-label">Date</div>
                <div class="meta-value"><?php echo $paymentDate . ' ' . $paymentTime; ?></div>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div class="section">
            <div class="section-title">Patient Information</div>
            <div class="info-grid">
                <div class="info-label">Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['patient_first_name'] . ' ' . $payment['patient_last_name']); ?></div>
                
                <div class="info-label">Patient ID:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['patient_display_id']); ?></div>
                
                <?php if ($payment['patient_mobile']): ?>
                <div class="info-label">Phone:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['patient_mobile']); ?></div>
                <?php endif; ?>
                
                <?php if ($payment['patient_email']): ?>
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['patient_email']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Service Details -->
        <div class="section">
            <div class="section-title">Service Details</div>
            <div class="info-grid">
                <div class="info-label">Service:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['service_type'] ?: 'Dental Service'); ?></div>
                
                <?php if ($appointmentDate !== 'N/A'): ?>
                <div class="info-label">Treatment Date:</div>
                <div class="info-value"><?php echo $appointmentDate . ($appointmentTime !== 'N/A' ? ' ' . $appointmentTime : ''); ?></div>
                <?php endif; ?>
                
                <?php if ($payment['booking_id']): ?>
                <div class="info-label">Booking ID:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['booking_id']); ?></div>
                <?php endif; ?>
                
                <?php if ($payment['notes']): ?>
                <div class="info-label">Diagnosis:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['notes']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="payment-summary">
            <div class="summary-row">
                <span class="summary-label">Subtotal:</span>
                <span class="summary-value">₱ <?php echo number_format($payment['amount'], 2); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Tax:</span>
                <span class="summary-value">₱ 0.00</span>
            </div>
            <div class="total-row">
                <span class="total-label">Total Amount:</span>
                <span class="total-amount">₱ <?php echo number_format($payment['amount'], 2); ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="section">
            <div class="section-title">Payment Information</div>
            <div class="info-grid">
                <div class="info-label">Payment Method:</div>
                <div class="info-value"><?php echo formatPaymentMethod($payment['payment_method']); ?></div>
                
                <?php if ($payment['reference_number']): ?>
                <div class="info-label">Reference No.:</div>
                <div class="info-value"><?php echo htmlspecialchars($payment['reference_number']); ?></div>
                <?php endif; ?>
                
                <div class="info-label">Payment Status:</div>
                <div class="info-value">
                    <span class="status-<?php echo strtolower($payment['status']); ?>">
                        <?php echo strtoupper(formatStatus($payment['status'])); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <div class="footer-message"><?php echo htmlspecialchars($invoiceThankYouMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="footer-note">This is a computer-generated receipt. No signature required.</div>
            <div class="footer-timestamp">Generated on <?php echo date('F d, Y') . ' at ' . date('h:i A'); ?></div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        async function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 20;
            let yPos = margin;
            
            // Try to load logo and center it
            try {
                const logoImg = new Image();
                logoImg.crossOrigin = 'Anonymous';
                logoImg.src = '<?php echo BASE_URL; ?>DRCGLogo2.png';
                
                await new Promise((resolve, reject) => {
                    logoImg.onload = () => {
                        try {
                            const canvas = document.createElement('canvas');
                            canvas.width = logoImg.width;
                            canvas.height = logoImg.height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(logoImg, 0, 0);
                            const logoData = canvas.toDataURL('image/png');
                            // Center the logo (logo width ~60, page width 210, so center at ~75)
                            const logoWidth = 60;
                            const logoHeight = 20;
                            const logoX = (pageWidth - logoWidth) / 2;
                            doc.addImage(logoData, 'PNG', logoX, yPos, logoWidth, logoHeight);
                            yPos += 25;
                            resolve();
                        } catch (e) {
                            console.warn('Could not add logo:', e);
                            resolve();
                        }
                    };
                    logoImg.onerror = () => resolve();
                    setTimeout(() => resolve(), 1000);
                });
            } catch (e) {
                console.warn('Logo loading failed:', e);
            }
            
            yPos += 5;
            
            // Receipt Title
            doc.setFontSize(24);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('PAYMENT RECEIPT', pageWidth / 2, yPos, { align: 'center' });
            yPos += 10;
            
            // Divider line
            doc.setDrawColor(124, 58, 237);
            doc.setLineWidth(2);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += 15;
            
            // Receipt Number and Date
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('RECEIPT NUMBER', margin, yPos);
            doc.text('DATE', pageWidth - margin, yPos, { align: 'right' });
            yPos += 6;
            
            doc.setFontSize(11);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('<?php echo htmlspecialchars($payment['payment_id']); ?>', margin, yPos);
            doc.text('<?php echo $paymentDate . ' ' . $paymentTime; ?>', pageWidth - margin, yPos, { align: 'right' });
            yPos += 20;
            
            // Patient Information
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(124, 58, 237);
            doc.text('PATIENT INFORMATION', margin, yPos);
            yPos += 8;
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            const patientInfo = [
                ['Name:', '<?php echo htmlspecialchars($payment['patient_first_name'] . ' ' . $payment['patient_last_name']); ?>'],
                ['Patient ID:', '<?php echo htmlspecialchars($payment['patient_display_id']); ?>'],
                <?php if ($payment['patient_mobile']): ?>['Phone:', '<?php echo htmlspecialchars($payment['patient_mobile']); ?>'],<?php endif; ?>
                <?php if ($payment['patient_email']): ?>['Email:', '<?php echo htmlspecialchars($payment['patient_email']); ?>'],<?php endif; ?>
            ];
            
            patientInfo.forEach(([label, value]) => {
                doc.text(label, margin, yPos);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(0, 0, 0);
                doc.text(value, margin + 40, yPos);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(100, 116, 139);
                yPos += 6;
            });
            
            yPos += 10;
            
            // Service Details
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(124, 58, 237);
            doc.text('SERVICE DETAILS', margin, yPos);
            yPos += 8;
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            const serviceInfo = [
                ['Service:', '<?php echo htmlspecialchars($payment['service_type'] ?: 'Dental Service'); ?>'],
                <?php if ($appointmentDate !== 'N/A'): ?>['Treatment Date:', '<?php echo $appointmentDate . ($appointmentTime !== 'N/A' ? ' ' . $appointmentTime : ''); ?>'],<?php endif; ?>
                <?php if ($payment['booking_id']): ?>['Booking ID:', '<?php echo htmlspecialchars($payment['booking_id']); ?>'],<?php endif; ?>
            ];
            
            serviceInfo.forEach(([label, value]) => {
                doc.text(label, margin, yPos);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(0, 0, 0);
                doc.text(value, margin + 40, yPos);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(100, 116, 139);
                yPos += 6;
            });
            
            yPos += 15;
            
            // Payment Summary
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.5);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += 10;
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('Subtotal:', margin, yPos);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('₱ <?php echo number_format($payment['amount'], 2); ?>', pageWidth - margin, yPos, { align: 'right' });
            yPos += 7;
            
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('Tax:', margin, yPos);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('₱ 0.00', pageWidth - margin, yPos, { align: 'right' });
            yPos += 12;
            
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(1);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += 10;
            
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(124, 58, 237);
            doc.text('Total Amount:', margin, yPos);
            doc.text('₱ <?php echo number_format($payment['amount'], 2); ?>', pageWidth - margin, yPos, { align: 'right' });
            yPos += 20;
            
            // Payment Information
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(124, 58, 237);
            doc.text('PAYMENT INFORMATION', margin, yPos);
            yPos += 8;
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('Payment Method:', margin, yPos);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('<?php echo formatPaymentMethod($payment['payment_method']); ?>', margin + 40, yPos);
            yPos += 6;
            
            <?php if ($payment['reference_number']): ?>
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('Reference No.:', margin, yPos);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('<?php echo htmlspecialchars($payment['reference_number']); ?>', margin + 40, yPos);
            yPos += 6;
            <?php endif; ?>
            
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text('Payment Status:', margin, yPos);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(22, 163, 74); // Green for paid
            doc.text('<?php echo strtoupper(formatStatus($payment['status'])); ?>', margin + 40, yPos);
            yPos += 25;
            
            // Footer
            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.5);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += 12;
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(0, 0, 0);
            doc.text(<?php echo $invoiceThankYouJs; ?>, pageWidth / 2, yPos, { align: 'center' });
            yPos += 6;
            doc.setFontSize(8);
            doc.setTextColor(100, 116, 139);
            doc.text('This is a computer-generated receipt. No signature required.', pageWidth / 2, yPos, { align: 'center' });
            yPos += 6;
            doc.text('Generated on <?php echo date('F d, Y') . ' at ' . date('h:i A'); ?>', pageWidth / 2, yPos, { align: 'center' });
            
            // Save PDF
            const fileName = `Receipt_<?php echo htmlspecialchars($payment['payment_id']); ?>_<?php echo date('Y-m-d'); ?>.pdf`;
            doc.save(fileName);
        }
    </script>
</body>
</html>
