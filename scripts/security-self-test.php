<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Config/bootstrap.php';
require_once dirname(__DIR__) . '/Config/commerce.php';

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? '[OK]   ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
};

$root = dirname(__DIR__);
$bootstrapSource = file_get_contents($root . '/Config/bootstrap.php') ?: '';
$dbSource = file_get_contents($root . '/Config/db.php') ?: '';
$securityClient = file_get_contents($root . '/Assets/js/security.js') ?: '';

echo "Velvet Vogue security self-test\n\n";

$expect(vv_parse_ini_bytes('2M') === 2 * 1024 * 1024, 'PHP size values are parsed correctly.');

$originalServer = $_SERVER;
$_SERVER['DOCUMENT_ROOT'] = dirname($root);
$_SERVER['HTTP_HOST'] = 'security-test.local';
$_SERVER['SCRIPT_NAME'] = '/' . basename($root) . '/Customer/home.php';
unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

$expectedCookiePath = '/' . basename($root) . '/';
$expect(vv_cookie_path() === $expectedCookiePath, 'Session cookies are scoped to the current project folder.');
$expect(str_starts_with(vv_session_cookie_name(), 'vv_session_') && strlen(vv_session_cookie_name()) > 20, 'The session cookie name is isolated from other local project copies.');
$expect(vv_session_cookie_name() !== 'vv_session', 'The legacy global session-cookie name is no longer used.');

$expect(defined('VV_REQUEST_SECURITY_VERSION') && VV_REQUEST_SECURITY_VERSION === 7, 'The server reports request-security protocol version 7.');
$expect(defined('VV_CSRF_PROTOCOL_VERSION') && VV_CSRF_PROTOCOL_VERSION === 7, 'The compatibility CSRF protocol reports version 7.');
$expect(str_contains($securityClient, 'const CSRF_PROTOCOL_VERSION = 7;'), 'The browser security client reports protocol version 7.');
$expect(str_contains($securityClient, '^v7\\.\\d{10}'), 'The browser accepts the stateless v7 token format.');
$expect(str_contains($dbSource, 'vv_verify_write_request();'), 'Database-backed write endpoints use the unified request guard.');
$expect(!str_contains($dbSource, 'VV_CART_MUTATION_ENDPOINT') && !str_contains($dbSource, 'VV_CHECKOUT_MUTATION_ENDPOINT'), 'The database bootstrap contains no cart or checkout security bypass.');

$token = vv_csrf_token();
$expect(str_starts_with($token, 'v7.'), 'The application issues a stateless v7 fallback token.');
$expect(vv_csrf_token_matches($token), 'A freshly signed fallback token is valid.');
$rotatedToken = vv_rotate_csrf_token();
$expect($rotatedToken !== $token && vv_csrf_token_matches($rotatedToken), 'Token renewal creates another valid signed token without changing the PHP session.');
$tamperedToken = substr($token, 0, -1) . ($token[-1] === '0' ? '1' : '0');
$expect(!vv_csrf_token_matches($tamperedToken), 'A modified fallback token is rejected.');
$expiredToken = vv_csrf_token_for_values(time() - vv_csrf_token_ttl() - 10, bin2hex(random_bytes(16)));
$futureToken = vv_csrf_token_for_values(time() + 600, bin2hex(random_bytes(16)));
$expect(!vv_csrf_token_matches($expiredToken), 'An expired fallback token is rejected.');
$expect(!vv_csrf_token_matches($futureToken), 'A token issued too far in the future is rejected.');
$expect(!str_contains($bootstrapSource, 'vv_prepare_csrf_binding') && !str_contains($bootstrapSource, 'vv_existing_csrf_browser_seed'), 'Request verification no longer depends on a CSRF browser-binding cookie.');

$_SERVER['HTTP_ORIGIN'] = 'http://security-test.local';
unset($_SERVER['HTTP_REFERER']);
$expect(vv_request_source_is_explicitly_same_origin(), 'A matching Origin header is accepted.');
$_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
$expect(!vv_request_source_is_explicitly_same_origin(), 'A cross-origin Origin header is rejected.');
unset($_SERVER['HTTP_ORIGIN']);
$_SERVER['HTTP_REFERER'] = 'http://security-test.local/' . basename($root) . '/Customer/cart.php';
$expect(vv_request_source_is_explicitly_same_origin(), 'A matching Referer header is accepted.');
$_SERVER['HTTP_REFERER'] = 'https://attacker.example/form';
$expect(!vv_request_source_is_explicitly_same_origin(), 'A cross-origin Referer header is rejected.');
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
$expect(vv_fetch_metadata_is_cross_site(), 'Cross-site Fetch Metadata is recognized.');
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
$expect(!vv_fetch_metadata_is_cross_site(), 'Same-origin Fetch Metadata is accepted.');
$_SERVER = $originalServer;

