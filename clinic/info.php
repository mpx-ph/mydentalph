<?php
/**
 * Minimal check: load config only. Use ?debug=1 on any page to show PHP errors.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo 'PHP ' . PHP_VERSION . '<br>';
echo 'Loading config... ';
require_once __DIR__ . '/config/config.php';
echo 'OK. BASE_URL=' . BASE_URL;
