<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';

header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'UNAUTH']); exit; }
$uid = (int)$_SESSION['uid'];
$pdo = \App\Db::pdo();
$fn  = strtolower((string)($_GET['fn'] ?? $_POST['fn'] ?? 'list'));

const USER_PROXY_LIMIT = 15;

function j(bool $ok, array $extra=[]){ echo json_encode(['ok'=>$ok]+$extra); exit; }

/* ---- LIST ---- */
if ($fn === 'list') {
  $stmt = $pdo->prepare("SELECT id, name, host, port, ptype AS type, username, status, latency_ms, last_check, meta_json, created_at
                         FROM user_proxies WHERE user_id=:u ORDER BY created_at DESC");
  $stmt->execute([':u'=>$uid]);
  $items = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $r['meta'] = $r['meta_json'] ? json_decode((string)$r['meta_json'], true) : null;
    unset($r['meta_json']);
    $items[] = $r;
  }
  $c = $pdo->prepare("SELECT 
            SUM(status='live') AS live,
            SUM(status='dead') AS dead,
            SUM(status='past') AS past,
            SUM(status IS NULL OR status='testing') AS untested
          FROM user_proxies WHERE user_id=:u");
  $c->execute([':u'=>$uid]);
  $stats = array_map('intval', $c->fetch(PDO::FETCH_ASSOC) ?: ['live'=>0,'dead'=>0,'past'=>0,'untested'=>0]);
  j(true, ['items'=>$items,'stats'=>$stats]);
}

/* ---- helper: count for limit ---- */
function count_user_proxies(PDO $pdo, int $uid): int {
  $q = $pdo->prepare("SELECT COUNT(*) FROM user_proxies WHERE user_id=:u");
  $q->execute([':u'=>$uid]);
  return (int)$q->fetchColumn();
}

/* ---- ADD ---- */
if ($fn === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $host = trim((string)($_POST['host'] ?? ''));
  $port = (int)($_POST['port'] ?? 0);
  $type = strtolower((string)($_POST['type'] ?? 'http'));
  $user = trim((string)($_POST['username'] ?? ''));
  $pass = trim((string)($_POST['password'] ?? ''));
  if ($host==='' || $port<=0) j(false,['error'=>'HOST_PORT_REQUIRED']);
  if (!in_array($type,['http','https','socks4','socks5'],true)) $type='http';

  // LIMIT enforce
  $cur = count_user_proxies($pdo, $uid);
  if ($cur >= USER_PROXY_LIMIT) j(false, ['error'=>'LIMIT','limit'=>USER_PROXY_LIMIT]);

  $stmt = $pdo->prepare("INSERT INTO user_proxies
      (user_id, name, host, port, username, password, ptype, status, created_at)
      VALUES (:uid, :name, :host, :port, NULLIF(:u,''), NULLIF(:p,''), :t, 'testing', NOW())
      ON DUPLICATE KEY UPDATE name=VALUES(name)");
  $ok = $stmt->execute([
    ':uid'=>$uid, ':name'=>$name, ':host'=>$host, ':port'=>$port,
    ':u'=>$user, ':p'=>$pass, ':t'=>$type
  ]);
  j($ok);
}

/* ---- BULK IMPORT ---- */
if ($fn === 'import' && $_SERVER['REQUEST_METHOD']==='POST') {
  $bulk = (string)($_POST['bulk'] ?? '');
  $defType = strtolower((string)($_POST['type'] ?? ''));
  $lines = preg_split('~\r\n|\r|\n~', $bulk);
  $ins = 0; $skip = 0;

  // LIMIT window
  $cur = count_user_proxies($pdo, $uid);
  $left = max(0, USER_PROXY_LIMIT - $cur);
  if ($left <= 0) j(false, ['error'=>'LIMIT','limit'=>USER_PROXY_LIMIT]);

  // Find current max Bulk-N for this user
  $maxQ = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(name,6) AS UNSIGNED)) AS mx FROM user_proxies WHERE user_id=:u AND name LIKE 'Bulk-%'");
  $maxQ->execute([':u'=>$uid]);
  $counter = (int)($maxQ->fetchColumn() ?: 0);

  $pdo->beginTransaction();
  $stmt = $pdo->prepare("INSERT INTO user_proxies (user_id,name,host,port,username,password,ptype,status,created_at)
    VALUES (:uid,:name,:host,:port, NULLIF(:u,''), NULLIF(:p,''), :t,'testing',NOW())
    ON DUPLICATE KEY UPDATE name=VALUES(name)");

  foreach ($lines as $raw) {
    if ($ins >= $left) { $skip++; continue; } // stop adding beyond limit

    $raw = trim($raw); if ($raw==='') { $skip++; continue; }
    $host=''; $port=0; $u=''; $p=''; $type=$defType?:'';

    if (strpos($raw,'@')!==false) {
      [$hp,$cred] = explode('@',$raw,2);
      [$host,$port] = explode(':',$hp,2)+['',0];
      [$u,$p] = explode(':',$cred,2)+['',''];
    } else {
      $parts = explode(':',$raw);
      if (count($parts)>=2){ $host=$parts[0]; $port=(int)$parts[1]; }
      if (count($parts)>=4){ $u=$parts[2]; $p=$parts[3]; }
      if (count($parts)>=5){ $type=strtolower($parts[4]); }
    }
    if (!$type) $type='http';
    if ($host==='' || $port<=0) { $skip++; continue; }

    $counter++;
    $name = 'Bulk-'.$counter;
    $ok = $stmt->execute([':uid'=>$uid, ':name'=>$name, ':host'=>$host, ':port'=>$port, ':u'=>$u, ':p'=>$p, ':t'=>$type]);
    if ($ok) $ins++; else $skip++;
  }
  $pdo->commit();

  $note = ($ins < count($lines)) ? 'Some entries skipped or limit reached.' : null;
  j(true, ['inserted'=>$ins,'skipped'=>$skip,'limit_note'=>$note]);
}

/* ---- DELETE ONE ---- */
if ($fn === 'delete') {
  $id = (int)($_GET['id'] ?? 0);
  $stmt = $pdo->prepare("DELETE FROM user_proxies WHERE id=:id AND user_id=:u LIMIT 1");
  $ok = $stmt->execute([':id'=>$id, ':u'=>$uid]);
  j($ok);
}

/* ---- DELETE ALL ---- */
if ($fn === 'delete_all') {
  $stmt = $pdo->prepare("DELETE FROM user_proxies WHERE user_id=:u");
  $ok = $stmt->execute([':u'=>$uid]);
  j($ok);
}

/* ---- EXPORT ---- */
if ($fn === 'export') {
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="proxies.txt"');
  $stmt = $pdo->prepare("SELECT host,port,username,password FROM user_proxies WHERE user_id=:u ORDER BY created_at DESC");
  $stmt->execute([':u'=>$uid]);
  while ($r=$stmt->fetch(PDO::FETCH_ASSOC)) {
    $line = $r['host'].':'.$r['port'];
    if (!empty($r['username'])) $line .= ':'.($r['username']).':'.($r['password'] ?? '');
    echo $line,"\n";
  }
  exit;
}

/* ---- Helper: cURL via proxy ---- */
function curl_via_proxy(string $url, array $p): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_PROXY => $p['host'].':'.$p['port'],
  ]);
  $pt = ['http'=>CURLPROXY_HTTP,'https'=>CURLPROXY_HTTP,'socks4'=>CURLPROXY_SOCKS4,'socks5'=>CURLPROXY_SOCKS5][strtolower($p['ptype'])] ?? CURLPROXY_HTTP;
  curl_setopt($ch, CURLOPT_PROXYTYPE, $pt);
  if (!empty($p['username'])) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['username'].':'.($p['password'] ?? ''));
  $t0 = microtime(true);
  $body = @curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $lat  = (int)round((microtime(true)-$t0)*1000);
  $err  = curl_error($ch);
  curl_close($ch);
  return ['code'=>$code,'body'=>$body,'lat'=>$lat,'err'=>$err];
}