$runProbe = static function (string $mode) use ($root): string {
    $probe = tempnam(sys_get_temp_dir(), 'vv-request-probe-');
    if ($probe === false) {
        return 'probe-create-failed';
    }

    $bootstrap = var_export($root . '/Config/bootstrap.php', true);
    $code = <<<'BASE'
<?php
putenv('APP_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'security-test.local';
$_SERVER['SCRIPT_NAME'] = '/velvet-vogue-main/Actions/probe.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
BASE;
    $code = str_replace("dirname(__DIR__)", var_export(dirname($root), true), $code);

    $modeCode = match ($mode) {
        'same-origin' => "\$_SERVER['HTTP_ORIGIN']='http://security-test.local';\n\$_SERVER['HTTP_SEC_FETCH_SITE']='same-origin';\n",
        'same-referer' => "\$_SERVER['HTTP_REFERER']='http://security-test.local/velvet-vogue-main/Customer/cart.php';\n\$_SERVER['HTTP_SEC_FETCH_SITE']='same-origin';\n",
        'cross-origin' => "\$_SERVER['HTTP_ORIGIN']='https://attacker.example';\n\$_SERVER['HTTP_SEC_FETCH_SITE']='cross-site';\n",
        'cross-site-metadata' => "\$_SERVER['HTTP_ORIGIN']='http://security-test.local';\n\$_SERVER['HTTP_SEC_FETCH_SITE']='cross-site';\n",
        'signed-fallback' => "",
        default => "",
    };

    $code .= $modeCode;
    $code .= 'require ' . $bootstrap . ";\n";
    if ($mode === 'signed-fallback') {
        $code .= "\$_POST['_csrf'] = vv_csrf_token();\n";
    }
    $code .= "vv_verify_write_request();\necho 'ACCEPTED';\n";
    file_put_contents($probe, $code);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1');
    @unlink($probe);
    return is_string($output) ? trim($output) : '';
};

$expect(str_contains($runProbe('same-origin'), 'ACCEPTED'), 'A same-origin POST succeeds without session or cookie token state.');
$expect(str_contains($runProbe('same-referer'), 'ACCEPTED'), 'A same-origin POST also succeeds using Referer evidence.');
$expect(str_contains($runProbe('signed-fallback'), 'ACCEPTED'), 'A signed fallback token works when source headers are unavailable.');
$expect(str_contains($runProbe('cross-origin'), 'request_verification_failed'), 'A cross-origin POST is rejected.');
$expect(str_contains($runProbe('cross-site-metadata'), 'request_verification_failed'), 'Cross-site Fetch Metadata is rejected even when Origin is forged as same-origin.');

$writeHandlers = [];
$unprotectedHandlers = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (!preg_match('#^(Actions|Admin|Customer)/#', $relative)) {
        continue;
    }

    $source = file_get_contents($file->getPathname()) ?: '';
    $handlesWrite = str_contains($source, '$_POST')
        || preg_match('/REQUEST_METHOD[^\n]{0,100}(?:POST|PUT|PATCH|DELETE)/i', $source) === 1;
    if (!$handlesWrite) {
        continue;
    }

    $writeHandlers[] = $relative;
    $protected = str_contains($source, 'Config/db.php')
        || str_contains($source, "Config\\db.php")
        || str_contains($source, 'vv_verify_write_request()');
    if (!$protected) {
        $unprotectedHandlers[] = $relative;
    }
}
sort($writeHandlers);
sort($unprotectedHandlers);
$expect(count($writeHandlers) >= 20, 'All state-changing PHP handlers were discovered for coverage validation.');
$expect($unprotectedHandlers === [], 'Every state-changing PHP handler enters the unified request guard.');
if ($unprotectedHandlers !== []) {
    echo '[INFO] Unprotected handlers: ' . implode(', ', $unprotectedHandlers) . PHP_EOL;
}

