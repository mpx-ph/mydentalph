<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once 'paymongo_config.php';

$client_key = $_SESSION['paymongo_client_key'] ?? null;
$payment_intent_id = $_SESSION['paymongo_payment_intent_id'] ?? null;
$paymongo_method = $_SESSION['paymongo_payment_method'] ?? 'card'; // card | gcash | paymaya
$plan_name = $_SESSION['paymongo_plan_name'] ?? 'Professional';
$plan_price = $_SESSION['paymongo_plan_price'] ?? 0;
$billing_email = $_SESSION['paymongo_billing_email'] ?? '';
$public_key = defined('PAYMONGO_PUBLIC_KEY') ? PAYMONGO_PUBLIC_KEY : '';

if (!$client_key || !$payment_intent_id) {
    header('Location: ProviderPurchase.php');
    exit;
}

$allowed_methods = ['card', 'gcash', 'paymaya'];
if (!in_array($paymongo_method, $allowed_methods, true)) {
    $paymongo_method = 'card';
}

// Build return URL for 3DS / post-payment receipt page
$return_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['REQUEST_URI'] ?? '') . '/ProviderPurchaseReceipt.php';
$return_url = str_replace('\\', '/', $return_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Complete Payment - MyDental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: '#2b8cee' } } } };
    </script>
    <style> body { font-family: 'Manrope', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen py-8 px-4">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 md:p-8">
            <h1 class="text-xl font-bold text-slate-900 mb-1">
                <?php echo $paymongo_method === 'card' ? 'Card payment' : ($paymongo_method === 'gcash' ? 'GCash payment' : 'Maya payment'); ?>
            </h1>
            <p class="text-slate-500 text-sm mb-6"><?php echo htmlspecialchars($plan_name); ?> — ₱<?php echo number_format($plan_price, 2); ?></p>

            <form id="payment-form" class="space-y-4 <?php echo $paymongo_method === 'card' ? '' : 'hidden'; ?>">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Card number</label>
                    <input type="text" id="card_number" placeholder="4242 4242 4242 4242" maxlength="19" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="cc-number"/>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Expiry (MM/YY)</label>
                        <input type="text" id="expiry" placeholder="12/28" maxlength="5" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="cc-exp"/>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">CVC</label>
                        <input type="text" id="cvc" placeholder="123" maxlength="4" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="cc-csc"/>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Billing email</label>
                    <input type="email" id="billing_email" placeholder="you@example.com" value="<?php echo htmlspecialchars($billing_email); ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="email" required/>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Name on card</label>
                    <input type="text" id="billing_name" placeholder="Juan Dela Cruz" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="cc-name"/>
                </div>
                <div id="card-errors" class="text-sm text-red-600 hidden"></div>
                <button type="submit" id="submit-btn" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 rounded-xl transition-colors">
                    Pay ₱<?php echo number_format($plan_price, 2); ?>
                </button>
            </form>

            <div id="ewallet-box" class="<?php echo $paymongo_method === 'card' ? 'hidden' : ''; ?>">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Billing email</label>
                        <input type="email" id="ewallet_email" placeholder="you@example.com" value="<?php echo htmlspecialchars($billing_email); ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="email" required/>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Full name</label>
                        <input type="text" id="ewallet_name" placeholder="Juan Dela Cruz" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary focus:border-primary" autocomplete="name"/>
                    </div>
                    <div id="ewallet-errors" class="text-sm text-red-600 hidden"></div>
                    <button type="button" id="ewallet-btn" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 rounded-xl transition-colors">
                        Continue to <?php echo $paymongo_method === 'gcash' ? 'GCash' : 'Maya'; ?>
                    </button>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        You’ll be redirected to complete the payment and then returned here automatically.
                    </p>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-4 text-center <?php echo $paymongo_method === 'card' ? '' : 'hidden'; ?>">Test card: 4343 4343 4343 4345 (Visa). Use any future expiry and 3-digit CVC.</p>
        </div>
        <p class="text-center mt-4"><a href="ProviderPurchase.php" class="text-primary text-sm font-medium">← Back to order</a></p>
    </div>

    <script>
(function() {
    var clientKey = <?php echo json_encode($client_key); ?>;
    var paymentIntentId = <?php echo json_encode($payment_intent_id); ?>;
    var publicKey = <?php echo json_encode($public_key); ?>;
    var returnUrl = <?php echo json_encode($return_url); ?>;
    var paymongoMethod = <?php echo json_encode($paymongo_method); ?>; // card|gcash|paymaya

    if (!publicKey || publicKey.indexOf('YOUR_') !== -1) {
        var errEl = document.getElementById('card-errors') || document.getElementById('ewallet-errors');
        if (errEl) {
            errEl.textContent = 'PayMongo public key not configured.';
            errEl.classList.remove('hidden');
        }
        var cardBtn = document.getElementById('submit-btn');
        if (cardBtn) cardBtn.disabled = true;
        var eBtn = document.getElementById('ewallet-btn');
        if (eBtn) eBtn.disabled = true;
        return;
    }

    function showError(msg) {
        var el = (paymongoMethod === 'card') ? document.getElementById('card-errors') : document.getElementById('ewallet-errors');
        el.textContent = msg;
        el.classList.remove('hidden');
    }
    function hideError() {
        var el = (paymongoMethod === 'card') ? document.getElementById('card-errors') : document.getElementById('ewallet-errors');
        el.classList.add('hidden');
    }

    function attachPaymentMethod(paymentMethodId) {
        return fetch('https://api.paymongo.com/v1/payment_intents/' + paymentIntentId + '/attach', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Basic ' + btoa(publicKey + ':')
            },
            body: JSON.stringify({
                data: {
                    attributes: {
                        client_key: clientKey,
                        payment_method: paymentMethodId,
                        return_url: returnUrl
                    }
                }
            })
        }).then(function(r) { return r.json(); });
    }

    function handleAttachResponse(attachRes) {
        if (attachRes && attachRes.errors && attachRes.errors.length) {
            var e = attachRes.errors[0];
            throw new Error((e && (e.detail || e.title)) || 'PayMongo rejected the request.');
        }
        var att = attachRes.data && attachRes.data.attributes;
        var status = att && att.status;
        if (status === 'succeeded') {
            window.location.href = returnUrl;
            return;
        }
        if (status === 'awaiting_next_action' && att && att.next_action && att.next_action.redirect && att.next_action.redirect.url) {
            window.location.href = att.next_action.redirect.url;
            return;
        }
        if (status === 'awaiting_payment_method' && att && att.last_payment_error) {
            throw new Error(att.last_payment_error.message || 'Payment failed.');
        }
        if (status === 'processing') {
            setTimeout(function() { window.location.href = returnUrl + '?check=1'; }, 2000);
            return;
        }
        throw new Error('Payment could not be completed. Please try again.');
    }

    var paymentForm = document.getElementById('payment-form');
    if (paymentForm) paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        hideError();
        var btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.textContent = 'Processing…';

        var cardNumber = (document.getElementById('card_number').value || '').replace(/\s/g, '');
        var expiry = (document.getElementById('expiry').value || '').trim();
        var parts = expiry.split('/');
        var expMonth = parseInt(parts[0], 10) || 0;
        var expYear = parseInt(parts[1], 10) || 0;
        if (expYear < 100) expYear += 2000;
        var cvc = (document.getElementById('cvc').value || '').trim();
        var billingName = (document.getElementById('billing_name').value || 'Cardholder').trim();
        var billingEmail = (document.getElementById('billing_email').value || '').trim();

        if (!billingEmail) {
            showError('Please enter your billing email.');
            btn.disabled = false;
            btn.textContent = 'Pay ₱<?php echo number_format($plan_price, 2); ?>';
            return;
        }
        if (cardNumber.length < 13) {
            showError('Please enter a valid card number.');
            btn.disabled = false;
            btn.textContent = 'Pay ₱<?php echo number_format($plan_price, 2); ?>';
            return;
        }

        // 1) Create Payment Method (client-side with public key)
        fetch('https://api.paymongo.com/v1/payment_methods', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Basic ' + btoa(publicKey + ':')
            },
            body: JSON.stringify({
                data: {
                    attributes: {
                        type: 'card',
                        details: {
                            card_number: cardNumber,
                            exp_month: expMonth,
                            exp_year: expYear,
                            cvc: cvc
                        },
                        billing: {
                            name: billingName,
                            email: billingEmail
                        }
                    }
                }
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(pmRes) {
            var pmId = pmRes.data && pmRes.data.id;
            if (!pmId) {
                var errMsg = (pmRes.errors && pmRes.errors[0] && (pmRes.errors[0].detail || pmRes.errors[0].title)) || 'Invalid card details.';
                throw new Error(errMsg);
            }
            // 2) Attach Payment Method to Payment Intent
            return attachPaymentMethod(pmId);
        })
        .then(function(attachRes) {
            return handleAttachResponse(attachRes);
        })
        .catch(function(err) {
            showError(err.message || 'Something went wrong.');
            btn.disabled = false;
            btn.textContent = 'Pay ₱<?php echo number_format($plan_price, 2); ?>';
        });
    });

    var ewalletBtn = document.getElementById('ewallet-btn');
    if (ewalletBtn) ewalletBtn.addEventListener('click', function() {
        hideError();
        var email = (document.getElementById('ewallet_email').value || '').trim();
        var name = (document.getElementById('ewallet_name').value || 'Customer').trim();
        if (!email) {
            showError('Please enter your billing email.');
            return;
        }

        ewalletBtn.disabled = true;
        var originalText = ewalletBtn.textContent;
        ewalletBtn.textContent = 'Redirecting…';

        fetch('https://api.paymongo.com/v1/payment_methods', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Basic ' + btoa(publicKey + ':')
            },
            body: JSON.stringify({
                data: {
                    attributes: {
                        type: paymongoMethod, // gcash | paymaya
                        billing: {
                            name: name,
                            email: email
                        }
                    }
                }
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(pmRes) {
            var pmId = pmRes.data && pmRes.data.id;
            if (!pmId) {
                var errMsg = (pmRes.errors && pmRes.errors[0] && (pmRes.errors[0].detail || pmRes.errors[0].title)) || 'Could not start e-wallet payment.';
                throw new Error(errMsg);
            }
            return attachPaymentMethod(pmId);
        })
        .then(function(attachRes) {
            return handleAttachResponse(attachRes);
        })
        .catch(function(err) {
            showError(err.message || 'Something went wrong.');
            ewalletBtn.disabled = false;
            ewalletBtn.textContent = originalText;
        });
    });
})();
    </script>
</body>
</html>
