<?php
/**
 * PayMongo API config (test or live).
 * Set PAYMONGO_SECRET_KEY and PAYMONGO_PUBLIC_KEY in your environment,
 * or define them below for local testing.
 */
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY') ?: 'sk_test_H3PLhEbxnwLFTyv6WqgZ3Hya');
}
if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY') ?: 'pk_test_REwqDhgbQUfbN9iK7HmrgtCT');
}
