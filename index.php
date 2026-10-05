<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$base_dir   = __DIR__;
$data_file  = $base_dir . '/tf_data.json';
$users_file = $base_dir . '/tf_users.json';
$conf_file  = $base_dir . '/tf_config.json';
$lock_file  = $base_dir . '/tf_installed.lock';
if (!file_exists($data_file))  file_put_contents($data_file, '[]');
if (!file_exists($users_file)) file_put_contents($users_file, '[]');
if (!file_exists($conf_file))  file_put_contents($conf_file, json_encode([
'site_name'        => 'پنل پابلو',
'force_redirect'   => '',
'force_redirect_on'=> false,
'force_redirect_all'=> true,
'allow_register'   => false,
'allow_user_delete'=> true,
'allow_test'       => true,
'theme_mode'       => 'auto',
'logo_url'         => 'https://sd1.mrpablo.ir/logo.jpg',
'logo_shape'       => 'circle',
], JSON_UNESCAPED_UNICODE));
function load_json($f) { $d = json_decode(file_get_contents($f), true); return is_array($d) ? $d : []; }
function save_json($f, $d) { file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }
function load_files()  { global $data_file;  return load_json($data_file); }
function save_files($d){ global $data_file;  save_json($data_file, array_values($d)); }
function load_users()  { global $users_file; return load_json($users_file); }
function save_users($d){ global $users_file; save_json($users_file, array_values($d)); }
function load_config() {
global $conf_file;
$defaults = [
'site_name'        => 'پنل پابلو',
'force_redirect'   => '',
'force_redirect_on'=> false,
'force_redirect_all'=> true,
'allow_register'   => false,
'allow_user_delete'=> true,
'allow_test'       => true,
'theme_mode'       => 'auto',
'logo_url'         => 'https://sd1.mrpablo.ir/logo.jpg',
'logo_shape'       => 'circle',
];
return array_merge($defaults, load_json($conf_file));
}
function save_config($d){ global $conf_file; save_json($conf_file, $d); }
function encode_pass($pw) { return base64_encode((string)$pw); }
function decode_pass($enc) { return $enc === '' ? '' : base64_decode((string)$enc); }
function get_base_url() {
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
return $proto . '://' . $host . ($dir !== '/' ? $dir : '') . '/';
}
function file_url($f) {
$base = get_base_url();
$rel  = ($f['folder'] !== '' ? $f['folder'] . '/' : '') . $f['filename'];
return $base . $rel;
}
function rebuild_htaccess() {
$files = load_files(); $now = time();
$self = basename($_SERVER['SCRIPT_NAME'] ?: 'index.php');
$rules = [];
foreach ($files as $f) {
if ($f['expires'] < $now) continue;
if (!empty($f['is_redirect']) && !empty($f['redirect_url'])) {
$rel = ($f['folder'] !== '' ? $f['folder'] . '/' : '') . $f['filename'];
$url = trim(str_replace(["\r","\n","\t"," "], '', $f['redirect_url']));
if ($url === '') continue;
$rules[] = 'RewriteRule ^' . preg_quote($rel) . '$ ' . $url . ' [R=302,L]';
}
}
$begin = '#TF_REDIRECTS_START';
$end   = '#TF_REDIRECTS_END';
$block  = $begin . "\nRewriteEngine On\n";
$block .= (empty($rules) ? '' : implode("\n", $rules) . "\n");
$block .= 'RewriteCond %{REQUEST_FILENAME} -f' . "\n";
$block .= 'RewriteCond %{REQUEST_URI} !\.(php|phtml|json|lock|htaccess|jpe?g|png|gif|webp|css|js|ico|svg|woff2?|ttf)$ [NC]' . "\n";
$block .= 'RewriteRule ^(.+)$ ' . $self . '?tf_file=$1 [L,QSA]' . "\n";
$block .= $end;
$ht = __DIR__ . '/.htaccess';
$existing = file_exists($ht) ? file_get_contents($ht) : '';
$regex = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '\n?/s';
$clean = trim(preg_replace($regex, '', $existing));
$content = $block . "\n" . ($clean !== '' ? $clean . "\n" : '');
if ($content !== $existing) file_put_contents($ht, $content);
}
function notice_page($icon, $title, $sub, $code) {
http_response_code($code);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$title?></title>
<link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#0c1a20;color:#e9f4f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#12262e;border:1px solid #1f3d48;border-radius:18px;padding:44px 40px;width:min(420px,100%);text-align:center;animation:pop .5s cubic-bezier(.2,.9,.3,1.2)}
@keyframes pop{from{opacity:0;transform:translateY(20px) scale(.95)}}
.ic{font-size:60px;display:block;margin-bottom:14px;animation:bob 3s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
h1{font-family:Lalezar;font-size:30px;color:#ffb454;margin-bottom:8px}
p{font-size:14px;color:#7f9aa2;line-height:1.9}
</style></head>
<body><div class="card"><span class="ic"><?=$icon?></span><h1><?=$title?></h1><p><?=$sub?></p></div></body></html>
<?php
exit;
}
function is_agent_expired($username) {
foreach (load_users() as $u) {
if ($u['username'] === $username && $u['role'] === 'agent') {
$exp = $u['agent_expires'] ?? 0;
if ($exp > 0 && $exp < time()) return true;
return false;
}
}
return false;
}
function handle_tf_route() {
if (!isset($_GET['tf_file'])) return;
$req = trim(str_replace(['..','\\'], ['','/'], (string)$_GET['tf_file']), '/');
$now = time();
$matched = null;
foreach (load_files() as $f) {
$rel = ($f['folder'] !== '' ? $f['folder'] . '/' : '') . $f['filename'];
if ($rel === $req) { $matched = $f; break; }
}
if (!$matched) notice_page('🔍', 'اتصال یافت نشد', 'این لینک وجود ندارد یا حذف شده است', 404);
if ($matched['expires'] < $now) notice_page('⏳', 'اتصال منقضی شده', 'اعتبار این اتصال به پایان رسیده است', 410);
$owner = $matched['owner'] ?? '';
if ($owner !== '' && is_agent_expired($owner)) {
notice_page('🧊', 'اتصال فریز شده', 'حساب نماینده این اتصال منقضی شده و لینک‌ها فریز هستند.', 403);
}
$target = '';
$cfg = load_config();
$force_applies = false;
if (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect'])) {
$force_all = !empty($cfg['force_redirect_all']);
if (!empty($matched['is_test'])) {
$force_applies = true;
} elseif ($force_all) {
$admin_owner = false;
foreach (load_users() as $u) {
if ($u['username'] === $owner && $u['role'] === 'admin') {
$admin_owner = true;
break;
}
}
$force_applies = !$admin_owner;
}
}
if ($force_applies) { $target = $cfg['force_redirect']; }
elseif (!empty($matched['is_redirect']) && !empty($matched['redirect_url'])) { $target = $matched['redirect_url']; }
if ($target !== '') { header('Location: ' . $target, true, 302); exit; }
if (file_exists($matched['path'])) {
header('Content-Type: text/plain; charset=utf-8');
header('Content-Length: ' . filesize($matched['path']));
readfile($matched['path']);
exit;
}
notice_page('📄', 'محتوایی یافت نشد', 'این اتصال خالی است', 404);
}
function cleanup_expired() {
$files = load_files(); $now = time(); $changed = false;
foreach ($files as $k => $f) {
if ($f['expires'] < $now && file_exists($f['path'])) { @unlink($files[$k]['path']); $changed = true; }
}
if ($changed) { save_files($files); rebuild_htaccess(); }
}
function cleanup_expired_agents() {
$users = load_users();
$now = time();
$one_week = 7 * 24 * 3600;
$agent_usernames_to_delete = [];
foreach ($users as $u) {
if ($u['role'] !== 'agent') continue;
$exp = $u['agent_expires'] ?? 0;
if ($exp > 0 && ($now - $exp) > $one_week) {
$agent_usernames_to_delete[] = $u['username'];
}
}
if (empty($agent_usernames_to_delete)) return;
$files = load_files();
$changed = false;
foreach ($files as $k => $f) {
if (in_array($f['owner'] ?? '', $agent_usernames_to_delete)) {
if (file_exists($f['path'])) @unlink($f['path']);
unset($files[$k]);
$changed = true;
}
}
if ($changed) { save_files($files); rebuild_htaccess(); }
}
function rrmdir($d) {
if (!is_dir($d)) return;
foreach (scandir($d) as $it) {
if ($it === '.' || $it === '..') continue;
$p = $d . '/' . $it;
is_dir($p) ? rrmdir($p) : @unlink($p);
}
@rmdir($d);
}
function to_jalali($gy, $gm, $gd) {
$gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
$jy = ($gy <= 1600) ? 0 : 979;
$gy -= ($gy <= 1600) ? 621 : 1600;
$gy2 = ($gm > 2) ? ($gy + 1) : $gy;
$days = 365*$gy + (int)(($gy2+3)/4) - (int)(($gy2+99)/100) + (int)(($gy2+399)/400) - 80 + $gd + $gdm[$gm-1];
$jy += 33 * (int)($days/12053); $days %= 12053;
$jy += 4 * (int)($days/1461); $days %= 1461;
$jy += (int)(($days-1)/365);
if ($days > 365) $days = ($days-1) % 365;
$jm = ($days < 186) ? 1 + (int)($days/31) : 7 + (int)(($days-186)/30);
$jd = 1 + (($days < 186) ? ($days % 31) : (($days-186) % 30));
return [$jy, $jm, $jd];
}
function fa_num($s) { return str_replace(range(0,9), ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$s); }
function fa_date($ts) {
$j = to_jalali((int)date('Y',$ts), (int)date('n',$ts), (int)date('j',$ts));
return fa_num(sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]) . ' — ' . date('H:i', $ts));
}
function list_dirs($base, $prefix = '', $depth = 0) {
$out = [];
if ($depth > 2 || !is_dir($base)) return $out;
foreach (scandir($base) as $it) {
if ($it === '.' || $it === '..' || $it[0] === '.') continue;
$p = $base . '/' . $it;
if (is_dir($p)) {
$rel = $prefix === '' ? $it : $prefix . '/' . $it;
$out[] = $rel;
$out = array_merge($out, list_dirs($p, $rel, $depth + 1));
}
}
return $out;
}
function current_user() {
if (!isset($_SESSION['tf_uid'])) return null;
foreach (load_users() as $u) { if ($u['id'] === $_SESSION['tf_uid']) return $u; }
return null;
}
function has_perm($perm) {
$u = current_user();
if (!$u) return false;
if ($u['role'] === 'admin') return true;
return in_array($perm, $u['permissions'] ?? []);
}
function is_admin() { $u = current_user(); return $u && $u['role'] === 'admin'; }
function json_out($arr) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf() { if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) json_out(['ok'=>false,'msg'=>'توکن امنیتی نامعتبر است']); }
function count_user_files($username, $type) {
$files = load_files(); $count = 0; $now = time();
foreach ($files as $f) {
if (($f['owner'] ?? '') !== $username) continue;
if ($f['expires'] < $now) continue;
if ($type === 'test' && !empty($f['is_test'])) $count++;
if ($type === 'normal' && empty($f['is_test'])) $count++;
}
return $count;
}
function check_limit($u, $type) {
if ($u['role'] === 'admin') return true;
$key = ($type === 'test') ? 'max_test' : 'max_normal';
$val = trim($u[$key] ?? '');
if ($val === '') return true;
$max = (int)$val;
if ($max <= 0) return false;
return count_user_files($u['username'], $type) < $max;
}
function quota_info($u, $type) {
$used = count_user_files($u['username'], $type);
if ($u['role'] === 'admin') return ['used'=>$used, 'max'=>null, 'disabled'=>false];
$key = ($type === 'test') ? 'max_test' : 'max_normal';
$val = trim($u[$key] ?? '');
if ($val === '') return ['used'=>$used, 'max'=>null, 'disabled'=>false];
$max = (int)$val;
if ($max <= 0) return ['used'=>$used, 'max'=>0, 'disabled'=>true];
return ['used'=>$used, 'max'=>$max, 'disabled'=>false];
}
function quota_summary($u) {
if ($u['role'] === 'admin') return '♾️ نامحدود';
$n = trim($u['max_normal'] ?? ''); $t = trim($u['max_test'] ?? '');
return 'عادی: '.($n===''?'∞':fa_num((int)$n)).' | تستی: '.($t===''?'∞':fa_num((int)$t));
}
function user_allowed_folders($u) { return is_array($u['allowed_folders'] ?? null) ? $u['allowed_folders'] : []; }
function folder_allowed($u, $folder) {
if ($u['role'] === 'admin') return true;
$allowed = user_allowed_folders($u);
if (empty($allowed)) return true;
return in_array($folder, $allowed);
}
function sanitize_folders_input($arr) {
if (!is_array($arr)) return [];
$out = [];
foreach ($arr as $f) { $out[] = trim(str_replace(['\\','..'],['/',''], (string)$f), '/'); }
return array_values(array_unique($out));
}
function render_perms_status($user_perms) {
global $ALL_PERMS;
$html = '<div style="display:flex;flex-wrap:wrap;gap:3px;max-width:200px">';
foreach ($ALL_PERMS as $key => $label) {
$has = in_array($key, $user_perms ?? []);
if ($has) {
$html .= '<span title="'.$label.'" style="color:var(--green);font-size:13px;cursor:help">✅</span>';
} else {
$html .= '<span title="'.$label.'" style="color:var(--red);opacity:0.3;font-size:13px;cursor:help">❌</span>';
}
}
$html .= '</div>';
return $html;
}
$ALL_PERMS = [
'view_files'        => 'مشاهده اتصالات',
'create_file'       => 'ساخت اتصال',
'edit_file'         => 'ویرایش اتصال',
'delete_file'       => 'حذف اتصال',
'manage_folders'    => 'مدیریت پوشه‌ها',
'manage_users'      => 'مدیریت کاربران',
'settings'          => 'تنظیمات سیستم',
];
$AGENT_DEFAULT_PERMS = ['view_files', 'create_file', 'edit_file', 'delete_file'];
rebuild_htaccess();
handle_tf_route();
session_start();
cleanup_expired();
cleanup_expired_agents();
if (!file_exists($lock_file)) {
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
$su = trim($_POST['username'] ?? ''); $sp = $_POST['password'] ?? '';
$sn = trim($_POST['site_name'] ?? 'پنل پابلو');
if ($su === '' || strlen($sp) < 4) {
$install_error = 'نام کاربری و رمز عبور (حداقل ۴ کاراکتر) الزامی است';
} else {
$admin = [
'id'=>uniqid('u',true),'username'=>$su,
'password'=>password_hash($sp,PASSWORD_DEFAULT),'role'=>'admin',
'permissions'=>array_keys($ALL_PERMS),'view_scope'=>'all',
'allowed_folders'=>[],'max_normal'=>'','max_test'=>'',
'created'=>time(),'last_login'=>0,'active'=>true,
'agent_expires'=>0,
'raw_password'=>encode_pass($sp),
];
save_users([$admin]);
$cfg = load_config(); $cfg['site_name'] = $sn; save_config($cfg);
file_put_contents($lock_file, date('Y-m-d H:i:s'));
header('Location: ?'); exit;
}
}
$cfg = load_config();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب — <?=htmlspecialchars($cfg['site_name'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#0c1a20;color:#e9f4f4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#12262e;border:1px solid #1f3d48;border-radius:18px;padding:36px 30px;width:min(440px,100%);text-align:center}
.box h1{font-family:Lalezar;font-size:30px;margin-bottom:6px;color:#ffb454}
.box p{font-size:13px;color:#7f9aa2;margin-bottom:24px}
.lbl{display:block;font-size:13px;font-weight:700;color:#7f9aa2;margin:16px 0 6px;text-align:right}
.inp{width:100%;background:#0d1f26;border:1px solid #1f3d48;border-radius:10px;padding:12px 14px;color:#e9f4f4;font-family:Vazirmatn;font-size:14px;outline:0;transition:border-color .2s}
.inp:focus{border-color:#35d0ba}
.btn{width:100%;margin-top:24px;padding:14px;border:0;border-radius:10px;background:linear-gradient(180deg,#35d0ba,#1fb3a0);color:#03251f;font-family:Vazirmatn;font-weight:900;font-size:16px;cursor:pointer;transition:transform .15s}
.btn:active{transform:scale(.96)}
.err{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.4);color:#ff6b6b;padding:10px;border-radius:10px;font-size:13px;margin-top:14px}
.ic{font-size:52px;margin-bottom:10px;display:block}
</style></head>
<body><div class="box"><span class="ic">⚙️</span><h1>نصب اولیه</h1><p>حساب مدیر کل را ایجاد کنید</p>
<?php if (!empty($install_error)): ?><div class="err"><?=$install_error?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="action" value="install">
<label class="lbl">نام سایت</label><input class="inp" name="site_name" value="پنل پابلو">
<label class="lbl">نام کاربری مدیر</label><input class="inp" name="username" required autocomplete="off">
<label class="lbl">رمز عبور مدیر</label><input class="inp" type="password" name="password" required>
<button class="btn" type="submit">🚀 نصب و راه‌اندازی</button></form></div></body></html>
<?php exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$action = $_POST['action'] ?? '';
if ($action === 'login') {
$un = trim($_POST['username'] ?? ''); $pw = $_POST['password'] ?? '';
foreach (load_users() as $u) {
if ($u['username'] === $un && password_verify($pw, $u['password'])) {
if (!$u['active']) json_out(['ok'=>false,'msg'=>'حساب شما غیرفعال است']);
session_regenerate_id(true); $_SESSION['tf_uid'] = $u['id'];
$users = load_users();
foreach ($users as &$uu) { if ($uu['id']===$u['id']) $uu['last_login']=time(); }
save_users($users); json_out(['ok'=>true,'redirect'=>'?']);
}
}
json_out(['ok'=>false,'msg'=>'نام کاربری یا رمز عبور اشتباه است']);
}
if ($action === 'logout') { session_destroy(); json_out(['ok'=>true]); }
$cu = current_user();
if (!$cu) json_out(['ok'=>false,'msg'=>'ابتدا وارد شوید']);
check_csrf();
$agent_frozen = false;
if ($cu['role'] === 'agent') {
$exp = $cu['agent_expires'] ?? 0;
if ($exp > 0 && $exp < time()) $agent_frozen = true;
}
if ($action === 'cleanup') { cleanup_expired(); json_out(['ok'=>true]); }
if ($action === 'create') {
if ($agent_frozen) json_out(['ok'=>false,'msg'=>'حساب شما منقضی شده و امکان ساخت اتصال ندارید.']);
$cfg = load_config();
$is_test = isset($_POST['is_test']) && $_POST['is_test'] === '1';
if ($is_test && empty($cfg['allow_test'])) json_out(['ok'=>false,'msg'=>'سرویس اتصال تستی در حال حاضر غیرفعال است']);
$folder = trim(str_replace(['\\','..'],['/',''], $_POST['folder'] ?? ''), '/');
if (!folder_allowed($cu, $folder)) json_out(['ok'=>false,'msg'=>'شما اجازه ساخت اتصال در این پوشه را ندارید']);
$filename = basename(trim($_POST['filename'] ?? ''));
if ($filename === '') json_out(['ok'=>false,'msg'=>'نام اتصال الزامی است']);
if (strpos($filename, '.') !== false) json_out(['ok'=>false,'msg'=>'نام اتصال نباید پسوند داشته باشد']);
if ($is_test) {
if (!check_limit($cu,'test')) {
$max = trim($cu['max_test'] ?? '');
if ($max === '0') json_out(['ok'=>false,'msg'=>'ساخت اتصال تستی برای شما غیرفعال است']);
json_out(['ok'=>false,'msg'=>'به سقف اتصال تستی خود ('.fa_num((int)$max).') رسیده‌اید']);
}
$redirect_url = '';
if (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect'])) $redirect_url = $cfg['force_redirect'];
$is_redirect = ($redirect_url !== '');
$content = '';
$sec = 4 * 3600;
} else {
if (!has_perm('create_file')) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
if (!check_limit($cu,'normal')) {
$max = trim($cu['max_normal'] ?? '');
if ($max === '0') json_out(['ok'=>false,'msg'=>'ساخت اتصال معمولی برای شما غیرفعال است']);
json_out(['ok'=>false,'msg'=>'به سقف اتصال معمولی خود ('.fa_num((int)$max).') رسیده‌اید']);
}
$redirect_url = trim($_POST['redirect_url'] ?? '');
if (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect']) && !empty($cfg['force_redirect_all']) && !is_admin()) $redirect_url = $cfg['force_redirect'];
$is_redirect = false;
if ($redirect_url !== '') {
if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) json_out(['ok'=>false,'msg'=>'لینک ریدایرکت نامعتبر است']);
$content = ''; $is_redirect = true;
} else { $content = $_POST['content'] ?? ''; }
$sec = max(0,(int)($_POST['m']??0))*2592000+max(0,(int)($_POST['d']??0))*86400+max(0,(int)($_POST['h']??0))*3600;
if ($sec <= 0) $sec = 3600;
}
$dir = __DIR__.($folder !== '' ? '/'.$folder : '');
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($dir.'/'.$filename, $content);
@chmod($dir.'/'.$filename, 0644);
$files = load_files();
$new = [
'id'=>uniqid('tf',true),'folder'=>$folder,'filename'=>$filename,
'path'=>$dir.'/'.$filename,'created'=>time(),'expires'=>time()+$sec,
'owner'=>$cu['username'],'is_redirect'=>$is_redirect,
'redirect_url'=>$is_redirect ? $redirect_url : '','is_test'=>$is_test,
];
$files[] = $new; save_files($files); rebuild_htaccess();
json_out(['ok'=>true,'url'=>file_url($new)]);
}
if ($action === 'update') {
if ($agent_frozen) json_out(['ok'=>false,'msg'=>'حساب شما منقضی شده و امکان ویرایش اتصال ندارید.']);
if (!has_perm('edit_file')) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
$files = load_files();
foreach ($files as $k => $f) {
if ($f['id'] === ($_POST['file_id'] ?? '')) {
if (!is_admin() && ($f['owner'] ?? '') !== $cu['username']) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
$redirect_url = trim($_POST['redirect_url'] ?? '');
$cfg = load_config();
if (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect']) && !empty($cfg['force_redirect_all']) && !is_admin()) $redirect_url = $cfg['force_redirect'];
if ($redirect_url !== '') {
if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) json_out(['ok'=>false,'msg'=>'لینک ریدایرکت نامعتبر است']);
$content = '';
$files[$k]['is_redirect'] = true; $files[$k]['redirect_url'] = $redirect_url;
} else {
$content = $_POST['content'] ?? '';
$files[$k]['is_redirect'] = false; $files[$k]['redirect_url'] = '';
}
if (!empty($f['is_test'])) { $sec = 4 * 3600; }
else {
$sec = max(0,(int)($_POST['m']??0))*2592000+max(0,(int)($_POST['d']??0))*86400+max(0,(int)($_POST['h']??0))*3600;
if ($sec <= 0) $sec = 3600;
}
file_put_contents($f['path'], $content);
@chmod($f['path'], 0644);
$files[$k]['expires'] = time()+$sec;
save_files($files); rebuild_htaccess();
json_out(['ok'=>true,'url'=>file_url($files[$k])]);
}
}
json_out(['ok'=>false,'msg'=>'اتصال یافت نشد']);
}
if ($action === 'get') {
foreach (load_files() as $f) {
if ($f['id'] === ($_POST['file_id'] ?? '')) {
if (!is_admin() && ($f['owner'] ?? '') !== $cu['username']) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
$is_r = !empty($f['is_redirect']);
$c = (!$is_r && file_exists($f['path'])) ? file_get_contents($f['path']) : '';
$cfg = load_config();
$hide_url = (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect']) && !empty($cfg['force_redirect_all']) && !is_admin());
json_out(['ok'=>true,'content'=>$c,'redirect_url'=>$hide_url ? '' : ($f['redirect_url'] ?? ''),'url'=>file_url($f),'is_test'=>!empty($f['is_test'])]);
}
}
json_out(['ok'=>false,'msg'=>'اتصال یافت نشد']);
}
if ($action === 'delete') {
if ($agent_frozen) json_out(['ok'=>false,'msg'=>'حساب شما منقضی شده و امکان حذف اتصال ندارید.']);
$cfg = load_config();
$allow_self = !empty($cfg['allow_user_delete']);
$files = load_files();
foreach ($files as $k => $f) {
if ($f['id'] === ($_POST['file_id'] ?? '')) {
if (!is_admin()) {
if (!has_perm('delete_file')) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
if (($f['owner'] ?? '') !== $cu['username']) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
if (!$allow_self) json_out(['ok'=>false,'msg'=>'حذف اتصال توسط مدیر غیرفعال شده است']);
}
if (file_exists($f['path'])) @unlink($f['path']);
unset($files[$k]); break;
}
}
save_files($files); rebuild_htaccess();
json_out(['ok'=>true]);
}
if ($action === 'delfolder') {
if (!has_perm('manage_folders')) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
$folder = trim(str_replace(['\\','..'],['/',''], $_POST['folder'] ?? ''), '/');
if ($folder === '') json_out(['ok'=>false,'msg'=>'پوشه ریشه قابل حذف نیست']);
rrmdir(__DIR__.'/'.$folder);
$files = load_files(); $ch = false;
foreach ($files as $k => $f) {
if ($f['folder']===$folder || strpos($f['folder'],$folder.'/')===0) { unset($files[$k]); $ch=true; }
}
if ($ch) save_files($files);
rebuild_htaccess(); json_out(['ok'=>true]);
}
if ($action === 'create_folder') {
if (!has_perm('manage_folders')) json_out(['ok'=>false,'msg'=>'دسترسی ندارید']);
$folder = trim(str_replace(['\\','..'],['/',''], $_POST['folder'] ?? ''), '/');
if ($folder === '') json_out(['ok'=>false,'msg'=>'نام پوشه نامعتبر است']);
$path = __DIR__.'/'.$folder;
if (!is_dir($path)) { if (mkdir($path,0755,true)) json_out(['ok'=>true]); }
json_out(['ok'=>false,'msg'=>'پوشه از قبل وجود دارد یا خطا رخ داد']);
}
if ($action === 'add_user') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$un = trim($_POST['username'] ?? ''); $pw = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user'; $perms = $_POST['perms'] ?? [];
$scope = $_POST['view_scope'] ?? 'own';
$folders = sanitize_folders_input($_POST['folders'] ?? []);
if ($un === '' || strlen($pw) < 4) json_out(['ok'=>false,'msg'=>'نام کاربری و رمز (حداقل ۴ کاراکتر) الزامی است']);
$users = load_users();
foreach ($users as $u) { if ($u['username']===$un) json_out(['ok'=>false,'msg'=>'این نام کاربری قبلاً ثبت شده']); }
if (!is_array($perms)) $perms = [];
$perms = array_values(array_intersect($perms, array_keys($ALL_PERMS)));
if (!in_array($scope, ['own','all','none'])) $scope = 'own';
$agent_expires = 0;
if ($role === 'agent') {
$exp_days = max(1, (int)($_POST['agent_expire_days'] ?? 30));
$agent_expires = time() + ($exp_days * 86400);
$perms = array_values(array_intersect($perms, $AGENT_DEFAULT_PERMS));
$scope = 'own';
}
$users[] = [
'id'=>uniqid('u',true),'username'=>$un,
'password'=>password_hash($pw,PASSWORD_DEFAULT),
'role'=>$role==='admin'?'admin':($role==='agent'?'agent':'user'),
'permissions'=>$perms,'view_scope'=>$scope,'allowed_folders'=>$folders,
'max_normal'=>trim($_POST['max_normal'] ?? ''),'max_test'=>trim($_POST['max_test'] ?? ''),
'created'=>time(),'last_login'=>0,'active'=>true,
'agent_expires'=>$agent_expires,
'raw_password'=>encode_pass($pw),
];
save_users($users); json_out(['ok'=>true]);
}
if ($action === 'edit_user') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$uid = $_POST['user_id'] ?? ''; $users = load_users();
foreach ($users as $k => $u) {
if ($u['id'] === $uid) {
if (!empty($_POST['password'])) {
$users[$k]['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
$users[$k]['raw_password'] = encode_pass($_POST['password']);
}
$new_role = ($_POST['role'] ?? 'user');
$users[$k]['role'] = $new_role==='admin'?'admin':($new_role==='agent'?'agent':'user');
$perms = $_POST['perms'] ?? []; if (!is_array($perms)) $perms = [];
$users[$k]['permissions'] = array_values(array_intersect($perms, array_keys($ALL_PERMS)));
$scope = $_POST['view_scope'] ?? 'own';
if (!in_array($scope, ['own','all','none'])) $scope = 'own';
$users[$k]['view_scope'] = $scope;
$users[$k]['allowed_folders'] = sanitize_folders_input($_POST['folders'] ?? []);
$users[$k]['max_normal'] = trim($_POST['max_normal'] ?? '');
$users[$k]['max_test']   = trim($_POST['max_test'] ?? '');
$users[$k]['active'] = isset($_POST['active']) && $_POST['active'] === '1';
if ($new_role === 'agent') {
$users[$k]['permissions'] = array_values(array_intersect($users[$k]['permissions'], $AGENT_DEFAULT_PERMS));
$users[$k]['view_scope'] = 'own';
$exp_days = (int)($_POST['agent_expire_days'] ?? 0);
if ($exp_days > 0) {
$users[$k]['agent_expires'] = time() + ($exp_days * 86400);
}
} else {
$users[$k]['agent_expires'] = 0;
}
save_users($users); json_out(['ok'=>true]);
}
}
json_out(['ok'=>false,'msg'=>'کاربر یافت نشد']);
}
if ($action === 'extend_agent') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$uid = $_POST['user_id'] ?? '';
$days = max(1, (int)($_POST['extend_days'] ?? 30));
$users = load_users();
foreach ($users as $k => $u) {
if ($u['id'] === $uid && $u['role'] === 'agent') {
$current = $u['agent_expires'] ?? 0;
$base = ($current > time()) ? $current : time();
$users[$k]['agent_expires'] = $base + ($days * 86400);
save_users($users);
json_out(['ok'=>true,'new_expires'=>$users[$k]['agent_expires']]);
}
}
json_out(['ok'=>false,'msg'=>'کاربر یافت نشد یا نماینده نیست']);
}
if ($action === 'delete_user') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$uid = $_POST['user_id'] ?? '';
if ($uid === $cu['id']) json_out(['ok'=>false,'msg'=>'نمی‌توانید خودتان را حذف کنید']);
$users = load_users();
foreach ($users as $k => $u) { if ($u['id'] === $uid) { unset($users[$k]); save_users($users); json_out(['ok'=>true]); } }
json_out(['ok'=>false,'msg'=>'کاربر یافت نشد']);
}
if ($action === 'toggle_user') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$uid = $_POST['user_id'] ?? '';
if ($uid === $cu['id']) json_out(['ok'=>false,'msg'=>'نمی‌توانید خودتان را غیرفعال کنید']);
$users = load_users();
foreach ($users as $k => $u) { if ($u['id'] === $uid) { $users[$k]['active'] = !$u['active']; save_users($users); json_out(['ok'=>true,'active'=>$users[$k]['active']]); } }
json_out(['ok'=>false]);
}
if ($action === 'save_settings') {
if (!is_admin()) json_out(['ok'=>false,'msg'=>'فقط مدیر مجاز است']);
$cfg = load_config();
$cfg['site_name']        = trim($_POST['site_name'] ?? $cfg['site_name']);
$cfg['force_redirect']   = trim($_POST['force_redirect'] ?? '');
$cfg['force_redirect_on']= isset($_POST['force_redirect_on']) && $_POST['force_redirect_on'] === '1';
$cfg['force_redirect_all']= isset($_POST['force_redirect_all']) && $_POST['force_redirect_all'] === '1';
$cfg['allow_user_delete']= isset($_POST['allow_user_delete']) && $_POST['allow_user_delete'] === '1';
$cfg['allow_test']       = isset($_POST['allow_test']) && $_POST['allow_test'] === '1';
$cfg['theme_mode']       = $_POST['theme_mode'] ?? 'auto';
$cfg['logo_url']         = trim($_POST['logo_url'] ?? ($cfg['logo_url'] ?? 'https://sd1.mrpablo.ir/logo.jpg'));
$cfg['logo_shape']       = ($_POST['logo_shape'] ?? ($cfg['logo_shape'] ?? 'circle')) === 'square' ? 'square' : 'circle';
save_config($cfg);
if (!empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect'])) {
$files = load_files();
$admin_usernames = [];
foreach (load_users() as $u) {
if ($u['role'] === 'admin') {
$admin_usernames[] = $u['username'];
}
}
$apply_all = !empty($cfg['force_redirect_all']);
foreach ($files as $k => $f) {
if (!empty($f['is_test'])) {
$files[$k]['is_redirect'] = true;
$files[$k]['redirect_url'] = $cfg['force_redirect'];
continue;
}
$owner = $f['owner'] ?? '';
if (in_array($owner, $admin_usernames)) {
continue;
}
if ($apply_all) {
$files[$k]['is_redirect'] = true;
$files[$k]['redirect_url'] = $cfg['force_redirect'];
} else {
if (!empty($f['is_redirect'])) {
$files[$k]['is_redirect'] = true;
$files[$k]['redirect_url'] = $cfg['force_redirect'];
}
}
}
save_files($files);
rebuild_htaccess();
}
json_out(['ok'=>true]);
}
json_out(['ok'=>false,'msg'=>'عملیات نامشخص']);
}
if (isset($_GET['ping'])) { echo '1'; exit; }
$cu = current_user();
if (!$cu) {
$cfg = load_config();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود — <?=htmlspecialchars($cfg['site_name'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0f1c;--panel:#111827;--panel2:#1f2937;--line:#374151;--line2:#4b5563;--text:#f9fafb;--muted:#9ca3af;--amber:#f59e0b;--teal:#14b8a6;--teal2:#0d9488;--red:#ef4444;--inbg:#1e293b}
html[data-theme="light"]{--bg:#f0f9ff;--panel:#ffffff;--panel2:#f8fafc;--line:#cbd5e1;--line2:#94a3b8;--text:#0f172a;--muted:#64748b;--amber:#d97706;--teal:#0d9488;--teal2:#0f766e;--red:#dc2626;--inbg:#f1f5f9}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Vazirmatn,Tahoma,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;transition:background .4s}
.galaxy-container{position:fixed;inset:0;z-index:-2;overflow:hidden;background:radial-gradient(ellipse at bottom,#1B2735 0%,#090A0F 100%)}
html[data-theme="light"] .galaxy-container{background:radial-gradient(ellipse at bottom,#dbeafe 0%,#f0f9ff 100%)}
.galaxy-container::before{content:'';position:absolute;inset:-50%;background:radial-gradient(circle at 30% 40%,rgba(139,92,246,.15),transparent 50%),radial-gradient(circle at 70% 60%,rgba(20,184,166,.12),transparent 50%);animation:nebulaDrift 150s linear infinite}
@keyframes nebulaDrift{0%{transform:rotate(0deg) scale(1)}50%{transform:rotate(180deg) scale(1.1)}100%{transform:rotate(360deg) scale(1)}}
.bot-card{width:min(420px,100%);background:var(--panel);border:1px solid var(--line);border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.bot-header{padding:16px 20px;background:linear-gradient(135deg,var(--teal),var(--teal2));display:flex;align-items:center;gap:12px}
.bot-avatar{width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.bot-header-info b{color:#fff;font-family:Lalezar;font-size:18px;display:block;line-height:1.2}
.bot-status{color:rgba(255,255,255,.85);font-size:11px;display:flex;align-items:center;gap:5px}
.bot-status::before{content:'';width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;animation:blink 2s infinite}
@keyframes blink{50%{opacity:.5}}
.bot-messages{height:380px;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:10px;background:var(--bg)}
.bot-messages::-webkit-scrollbar{width:6px}
.bot-messages::-webkit-scrollbar-thumb{background:var(--line2);border-radius:99px}
.bmsg{max-width:82%;padding:11px 15px;border-radius:16px;font-size:13.5px;line-height:1.7;animation:bmsgIn .35s ease;word-break:break-word}
.bmsg.bot{background:var(--inbg);color:var(--text);align-self:flex-start;border-bottom-left-radius:4px;border:1px solid var(--line)}
.bmsg.user{background:linear-gradient(180deg,var(--teal),var(--teal2));color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
@keyframes bmsgIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.bot-typing{display:flex;gap:4px;padding:12px 16px;background:var(--inbg);border-radius:16px;border-bottom-left-radius:4px;align-self:flex-start;border:1px solid var(--line)}
.bot-typing span{width:7px;height:7px;border-radius:50%;background:var(--muted);animation:typing 1.2s infinite}
.bot-typing span:nth-child(2){animation-delay:.2s}
.bot-typing span:nth-child(3){animation-delay:.4s}
@keyframes typing{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
.bot-input-row{padding:14px;border-top:1px solid var(--line);display:flex;gap:8px;background:var(--panel)}
.bot-input-row input{flex:1;background:var(--inbg);border:1px solid var(--line);border-radius:12px;padding:12px 14px;color:var(--text);font-family:Vazirmatn;font-size:14px;outline:0;transition:border-color .2s}
.bot-input-row input:focus{border-color:var(--teal)}
.bot-send{width:46px;height:46px;border-radius:12px;border:0;background:linear-gradient(180deg,var(--teal),var(--teal2));color:#fff;font-size:18px;cursor:pointer;transition:transform .15s;flex-shrink:0}
.bot-send:hover{transform:scale(1.05)}
.bot-send:active{transform:scale(.95)}
.bot-send:disabled{opacity:.5;cursor:wait}
.theme-btn-bot{width:46px;height:46px;border-radius:12px;border:1px solid var(--line);background:var(--inbg);font-size:19px;cursor:pointer;transition:all .3s;flex-shrink:0}
.theme-btn-bot:hover{border-color:var(--amber)}
</style>
</head>
<body>
<div class="galaxy-container"></div>
<div class="bot-card">
<div class="bot-header">
<div class="bot-avatar">🤖</div>
<div class="bot-header-info">
<b>دستیار ورود</b>
<span class="bot-status">آنلاین</span>
</div>
<div style="margin-right:auto">
<button class="theme-btn-bot" id="botThemeBtn" onclick="toggleBotTheme()">🌙</button>
</div>
</div>
<div class="bot-messages" id="botMessages"></div>
<div class="bot-input-row">
<input type="text" id="botInput" placeholder="پیام خود را بنویسید..." autocomplete="off">
<button class="bot-send" id="botSendBtn" onclick="botSend()">➤</button>
</div>
</div>
<script>
var botState='username';
var botUser='';
var botPass='';
var botThemeMode='dark';
function toggleBotTheme(){
botThemeMode=botThemeMode==='dark'?'light':'dark';
document.documentElement.setAttribute('data-theme',botThemeMode);
document.getElementById('botThemeBtn').textContent=botThemeMode==='dark'?'🌙':'☀️';
}
function addBotMsg(html){
var d=document.createElement('div');
d.className='bmsg bot';
d.innerHTML=html;
document.getElementById('botMessages').appendChild(d);
document.getElementById('botMessages').scrollTop=99999;
}
function addUserMsg(html){
var d=document.createElement('div');
d.className='bmsg user';
d.innerHTML=html;
document.getElementById('botMessages').appendChild(d);
document.getElementById('botMessages').scrollTop=99999;
}
function showTyping(){
var d=document.createElement('div');
d.className='bot-typing';
d.id='botTyping';
d.innerHTML='<span></span><span></span><span></span>';
document.getElementById('botMessages').appendChild(d);
document.getElementById('botMessages').scrollTop=99999;
}
function hideTyping(){
var t=document.getElementById('botTyping');
if(t)t.remove();
}
function botSend(){
var input=document.getElementById('botInput');
var btn=document.getElementById('botSendBtn');
var val=input.value.trim();
if(!val)return;
if(botState==='username'){
botUser=val;
addUserMsg(val);
input.value='';
showTyping();
btn.disabled=true;
setTimeout(function(){
hideTyping();
addBotMsg('حالا رمز عبورتون رو بفرستید 🔒');
botState='password';
input.type='password';
input.placeholder='رمز عبور...';
btn.disabled=false;
input.focus();
},700);
}else if(botState==='password'){
botPass=val;
addUserMsg('••••••');
input.value='';
showTyping();
btn.disabled=true;
var fd=new FormData();
fd.append('action','login');
fd.append('username',botUser);
fd.append('password',botPass);
fetch('',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(r){
setTimeout(function(){
hideTyping();
if(r.ok){
addBotMsg('✅ ورود موفق! در حال انتقال...');
setTimeout(function(){location.href=r.redirect||'?'},800);
}else{
addBotMsg('❌ '+r.msg+'<br><br>لطفاً دوباره نام کاربری را بفرستید 👇');
botState='username';
input.type='text';
input.placeholder='نام کاربری...';
btn.disabled=false;
input.focus();
}
},700);
}).catch(function(){
hideTyping();
addBotMsg('❌ خطا در ارتباط با سرور. دوباره تلاش کنید.');
botState='username';
input.type='text';
input.placeholder='نام کاربری...';
btn.disabled=false;
});
}
}
document.getElementById('botInput').addEventListener('keydown',function(e){
if(e.key==='Enter'){botSend()}
});
window.addEventListener('load',function(){
setTimeout(function(){
addBotMsg('سلام! 👋 به پنل خوش آمدید.<br>لطفاً نام کاربری‌تون رو بفرستید.');
},400);
document.getElementById('botInput').focus();
});
</script>
</body>
</html>
<?php exit;
}
$cfg = load_config();
$allow_self_delete = !empty($cfg['allow_user_delete']);
$allow_test = !empty($cfg['allow_test']);
$agent_frozen = false;
$agent_seconds_left = 0;
if ($cu['role'] === 'agent') {
$exp = $cu['agent_expires'] ?? 0;
if ($exp > 0) {
if ($exp < time()) {
$agent_frozen = true;
} else {
$agent_seconds_left = $exp - time();
}
}
}
$files = load_files();
if (!is_admin()) {
$scope = $cu['view_scope'] ?? 'own';
if ($cu['role'] === 'agent') $scope = 'own';
if ($scope === 'none') { $files = []; }
elseif ($scope === 'own') {
$files = array_values(array_filter($files, function($f) use ($cu) {
return ($f['owner'] ?? '') === $cu['username'];
}));
}
}
$dirs  = list_dirs(__DIR__);
$now   = time();
$folder_counts = [];
$text_files = []; $redirect_files = []; $test_files = [];
$active_count = 0;
foreach ($files as $f) {
if ($f['expires'] > $now) $active_count++;
if (!empty($f['is_test'])) $test_files[] = $f;
elseif (!empty($f['is_redirect'])) $redirect_files[] = $f;
else $text_files[] = $f;
$folder_counts[$f['folder']] = ($folder_counts[$f['folder']] ?? 0) + 1;
}
$users_list = load_users();
$csrf = csrf_token();
$site_name = htmlspecialchars($cfg['site_name']);
$force_on = !empty($cfg['force_redirect_on']) && !empty($cfg['force_redirect']);
$hide_redirect_info = $force_on && !empty($cfg['force_redirect_all']) && !is_admin();
$my_allowed = user_allowed_folders($cu);
$all_folders_ok = is_admin() || empty($my_allowed);
$can_root = $all_folders_ok || in_array('', $my_allowed);
$available_dirs = $all_folders_ok ? $dirs : array_values(array_intersect($dirs, $my_allowed));
$q_normal = quota_info($cu, 'normal');
$q_test   = quota_info($cu, 'test');
$normal_disabled = $q_normal['disabled'];
$test_disabled   = $q_test['disabled'];
$can_normal_form = has_perm('create_file') && !$normal_disabled && !$agent_frozen;
$can_test_form   = $allow_test && !$test_disabled && !$agent_frozen;
$my_normal=0;$my_redirect=0;$my_test=0;$my_expired=0;
foreach (load_files() as $f) {
if (($f['owner'] ?? '') !== $cu['username']) continue;
if ($f['expires'] < $now) { $my_expired++; continue; }
if (!empty($f['is_test'])) $my_test++;
elseif (!empty($f['is_redirect'])) $my_redirect++;
else $my_normal++;
}
$my_total = $my_normal + $my_redirect + $my_test + $my_expired;
$C = 339.292;
$dlen = function($c) use ($my_total,$C) { return $my_total ? round($c/$my_total*$C,2) : 0; };
$len_n=$dlen($my_normal); $len_r=$dlen($my_redirect); $len_t=$dlen($my_test); $len_e=$dlen($my_expired);
$off_n=0; $off_r=-$len_n; $off_t=-($len_n+$len_r); $off_e=-($len_n+$len_r+$len_t);
$qr_svg = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><path d="M15 15h2v2h-2zM19 15h2v2h-2zM15 19h2v2h-2zM19 19h2v2h-2z" fill="currentColor" stroke="none"/></svg>';
$IS_ADMIN = is_admin();
$logo_url = htmlspecialchars($cfg['logo_url'] ?? 'https://sd1.mrpablo.ir/logo.jpg');
$logo_shape = ($cfg['logo_shape'] ?? 'circle') === 'square' ? '14px' : '50%';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=$site_name?></title>
<link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Vazirmatn:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0f1c;--panel:#111827;--panel2:#1f2937;--line:#374151;--line2:#4b5563;--text:#f9fafb;--muted:#9ca3af;--amber:#f59e0b;--amber2:#d97706;--teal:#14b8a6;--teal2:#0d9488;--red:#ef4444;--green:#22c55e;--purple:#a855f7;--inbg:#1e293b;--barbg:#0f172a;--shadowc:rgba(0,0,0,.5);--ovl:rgba(2,6,23,.85);--galaxy-bg:#020617;--star-color:rgba(255,255,255,.8);--agent:#3b82f6}
html[data-theme="light"]{--bg:#f0f9ff;--panel:#ffffff;--panel2:#f8fafc;--line:#cbd5e1;--line2:#94a3b8;--text:#0f172a;--muted:#64748b;--amber:#d97706;--amber2:#b45309;--teal:#0d9488;--teal2:#0f766e;--red:#dc2626;--green:#16a34a;--purple:#9333ea;--inbg:#f1f5f9;--barbg:#e2e8f0;--shadowc:rgba(15,23,42,.15);--ovl:rgba(240,249,255,.7);--galaxy-bg:#e0f2fe;--star-color:rgba(59,130,246,.6);--agent:#2563eb}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:Vazirmatn,Tahoma,sans-serif;color:var(--text);min-height:100vh;transition:all .4s;overflow-x:hidden;background:var(--galaxy-bg);position:relative}
.galaxy-container{position:fixed;inset:0;z-index:-2;overflow:hidden;background:radial-gradient(ellipse at bottom,#1B2735 0%,#090A0F 100%);transition:background .5s}
html[data-theme="light"] .galaxy-container{background:radial-gradient(ellipse at bottom,#dbeafe 0%,#f0f9ff 100%)}
.galaxy-container::before{content:'';position:absolute;inset:-50%;background:radial-gradient(circle at 30% 40%,rgba(139,92,246,.15),transparent 50%),radial-gradient(circle at 70% 60%,rgba(20,184,166,.12),transparent 50%),radial-gradient(circle at 50% 80%,rgba(245,158,11,.1),transparent 40%);animation:nebulaDrift 150s linear infinite}
html[data-theme="light"] .galaxy-container::before{background:radial-gradient(circle at 30% 40%,rgba(139,92,246,.08),transparent 50%),radial-gradient(circle at 70% 60%,rgba(20,184,166,.06),transparent 50%)}
@keyframes nebulaDrift{0%{transform:rotate(0deg) scale(1)}50%{transform:rotate(180deg) scale(1.1)}100%{transform:rotate(360deg) scale(1)}}
.star{position:absolute;border-radius:50%;background:#fff;box-shadow:0 0 10px rgba(255,255,255,.8),0 0 20px rgba(255,255,255,.4);animation:starFall linear infinite}
html[data-theme="light"] .star{background:#3b82f6;box-shadow:0 0 6px rgba(59,130,246,.6)}
@keyframes starFall{0%{transform:translateY(-10vh) translateX(0) scale(.5);opacity:0}10%{opacity:1}90%{opacity:1}100%{transform:translateY(110vh) translateX(20px) scale(1);opacity:0}}
.star:nth-child(1){width:2px;height:2px;left:10%;animation-duration:15s;animation-delay:0s;opacity:.8}
.star:nth-child(2){width:3px;height:3px;left:20%;animation-duration:25s;animation-delay:2s;opacity:.6}
.star:nth-child(3){width:2px;height:2px;left:30%;animation-duration:18s;animation-delay:1s;opacity:.9}
.star:nth-child(4){width:1px;height:1px;left:40%;animation-duration:22s;animation-delay:3s;opacity:.7}
.star:nth-child(5){width:3px;height:3px;left:50%;animation-duration:16s;animation-delay:0s;opacity:.8}
.star:nth-child(6){width:2px;height:2px;left:60%;animation-duration:19s;animation-delay:2s;opacity:.6}
.star:nth-child(7){width:1px;height:1px;left:70%;animation-duration:17s;animation-delay:1s;opacity:.8}
.star:nth-child(8){width:3px;height:3px;left:80%;animation-duration:21s;animation-delay:3s;opacity:.7}
.star:nth-child(9){width:2px;height:2px;left:90%;animation-duration:15s;animation-delay:0s;opacity:.9}
.star:nth-child(10){width:1px;height:1px;left:15%;animation-duration:23s;animation-delay:4s;opacity:.6}
.star:nth-child(11){width:3px;height:3px;left:25%;animation-duration:18s;animation-delay:2s;opacity:.8}
.star:nth-child(12){width:2px;height:2px;left:35%;animation-duration:20s;animation-delay:1s;opacity:.7}
.star:nth-child(13){width:1px;height:1px;left:45%;animation-duration:16s;animation-delay:3s;opacity:.8}
.star:nth-child(14){width:3px;height:3px;left:55%;animation-duration:22s;animation-delay:0s;opacity:.6}
.star:nth-child(15){width:2px;height:2px;left:65%;animation-duration:17s;animation-delay:2s;opacity:.9}
.star:nth-child(16){width:1px;height:1px;left:75%;animation-duration:19s;animation-delay:4s;opacity:.7}
.star:nth-child(17){width:3px;height:3px;left:85%;animation-duration:21s;animation-delay:1s;opacity:.8}
.star:nth-child(18){width:2px;height:2px;left:95%;animation-duration:18s;animation-delay:3s;opacity:.6}
.star:nth-child(19){width:1px;height:1px;left:5%;animation-duration:20s;animation-delay:0s;opacity:.8}
.star:nth-child(20){width:3px;height:3px;left:12%;animation-duration:16s;animation-delay:2s;opacity:.7}
.star:nth-child(21){width:2px;height:2px;left:22%;animation-duration:24s;animation-delay:5s;opacity:.6}
.star:nth-child(22){width:1px;height:1px;left:32%;animation-duration:19s;animation-delay:1s;opacity:.8}
.star:nth-child(23){width:3px;height:3px;left:42%;animation-duration:17s;animation-delay:3s;opacity:.7}
.star:nth-child(24){width:2px;height:2px;left:52%;animation-duration:21s;animation-delay:0s;opacity:.9}
.star:nth-child(25){width:1px;height:1px;left:62%;animation-duration:15s;animation-delay:2s;opacity:.6}
.star:nth-child(26){width:3px;height:3px;left:72%;animation-duration:23s;animation-delay:4s;opacity:.8}
.star:nth-child(27){width:2px;height:2px;left:82%;animation-duration:18s;animation-delay:1s;opacity:.7}
.star:nth-child(28){width:1px;height:1px;left:92%;animation-duration:20s;animation-delay:3s;opacity:.9}
.star:nth-child(29){width:3px;height:3px;left:8%;animation-duration:16s;animation-delay:5s;opacity:.6}
.star:nth-child(30){width:2px;height:2px;left:18%;animation-duration:22s;animation-delay:2s;opacity:.8}
.meteor{position:absolute;width:4px;height:4px;background:#fff;border-radius:50%;opacity:0;filter:drop-shadow(0 0 6px #fff) drop-shadow(0 0 15px rgba(139,92,246,.8));animation:meteorFly linear infinite}
.meteor::before{content:'';position:absolute;top:50%;right:100%;width:140px;height:2px;transform:translateY(-50%);background:linear-gradient(90deg,rgba(255,255,255,.9) 0%,rgba(139,92,246,.5) 40%,rgba(59,130,246,.2) 70%,transparent 100%);border-radius:99px}
.meteor::after{content:'';position:absolute;top:50%;right:100%;width:80px;height:5px;transform:translateY(-50%);background:linear-gradient(90deg,rgba(139,92,246,.4),rgba(59,130,246,.15),transparent);border-radius:99px;filter:blur(2px)}
html[data-theme="light"] .meteor{background:#3b82f6;filter:drop-shadow(0 0 6px rgba(59,130,246,.8)) drop-shadow(0 0 15px rgba(59,130,246,.4))}
html[data-theme="light"] .meteor::before{background:linear-gradient(90deg,rgba(59,130,246,.8) 0%,rgba(139,92,246,.4) 40%,transparent 100%)}
html[data-theme="light"] .meteor::after{background:linear-gradient(90deg,rgba(59,130,246,.3),transparent);filter:blur(2px)}
.meteor:nth-child(31){top:8%;left:85%;animation-duration:6s;animation-delay:1s}
.meteor:nth-child(32){top:25%;left:95%;animation-duration:8s;animation-delay:6s}
.meteor:nth-child(33){top:45%;left:90%;animation-duration:7s;animation-delay:12s}
.meteor:nth-child(34){top:15%;left:70%;animation-duration:9s;animation-delay:18s}
.meteor:nth-child(35){top:55%;left:100%;animation-duration:6.5s;animation-delay:24s}
.meteor:nth-child(36){top:35%;left:80%;animation-duration:7.5s;animation-delay:30s}
@keyframes meteorFly{0%{transform:translate(0,0) rotate(-35deg) scale(1);opacity:0}3%{opacity:1}12%{opacity:1}18%{transform:translate(-420px,280px) rotate(-35deg) scale(.3);opacity:0}100%{opacity:0}}
::selection{background:rgba(20,184,166,.3)}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--line2);border-radius:99px}
.wrap{max-width:1280px;margin:0 auto;padding:20px;position:relative;padding-bottom:100px}
header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:6px 0 18px;border-bottom:1px solid var(--line);margin-bottom:18px;position:relative;z-index:100}
.brand{display:flex;align-items:center;gap:14px}
.logo{width:52px;height:52px;overflow:hidden;transform:rotate(-6deg);box-shadow:0 8px 22px rgba(245,158,11,.35);animation:bob 4s ease-in-out infinite;border:2px solid var(--amber);background:var(--panel);flex-shrink:0}
.logo img{width:100%;height:100%;object-fit:cover;display:block}
@keyframes bob{0%,100%{transform:rotate(-6deg) translateY(0)}50%{transform:rotate(-3deg) translateY(-4px)}}
h1{font-family:Lalezar;font-size:34px;line-height:1}
.brand p{font-size:12.5px;color:var(--muted);margin-top:4px}
.h-left{display:flex;align-items:center;gap:12px}
.clock{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:10px 20px;text-align:center;box-shadow:0 4px 12px var(--shadowc)}
#clockTime{display:block;font-family:Lalezar;font-size:26px;color:var(--amber);line-height:1.15;direction:ltr}
#clockDate{font-size:11.5px;color:var(--muted)}
.theme-btn{width:48px;height:48px;border-radius:12px;border:1px solid var(--line2);background:var(--panel);font-size:21px;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px var(--shadowc)}
.theme-btn:hover{border-color:var(--amber);transform:translateY(-2px) rotate(15deg);box-shadow:0 8px 20px var(--shadowc)}
.hamburger{width:48px;height:48px;border-radius:12px;border:1px solid var(--line2);background:var(--panel);cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;transition:all .3s;z-index:110;box-shadow:0 4px 12px var(--shadowc);position:relative}
.hamburger span{display:block;width:22px;height:2.5px;background:var(--text);border-radius:9px;transition:all .35s cubic-bezier(.4,0,.2,1)}
.hamburger.open span:nth-child(1){transform:rotate(45deg) translate(5px,5px)}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0)}
.hamburger.open span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px)}
.sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:150;opacity:0;pointer-events:none;transition:opacity .3s}
.sidebar-overlay.on{opacity:1;pointer-events:auto}
.sidebar{position:fixed;top:0;right:-320px;width:300px;max-width:85vw;height:100vh;background:var(--panel);border-left:1px solid var(--line2);z-index:160;transition:right .35s cubic-bezier(.4,0,.2,1);overflow-y:auto;padding:24px 20px;display:flex;flex-direction:column;gap:6px;box-shadow:-4px 0 20px var(--shadowc)}
.sidebar.on{right:0}
.sb-head{display:flex;align-items:center;gap:12px;padding-bottom:18px;border-bottom:1px solid var(--line);margin-bottom:12px}
.sb-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--amber),var(--teal));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;color:#fff;flex-shrink:0}
.sb-info b{display:block;font-size:15px;font-family:Lalezar}
.sb-info span{font-size:11px;color:var(--muted)}
.sb-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;font-size:14px;font-weight:600;color:var(--text);cursor:pointer;transition:all .2s;border:1px solid transparent;text-decoration:none;position:relative}
.sb-item:hover{background:var(--inbg);border-color:var(--line)}
.sb-item.active{background:rgba(20,184,166,.1);border-color:rgba(20,184,166,.3);color:var(--teal)}
.sb-item .sb-ic{font-size:19px;width:26px;text-align:center}
.sb-item.danger{color:var(--red)}
.sb-item.danger:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.3)}
.sb-sep{height:1px;background:var(--line);margin:8px 0}
.sb-badge{margin-right:auto;background:var(--amber);color:#4a2506;font-size:10px;font-weight:900;padding:2px 8px;border-radius:99px}
.stats{display:flex;background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:22px;flex-wrap:wrap;box-shadow:0 4px 12px var(--shadowc)}
.stat{flex:1;min-width:140px;padding:14px 22px;border-inline-start:1px solid var(--line);display:flex;flex-direction:column;gap:2px}
.stat:first-child{border:0}
.stat b{font-family:Lalezar;font-size:23px;font-weight:400}
.stat span{font-size:12px;color:var(--muted)}
.work{display:grid;grid-template-columns:390px 1fr;gap:22px;align-items:start}
.aside{position:sticky;top:18px;display:flex;flex-direction:column;gap:20px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 4px 12px var(--shadowc)}
.panel h2{font-family:Lalezar;font-size:21px;font-weight:400}
.mk{display:inline-block;width:9px;height:21px;border-radius:3px;margin-inline-end:9px;vertical-align:-2px;background:linear-gradient(180deg,var(--teal),var(--teal2))}
.mk-a{background:linear-gradient(180deg,var(--amber),var(--amber2))}
.mk-r{background:linear-gradient(180deg,#f87171,var(--red))}
.mk-g{background:linear-gradient(180deg,#4ade80,var(--green))}
.mk-p{background:linear-gradient(180deg,#c4b5fd,var(--purple))}
.sub{font-size:12px;color:var(--muted);margin:4px 0 6px}
.lbl{display:block;font-size:12.5px;font-weight:700;color:var(--muted);margin:14px 0 6px}
.inp,textarea,select{width:100%;background:var(--inbg);border:1px solid var(--line);border-radius:9px;padding:11px 12px;color:var(--text);font-family:Vazirmatn;font-size:14px;outline:0;transition:border-color .2s,box-shadow .2s}
.inp:focus,textarea:focus,select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(20,184,166,.15)}
textarea{min-height:130px;resize:vertical;line-height:1.8}
select.inp{appearance:none;cursor:pointer}
input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none}
input[type=url]{direction:ltr;text-align:left}
.mini{display:flex;gap:8px;margin-top:8px}
.dur{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.dur-box{background:var(--inbg);border:1px solid var(--line);border-radius:10px;padding:10px 8px 8px;text-align:center}
.dur-box:focus-within{border-color:var(--amber);box-shadow:0 0 0 3px rgba(245,158,11,.12)}
.dur-box input{width:100%;background:none;border:0;outline:0;color:var(--amber);font-family:Lalezar;font-size:27px;text-align:center;line-height:1.1}
.dur-box span{font-size:11px;color:var(--muted)}
.btn{border:0;border-radius:8px;padding:10px 18px;font-family:Vazirmatn;font-weight:700;font-size:14px;cursor:pointer;transition:transform .15s,box-shadow .2s;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.btn:active{transform:scale(.95)}
.btn.teal{background:linear-gradient(180deg,var(--teal),var(--teal2));color:#fff;box-shadow:0 4px 14px rgba(20,184,166,.25)}
.btn.teal:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(20,184,166,.35)}
.btn.purple{background:linear-gradient(180deg,var(--purple),#9333ea);color:#fff;box-shadow:0 4px 14px rgba(168,85,247,.25)}
.btn.purple:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(168,85,247,.35)}
.btn.agent-btn{background:linear-gradient(180deg,#60a5fa,#2563eb);color:#fff;box-shadow:0 4px 14px rgba(59,130,246,.25)}
.btn.agent-btn:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(59,130,246,.35)}
.btn.ghost{background:transparent;border:1px solid var(--line2);color:var(--muted)}
.btn.ghost:hover{color:var(--text);border-color:var(--teal);transform:translateY(-2px)}
.btn.red{background:transparent;border:1px solid rgba(239,68,68,.45);color:var(--red)}
.btn.red:hover{background:rgba(239,68,68,.12)}
.btn.green{background:transparent;border:1px solid rgba(34,197,94,.45);color:var(--green)}
.btn.green:hover{background:rgba(34,197,94,.12)}
.btn.sm{padding:7px 12px;font-size:12.5px;border-radius:7px}
.btn.wide{width:100%;padding:13px;margin-top:18px;font-size:15px}
.btn:disabled{opacity:.55;cursor:wait;transform:none}
.sec-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.sec-head h2{font-family:Lalezar;font-size:22px;font-weight:400}
.hint{font-size:11.5px;color:var(--amber);background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);padding:4px 11px;border-radius:99px}
.tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.tab-btn{padding:8px 16px;border-radius:99px;border:1px solid var(--line);background:var(--panel);color:var(--muted);font-family:Vazirmatn;font-weight:700;font-size:13px;cursor:pointer;transition:all .25s}
.tab-btn.active{background:var(--amber);color:#4a2506;border-color:var(--amber);box-shadow:0 4px 12px rgba(245,158,11,.3)}
.tab-btn.active.purple-tab{background:var(--purple);color:#fff;border-color:var(--purple);box-shadow:0 4px 12px rgba(168,85,247,.3)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.card{background:linear-gradient(180deg,var(--panel2),var(--panel));border:1px solid var(--line);border-inline-start:4px solid var(--teal);border-radius:12px;padding:16px;transition:transform .25s,border-color .25s,box-shadow .25s,opacity .3s}
.card:hover{transform:translateY(-4px);border-color:var(--line2);box-shadow:0 14px 34px var(--shadowc)}
.card.mid{border-inline-start-color:var(--amber)}
.card.low{border-inline-start-color:var(--red)}
.card.test-card{border-inline-start-color:var(--purple)}
.card.expired-card{opacity:.55;filter:grayscale(.45);border-inline-start-color:var(--line2)}
.card.expired-card:hover{opacity:.8}
.card.frozen-card{opacity:.6;filter:grayscale(.6);border-inline-start-color:var(--agent)}
.card-top{display:flex;justify-content:space-between;align-items:center;gap:10px}
.fname{font-family:Lalezar;font-size:18px;font-weight:400;word-break:break-all;line-height:1.3}
.dot{width:10px;height:10px;min-width:10px;border-radius:50%;background:var(--teal);animation:pulse 2s infinite}
.card.mid .dot{background:var(--amber)}
.card.low .dot{background:var(--red);animation-duration:1s}
.card.test-card .dot{background:var(--purple)}
.card.expired-card .dot{background:var(--line2);animation:none}
.card.frozen-card .dot{background:var(--agent);animation:none}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(20,184,166,.45)}70%{box-shadow:0 0 0 9px rgba(20,184,166,0)}100%{box-shadow:0 0 0 0 rgba(20,184,166,0)}}
.fmeta{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.chip{font-size:11px;background:var(--inbg);border:1px solid var(--line);color:var(--muted);padding:3px 10px;border-radius:99px}
.chip.test-chip{background:rgba(168,85,247,.12);border-color:rgba(168,85,247,.3);color:var(--purple)}
.chip.exp-chip{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:var(--red)}
.chip.frozen-chip{background:rgba(59,130,246,.12);border-color:rgba(59,130,246,.3);color:var(--agent)}
.chip.owner-chip{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.3);color:var(--amber)}
.url-box{margin-top:10px;background:var(--inbg);border:1px solid var(--line);border-radius:8px;padding:8px 10px;display:flex;align-items:center;gap:8px}
.url-box input{flex:1;background:none;border:0;outline:0;color:var(--teal);font-size:12px;direction:ltr;text-align:left;font-family:monospace}
.url-box button{background:none;border:0;cursor:pointer;font-size:16px;transition:transform .2s;color:var(--muted);display:flex;align-items:center}
.url-box button:hover{transform:scale(1.2);color:var(--text)}
.url-box a{font-size:16px;text-decoration:none;transition:transform .2s;display:flex}
.url-box a:hover{transform:scale(1.2)}
.cd-wrap{margin-top:12px}
.cd-label{font-size:11px;color:var(--muted);display:block;margin-bottom:2px}
.cd{font-family:Lalezar;font-size:27px;color:var(--teal);letter-spacing:.5px;min-height:34px}
.card.mid .cd{color:var(--amber)}
.card.low .cd{color:var(--red);animation:blink 1s steps(2) infinite}
.card.test-card .cd{color:var(--purple)}
.card.expired-card .cd{color:var(--red);font-size:20px;animation:none}
.card.frozen-card .cd{color:var(--agent);font-size:20px;animation:none}
@keyframes blink{50%{opacity:.55}}
.n{unicode-bidi:isolate;direction:ltr;display:inline-block}
.ltr{unicode-bidi:isolate;direction:ltr;display:inline-block}
.bar{height:6px;background:var(--barbg);border-radius:99px;overflow:hidden;margin:8px 0}
.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--teal2),var(--teal));border-radius:99px;transition:width 1s linear}
.card.mid .bar i{background:linear-gradient(90deg,var(--amber2),var(--amber))}
.card.low .bar i{background:linear-gradient(90deg,#dc2626,var(--red))}
.card.test-card .bar i{background:linear-gradient(90deg,#9333ea,var(--purple))}
.dates{display:flex;flex-direction:column;gap:3px;font-size:11.5px;color:var(--muted)}
.acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.frow{display:flex;align-items:center;gap:8px;padding:9px 10px;border:1px solid var(--line);border-radius:9px;margin-top:8px;background:var(--inbg);transition:border-color .2s,transform .2s}
.frow:hover{border-color:var(--line2);transform:translateX(-3px)}
.fname-f{flex:1;font-size:13px;word-break:break-all}
.nofolder{font-size:12.5px;color:var(--muted);margin-top:12px;text-align:center}
.empty{border:2px dashed var(--line2);border-radius:14px;padding:50px 20px;text-align:center;color:var(--muted)}
.empty-ic{font-size:44px;display:inline-block;animation:bob 3s ease-in-out infinite}
.empty h3{font-family:Lalezar;font-size:20px;font-weight:400;color:var(--text);margin:10px 0 4px}
.overlay{position:fixed;inset:0;background:var(--ovl);display:flex;align-items:center;justify-content:center;padding:18px;opacity:0;pointer-events:none;transition:opacity .25s;z-index:200}
.overlay.on{opacity:1;pointer-events:auto}
.modal{background:var(--panel);border:1px solid var(--line2);border-radius:14px;width:min(620px,100%);max-height:90vh;overflow:auto;padding:22px;transform:translateY(18px) scale(.97);transition:transform .25s;box-shadow:0 20px 40px var(--shadowc)}
.overlay.on .modal{transform:none}
.m-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.m-head h3{font-family:Lalezar;font-size:20px;font-weight:400}
.m-head h3 span{color:var(--amber)}
.x{background:none;border:1px solid var(--line2);color:var(--muted);width:32px;height:32px;border-radius:8px;font-size:18px;cursor:pointer;transition:.25s}
.x:hover{color:var(--red);border-color:var(--red);transform:rotate(90deg)}
.m-foot{display:flex;gap:10px;margin-top:18px}
#toasts{position:fixed;bottom:18px;inset-inline-start:18px;z-index:300;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--panel2);border:1px solid var(--line2);border-inline-start:4px solid var(--teal);border-radius:10px;padding:12px 16px;font-size:13.5px;box-shadow:0 10px 26px var(--shadowc);animation:tin .3s cubic-bezier(.2,.9,.3,1.2)}
.toast.err{border-inline-start-color:var(--red)}
@keyframes tin{from{transform:translateY(14px);opacity:0}}
.reveal{opacity:0}
.reveal.in{animation:rise .55s cubic-bezier(.2,.7,.3,1) forwards}
@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
footer{margin-top:26px;text-align:center;font-size:11.5px;color:var(--muted)}
.utbl{width:100%;border-collapse:collapse;font-size:13px}
.utbl th{text-align:right;padding:10px 12px;border-bottom:2px solid var(--line2);color:var(--muted);font-size:12px;font-weight:700}
.utbl td{padding:10px 12px;border-bottom:1px solid var(--line);vertical-align:middle}
.utbl tr:hover td{background:var(--inbg)}
.role-badge{font-size:11px;padding:3px 10px;border-radius:99px;font-weight:700}
.role-badge.admin{background:rgba(245,158,11,.15);color:var(--amber);border:1px solid rgba(245,158,11,.3)}
.role-badge.user{background:rgba(20,184,166,.12);color:var(--teal);border:1px solid rgba(20,184,166,.3)}
.role-badge.agent{background:rgba(59,130,246,.12);color:var(--agent);border:1px solid rgba(59,130,246,.3)}
.st-badge{font-size:11px;padding:3px 10px;border-radius:99px;font-weight:700}
.st-badge.on{background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.3)}
.st-badge.off{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.3)}
.perm-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.perm-item{display:flex;align-items:center;gap:8px;font-size:13px;padding:8px 10px;background:var(--inbg);border:1px solid var(--line);border-radius:8px;cursor:pointer;transition:all .2s}
.perm-item:hover{border-color:var(--teal)}
.perm-item.checked{background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.5);color:var(--green)}
.perm-item input{accent-color:var(--green);width:16px;height:16px}
.quota-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)}
.quota-row:last-child{border-bottom:0}
.quota-ic{font-size:22px;width:34px;text-align:center;flex-shrink:0}
.quota-body{flex:1}
.quota-title{font-size:13px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.quota-title .qv{font-family:Lalezar;font-size:17px;color:var(--amber)}
.quota-bar{height:7px;background:var(--barbg);border-radius:99px;overflow:hidden;margin-top:7px}
.quota-bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--teal2),var(--teal));transition:width .8s cubic-bezier(.2,.7,.3,1)}
.quota-bar i.warn{background:linear-gradient(90deg,var(--amber2),var(--amber))}
.quota-bar i.full{background:linear-gradient(90deg,#dc2626,var(--red))}
.quota-bar i.off{background:var(--line2)}
.quota-note{font-size:10.5px;color:var(--muted);margin-top:5px}
.badge-off{font-size:10px;background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.3);padding:2px 8px;border-radius:99px;font-weight:700}
.badge-inf{font-size:10px;background:rgba(34,197,94,.12);color:var(--green);border:1px solid rgba(34,197,94,.3);padding:2px 8px;border-radius:99px;font-weight:700}
.donut-wrap{display:flex;align-items:center;gap:18px;margin-top:14px}
.donut-box{position:relative;width:132px;height:132px;flex-shrink:0}
.donut{width:100%;height:100%}
.donut-bg{fill:none;stroke:var(--barbg);stroke-width:14}
.donut-seg{fill:none;stroke-width:14;transition:stroke-dasharray 1.2s cubic-bezier(.2,.7,.3,1)}
.donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none}
.donut-center b{font-family:Lalezar;font-size:30px;color:var(--amber);line-height:1}
.donut-center span{font-size:10.5px;color:var(--muted);margin-top:2px}
.donut-legend{flex:1;display:flex;flex-direction:column;gap:9px}
.lg-item{display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--muted)}
.lg-item i{width:11px;height:11px;border-radius:3px;flex-shrink:0}
.lg-item b{margin-right:auto;font-family:Lalezar;font-size:17px;color:var(--text)}
.test-toggle{display:flex;align-items:center;gap:12px;margin-top:16px;padding:12px 14px;background:rgba(168,85,247,.07);border:1px solid rgba(168,85,247,.28);border-radius:12px;cursor:pointer;transition:all .25s;user-select:none}
.test-toggle:hover{border-color:var(--purple);box-shadow:0 4px 14px rgba(168,85,247,.15)}
.test-toggle input{display:none}
.tgl-slider{width:44px;height:24px;border-radius:99px;background:var(--line2);position:relative;transition:background .25s;flex-shrink:0}
.tgl-slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;right:3px;transition:all .25s cubic-bezier(.4,0,.2,1)}
.test-toggle input:checked + .tgl-slider{background:var(--purple)}
.test-toggle input:checked + .tgl-slider:before{right:23px}
.tgl-txt{font-size:13px;font-weight:700;color:var(--purple)}
.tgl-txt small{display:block;font-size:10.5px;color:var(--muted);font-weight:400;margin-top:2px}
.switch-row{display:flex;align-items:center;gap:12px;cursor:pointer;user-select:none}
.switch-row input{display:none}
.switch{width:44px;height:24px;border-radius:99px;background:var(--line2);position:relative;transition:background .25s;flex-shrink:0;display:inline-block}
.switch:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:3px;right:3px;transition:all .25s cubic-bezier(.4,0,.2,1)}
.switch-row input:checked + .switch{background:var(--teal)}
.switch-row input:checked + .switch:before{right:23px}
.qr-img{display:flex;justify-content:center;padding:20px 0 10px}
.qr-img img{width:230px;height:230px;border-radius:14px;border:5px solid #fff;background:#fff;box-shadow:0 10px 34px var(--shadowc);transition:transform .3s}
.qr-img img:hover{transform:scale(1.03)}
.qr-modal{width:min(380px,100%);text-align:center}
.qr-name{font-family:Lalezar;font-size:22px;color:var(--teal);margin-top:4px}
.dynamic-island{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:rgba(10,15,28,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--line2);border-radius:99px;width:200px;height:48px;display:flex;align-items:center;justify-content:center;z-index:1000;transition:all .45s cubic-bezier(.175,.885,.32,1.275);overflow:hidden;cursor:pointer;box-shadow:0 8px 32px rgba(0,0,0,.4)}
html[data-theme="light"] .dynamic-island{background:rgba(240,249,255,.92)}
.dynamic-island.expanded{width:min(320px,92vw);height:auto;min-height:200px;border-radius:28px;cursor:default}
.island-compact{position:absolute;width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:10px;transition:opacity .2s;padding:0 16px}
.island-compact .ic-time{font-family:Lalezar;font-size:19px;color:var(--amber);direction:ltr}
.island-compact .ic-date{font-size:10px;color:var(--muted)}
.island-compact .ic-dot{width:8px;height:8px;border-radius:50%;background:var(--teal);animation:pulse 2s infinite;flex-shrink:0}
.dynamic-island.expanded .island-compact{opacity:0;pointer-events:none}
.island-expanded{display:flex;flex-direction:column;gap:8px;width:100%;padding:18px 16px 16px;opacity:0;transform:translateY(12px);transition:all .35s ease .12s}
.dynamic-island.expanded .island-expanded{opacity:1;transform:translateY(0)}
.island-clock{text-align:center;margin-bottom:6px}
.island-clock .ik-time{font-family:Lalezar;font-size:36px;color:var(--amber);direction:ltr;line-height:1.1}
.island-clock .ik-day{font-size:13px;color:var(--teal);font-weight:700}
.island-clock .ik-date{font-size:12px;color:var(--muted);margin-top:2px}
.island-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.island-btn{background:var(--inbg);border:1px solid var(--line);color:var(--text);padding:12px 8px;border-radius:14px;font-family:Vazirmatn;font-size:12px;font-weight:600;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;transition:all .2s}
.island-btn:hover{background:var(--line);transform:scale(1.03)}
.island-btn:active{transform:scale(.96)}
.island-btn .ib-ic{font-size:20px}
.island-btn.full{grid-column:1/-1}
.island-btn.danger{border-color:rgba(239,68,68,.3);color:var(--red)}
.page{display:none}
.page.active{display:block}
.agent-banner{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;font-size:13px}
.agent-banner.frozen{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3)}
.agent-banner .ab-ic{font-size:28px}
.agent-banner b{display:block;font-family:Lalezar;font-size:15px;margin-bottom:2px}
.agent-timer{font-family:Lalezar;font-size:18px;color:var(--agent);direction:ltr;display:inline-block;margin-top:4px;letter-spacing:1px}
.agent-banner.frozen .agent-timer{color:var(--red)}
.logo-preview{width:60px;height:60px;border:2px solid var(--amber);overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--inbg);margin-top:10px}
.logo-preview img{width:100%;height:100%;object-fit:cover}
.pw-cell{display:flex;align-items:center;gap:6px;font-size:12px;font-family:monospace;direction:ltr}
.pw-cell .pw-val{background:var(--inbg);padding:3px 8px;border-radius:5px;border:1px solid var(--line);min-width:80px;text-align:center;color:var(--amber);font-weight:700}
.pw-cell .pw-btn{background:none;border:1px solid var(--line2);padding:3px 7px;border-radius:5px;cursor:pointer;font-size:11px;color:var(--muted);transition:.2s}
.pw-cell .pw-btn:hover{border-color:var(--teal);color:var(--teal)}
.pw-cell .pw-na{color:var(--muted);font-style:italic;font-family:Vazirmatn;font-size:11px}
@media(max-width:768px){
.work{grid-template-columns:1fr}
.aside{position:static}
.h-left .clock{display:none}
.h-left .theme-btn{display:none}
}
@media(min-width:769px){
.dynamic-island{display:none}
}
@media(max-width:640px){
h1{font-size:27px}
.cd{font-size:23px}
.stat b{font-size:19px}
.wrap{padding:14px;padding-top:74px}
.acts .btn{flex:1}
.perm-grid{grid-template-columns:1fr}
.utbl{font-size:11.5px}
.utbl th,.utbl td{padding:8px 6px}
.donut-wrap{flex-direction:column}
}
</style>
</head>
<body>
<div class="galaxy-container">
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div><div class="star"></div>
<div class="meteor"></div><div class="meteor"></div><div class="meteor"></div>
<div class="meteor"></div><div class="meteor"></div><div class="meteor"></div>
</div>
<div class="wrap">
<header>
<div class="brand">
<button class="hamburger" id="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
<div class="logo" style="border-radius:<?=$logo_shape?>"><img src="<?=$logo_url?>" alt="لوگو"></div>
<div>
<h1><?=$site_name?></h1>
<p>خوش آمدید، <?=htmlspecialchars($cu['username'])?> 👋</p>
</div>
</div>
<div class="h-left">
<div class="clock">
<span id="clockTime">--:--:--</span>
<span id="clockDate"></span>
</div>
<button class="theme-btn" id="themeBtn" onclick="cycleTheme()" title="تغییر پوسته"></button>
</div>
</header>
<?php if($cu['role'] === 'agent'): ?>
<?php if($agent_frozen): ?>
<div class="agent-banner frozen">
<span class="ab-ic">🧊</span>
<div>
<b>حساب نماینده شما منقضی شده است</b>
<div class="agent-timer" id="agentTimerDisplay">منقضی شده</div>
<div style="margin-top:4px;font-size:12px">تمام اتصالات شما فریز شده‌اند. لطفاً برای تمدید با مدیر تماس بگیرید.</div>
</div>
</div>
<?php else: ?>
<div class="agent-banner">
<span class="ab-ic">🎫</span>
<div>
<b>حساب نماینده فعال</b>
<div class="agent-timer" id="agentTimerDisplay" data-seconds="<?=$agent_seconds_left?>">--:--:--:--</div>
</div>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="page active" id="page-files">
<div class="stats">
<div class="stat"><b id="sCount"><?=fa_num($active_count)?></b><span>اتصال فعال</span></div>
<div class="stat"><b id="nextExp">—</b><span>نزدیک‌ترین انقضا</span></div>
</div>
<div class="work">
<aside class="aside">
<div class="panel" id="donutPanel">
<h2><i class="mk mk-a"></i>نمودار مصرف شما</h2>
<div class="donut-wrap">
<div class="donut-box">
<svg viewBox="0 0 140 140" class="donut">
<circle cx="70" cy="70" r="54" class="donut-bg"/>
<g transform="rotate(-90 70 70)">
<circle cx="70" cy="70" r="54" class="donut-seg" data-len="<?=$len_n?>" style="stroke:var(--teal);stroke-dasharray:0 <?=$C?>;stroke-dashoffset:<?=$off_n?>"/>
<circle cx="70" cy="70" r="54" class="donut-seg" data-len="<?=$len_r?>" style="stroke:var(--amber);stroke-dasharray:0 <?=$C?>;stroke-dashoffset:<?=$off_r?>"/>
<circle cx="70" cy="70" r="54" class="donut-seg" data-len="<?=$len_t?>" style="stroke:var(--purple);stroke-dasharray:0 <?=$C?>;stroke-dashoffset:<?=$off_t?>"/>
<circle cx="70" cy="70" r="54" class="donut-seg" data-len="<?=$len_e?>" style="stroke:var(--line2);stroke-dasharray:0 <?=$C?>;stroke-dashoffset:<?=$off_e?>"/>
</g>
</svg>
<div class="donut-center"><b><?=fa_num($my_total)?></b><span>اتصال</span></div>
</div>
<div class="donut-legend">
<div class="lg-item"><i style="background:var(--teal)"></i>عادی <b><?=fa_num($my_normal)?></b></div>
<div class="lg-item"><i style="background:var(--amber)"></i>ریدایرکت <b><?=fa_num($my_redirect)?></b></div>
<?php if($allow_test): ?>
<div class="lg-item"><i style="background:var(--purple)"></i>تستی <b><?=fa_num($my_test)?></b></div>
<?php endif; ?>
<div class="lg-item"><i style="background:var(--line2)"></i>منقضی <b><?=fa_num($my_expired)?></b></div>
</div>
</div>
</div>
<?php if(!is_admin()): ?>
<div class="panel" id="quotaPanel">
<h2><i class="mk mk-a"></i>سهمیه شما</h2>
<div class="quota-row">
<span class="quota-ic">🔗</span>
<div class="quota-body">
<div class="quota-title">
<span>اتصال معمولی</span>
<?php if($q_normal['disabled']): ?><span class="badge-off">غیرفعال</span>
<?php elseif($q_normal['max']===null): ?><span class="badge-inf">نامحدود</span>
<?php else: ?><span class="qv"><?=fa_num($q_normal['used'])?> / <?=fa_num($q_normal['max'])?></span><?php endif; ?>
</div>
<?php if(!$q_normal['disabled'] && $q_normal['max']!==null):
$qp=min(100,(int)($q_normal['max']?($q_normal['used']/$q_normal['max']*100):0));
$qcls=$qp>=100?'full':($qp>=60?'warn':''); ?>
<div class="quota-bar"><i class="<?=$qcls?>" style="width:<?=$qp?>%"></i></div>
<div class="quota-note"><?=fa_num(max(0,$q_normal['max']-$q_normal['used']))?> جای خالی باقی‌مانده</div>
<?php elseif($q_normal['disabled']): ?>
<div class="quota-bar"><i class="off" style="width:100%"></i></div>
<?php endif; ?>
</div>
</div>
<?php if($allow_test): ?>
<div class="quota-row">
<span class="quota-ic">🧪</span>
<div class="quota-body">
<div class="quota-title">
<span>اتصال تستی (۴ ساعته)</span>
<?php if($q_test['disabled']): ?><span class="badge-off">غیرفعال</span>
<?php elseif($q_test['max']===null): ?><span class="badge-inf">نامحدود</span>
<?php else: ?><span class="qv"><?=fa_num($q_test['used'])?> / <?=fa_num($q_test['max'])?></span><?php endif; ?>
</div>
<?php if(!$q_test['disabled'] && $q_test['max']!==null):
$qp=min(100,(int)($q_test['max']?($q_test['used']/$q_test['max']*100):0));
$qcls=$qp>=100?'full':($qp>=60?'warn':''); ?>
<div class="quota-bar"><i class="<?=$qcls?>" style="width:<?=$qp?>%"></i></div>
<div class="quota-note"><?=fa_num(max(0,$q_test['max']-$q_test['used']))?> جای خالی باقی‌مانده</div>
<?php elseif($q_test['disabled']): ?>
<div class="quota-bar"><i class="off" style="width:100%"></i></div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php if(!$all_folders_ok): ?>
<div class="quota-note" style="margin-top:10px">📁 پوشه‌های مجاز شما:
<?php foreach($my_allowed as $af): ?><span class="chip" style="display:inline-block;margin:2px"><?=htmlspecialchars($af===''?'ریشه':$af)?></span><?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if($can_normal_form || $can_test_form): ?>
<div class="panel" id="createFormPanel">
<h2><i class="mk"></i>ساخت اتصال جدید</h2>
<p class="sub">مسیر و نام را مشخص کنید</p>
<form id="createForm">
<input type="hidden" name="csrf" value="<?=$csrf?>">
<label class="lbl">پوشه مقصد</label>
<select name="folder" id="folderSel" class="inp">
<?php if($can_root): ?><option value="">📁 ریشه</option><?php endif; ?>
<?php foreach($available_dirs as $d): ?><option value="<?=htmlspecialchars($d)?>">📁 <?=htmlspecialchars($d)?></option><?php endforeach; ?>
</select>
<label class="lbl">نام اتصال</label>
<div class="mini" style="margin-top:0">
<input type="text" name="filename" id="cFilename" class="inp" placeholder="مثلاً my-link" required style="flex:1">
<button type="button" class="btn sm purple" onclick="randomFileName()" title="تولید نام رندوم">🎲</button>
</div>
<?php if($can_test_form): ?>
<label class="test-toggle">
<input type="checkbox" name="is_test" id="isTestChk" value="1" onchange="toggleTestMode()" <?=$can_normal_form?'':'checked disabled'?>>
<span class="tgl-slider"></span>
<span class="tgl-txt">🧪 اتصال تستی <small>اعتبار ثابت ۴ ساعت — بدون محتوا</small></span>
</label>
<?php endif; ?>
<?php if($can_normal_form): ?>
<div id="normalFields">
<?php if(!$hide_redirect_info): ?>
<label class="lbl">لینک ریدایرکت (اختیاری)</label>
<input type="url" name="redirect_url" id="cRedirect" class="inp" placeholder="https://example.com">
<label class="lbl">محتوای اتصال</label>
<textarea name="content" id="cContent" class="inp"></textarea>
<div class="mini">
<button type="button" class="btn sm ghost" onclick="cCopy()">📋 کپی</button>
<button type="button" class="btn sm ghost" onclick="cPaste()">📥 چسباندن</button>
<button type="button" class="btn sm red" onclick="cClear()">🗑 حذف محتویات</button>
</div>
<?php endif; ?>
<label class="lbl">مدت اعتبار</label>
<div class="dur">
<div class="dur-box"><input type="number" name="m" min="0" value="1"><span>ماه</span></div>
<div class="dur-box"><input type="number" name="d" min="0" value="0"><span>روز</span></div>
<div class="dur-box"><input type="number" name="h" min="0" value="0"><span>ساعت</span></div>
</div>
</div>
<?php endif; ?>
<button type="submit" class="btn <?=$can_normal_form?'teal':'purple'?> wide" id="submitBtn"><?=$can_normal_form?'＋ ساخت اتصال':'🧪 ساخت اتصال تستی (۴ ساعته)'?></button>
</form>
</div>
<?php endif; ?>
</aside>
<section>
<div class="sec-head">
<h2><i class="mk mk-a"></i>مدیریت اتصالات</h2>
<span class="hint">⏳ منقضی‌ها در لیست می‌مانند</span>
</div>
<?php if($hide_redirect_info): ?>
<div class="tabs">
<button class="tab-btn active" data-tab="redirect" onclick="showTab('redirect')">🔗 اتصالات من (<?=fa_num(count($redirect_files))?>)</button>
<?php if($allow_test): ?>
<button class="tab-btn purple-tab" data-tab="test" onclick="showTab('test')">🧪 تستی (<?=fa_num(count($test_files))?>)</button>
<?php endif; ?>
</div>
<div id="tab-text" class="grid tab-content" style="display:none"></div>
<div id="tab-redirect" class="grid tab-content">
<?php else: ?>
<div class="tabs">
<button class="tab-btn active" data-tab="text" onclick="showTab('text')">🔗 لینک اتصال (<?=fa_num(count($text_files))?>)</button>
<button class="tab-btn" data-tab="redirect" onclick="showTab('redirect')">🔗 ریدایرکت (<?=fa_num(count($redirect_files))?>)</button>
<?php if($allow_test): ?>
<button class="tab-btn purple-tab" data-tab="test" onclick="showTab('test')">🧪 تستی (<?=fa_num(count($test_files))?>)</button>
<?php endif; ?>
</div>
<div id="tab-text" class="grid tab-content">
<?php endif; ?>
<?php if(!$hide_redirect_info): ?>
<?php foreach($text_files as $i=>$f):
$is_exp=$f['expires']<$now;$rem=max(0,$f['expires']-$now);$total=max(1,$f['expires']-$f['created']);$pct=min(100,$rem/$total*100);
$cls=$is_exp?'':($rem<3600?'low':($pct<50?'mid':''));$size=(!$is_exp && file_exists($f['path']))?filesize($f['path']):0;$furl=file_url($f);
$can_modify=is_admin()||($f['owner']??'')===$cu['username'];$show_del=$can_modify&&(is_admin()||($allow_self_delete&&has_perm('delete_file')));
$is_frozen = ($cu['role']==='agent' && $agent_frozen);
?>
<article class="card reveal <?=$cls?><?=$is_exp?' expired-card':''?><?=$is_frozen?' frozen-card':''?>" data-id="<?=$f['id']?>" data-folder="<?=htmlspecialchars($f['folder'])?>" data-expires="<?=$f['expires']?>" data-created="<?=$f['created']?>" data-total="<?=$total?>" data-url="<?=htmlspecialchars($furl)?>" <?=$is_exp?'data-expired="1"':''?> style="animation-delay:<?=$i*70?>ms">
<div class="card-top"><h3 class="fname"><?=htmlspecialchars($f['filename'])?></h3><span class="dot"></span></div>
<div class="fmeta"><span class="chip">📁 <?=htmlspecialchars($f['folder']===''?'ریشه':$f['folder'])?></span><?php if($is_exp): ?><span class="chip exp-chip">⏳ منقضی شده</span><?php elseif($is_frozen): ?><span class="chip frozen-chip">🧊 فریز شده</span><?php else: ?><span class="chip"><?=fa_num($size)?> بایت</span><?php endif; ?><?php if($IS_ADMIN && !empty($f['owner'])): ?><span class="chip owner-chip">👤 <?=htmlspecialchars($f['owner'])?></span><?php endif; ?></div>
<div class="url-box"><input type="text" value="<?=htmlspecialchars($furl)?>" readonly onclick="this.select()"><button onclick="copyUrl('<?=$f['id']?>')">📋</button><button onclick="openQr('<?=$f['id']?>')"><?=$qr_svg?></button><a href="<?=htmlspecialchars($furl)?>" target="_blank">🔗</a></div>
<div class="cd-wrap"><span class="cd-label">زمان باقی‌مانده</span><div class="cd"><?=$is_exp?'⏳ منقضی شده':($is_frozen?'🧊 فریز شده':'')?></div></div>
<div class="bar"><i style="width:<?=$is_exp?0:$pct?>%"></i></div>
<div class="dates"><span>ساخت: <span class="ltr"><?=fa_date($f['created'])?></span></span><span>پایان: <span class="ltr"><?=fa_date($f['expires'])?></span></span></div>
<div class="acts"><?php if(has_perm('edit_file')&&$can_modify&&!$agent_frozen): ?><button class="btn sm ghost" onclick="openEdit('<?=$f['id']?>')">✏️ ویرایش</button><?php endif; ?><button class="btn sm ghost" onclick="copyUrl('<?=$f['id']?>')">📋 کپی لینک</button><button class="btn sm ghost" onclick="openQr('<?=$f['id']?>')">▦ بارکد</button><?php if($show_del&&!$agent_frozen): ?><button class="btn sm red" onclick="delFile('<?=$f['id']?>')">🗑 حذف</button><?php endif; ?></div>
</article>
<?php endforeach; ?>
<div class="empty" id="emptyText" style="<?=!empty($text_files)?'display:none':''?>"><div class="empty-ic">🔗</div><h3>لینک اتصال</h3></div>
</div>
<?php endif; ?>
<?php if(!$hide_redirect_info): ?><div id="tab-redirect" class="grid tab-content" style="display:none"><?php endif; ?>
<?php foreach($redirect_files as $i=>$f):
$is_exp=$f['expires']<$now;$rem=max(0,$f['expires']-$now);$total=max(1,$f['expires']-$f['created']);$pct=min(100,$rem/$total*100);
$cls=$is_exp?'':($rem<3600?'low':($pct<50?'mid':''));$furl=file_url($f);$rurl=$f['redirect_url']??'';
$can_modify=is_admin()||($f['owner']??'')===$cu['username'];$show_del=$can_modify&&(is_admin()||($allow_self_delete&&has_perm('delete_file')));
$is_frozen = ($cu['role']==='agent' && $agent_frozen);
?>
<article class="card reveal <?=$cls?><?=$is_exp?' expired-card':''?><?=$is_frozen?' frozen-card':''?>" data-id="<?=$f['id']?>" data-folder="<?=htmlspecialchars($f['folder'])?>" data-expires="<?=$f['expires']?>" data-created="<?=$f['created']?>" data-total="<?=$total?>" data-url="<?=htmlspecialchars($furl)?>" <?=$is_exp?'data-expired="1"':''?> style="animation-delay:<?=$i*70?>ms">
<div class="card-top"><h3 class="fname"><?=htmlspecialchars($f['filename'])?></h3><span class="dot"></span></div>
<div class="fmeta"><span class="chip">📁 <?=htmlspecialchars($f['folder']===''?'ریشه':$f['folder'])?></span><span class="chip">🔗 ریدایرکت</span><?php if($is_exp): ?><span class="chip exp-chip">⏳ منقضی شده</span><?php elseif($is_frozen): ?><span class="chip frozen-chip">🧊 فریز شده</span><?php endif; ?><?php if(!$hide_redirect_info&&$rurl): ?><span class="chip" style="direction:ltr;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($rurl)?></span><?php endif; ?><?php if($IS_ADMIN && !empty($f['owner'])): ?><span class="chip owner-chip">👤 <?=htmlspecialchars($f['owner'])?></span><?php endif; ?></div>
<div class="url-box"><input type="text" value="<?=htmlspecialchars($furl)?>" readonly onclick="this.select()"><button onclick="copyUrl('<?=$f['id']?>')">📋</button><button onclick="openQr('<?=$f['id']?>')"><?=$qr_svg?></button><a href="<?=htmlspecialchars($furl)?>" target="_blank">🔗</a></div>
<div class="cd-wrap"><span class="cd-label">زمان باقی‌مانده</span><div class="cd"><?=$is_exp?'⏳ منقضی شده':($is_frozen?'🧊 فریز شده':'')?></div></div>
<div class="bar"><i style="width:<?=$is_exp?0:$pct?>%"></i></div>
<div class="dates"><span>ساخت: <span class="ltr"><?=fa_date($f['created'])?></span></span><span>پایان: <span class="ltr"><?=fa_date($f['expires'])?></span></span></div>
<div class="acts"><?php if(has_perm('edit_file')&&$can_modify&&!$agent_frozen): ?><button class="btn sm ghost" onclick="openEdit('<?=$f['id']?>')">✏️ ویرایش</button><?php endif; ?><button class="btn sm ghost" onclick="copyUrl('<?=$f['id']?>')">📋 کپی لینک</button><button class="btn sm ghost" onclick="openQr('<?=$f['id']?>')">▦ بارکد</button><?php if($show_del&&!$agent_frozen): ?><button class="btn sm red" onclick="delFile('<?=$f['id']?>')">🗑 حذف</button><?php endif; ?></div>
</article>
<?php endforeach; ?>
<div class="empty" id="emptyRedirect" style="<?=!empty($redirect_files)?'display:none':''?>"><div class="empty-ic">🔗</div><h3>اتصالی وجود ندارد</h3></div>
</div>
<?php if($allow_test): ?>
<div id="tab-test" class="grid tab-content" style="display:none">
<?php foreach($test_files as $i=>$f):
$is_exp=$f['expires']<$now;$rem=max(0,$f['expires']-$now);$total=max(1,$f['expires']-$f['created']);$pct=min(100,$rem/$total*100);
$cls=$is_exp?'':($rem<3600?'low':'');$furl=file_url($f);
$can_modify=is_admin()||($f['owner']??'')===$cu['username'];$show_del=$can_modify&&(is_admin()||($allow_self_delete&&has_perm('delete_file')));
$is_frozen = ($cu['role']==='agent' && $agent_frozen);
?>
<article class="card reveal test-card <?=$cls?><?=$is_exp?' expired-card':''?><?=$is_frozen?' frozen-card':''?>" data-id="<?=$f['id']?>" data-folder="<?=htmlspecialchars($f['folder'])?>" data-expires="<?=$f['expires']?>" data-created="<?=$f['created']?>" data-total="<?=$total?>" data-url="<?=htmlspecialchars($furl)?>" <?=$is_exp?'data-expired="1"':''?> style="animation-delay:<?=$i*70?>ms">
<div class="card-top"><h3 class="fname"><?=htmlspecialchars($f['filename'])?></h3><span class="dot"></span></div>
<div class="fmeta"><span class="chip">📁 <?=htmlspecialchars($f['folder']===''?'ریشه':$f['folder'])?></span><span class="chip test-chip">🧪 تستی — ۴ ساعته</span><?php if($is_exp): ?><span class="chip exp-chip">⏳ منقضی شده</span><?php elseif($is_frozen): ?><span class="chip frozen-chip">🧊 فریز شده</span><?php endif; ?><?php if($IS_ADMIN && !empty($f['owner'])): ?><span class="chip owner-chip">👤 <?=htmlspecialchars($f['owner'])?></span><?php endif; ?></div>
<div class="url-box"><input type="text" value="<?=htmlspecialchars($furl)?>" readonly onclick="this.select()"><button onclick="copyUrl('<?=$f['id']?>')">📋</button><button onclick="openQr('<?=$f['id']?>')"><?=$qr_svg?></button><a href="<?=htmlspecialchars($furl)?>" target="_blank">🔗</a></div>
<div class="cd-wrap"><span class="cd-label">زمان باقی‌مانده</span><div class="cd"><?=$is_exp?'⏳ منقضی شده':($is_frozen?'🧊 فریز شده':'')?></div></div>
<div class="bar"><i style="width:<?=$is_exp?0:$pct?>%"></i></div>
<div class="dates"><span>ساخت: <span class="ltr"><?=fa_date($f['created'])?></span></span><span>پایان: <span class="ltr"><?=fa_date($f['expires'])?></span></span></div>
<div class="acts"><button class="btn sm ghost" onclick="copyUrl('<?=$f['id']?>')">📋 کپی لینک</button><button class="btn sm ghost" onclick="openQr('<?=$f['id']?>')">▦ بارکد</button><?php if($show_del&&!$agent_frozen): ?><button class="btn sm red" onclick="delFile('<?=$f['id']?>')">🗑 حذف</button><?php endif; ?></div>
</article>
<?php endforeach; ?>
<div class="empty" id="emptyTest" style="<?=!empty($test_files)?'display:none':''?>"><div class="empty-ic">🧪</div><h3>اتصال تستی وجود ندارد</h3></div>
</div>
<?php endif; ?>
</section>
</div>
</div>
<?php if(has_perm('manage_folders')): ?>
<div class="page" id="page-folders">
<div class="sec-head">
<h2><i class="mk mk-r"></i>مدیریت پوشه‌ها</h2>
<span class="hint">📁 حذف پوشه، اتصالاتش را هم حذف می‌کند</span>
</div>
<div class="panel" style="max-width:700px">
<h2><i class="mk mk-r"></i>پوشه‌ها</h2>
<p class="sub">حذف پوشه، اتصالاتش را هم حذف می‌کند</p>
<div class="mini" style="margin-bottom:12px">
<input type="text" id="newFolderName" class="inp" placeholder="نام پوشه جدید..." style="flex:1">
<button class="btn sm teal" onclick="createFolder()">ساخت</button>
</div>
<div id="folderList">
<?php if(empty($dirs)): ?><p class="nofolder" id="noFolder">هنوز پوشه‌ای نیست</p>
<?php else: ?><p class="nofolder" id="noFolder" style="display:none">هنوز پوشه‌ای نیست</p>
<?php foreach($dirs as $d): ?>
<div class="frow" data-folder="<?=htmlspecialchars($d)?>">
<span class="fname-f">📁 <?=htmlspecialchars($d)?></span>
<span class="chip"><?=fa_num($folder_counts[$d]??0)?> اتصال</span>
<button class="btn sm red" onclick="delFolder(this)">🗑</button>
</div>
<?php endforeach; endif; ?>
</div>
</div>
</div>
<?php endif; ?>
<?php if($IS_ADMIN): ?>
<div class="page" id="page-users">
<div class="sec-head"><h2><i class="mk mk-g"></i>مدیریت کاربران</h2><button class="btn teal sm" onclick="openAddUser()">＋ افزودن کاربر</button></div>
<div class="panel" style="overflow-x:auto">
<table class="utbl">
<thead><tr><th>کاربر</th><th>نقش</th><th>وضعیت</th><th>رمز عبور</th><th>دسترسی‌ها</th><th>پوشه‌ها</th><th>سهمیه</th><th>اعتبار</th><th>آخرین ورود</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach($users_list as $u): $uf=user_allowed_folders($u);
$agent_exp_text = '—';
if($u['role']==='agent' && !empty($u['agent_expires'])){
$exp = $u['agent_expires'];
if($exp < time()){
$days_over = floor((time()-$exp)/86400);
$agent_exp_text = '<span style="color:var(--red)">منقضی ('.fa_num($days_over).' روز پیش)</span>';
} else {
$days_left = ceil(($exp-time())/86400);
$agent_exp_text = '<span style="color:var(--green)">'.fa_num($days_left).' روز</span>';
}
}
$raw_pw = decode_pass($u['raw_password'] ?? '');
?>
<tr data-uid="<?=$u['id']?>" data-scope="<?=htmlspecialchars($u['view_scope']??'own')?>" data-perms="<?=htmlspecialchars(implode(',',$u['permissions'] ?? []))?>" data-folders="<?=htmlspecialchars(implode(',',array_map(function($x){return $x===''?'__ROOT__':$x;},$uf)))?>" data-maxnormal="<?=htmlspecialchars($u['max_normal']??'')?>" data-maxtest="<?=htmlspecialchars($u['max_test']??'')?>" data-agent-expires="<?=htmlspecialchars($u['agent_expires']??'0')?>" data-rawpass="<?=htmlspecialchars($raw_pw)?>">
<td><b><?=htmlspecialchars($u['username'])?></b></td>
<td><span class="role-badge <?=$u['role']?>"><?=$u['role']==='admin'?'👑 مدیر':($u['role']==='agent'?'🎫 نماینده':'👤 کاربر')?></span></td>
<td><span class="st-badge <?=$u['active']?'on':'off'?>"><?=$u['active']?'✅ فعال':'❌ غیرفعال'?></span></td>
<td>
<?php if($raw_pw !== ''): ?>
<div class="pw-cell">
<span class="pw-val" id="pw_<?=$u['id']?>">••••••</span>
<button class="pw-btn" onclick="togglePw('<?=$u['id']?>', '<?=htmlspecialchars(addslashes($raw_pw))?>')" id="pwbtn_<?=$u['id']?>">👁 نمایش</button>
<button class="pw-btn" onclick="copyPw('<?=htmlspecialchars(addslashes($raw_pw))?>')">📋</button>
</div>
<?php else: ?>
<div class="pw-cell"><span class="pw-na">نامشخص</span></div>
<?php endif; ?>
</td>
<td style="min-width:180px"><?=render_perms_status($u['permissions'] ?? [])?></td>
<td style="font-size:11px;color:var(--muted)"><?=$u['role']==='admin'||empty($uf)?'🌐 همه':'📁 '.fa_num(count($uf)).' پوشه'?></td>
<td style="font-size:11px;color:var(--muted)"><?=quota_summary($u)?></td>
<td style="font-size:11px"><?=$agent_exp_text?></td>
<td style="font-size:11px;color:var(--muted)"><?=$u['last_login']?'<span class="ltr">'.fa_date($u['last_login']).'</span>':'—'?></td>
<td><div style="display:flex;gap:6px;flex-wrap:wrap">
<?php if($u['role']==='agent'): ?><button class="btn sm agent-btn" onclick="extendAgent('<?=$u['id']?>','<?=htmlspecialchars($u['username'])?>')" title="تمدید اعتبار">⏰</button><?php endif; ?>
<button class="btn sm ghost" onclick="openEditUser('<?=$u['id']?>')">✏️</button>
<button class="btn sm green" onclick="toggleUser('<?=$u['id']?>')"><?=$u['active']?'🔒':'🔓'?></button>
<?php if($u['id']!==$cu['id']): ?><button class="btn sm red" onclick="deleteUser('<?=$u['id']?>','<?=htmlspecialchars($u['username'])?>')">🗑</button><?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
<?php if($IS_ADMIN): ?>
<div class="page" id="page-settings">
<div class="sec-head"><h2><i class="mk mk-a"></i>تنظیمات سیستم</h2></div>
<div class="panel" style="max-width:600px">
<h2 style="font-size:18px;margin-bottom:14px">⚙️ تنظیمات عمومی</h2>
<label class="lbl">نام سایت</label>
<input class="inp" id="setSiteName" value="<?=htmlspecialchars($cfg['site_name'])?>">
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">🖼️ لوگوی پنل</h3>
<label class="lbl">لینک لوگو (URL خارجی)</label>
<input type="url" class="inp" id="setLogoUrl" value="<?=htmlspecialchars($cfg['logo_url'])?>" placeholder="https://example.com/logo.png" style="direction:ltr;text-align:left">
<label class="lbl">شکل لوگو</label>
<div class="mini" style="margin-top:6px">
<button type="button" class="btn sm <?=($cfg['logo_shape']==='circle')?'teal':'ghost'?>" id="logoCircleBtn" onclick="setLogoShape('circle')">⭕ دایره‌ای</button>
<button type="button" class="btn sm <?=($cfg['logo_shape']==='square')?'teal':'ghost'?>" id="logoSquareBtn" onclick="setLogoShape('square')">⬜ مربع گوشه گرد</button>
</div>
<div class="logo-preview" id="logoPreview" style="border-radius:<?=$logo_shape?>">
<img src="<?=htmlspecialchars($cfg['logo_url'])?>" alt="پیش‌نمایش لوگو" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect fill=%22%23374151%22 width=%2260%22 height=%2260%22/></svg>'">
</div>
</div>
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">🎨 حالت نمایش</h3>
<p class="sub">انتخاب کنید پنل در چه حالتی نمایش داده شود</p>
<div class="mini" style="margin-top:10px">
<button class="btn sm <?=($cfg['theme_mode']=='dark')?'teal':'ghost'?>" onclick="setThemeMode('dark')">🌙 تاریک</button>
<button class="btn sm <?=($cfg['theme_mode']=='light')?'teal':'ghost'?>" onclick="setThemeMode('light')">☀️ روشن</button>
<button class="btn sm <?=($cfg['theme_mode']=='auto')?'teal':'ghost'?>" onclick="setThemeMode('auto')">🔄 خودکار</button>
</div>
</div>
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">🔗 ریدایرکت اجباری</h3>
<label class="switch-row" style="margin-top:10px"><input type="checkbox" id="setForceOn" <?=$cfg['force_redirect_on']?'checked':''?>><span class="switch"></span><span style="font-size:13px;font-weight:700">فعال‌سازی ریدایرکت اجباری</span></label>
<label class="lbl">لینک مقصد</label>
<input type="url" class="inp" id="setForceUrl" value="<?=htmlspecialchars($cfg['force_redirect'])?>" placeholder="https://example.com">
<label class="switch-row" style="margin-top:10px"><input type="checkbox" id="setForceAll" <?=(!isset($cfg['force_redirect_all']) || !empty($cfg['force_redirect_all']))?'checked':''?>><span class="switch"></span><span style="font-size:13px;font-weight:700">اعمال ریدایرکت برای همه اتصالات</span></label>
<p class="sub" style="margin-top:6px">اگر غیرفعال باشد، فقط اتصالات تستی و اتصالاتی که ریدایرکت هستند اعمال می‌شوند.</p>
</div>
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">🧪 سرویس اتصال تستی</h3>
<label class="switch-row" style="margin-top:10px"><input type="checkbox" id="setAllowTest" <?=$cfg['allow_test']?'checked':''?>><span class="switch"></span><span style="font-size:13px;font-weight:700">فعال‌سازی سرویس اتصال تستی</span></label>
</div>
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">🗑 حذف اتصال توسط کاربر</h3>
<label class="switch-row" style="margin-top:10px"><input type="checkbox" id="setAllowDelete" <?=$allow_self_delete?'checked':''?>><span class="switch"></span><span style="font-size:13px;font-weight:700">کاربران بتوانند اتصال‌های خودشان را حذف کنند</span></label>
</div>
<button class="btn teal wide" onclick="saveSettings()">💾 ذخیره تنظیمات</button>
</div>
</div>
<?php endif; ?>
<footer><?=$site_name?> — <?=htmlspecialchars($cu['username'])?></footer>
</div>
<div class="sidebar-overlay" id="sbOverlay" onclick="closeSidebar()"></div>
<nav class="sidebar" id="sidebar">
<div class="sb-head"><div class="sb-avatar"><?=mb_substr($cu['username'],0,1)?></div><div class="sb-info"><b><?=htmlspecialchars($cu['username'])?></b><span><?=$cu['role']==='admin'?'👑 مدیر کل':($cu['role']==='agent'?'🎫 نماینده':'👤 کاربر')?></span></div></div>
<a class="sb-item active" data-page="files" onclick="showPage('files')"><span class="sb-ic">🔗</span>اتصالات<span class="sb-badge"><?=fa_num(count($files))?></span></a>
<?php if(has_perm('manage_folders')): ?><a class="sb-item" data-page="folders" onclick="showPage('folders')"><span class="sb-ic">📁</span>پوشه‌ها</a><?php endif; ?>
<?php if($IS_ADMIN): ?>
<div class="sb-sep"></div>
<a class="sb-item" data-page="users" onclick="showPage('users')"><span class="sb-ic">👥</span>مدیریت کاربران<span class="sb-badge"><?=fa_num(count($users_list))?></span></a>
<a class="sb-item" data-page="settings" onclick="showPage('settings')"><span class="sb-ic">⚙️</span>تنظیمات</a>
<?php endif; ?>
<div class="sb-sep"></div>
<a class="sb-item" onclick="cycleTheme()"><span class="sb-ic" id="sbThemeIc">🌙</span>تغییر پوسته</a>
<a class="sb-item danger" onclick="doLogout()"><span class="sb-ic">🚪</span>خروج از حساب</a>
</nav>
<div class="dynamic-island" id="dynamicIsland">
<div class="island-compact"><span class="ic-dot"></span><span class="ic-time" id="islandTime">--:--:--</span><span class="ic-date" id="islandDateS"></span></div>
<div class="island-expanded">
<div class="island-clock"><div class="ik-time" id="ikTime">--:--:--</div><div class="ik-day" id="ikDay"></div><div class="ik-date" id="ikDate"></div></div>
<div class="island-grid">
<button class="island-btn" onclick="event.stopPropagation();cycleTheme()"><span class="ib-ic" id="isThemeIc">🌙</span>پوسته</button>
<button class="island-btn" onclick="event.stopPropagation();smoothReload()"><span class="ib-ic">🔄</span>بروزرسانی</button>
<button class="island-btn" onclick="event.stopPropagation();showPage('files');closeIsland()"><span class="ib-ic">🔗</span>اتصالات</button>
<?php if(has_perm('manage_folders')): ?>
<button class="island-btn" onclick="event.stopPropagation();showPage('folders');closeIsland()"><span class="ib-ic">📁</span>پوشه‌ها</button>
<?php endif; ?>
<?php if($IS_ADMIN): ?>
<button class="island-btn" onclick="event.stopPropagation();showPage('users');closeIsland()"><span class="ib-ic">👥</span>کاربران</button>
<button class="island-btn" onclick="event.stopPropagation();showPage('settings');closeIsland()"><span class="ib-ic">⚙️</span>تنظیمات</button>
<?php endif; ?>
<button class="island-btn danger full" onclick="event.stopPropagation();doLogout()"><span class="ib-ic">🚪</span>خروج از حساب</button>
</div>
</div>
</div>
<div class="overlay" id="overlay">
<div class="modal">
<div class="m-head"><h3>ویرایش <span id="eName"></span></h3><button class="x" onclick="closeEdit()">×</button></div>
<input type="hidden" id="eCsrf" value="<?=$csrf?>">
<?php if(!$hide_redirect_info): ?>
<label class="lbl">لینک ریدایرکت (اختیاری)</label><input type="url" id="eRedirect" class="inp" placeholder="https://example.com">
<label class="lbl">محتوای اتصال</label><textarea id="eContent" class="inp"></textarea>
<div class="mini"><button type="button" class="btn sm ghost" onclick="eCopy()">📋 کپی</button><button type="button" class="btn sm ghost" onclick="ePaste()">📥 چسباندن</button><button type="button" class="btn sm red" onclick="eClear()">🗑 حذف</button></div>
<?php else: ?><input type="hidden" id="eRedirect" value=""><textarea id="eContent" style="display:none"></textarea><?php endif; ?>
<div id="eDurSection"><label class="lbl">اعتبار جدید (از الان)</label>
<div class="dur"><div class="dur-box"><input type="number" id="eM" min="0" value="0"><span>ماه</span></div><div class="dur-box"><input type="number" id="eD" min="0" value="0"><span>روز</span></div><div class="dur-box"><input type="number" id="eH" min="0" value="0"><span>ساعت</span></div></div></div>
<div class="m-foot"><button class="btn teal" onclick="saveEdit()">ذخیره</button><button class="btn ghost" onclick="closeEdit()">انصراف</button></div>
</div>
</div>
<div class="overlay" id="userOverlay">
<div class="modal">
<div class="m-head"><h3 id="uModalTitle">افزودن <span>کاربر</span></h3><button class="x" onclick="closeUserModal()">×</button></div>
<input type="hidden" id="uId" value=""><input type="hidden" id="uCsrf" value="<?=$csrf?>">
<label class="lbl">نام کاربری</label><input class="inp" id="uUsername" autocomplete="off">
<label class="lbl">رمز عبور <small style="color:var(--muted)">(در ویرایش خالی = بدون تغییر)</small></label><input type="password" class="inp" id="uPassword" autocomplete="new-password">
<label class="lbl">نقش</label>
<select class="inp" id="uRole" onchange="onRoleChange()">
<option value="user">👤 کاربر عادی</option>
<option value="agent">🎫 نماینده</option>
<option value="admin">👑 مدیر</option>
</select>
<div id="agentExpireBox" style="display:none;margin-top:12px;padding:12px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);border-radius:10px">
<label class="lbl" style="margin-top:0">⏰ مدت اعتبار نماینده (روز)</label>
<input type="number" class="inp" id="uAgentExpireDays" min="1" value="30" placeholder="تعداد روز">
<p style="font-size:11px;color:var(--muted);margin-top:6px">بعد از انقضا، اتصالات فریز می‌شوند. ۷ روز بعد از انقضا حذف می‌شوند.</p>
</div>
<label class="lbl">وضعیت</label><select class="inp" id="uActive"><option value="1">✅ فعال</option><option value="0">❌ غیرفعال</option></select>
<label class="lbl">مشاهده اتصالات</label><select class="inp" id="uViewScope"><option value="own">🔒 فقط خودش</option><option value="all">🌐 همه</option><option value="none">🚫 هیچ</option></select>
<label class="lbl">دسترسی‌ها</label>
<div class="perm-grid" id="permGrid"><?php foreach($ALL_PERMS as $pk=>$pv): ?><label class="perm-item"><input type="checkbox" value="<?=$pk?>" class="perm-cb" onchange="updatePermStyle(this)"> <?=$pv?></label><?php endforeach; ?></div>
<label class="lbl">📁 پوشه‌های مجاز <small style="color:var(--muted)">(هیچ = همه)</small></label>
<div class="perm-grid" id="folderGrid"><label class="perm-item"><input type="checkbox" value="__ROOT__" class="folder-cb" onchange="updatePermStyle(this)"> 📁 ریشه</label><?php foreach($dirs as $d): ?><label class="perm-item"><input type="checkbox" value="<?=htmlspecialchars($d)?>" class="folder-cb" onchange="updatePermStyle(this)"> 📁 <?=htmlspecialchars($d)?></label><?php endforeach; ?></div>
<div style="margin-top:20px;padding:16px;background:var(--inbg);border:1px solid var(--line);border-radius:12px">
<h3 style="font-family:Lalezar;font-size:16px;margin-bottom:10px">📊 سقف ساخت اتصال</h3>
<p class="sub">خالی = نامحدود | صفر = غیرفعال | عدد = حداکثر</p>
<label class="lbl">سقف اتصال معمولی</label><input type="text" class="inp" id="uMaxNormal" placeholder="خالی = نامحدود" style="direction:ltr;text-align:center">
<label class="lbl">سقف اتصال تستی</label><input type="text" class="inp" id="uMaxTest" placeholder="خالی = نامحدود" style="direction:ltr;text-align:center">
</div>
<div class="m-foot"><button class="btn teal" onclick="saveUser()">💾 ذخیره</button><button class="btn ghost" onclick="closeUserModal()">انصراف</button></div>
</div>
</div>
<div class="overlay" id="qrOverlay">
<div class="modal qr-modal">
<div class="m-head"><h3>بارکد اتصال</h3><button class="x" onclick="closeQr()">×</button></div>
<div class="qr-name" id="qrName"></div>
<div class="qr-img"><img id="qrImg" src="" alt="بارکد"></div>
<div class="url-box" style="text-align:right"><input type="text" id="qrUrl" readonly onclick="this.select()"><button onclick="copyQrUrl()">📋</button></div>
<a id="qrDownload" href="#" target="_blank" class="btn teal wide" style="text-decoration:none">⬇ دانلود بارکد</a>
</div>
</div>
<div id="toasts"></div>
<script>
var CSRF='<?=$csrf?>';
var THEME_MODE='<?=$cfg['theme_mode']?>';
var CURRENT_USER='<?=htmlspecialchars($cu['username'])?>';
var IS_ADMIN=<?=$IS_ADMIN?'true':'false'?>;
var AGENT_PERMS=<?=json_encode($AGENT_DEFAULT_PERMS)?>;
var AGENT_SECONDS_LEFT=<?=$agent_seconds_left?>;
var AGENT_FROZEN=<?=$agent_frozen?'true':'false'?>;
var LOGO_SHAPE='<?=$cfg['logo_shape']?>';
function faNum(s){return String(s).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])}
function pad(n){return String(n).padStart(2,'0')}
function toJalali(gy,gm,gd){var gdm=[0,31,59,90,120,151,181,212,243,273,304,334];var jy=(gy<=1600)?0:979;gy-=(gy<=1600)?621:1600;var gy2=(gm>2)?(gy+1):gy;var days=365*gy+Math.floor((gy2+3)/4)-Math.floor((gy2+99)/100)+Math.floor((gy2+399)/400)-80+gd+gdm[gm-1];jy+=33*Math.floor(days/12053);days%=12053;jy+=4*Math.floor(days/1461);days%=1461;jy+=Math.floor((days-1)/365);if(days>365)days=(days-1)%365;var jm=(days<186)?1+Math.floor(days/31):7+Math.floor((days-186)/30);var jd=1+((days<186)?(days%31):((days-186)%30));return[jy,jm,jd]}
function post(fd){fd.append('csrf',CSRF);return fetch('',{method:'POST',body:fd}).then(function(r){return r.json()})}
function toast(m,t){var d=document.createElement('div');d.className='toast'+(t==='err'?' err':'');d.textContent=m;document.getElementById('toasts').appendChild(d);setTimeout(function(){d.style.transition='opacity .3s';d.style.opacity='0';setTimeout(function(){d.remove()},320)},2800)}
function smoothReload(){var d=document.createElement('div');d.style.cssText='position:fixed;inset:0;background:var(--bg);z-index:9999;opacity:0;transition:opacity .35s;pointer-events:none';document.body.appendChild(d);requestAnimationFrame(function(){d.style.opacity='1'});setTimeout(function(){location.reload()},400)}
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]})}
function getSystemTheme(){return window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
function applyTheme(mode){var theme=mode==='auto'?getSystemTheme():mode;document.documentElement.setAttribute('data-theme',theme);THEME_MODE=mode;var icon=theme==='light'?'☀️':'🌙';document.getElementById('themeBtn').textContent=icon;var a=document.getElementById('isThemeIc');if(a)a.textContent=icon;var b=document.getElementById('sbThemeIc');if(b)b.textContent=icon}
function cycleTheme(){var modes=['dark','light','auto'];var idx=modes.indexOf(THEME_MODE);var next=modes[(idx+1)%modes.length];applyTheme(next);var names={dark:'تاریک',light:'روشن',auto:'خودکار'};toast('حالت '+names[next]+' انتخاب شد')}
function setThemeMode(mode){applyTheme(mode)}
function setLogoShape(shape){
LOGO_SHAPE=shape;
var preview=document.getElementById('logoPreview');
if(preview)preview.style.borderRadius=shape==='square'?'14px':'50%';
var cb=document.getElementById('logoCircleBtn');
var sb=document.getElementById('logoSquareBtn');
if(cb){cb.className='btn sm '+(shape==='circle'?'teal':'ghost');}
if(sb){sb.className='btn sm '+(shape==='square'?'teal':'ghost');}
}
function randomFileName(){
var adj=['swift','nova','pablo','silent','dark','star','cosmic','rapid','shadow','blue','red','green','alpha','beta','omega','lucky','magic','prime','turbo','ultra','neo','astro','comet','orbit','quantum','violet','crystal','falcon','phoenix','storm'];
var noun=['link','file','node','path','wave','core','pulse','spark','beam','zone','hub','base','gate','flow','stream','line','net','box','token','key'];
var a=adj[Math.floor(Math.random()*adj.length)];
var b=noun[Math.floor(Math.random()*noun.length)];
var n=Math.floor(Math.random()*9000)+1000;
var el=document.getElementById('cFilename');
if(el){el.value=a+'-'+b+'-'+n;toast('نام رندوم ساخته شد ✓');}
}
var WD=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
var JM=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
function tickClock(){var n=new Date(),j=toJalali(n.getFullYear(),n.getMonth()+1,n.getDate());var ts=faNum(pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds()));var ds=WD[n.getDay()]+' '+faNum(j[2])+' '+JM[j[1]-1]+' '+faNum(j[0]);var dsShort=faNum(j[0]+'/'+pad(j[1])+'/'+pad(j[2]));document.getElementById('clockTime').textContent=ts;document.getElementById('clockDate').textContent=ds;document.getElementById('islandTime').textContent=ts;document.getElementById('islandDateS').textContent=dsShort;var ikt=document.getElementById('ikTime');if(ikt)ikt.textContent=ts;var ikd=document.getElementById('ikDay');if(ikd)ikd.textContent=WD[n.getDay()];var ikdt=document.getElementById('ikDate');if(ikdt)ikdt.textContent=faNum(j[2])+' '+JM[j[1]-1]+' '+faNum(j[0])}
var agentSecLeft = AGENT_SECONDS_LEFT;
function tickAgentTimer(){
var el = document.getElementById('agentTimerDisplay');
if(!el || AGENT_FROZEN) return;
if(agentSecLeft <= 0){
el.textContent = 'منقضی شده';
el.parentElement.parentElement.classList.add('frozen');
AGENT_FROZEN = true;
smoothReload();
return;
}
agentSecLeft--;
var d = Math.floor(agentSecLeft / 86400);
var h = Math.floor((agentSecLeft % 86400) / 3600);
var m = Math.floor((agentSecLeft % 3600) / 60);
var s = agentSecLeft % 60;
el.textContent = faNum(d) + ' روز ' + faNum(pad(h)) + ':' + faNum(pad(m)) + ':' + faNum(pad(s));
}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('on');document.getElementById('sbOverlay').classList.toggle('on');document.getElementById('hamburger').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('on');document.getElementById('sbOverlay').classList.remove('on');document.getElementById('hamburger').classList.remove('open')}
function closeIsland(){document.getElementById('dynamicIsland').classList.remove('expanded')}
document.getElementById('dynamicIsland').addEventListener('click',function(){if(!this.classList.contains('expanded'))this.classList.add('expanded')});
document.addEventListener('click',function(e){var i=document.getElementById('dynamicIsland');if(i&&!i.contains(e.target))i.classList.remove('expanded')});
function showPage(p){
document.querySelectorAll('.page').forEach(function(el){el.classList.remove('active')});
var target=document.getElementById('page-'+p);
if(target)target.classList.add('active');
document.querySelectorAll('.sb-item[data-page]').forEach(function(el){
el.classList.toggle('active',el.dataset.page===p);
});
closeSidebar();
}
function showTab(t){document.querySelectorAll('.tab-content').forEach(function(el){el.style.display='none'});document.querySelectorAll('.tab-btn').forEach(function(el){el.classList.remove('active')});document.getElementById('tab-'+t).style.display='grid';document.querySelector('.tab-btn[data-tab="'+t+'"]').classList.add('active')}
function drawDonut(){var C=339.292;document.querySelectorAll('.donut-seg').forEach(function(el){var len=parseFloat(el.dataset.len)||0;requestAnimationFrame(function(){requestAnimationFrame(function(){el.style.strokeDasharray=len+' '+C})})})}
function toggleTestMode(){var chk=document.getElementById('isTestChk');if(!chk)return;var on=chk.checked;var nf=document.getElementById('normalFields');if(nf)nf.style.display=on?'none':'block';var b=document.getElementById('submitBtn');if(on){b.textContent='🧪 ساخت اتصال تستی (۴ ساعته)';b.classList.add('purple');b.classList.remove('teal')}else{b.textContent='＋ ساخت اتصال';b.classList.remove('purple');b.classList.add('teal')}}
function checkEmpty(){var et=document.getElementById('emptyText');var er=document.getElementById('emptyRedirect');var etst=document.getElementById('emptyTest');if(et)et.style.display=document.querySelectorAll('#tab-text .card[data-expires]').length?'none':'block';if(er)er.style.display=document.querySelectorAll('#tab-redirect .card[data-expires]').length?'none':'block';if(etst)etst.style.display=document.querySelectorAll('#tab-test .card[data-expires]').length?'none':'block'}
function updateStats(){var cs=document.querySelectorAll('.card[data-expires]');var active=0;cs.forEach(function(c){if(c.dataset.expired!=='1')active++});document.getElementById('sCount').textContent=faNum(active)}
function updateNext(){var now=Math.floor(Date.now()/1000),min=Infinity;document.querySelectorAll('.card[data-expires]').forEach(function(c){var r=+c.dataset.expires-now;if(r>0&&r<min)min=r});var el=document.getElementById('nextExp');if(min===Infinity){el.textContent='—';return}var d=Math.floor(min/86400),h=Math.floor(min%86400/3600),m=Math.floor(min%3600/60);el.innerHTML=(d>0?'<span class="n">'+faNum(d)+'</span> روز ':'')+'<span class="n">'+faNum(pad(h)+':'+pad(m))+'</span>'}
function tickCards(){var now=Math.floor(Date.now()/1000);var changed=false;document.querySelectorAll('.card[data-expires]').forEach(function(c){var rem=+c.dataset.expires-now;if(rem<=0){if(c.dataset.expired!=='1'){c.dataset.expired='1';c.classList.add('expired-card');c.classList.remove('low','mid');c.querySelector('.cd').innerHTML='⏳ منقضی شده';c.querySelector('.bar i').style.width='0%';changed=true}return}var d=Math.floor(rem/86400),h=Math.floor(rem%86400/3600),m=Math.floor(rem%3600/60),s=rem%60;c.querySelector('.cd').innerHTML=(d>0?'<span class="n">'+faNum(d)+'</span> روز ':'')+'<span class="n">'+faNum(pad(h)+':'+pad(m)+':'+pad(s))+'</span>';var pct=Math.max(0,Math.min(100,rem/+c.dataset.total*100));c.querySelector('.bar i').style.width=pct+'%';c.classList.toggle('low',rem<3600);if(!c.classList.contains('test-card'))c.classList.toggle('mid',rem>=3600&&pct<50)});if(changed)updateStats();updateNext()}
function copyUrl(id){var c=document.querySelector('.card[data-id="'+id+'"]');if(!c)return;var url=c.dataset.url;if(!url)return;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(url).then(function(){toast('لینک کپی شد ✓')}).catch(function(){fbCopy(url)})}else fbCopy(url)}
function fbCopy(t){var a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.focus();a.select();try{document.execCommand('copy');toast('لینک کپی شد ✓')}catch(e){toast('کپی ناموفق بود','err')}a.remove()}
function openQr(id){var c=document.querySelector('.card[data-id="'+id+'"]');if(!c)return;var url=c.dataset.url;if(!url)return;document.getElementById('qrName').textContent=c.querySelector('.fname').textContent;var src='https://api.qrserver.com/v1/create-qr-code/?size=260x260&data='+encodeURIComponent(url);document.getElementById('qrImg').src=src;document.getElementById('qrUrl').value=url;document.getElementById('qrDownload').href=src;document.getElementById('qrOverlay').classList.add('on')}
function closeQr(){document.getElementById('qrOverlay').classList.remove('on')}
function copyQrUrl(){var u=document.getElementById('qrUrl').value;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){toast('لینک کپی شد ✓')}).catch(function(){fbCopy(u)})}else fbCopy(u)}
document.getElementById('qrOverlay').addEventListener('click',function(e){if(e.target===this)closeQr()});
var cf=document.getElementById('createForm');
if(cf)cf.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fd.append('action','create');var isTest=document.getElementById('isTestChk')&&document.getElementById('isTestChk').checked;var b=document.getElementById('submitBtn');b.disabled=true;b.textContent='در حال ساخت...';post(fd).then(function(r){b.disabled=false;b.textContent=isTest?'🧪 ساخت اتصال تستی (۴ ساعته)':'＋ ساخت اتصال';if(r.ok){toast(isTest?'اتصال تستی ساخته شد ✓':'اتصال ساخته شد ✓');smoothReload()}else toast(r.msg||'خطا','err')})});
function createFolder(){var n=document.getElementById('newFolderName').value.trim();if(!n){toast('نام پوشه را وارد کنید','err');return}var fd=new FormData();fd.append('action','create_folder');fd.append('folder',n);post(fd).then(function(r){if(r.ok){toast('پوشه ساخته شد ✓');smoothReload()}else toast(r.msg||'خطا','err')})}
function delFolder(btn){var row=btn.closest('.frow'),f=row.dataset.folder;if(!confirm('پوشه «'+f+'» حذف شود؟'))return;btn.disabled=true;var fd=new FormData();fd.append('action','delfolder');fd.append('folder',f);post(fd).then(function(r){if(r.ok){row.style.transition='opacity .3s';row.style.opacity='0';setTimeout(function(){row.remove();if(!document.querySelectorAll('.frow').length)document.getElementById('noFolder').style.display='block'},320);toast('پوشه حذف شد');updateStats()}else{toast(r.msg||'خطا','err');btn.disabled=false}})}
var editId=null;
function openEdit(id){var c=document.querySelector('.card[data-id="'+id+'"]');if(!c)return;editId=id;document.getElementById('eName').textContent=c.querySelector('.fname').textContent;var isTest=c.classList.contains('test-card');var durSec=document.getElementById('eDurSection');if(isTest){durSec.style.display='none'}else{durSec.style.display='block';var rem=Math.max(0,+c.dataset.expires-Math.floor(Date.now()/1000));document.getElementById('eM').value=Math.floor(rem/2592000);document.getElementById('eD').value=Math.floor(rem%2592000/86400);document.getElementById('eH').value=Math.floor(rem%86400/3600)}var ec=document.getElementById('eContent');if(ec.style.display!=='none')ec.value='در حال بارگذاری...';var er=document.getElementById('eRedirect');if(er.type!=='hidden')er.value='';document.getElementById('overlay').classList.add('on');var fd=new FormData();fd.append('action','get');fd.append('file_id',id);post(fd).then(function(r){if(r.ok){if(ec.style.display!=='none')ec.value=r.content||'';if(er.type!=='hidden')er.value=r.redirect_url||''}})}
function closeEdit(){document.getElementById('overlay').classList.remove('on');editId=null}
function saveEdit(){if(!editId)return;var fd=new FormData();fd.append('action','update');fd.append('file_id',editId);fd.append('content',document.getElementById('eContent').value);fd.append('redirect_url',document.getElementById('eRedirect').value);fd.append('m',document.getElementById('eM').value||0);fd.append('d',document.getElementById('eD').value||0);fd.append('h',document.getElementById('eH').value||0);post(fd).then(function(r){if(r.ok){toast('ذخیره شد ✓');smoothReload()}else toast(r.msg||'خطا','err')})}
document.getElementById('overlay').addEventListener('click',function(e){if(e.target===this)closeEdit()});
function delFile(id){if(!confirm('این اتصال حذف شود؟'))return;var fd=new FormData();fd.append('action','delete');fd.append('file_id',id);post(fd).then(function(r){if(r.ok){var c=document.querySelector('.card[data-id="'+id+'"]');if(c){c.style.transition='opacity .35s,transform .35s';c.style.opacity='0';c.style.transform='scale(.92)';setTimeout(function(){c.remove()},360)}toast('حذف شد');updateStats();checkEmpty()}else toast(r.msg||'خطا','err')})}
function copyText(t){if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){toast('کپی شد ✓')}).catch(function(){fbCopy2(t)})}else fbCopy2(t)}
function fbCopy2(t){var a=document.createElement('textarea');a.value=t;a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.focus();a.select();try{document.execCommand('copy');toast('کپی شد ✓')}catch(e){toast('کپی ناموفق بود','err')}a.remove()}
function pasteInto(ta){if(!ta)return;if(navigator.clipboard&&navigator.clipboard.readText){navigator.clipboard.readText().then(function(t){ta.value=t;toast('چسبانده شد ✓')}).catch(function(){ta.focus();toast('از Ctrl+V استفاده کنید','err')})}else{ta.focus();toast('مرورگر از Clipboard API پشتیبانی نمی‌کند','err')}}
function cCopy(){var el=document.getElementById('cContent');if(el)copyText(el.value)}
function cPaste(){var el=document.getElementById('cContent');if(el)pasteInto(el)}
function cClear(){var el=document.getElementById('cContent');if(el){el.value='';toast('محتویات پاک شد')}}
function eCopy(){var el=document.getElementById('eContent');if(el)copyText(el.value)}
function ePaste(){var el=document.getElementById('eContent');if(el)pasteInto(el)}
function eClear(){var el=document.getElementById('eContent');if(el){el.value='';toast('محتویات پاک شد')}}
function doLogout(){if(!confirm('خروج از حساب؟'))return;var fd=new FormData();fd.append('action','logout');fetch('',{method:'POST',body:fd}).then(function(){location.reload()})}
function updatePermStyle(cb){
var item=cb.closest('.perm-item');
if(item){
item.classList.toggle('checked',cb.checked);
}
}
function initPermStyles(){
document.querySelectorAll('.perm-item input').forEach(function(cb){
updatePermStyle(cb);
});
}
function onRoleChange(){
var role=document.getElementById('uRole').value;
var agentBox=document.getElementById('agentExpireBox');
agentBox.style.display=(role==='agent')?'block':'none';
if(role==='agent'){
document.querySelectorAll('.perm-cb').forEach(function(cb){
var isAgentPerm=AGENT_PERMS.indexOf(cb.value)!==-1;
cb.checked=isAgentPerm;
updatePermStyle(cb);
});
document.getElementById('uViewScope').value='own';
}
}
function openAddUser(){
document.getElementById('uModalTitle').innerHTML='افزودن <span>کاربر جدید</span>';
document.getElementById('uId').value='';
document.getElementById('uUsername').value='';
document.getElementById('uUsername').disabled=false;
document.getElementById('uPassword').value='';
document.getElementById('uRole').value='user';
document.getElementById('uActive').value='1';
document.getElementById('uViewScope').value='own';
document.getElementById('uMaxNormal').value='';
document.getElementById('uMaxTest').value='';
document.getElementById('uAgentExpireDays').value='30';
document.getElementById('agentExpireBox').style.display='none';
document.querySelectorAll('.perm-cb').forEach(function(cb){cb.checked=false;updatePermStyle(cb)});
document.querySelectorAll('.folder-cb').forEach(function(cb){cb.checked=false;updatePermStyle(cb)});
document.getElementById('userOverlay').classList.add('on');
}
function openEditUser(uid){
var tr=document.querySelector('tr[data-uid="'+uid+'"]');
if(!tr)return;
document.getElementById('uModalTitle').innerHTML='ویرایش <span>'+tr.cells[0].textContent+'</span>';
document.getElementById('uId').value=uid;
document.getElementById('uUsername').value=tr.cells[0].textContent.trim();
document.getElementById('uUsername').disabled=true;
document.getElementById('uPassword').value='';
var roleBadge=tr.querySelector('.role-badge');
var role='user';
if(roleBadge.classList.contains('admin'))role='admin';
else if(roleBadge.classList.contains('agent'))role='agent';
document.getElementById('uRole').value=role;
document.getElementById('uActive').value=tr.querySelector('.st-badge').classList.contains('on')?'1':'0';
document.getElementById('uViewScope').value=tr.dataset.scope||'own';
document.getElementById('uMaxNormal').value=tr.dataset.maxnormal||'';
document.getElementById('uMaxTest').value=tr.dataset.maxtest||'';
document.getElementById('agentExpireBox').style.display=(role==='agent')?'block':'none';
var agentExp=parseInt(tr.dataset.agentExpires||'0');
if(agentExp>0){
var daysLeft=Math.max(1,Math.ceil((agentExp-Math.floor(Date.now()/1000))/86400));
document.getElementById('uAgentExpireDays').value=daysLeft;
}
document.querySelectorAll('.perm-cb').forEach(function(cb){cb.checked=false;updatePermStyle(cb)});
var pl=(tr.dataset.perms||'').split(',').filter(function(x){return x!==''});
document.querySelectorAll('.perm-cb').forEach(function(cb){
if(pl.indexOf(cb.value)!==-1){cb.checked=true;updatePermStyle(cb);}
});
var fl=(tr.dataset.folders||'').split(',').filter(function(x){return x!==''});
document.querySelectorAll('.folder-cb').forEach(function(cb){
cb.checked=fl.indexOf(cb.value)!==-1;
updatePermStyle(cb);
});
document.getElementById('userOverlay').classList.add('on');
}
function closeUserModal(){document.getElementById('userOverlay').classList.remove('on');document.getElementById('uUsername').disabled=false}
document.getElementById('userOverlay').addEventListener('click',function(e){if(e.target===this)closeUserModal()});
function saveUser(){
var uid=document.getElementById('uId').value;
var fd=new FormData();
fd.append('action',uid?'edit_user':'add_user');
if(uid)fd.append('user_id',uid);
fd.append('username',document.getElementById('uUsername').value.trim());
fd.append('password',document.getElementById('uPassword').value);
fd.append('role',document.getElementById('uRole').value);
fd.append('active',document.getElementById('uActive').value);
fd.append('view_scope',document.getElementById('uViewScope').value);
fd.append('max_normal',document.getElementById('uMaxNormal').value.trim());
fd.append('max_test',document.getElementById('uMaxTest').value.trim());
fd.append('agent_expire_days',document.getElementById('uAgentExpireDays').value||'30');
document.querySelectorAll('.perm-cb:checked').forEach(function(cb){fd.append('perms[]',cb.value)});
document.querySelectorAll('.folder-cb:checked').forEach(function(cb){fd.append('folders[]',cb.value==='__ROOT__'?'':cb.value)});
post(fd).then(function(r){
if(r.ok){toast(uid?'کاربر ویرایش شد ✓':'کاربر اضافه شد ✓');smoothReload()}
else toast(r.msg||'خطا','err');
});
}
function deleteUser(uid,un){if(!confirm('کاربر «'+un+'» حذف شود؟'))return;var fd=new FormData();fd.append('action','delete_user');fd.append('user_id',uid);post(fd).then(function(r){if(r.ok){toast('کاربر حذف شد');smoothReload()}else toast(r.msg||'خطا','err')})}
function toggleUser(uid){var fd=new FormData();fd.append('action','toggle_user');fd.append('user_id',uid);post(fd).then(function(r){if(r.ok)smoothReload();else toast(r.msg||'خطا','err')})}
function extendAgent(uid,username){
var days=prompt('چند روز تمدید شود؟','30');
if(!days||isNaN(days)||parseInt(days)<1)return;
if(!confirm('حساب «'+username+'» به مدت '+days+' روز تمدید شود؟'))return;
var fd=new FormData();
fd.append('action','extend_agent');
fd.append('user_id',uid);
fd.append('extend_days',days);
post(fd).then(function(r){
if(r.ok){toast('حساب نماینده تمدید شد ✓');smoothReload()}
else toast(r.msg||'خطا','err');
});
}
function togglePw(uid, pw){
var el = document.getElementById('pw_'+uid);
var btn = document.getElementById('pwbtn_'+uid);
if(!el || !btn) return;
if(el.dataset.visible === '1'){
el.textContent = '••••••';
el.dataset.visible = '0';
btn.textContent = '👁 نمایش';
} else {
el.textContent = pw;
el.dataset.visible = '1';
btn.textContent = '🙈 مخفی';
setTimeout(function(){
if(el.dataset.visible === '1'){
el.textContent = '••••••';
el.dataset.visible = '0';
btn.textContent = '👁 نمایش';
}
}, 8000);
}
}
function copyPw(pw){
if(navigator.clipboard && navigator.clipboard.writeText){
navigator.clipboard.writeText(pw).then(function(){
toast('رمز عبور کپی شد ✓');
}).catch(function(){
fbCopy(pw);
});
} else {
fbCopy(pw);
}
}
function saveSettings(){var fd=new FormData();fd.append('action','save_settings');fd.append('site_name',document.getElementById('setSiteName').value);fd.append('force_redirect',document.getElementById('setForceUrl').value);fd.append('force_redirect_on',document.getElementById('setForceOn').checked?'1':'0');fd.append('force_redirect_all',document.getElementById('setForceAll').checked?'1':'0');fd.append('allow_user_delete',document.getElementById('setAllowDelete').checked?'1':'0');fd.append('allow_test',document.getElementById('setAllowTest').checked?'1':'0');fd.append('theme_mode',THEME_MODE);fd.append('logo_url',document.getElementById('setLogoUrl').value);fd.append('logo_shape',LOGO_SHAPE);post(fd).then(function(r){if(r.ok){toast('تنظیمات ذخیره شد ✓');setTimeout(function(){location.reload()},800)}else toast(r.msg||'خطا','err')})}
var logoUrlInput=document.getElementById('setLogoUrl');
if(logoUrlInput){
logoUrlInput.addEventListener('input',function(){
var preview=document.querySelector('#logoPreview img');
if(preview&&this.value.trim()){
preview.src=this.value.trim();
}
});
}
var io=new IntersectionObserver(function(es){es.forEach(function(en){if(en.isIntersecting){en.target.classList.add('in');io.unobserve(en.target)}})},{threshold:.08});
document.querySelectorAll('.reveal').forEach(function(el){io.observe(el)});
document.addEventListener('animationend',function(e){if(e.target.classList&&e.target.classList.contains('reveal')){e.target.style.opacity='1';e.target.style.animation='none';e.target.classList.remove('in')}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeEdit();closeUserModal();closeSidebar();closeIsland();closeQr()}});
applyTheme(THEME_MODE);
toggleTestMode();
drawDonut();
tickClock();setInterval(tickClock,1000);
tickCards();setInterval(tickCards,1000);
updateStats();
initPermStyles();
if(!AGENT_FROZEN && AGENT_SECONDS_LEFT > 0){
tickAgentTimer();
setInterval(tickAgentTimer,1000);
}
setInterval(function(){fetch('?ping=1')},60000);
window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',function(){if(THEME_MODE==='auto')applyTheme('auto')});
</script>
</body>
</html>