/* ---- Helper: geo/fraud lookup for an IP (no key needed) ---- */
function enrich_ip(string $ip): array {
  $geo = null;
  $gch = curl_init('https://ipwho.is/'.rawurlencode($ip));
  curl_setopt_array($gch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8]);
  $gb = curl_exec($gch); curl_close($gch);
  if ($gb) { $j = json_decode($gb,true); if (!empty($j['success'])) $geo=$j; }

  $data = [
    'exit_ip' => $ip,
    'country' => $geo['country'] ?? null,
    'country_code' => $geo['country_code'] ?? null,
    'region' => $geo['region'] ?? null,
    'city' => $geo['city'] ?? null,
    'latitude' => $geo['latitude'] ?? null,
    'longitude' => $geo['longitude'] ?? null,
    'timezone' => $geo['timezone']['id'] ?? null,
    'asn' => $geo['connection']['asn'] ?? null,
    'isp' => $geo['connection']['isp'] ?? null,
    'org' => $geo['connection']['org'] ?? null,
    'fraud_score' => null,
    'source' => 'ipwho.is'
  ];

  $ipqs = $_ENV['IPQS_KEY'] ?? '';
  if ($ipqs !== '') {
    $fq = curl_init("https://ipqualityscore.com/api/json/ip/".rawurlencode($ipqs)."/".rawurlencode($ip)."?strictness=0&allow_public_access_points=true");
    curl_setopt_array($fq,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8]);
    $fb = curl_exec($fq); curl_close($fq);
    if ($fb) {
      $fj = json_decode($fb,true);
      if (isset($fj['fraud_score'])) { $data['fraud_score'] = (int)$fj['fraud_score']; $data['source'] = 'ipwho.is + IPQS'; }
    }
  }
  return $data;
}

