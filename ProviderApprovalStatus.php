<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_auth.php';

[$tenantId, $ownerUserId] = provider_get_candidate_identity_from_session();

if ($tenantId === '' || $ownerUserId === '') {
    header('Location: ProviderLogin.php');
    exit;
}

$verificationStatus = provider_get_verification_request_status($pdo, $tenantId, $ownerUserId);
$verificationStatus = $verificationStatus !== null ? $verificationStatus : 'pending';
$setupLinkError = trim((string) ($_SESSION['provider_setup_link_error'] ?? ''));
unset($_SESSION['provider_setup_link_error']);

$emailVerified = provider_is_email_verified($pdo, $tenantId, $ownerUserId);
$docsSubmitted = provider_has_submitted_clinic_docs($pdo, $tenantId, $ownerUserId);

// Populate onboarding session fields so the user can continue the onboarding UI if desired.
if (empty($_SESSION['onboarding_tenant_id'])) {
    $_SESSION['onboarding_tenant_id'] = $tenantId;
}
if (empty($_SESSION['onboarding_user_id'])) {
    $_SESSION['onboarding_user_id'] = $ownerUserId;
}
if ($emailVerified && empty($_SESSION['onboarding_email_verified_at'])) {
    $_SESSION['onboarding_email_verified_at'] = time();
}
if ($docsSubmitted && empty($_SESSION['onboarding_clinic_docs_submitted_at'])) {
    $_SESSION['onboarding_clinic_docs_submitted_at'] = time();
}

if ($verificationStatus === 'approved') {
    header('Location: ProviderTenantDashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Account Status - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased min-h-screen">
<?php include 'ProviderNavbar.php'; ?>
<main class="pt-20 pb-16 px-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white/90 dark:bg-slate-950/60 border border-on-surface/5 rounded-[2rem] p-8 shadow">
            <?php if ($setupLinkError !== ''): ?>
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 font-semibold">
                    <?php echo htmlspecialchars($setupLinkError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">schedule</span>
                </div>
                <div>
                    <h1 class="font-headline text-3xl font-extrabold tracking-tight">Your account is not approved yet</h1>
                    <p class="mt-2 text-on-surface-variant font-medium">
                        <?php if (!$emailVerified): ?>
                            Please complete email OTP verification to continue.
                        <?php elseif (!$docsSubmitted): ?>
                            Please upload your clinic verification documents to start the review.
                        <?php elseif ($verificationStatus === 'rejected'): ?>
                            Your account was rejected. You cannot log in at this time. Please contact support for next steps.
                        <?php else: ?>
                            Your account is under review. Please wait for approval.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3">
                <?php if (!$emailVerified): ?>
                    <?php if (!empty($_SESSION['onboarding_pending_id'])): ?>
                        <a href="ProviderOTP.php" class="inline-flex justify-center rounded-xl bg-primary text-white font-bold px-5 py-3 hover:bg-primary/90">Go to Email OTP Verification</a>
                    <?php else: ?>
                        <a href="ProviderCreate.php" class="inline-flex justify-center rounded-xl bg-primary text-white font-bold px-5 py-3 hover:bg-primary/90">Create / Restart Account</a>
                    <?php endif; ?>
                <?php elseif (!$docsSubmitted): ?>
                    <a href="ProviderClinicVerif.php" class="inline-flex justify-center rounded-xl bg-primary text-white font-bold px-5 py-3 hover:bg-primary/90">Upload Clinic Verification Documents</a>
                <?php else: ?>
                    <?php if ($verificationStatus === 'rejected'): ?>
                        <a href="ProviderContact.php" class="inline-flex justify-center rounded-xl border border-on-surface/10 bg-white px-5 py-3 hover:bg-slate-50 font-semibold">Contact Support</a>
                        <a href="ProviderCreate.php" class="inline-flex justify-center rounded-xl bg-primary text-white font-bold px-5 py-3 hover:bg-primary/90">Create a New Account</a>
                    <?php else: ?>
                        <a href="ProviderApplication.php" class="inline-flex justify-center rounded-xl bg-primary text-white font-bold px-5 py-3 hover:bg-primary/90">View Application Details</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>