$phpSources = '';
foreach (['Actions', 'Admin', 'Customer', 'Config', 'Assets/js'] as $directory) {
    $sourceRoot = $root . '/' . $directory;
    if (!is_dir($sourceRoot)) {
        continue;
    }
    $sourceFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($sourceFiles as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
            $phpSources .= file_get_contents($file->getPathname()) ?: '';
        }
    }
}
$expect(!str_contains($phpSources, 'VV_CART_MUTATION_ENDPOINT') && !str_contains($phpSources, 'VV_CHECKOUT_MUTATION_ENDPOINT'), 'No endpoint-specific mutation bypass remains.');
$expect(!str_contains($phpSources, 'The security check could not be completed.'), 'The obsolete session-token failure message is absent from the project.');

$checkoutSource = file_get_contents($root . '/Actions/process_order.php') ?: '';
$checkoutPageSource = file_get_contents($root . '/Customer/checkout.php') ?: '';
$checkoutClientSource = file_get_contents($root . '/Assets/js/pages/checkout.js') ?: '';
$expect(defined('VV_CHECKOUT_SECURITY_VERSION') && VV_CHECKOUT_SECURITY_VERSION === 6, 'Checkout retains its signed order-intent boundary.');
$expect(str_contains($checkoutSource, 'vv_verify_checkout_intent') && str_contains($checkoutPageSource, 'vv_checkout_intent_token($userID)') && str_contains($checkoutClientSource, "formData.append('checkout_intent', checkoutIntent)"), 'Checkout still verifies a user-specific signed order intent.');
$checkoutIntent = vv_checkout_intent_token(42);
$expect(vv_checkout_intent_is_valid($checkoutIntent, 42), 'A checkout intent is valid for its intended user.');
$expect(!vv_checkout_intent_is_valid($checkoutIntent, 43), 'A checkout intent cannot be used by another user.');

$shopSource = file_get_contents($root . '/Assets/js/pages/shop.js') ?: '';
$expect(str_contains($shopSource, 'currentVariants.combinations') && str_contains($shopSource, 'dataset.stock') && str_contains($shopSource, 'VelvetVogueSecurity.fetchJson'), 'The mini product view uses real in-stock variants and the shared request client.');

$expect(vv_valid_name('Safan', 80), 'Normal names pass validation.');
$expect(!vv_valid_name('<script>alert(1)</script>', 80), 'Markup is rejected in name fields.');
$unsafeMarkup = '<div><script>alert(1)</script><p>Safe <a href="javascript:alert(1)" onclick="alert(1)">link</a></p></div>';
$cleanMarkup = vv_sanitize_rich_text($unsafeMarkup);
$expect(!str_contains(strtolower($cleanMarkup), '<script'), 'Rich text removes script elements.');
$expect(!str_contains(strtolower($cleanMarkup), 'javascript:'), 'Rich text removes unsafe link protocols.');
$expect(!str_contains(strtolower($cleanMarkup), 'onclick='), 'Rich text removes event-handler attributes.');
$expect(str_contains($cleanMarkup, '<p>Safe'), 'Allowed rich-text content is preserved.');

$expect(vv_public_asset_url('Admin/image/example.jpg') === '../Admin/image/example.jpg', 'Approved image paths are accepted.');
$expect(vv_public_asset_url('admin/image/example.jpg') === '../Admin/image/example.jpg', 'Legacy lowercase image paths are normalized.');
$expect(vv_public_asset_url('../../Config/db.php', 'fallback.png') === 'fallback.png', 'Traversal paths are rejected.');
$expect(vv_public_asset_url('Admin/image/payload.php', 'fallback.png') === 'fallback.png', 'Non-image paths are rejected by the public asset policy.');

$percentage = vv_calculate_coupon_discount(['discountType' => 'percentage', 'discountValue' => 150], 1000);
$fixed = vv_calculate_coupon_discount(['discountType' => 'fixed', 'discountValue' => 5000], 1000);
$expect($percentage === 1000.0, 'Percentage discounts cannot exceed the subtotal.');
$expect($fixed === 1000.0, 'Fixed discounts cannot exceed the subtotal.');

if ($failures !== []) {
    echo "\nSelf-test failed with " . count($failures) . " issue(s).\n";
    exit(1);
}

echo "\nAll security self-tests passed.\n";