/* ---- TEST ONE ---- */
if ($fn === 'test') {
  $id = (int)($_GET['id'] ?? 0);
  $r = $pdo->prepare("SELECT id, host, port, username, password, ptype FROM user_proxies WHERE id=:id AND user_id=:u LIMIT 1");
  $r->execute([':id'=>$id, ':u'=>$uid]);
  $p = $r->fetch(PDO::FETCH_ASSOC);
  if (!$p) j(false,['error'=>'NOT_FOUND']);

  $status='dead'; $lat=null; $meta=null;

  if (function_exists('curl_init')) {
    $res = curl_via_proxy('https://www.google.com/generate_204', $p);
    $lat = $res['lat'];
    if ($res['code']===204 || ($res['code']>=200 && $res['code']<400)) {
      $status='live';
      $ipres = curl_via_proxy('https://api.ipify.org?format=json', $p);
      $exit_ip = null;
      if ($ipres['body']) { $jj = json_decode($ipres['body'], true); $exit_ip = $jj['ip'] ?? null; }
      if ($exit_ip) $meta = enrich_ip($exit_ip);
    }
  }

  $pdo->prepare("UPDATE user_proxies SET status=:s, latency_ms=:l, last_check=NOW(), meta_json=:m WHERE id=:id AND user_id=:u")
      ->execute([':s'=>$status,':l'=>$lat,':m'=>$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null,':id'=>$id,':u'=>$uid]);

  j(true,['status'=>$status,'latency_ms'=>$lat,'meta'=>$meta]);
}

/* ---- TEST ALL ---- */
if ($fn === 'test_all') {
  $rows = $pdo->prepare("SELECT id FROM user_proxies WHERE user_id=:u ORDER BY last_check IS NULL DESC, last_check ASC LIMIT 40");
  $rows->execute([':u'=>$uid]);
  $ids = $rows->fetchAll(PDO::FETCH_COLUMN,0);

  foreach ($ids as $id) {
    $r = $pdo->prepare("SELECT id, host, port, username, password, ptype FROM user_proxies WHERE id=:id AND user_id=:u LIMIT 1");
    $r->execute([':id'=>$id, ':u'=>$uid]);
    $p = $r->fetch(PDO::FETCH_ASSOC);
    if (!$p) continue;

    $status='dead'; $lat=null; $meta=null;
    if (function_exists('curl_init')) {
      $res = curl_via_proxy('https://www.google.com/generate_204', $p);
      $lat = $res['lat'];
      if ($res['code']===204 || ($res['code']>=200 && $res['code']<400)) {
        $status='live';
        $ipres = curl_via_proxy('https://api.ipify.org?format=json', $p);
        $exit_ip = null; if ($ipres['body']) { $jj=json_decode($ipres['body'],true); $exit_ip=$jj['ip']??null; }
        if ($exit_ip) $meta = enrich_ip($exit_ip);
      }
    }
    $pdo->prepare("UPDATE user_proxies SET status=:s, latency_ms=:l, last_check=NOW(), meta_json=:m WHERE id=:id AND user_id=:u")
        ->execute([':s'=>$status,':l'=>$lat,':m'=>$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null,':id'=>$id,':u'=>$uid]);
  }
  j(true,['tested'=>count($ids)]);
}

j(false,['error'=>'BAD_FN']);
