<?php
session_start();
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
if (!empty($_SESSION['paymongo_payment_intent_id']) && !empty($_SESSION['paymongo_client_key'])) {
    header('Location: ProviderPurchaseReceipt.php');
    exit;
}
header('Location: ProviderPurchase.php');
exit;
