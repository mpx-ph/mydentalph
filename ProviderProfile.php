<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';

// "My Profile" is represented by the tenant account settings screen.
header('Location: ProviderTenantDashboard.php');
exit;

