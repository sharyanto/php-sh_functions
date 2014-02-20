<?php

if (!isset($TEMPLATE_ROOT)) $TEMPLATE_ROOT = $_SERVER['DOCUMENT_ROOT'];

function connect_db() {
  global $DB_HOST, $DB_USER, $DB_DB, $DB_PASS;
  global $CONN;

  $CONN = mysql_connect($DB_HOST, $DB_USER, $DB_PASS);
  mysql_select_db($DB_DB);
}

// BEGIN PASTE from stackoverflow (modified slightly to also $_REQUEST and deal
// with register_globals. note: despite the name, i've heard that actually
// everything like $_SERVERS is also escaped by the wretched magic_quotes_gpc)
//
// Strips slashes recursively only up to 3 levels to prevent attackers from
// causing a stack overflow error.
function stripslashes_array(&$array, $iterations=0) {
  if ($iterations < 3) {
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        stripslashes_array($array[$key], $iterations + 1);
      } else {
        $array[$key] = stripslashes($array[$key]);
      }
    }
  }
}

function reverse_magic_quotes_gpc() {
  if (get_magic_quotes_gpc()) {
    stripslashes_array($_REQUEST);
    stripslashes_array($_GET);
    stripslashes_array($_POST);
    stripslashes_array($_COOKIE);
    if (ini_get('register_globals')) {
      // import_request_variables('gpc'); # doesn't work?
      foreach ($_REQUEST as $k=>$v) $GLOBALS[$k] = $v;
    }
  }
}
// END PASTE

function fix_gpc_vars() { reverse_magic_quotes_gpc(); }

function emulate_register_globals() {
  if (!ini_get("register_globals")) {
    $superglobals = array($_SERVER, $_ENV,
                          $_FILES, $_COOKIE, $_POST, $_GET);
    if (isset($_SESSION)) {
      array_unshift($superglobals, $_SESSION);
    }
    foreach ($superglobals as $superglobal) {
      #extract($superglobal, EXTR_SKIP); #only works outside func
      foreach ($superglobal as $k => $v) {
        if (!isset($GLOBALS[$k])) $GLOBALS[$k] = $v;
      }
    }
    ini_set('register_globals', true);
  }
}

function gettemplatefile($templatefilename) {
  global $TEMPLATE_ROOT;
  $path = "$TEMPLATE_ROOT$templatefilename";

  if (!file_exists($path)) die("ERROR: Template file `$templatefilename' does not exist");

  $fp = fopen($path, 'r');
  return fread($fp, filesize($path));
}

# replace template file with another content
function puttemplatefile($templatefilename, $content) {
  global $TEMPLATE_ROOT;
  $path = "$TEMPLATE_ROOT$templatefilename";

  #if (!file_exists($path)) die("ERROR: Template file `$templatefilename' does not exist");

  $fp = fopen($path, 'w');
  fputs($fp, $content);
}

function _filltemplatevar($label, $label_opt, &$vars, $opt_raw=false) {
  $tt = preg_split('/\./', $label);
  $v =& $vars;
  foreach ($tt as $t) {
    if (!is_array($v)) return "[[ERROR:NOTARRAY:$t]]";
    if (isset($v[$t])) $v =& $v[$t];
    else return ($label_opt == 'emptydefault' ? "" : "[[UNDEFINED:$label]]");
  }
  if ($opt_raw || $label_opt == 'raw') return $v;
  if ($label_opt == 'url') return urlencode($v);
  return htmlentities($v, ENT_COMPAT, "UTF-8");
}

function filltemplate($template, $templatevars = array(), $opt_raw=false) {
  return preg_replace('/\[\[([\w.]+)(?::(raw|url|emptydefault))?\]\]/e',
                      '_filltemplatevar("\1", "\2", $templatevars, $opt_raw)',
                      $template);
}

function filltemplatefile($templatefilename, $templatevars = array(), $opt_raw=false) {
  global $TEMPLATE_ROOT;
  $path = "$TEMPLATE_ROOT$templatefilename";

  if (!file_exists($path)) die("ERROR: Template file `$templatefilename' does not exist");

  $fp = fopen($path, 'r');
  return filltemplate(fread($fp, filesize($path)), $templatevars, $opt_raw);
}

function displaytemplate($template, $templatevars = array(), $opt_raw=false) {
  echo filltemplate($template, $templatevars, $opt_raw);
}

function displaytemplatefile($templatefilename, $templatevars = array(), $opt_raw=false) {
  echo filltemplatefile($templatefilename, $templatevars, $opt_raw);
}

function writetemplatefile($templatefilename, $templatevars = array(), $destpath, $opt_raw=false) {
  $fp = fopen($destpath, 'w');
  fputs($fp, filltemplatefile($templatefilename, $templatevars, $opt_raw));
}

# opts:
# * min_break: minimum number before it is broken into smaller unit. default is 1.
#     e.g. if min_break is 2 then 110 will still be called 110 detik instead of
#     1 menit 50 detik, but 120 will become 2 menit.
function show_duration($time, $now=null, $truncate=2, $show_ago=true, $opts=array()) {
  if (!isset($now)) $now = time();
  $dur = $time - $now;
  $is_neg = $dur < 0 ? 1:0; $dur = abs($dur);
  $m = isset($opts['min_break']) ? $opts['min_break'] : 1;

  if (!$dur) return "detik ini";

  $years   = floor($dur / (365*86400)); if ($years   < $m) $years   = 0; $dur -= $years   * (365*86400);
  $months  = floor($dur /  (30*86400)); if ($months  < $m) $months  = 0; $dur -= $months  *  (30*86400);
  $days    = floor($dur /     (86400)); if ($days    < $m) $days    = 0; $dur -= $days    *     (86400);
  $hours   = floor($dur /      (3600)); if ($hours   < $m) $hours   = 0; $dur -= $hours   *      (3600);
  $minutes = floor($dur /        (60)); if ($minutes < $m) $minutes = 0; $dur -= $minutes *        (60);
  $secs    = $dur;

  $d = array();
  if ($years)   $d[] = "$years tahun";
  if ($months)  $d[] = "$months bulan";
  if ($days)    $d[] = "$days hari";
  if ($hours)   $d[] = "$hours jam";
  if ($minutes) $d[] = "$minutes menit";
  if ($secs)    $d[] = "$secs detik";
  if ($truncate > 0) array_splice($d, $truncate, count($d)-$truncate);
  if ($show_ago) $d[] = ($is_neg ? "lalu" : "lagi");
  return join(" ", $d);
}

function ellipsis($str, $len) {
  if (strlen($str) > $len-3) return substr($str, 0, $len-3)."...";
  return $str;
}

# nyatakan $str dalam string Javascript (e.g. kutip dsb di-escape)
function jsstring_encode($str) {
  $jsstring_lines = array();

  foreach (preg_split('/\015?\012/', $str) as $line) {
    if (count($jsstring_lines)) $jsstring_lines[count($jsstring_lines)-1] .= "+\n";
    $line = preg_replace('/([\x27\x29\x5c])/', '\\\\' . '\1', $line); # escape ' -> \', \ -> \\, ) -> \)
    $line = preg_replace('#(</?scr)(ipt)#', "\\1'+'\\2", $line);
    $jsstring_lines[] = "'$line\\n'";
  }
  return join("", $jsstring_lines);
}

# gw lupa deh jsstring_encode buat apa, tapi untuk mengencode string dalam javascript, gunakan fungsi ini (gak nambahin newlines)
function jsstring_quote($str) {
  if (!isset($str)) return "null";
  return "'" . preg_replace("/([^\x20-\x7f]|'|\\\\)/e", 'sprintf("\\x%02x", ord(\'\1\'))', $str) . "'";
}

function _iddate_sub($char, $time) {
  if ($char == 'Y') {
    return date('Y', $time);
  } elseif ($char == 'd') {
    return date('d', $time);
  } elseif ($char == 'M') {
    $mon = date('m', $time);
    if ($mon== 1) return "Jan"; elseif ($mon== 2) return "Feb"; elseif ($mon== 3) return "Mar";
    if ($mon== 4) return "Apr"; elseif ($mon== 5) return "Mei"; elseif ($mon== 6) return "Jun";
    if ($mon== 7) return "Jul"; elseif ($mon== 8) return "Agu"; elseif ($mon== 9) return "Sep";
    if ($mon==10) return "Okt"; elseif ($mon==11) return "Nop"; elseif ($mon==12) return "Des";
  } elseif ($char == 'F') {
    $mon = date('m', $time);
    if ($mon== 1) return "Januari"; elseif ($mon== 2) return "Februari"; elseif ($mon== 3) return "Maret";
    if ($mon== 4) return "April"  ; elseif ($mon== 5) return "Mei"     ; elseif ($mon== 6) return "Juni";
    if ($mon== 7) return "Juli"   ; elseif ($mon== 8) return "Agustus" ; elseif ($mon== 9) return "September";
    if ($mon==10) return "Oktober"; elseif ($mon==11) return "Nopember"; elseif ($mon==12) return "Desember";
  } elseif ($char == 'H') {
    return date('H', $time);
  } elseif ($char == 'i') {
    return date('i', $time);
  } elseif ($char == 's') {
    return date('s', $time);
  } elseif ($char == 'D') {
     $dow = date('w', $time);
     if ($dow==0) return "Min"; elseif ($dow==1) return "Sen";
     if ($dow==2) return "Sel"; elseif ($dow==1) return "Rab";
     if ($dow==4) return "Kam"; elseif ($dow==1) return "Jum";
     if ($dow==6) return "Sab";
  } elseif ($char == 'l') {
     $dow = date('w', $time);
     if ($dow==0) return "Minggu"; elseif ($dow==1) return "Senin";
     if ($dow==2) return "Selasa"; elseif ($dow==1) return "Rabu";
     if ($dow==4) return "Kamis" ; elseif ($dow==1) return "Jumat";
     if ($dow==6) return "Sabtu" ;
  } else {
    return $char;
  }
}

function iddate($format, $timestamp=-1) {
  if ($timestamp==-1 || $timestamp=="") $timestamp = time();
  return preg_replace('/([YMdFHisDl])/e', "_iddate_sub('\\1',$timestamp)", $format);
}

# 2012-10-23 - gak dipake?
function siteurl() {
  return
    (isset($_SERVER['HTTPS']) ? "https://" : "http://") .
    $_SERVER['SERVER_NAME'] .
    "/";
}

# 2012-10-23
function myurl() {
  $is_https = isset($_SERVER['HTTPS']);
  $port = $_SERVER['SERVER_PORT'];
  $is_default_port = $port == ($is_https ? 443 : 80);

  return
    ($is_https ? "https://" : "http://") .
    $_SERVER['SERVER_NAME'] .
    ($is_default_port ? "" : ":$port").
    $_SERVER['REQUEST_URI'];
}

# 2012-10-25
function myurl_noquery() {
  return preg_replace('/\?.*/', '', myurl());
}

# returns an image object berisi thumbnail, tinggal dioutput pake imagepng(...), etc.
function thumbnail($f, $width, $height) {
  $fp = fopen($f, "rb"); $content = "";
  while (1) {
    $chunk = fread($fp, 64*1024);
    $content .= $chunk;
    if (!strlen($chunk)) break;
  }
  fclose($fp);

  $im = imagecreatefromstring($content);
  $sizes = getimagesize($f);

  // hitung ukuran
  if ($width == 0) {
    $width = $sizes[0] * ($height/$sizes[1]);
  } elseif ($height == 0) {
    $height = $sizes[1] * ($width/$sizes[0]);
  }

  $im2 = imagecreate($width, $height);
  imagecopyresized($im2, $im, 0, 0, 0, 0,
                   $width, $height, $sizes[0], $sizes[1]);
  return $im2;
}

function show_sizewithunit($size) {
  if ($size <= 1.5*1024) return "{$size}b";
  elseif ($size <= 1.5*1024*1024) return sprintf("%.1fK", $size/1024);
  elseif ($size <= 1.5*1024*1024*1024) return sprintf("%.2fM", $size/1024/1024);
  else return sprintf("%.2fG", $size/1024/1024/1024);
}

# returns null if name is unknown
function colorname2rgb($name) {
  if     ($name == 'black'     ) return '#000000';
  elseif ($name == 'red'       ) return '#ff0000';
  elseif ($name == 'green'     ) return '#00ff00';
  elseif ($name == 'blue'      ) return '#0000ff';
  elseif ($name == 'yellow'    ) return '#ffff00';
  elseif ($name == 'magenta'   ) return '#ff00ff';
  elseif ($name == 'cyan'      ) return '#00ffff';
  elseif ($name == 'white'     ) return '#ffffff';

  elseif ($name == 'grey'      ) return '#808080';
  elseif ($name == 'gray'      ) return '#808080';
  elseif ($name == 'light grey') return '#c0c0c0';
  elseif ($name == 'light gray') return '#c0c0c0';

  else return;
}

# returns null if one of the color is invalid or unknown
function mix_2_rgb_colors($color1, $color2, $fraction_of_color2=0.5) {
  $re = '/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/';
  if (!isset($color1) || $color1 === '' ||
      !isset($color2) || $color2 === '') return;
  $rgb1 = preg_match($re, $color1) ? $color1 : colorname2rgb($color1);
  $rgb2 = preg_match($re, $color2) ? $color2 : colorname2rgb($color2);
  if (!isset($rgb1) || !isset($rgb2)) return;
  preg_match($re, $rgb1, $r1);
  preg_match($re, $rgb2, $r2);
  return sprintf("#%02x%02x%02x", hexdec($r1[1])*(1.0-$fraction_of_color2) + hexdec($r2[1])*$fraction_of_color2,
                                  hexdec($r1[2])*(1.0-$fraction_of_color2) + hexdec($r2[2])*$fraction_of_color2,
                                  hexdec($r1[3])*(1.0-$fraction_of_color2) + hexdec($r2[3])*$fraction_of_color2);
}

# deprecated, just use file_get_contents() nowadays
function my_readfile($path) {
  $fp = @fopen($path, "r");
  if (!$fp) return;
  return @fread($fp, @filesize($path));
}

function my_readfilec($path) {
  $fp = @fopen($path, "r");
  if (!$fp) return;
  return preg_replace('/\s+$/s', '', @fread($fp, @filesize($path)));
}

function my_readfilea($path) {
  $fp = @fopen($path, "r");
  if (!$fp) return;
  $lines = array();
  while (1) {
    $line = rtrim(fgets($fp, 4096));
    if (feof($fp)) break;
    $lines[] = $line;
  }
  fclose($fp);
  return $lines;
}

function my_writefile($path, $string) {
  $fp = @fopen($path, "w");
  if (!$fp) return;
  return fputs($fp, $string);
}

function my_writefilec($path, $string) {
  $fp = @fopen($path, "w");
  if (!$fp) return;
  return fputs($fp, preg_replace('/\s+$/s', '', $string));
}

function cmp_bylength_asc($a, $b) {
  $la = strlen($a); $lb = strlen($b);
  if ($la>$lb) return 1; if ($la<$lb) return -1;
  return 0;
}

function cmp_bylength_desc($a, $b) {
  $la = strlen($a); $lb = strlen($b);
  if ($la>$lb) return -1; if ($la<$lb) return 1;
  return 0;
}

# parallel substitution. mirip2x tr/// di perl. XXX belum dicek jika pola mengandung ', ", \
# contoh penggunaan: psub("ai", "ia", "bisa"); // "basi"
# contoh 2: psub(array('.', ','), array('', '.'), '1.234,50'); // 1234.50

function psub($x, $y, $str) {
  if (!is_array($x)) $x = preg_split('//', $x, -1, PREG_SPLIT_NO_EMPTY);
  if (!is_array($y)) $y = preg_split('//', $y, -1, PREG_SPLIT_NO_EMPTY);
  $x2 = array_flip($x);
  $pat = '/(' . join('|', array_map('preg_quote', $x)) . ')/e';
  return preg_replace($pat, '$y[$x2["\1"]]', $str);
}

function get_include_contents($filename) {
  if (is_file($filename)) {
    ob_start();
    include $filename;
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
  }
  return false;
}

function cmp($a, $b) {
  if ($a == $b) return 0;
  return ($a < $b) ? -1 : 1;
}

function mysqldate2unixtime($d) {
  if(!preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?)/', $d, $m)) return;
  return mktime(0,0,0, $m[2], $m[3], $m[1]);
}

function mysqldatetime2unixtime($d) {
  if(!preg_match('/^(\d\d\d\d)-(\d\d?)-(\d\d?) ?(\d\d)?:?(\d\d)?:?(\d\d)?/', $d, $m)) return;
  return mktime($m[4],$m[5],$m[6], $m[2], $m[3], $m[1]);
}

function unixtime2mysqldate($t) {
  return date("Y-m-d", $t);
}

function unixtime2mysqldatetime($t) {
  return date("Y-m-d H:i:s", $t);
}

# convenience functions

function m($str) {
  return mysql_escape_string($str);
}

function mq($val) {
  if (!isset($val)) {
    return "NULL";
  } else {
    return "'" . mysql_escape_string($val) . "'";
  }
  # XXX if number, don't use quote?
}

function _humanesc($v) {
  if (!isset($v)) return "''";
  if ($v==="" || preg_match('/\s/i', $v)) return "'$v'";
  return $v;
}

# XXX saat ini hardcoded utk lang=id
function humandiff($ar1, $ar2, $separator=null) {
  $res = array();
  foreach (array_diff_key($ar1, $ar2) as $k=>$v) {
    $res[] = "$k dihapus";
  }
  foreach (array_diff_key($ar2, $ar1) as $k=>$v) {
    $res[] = "$k diset menjadi "._humanesc($v);
  }
  foreach (array_intersect_key($ar1, $ar2) as $k=>$v) {
    $res[] = "$k diubah dari "._humanesc($ar1[$k])." menjadi "._humanesc($ar2[$k]);
  }
  if (isset($separator)) {
    return join($separator, $res);
  } else {
    return $res;
  }
}

function send_expire_headers() {
    header("Expires: Tue, 01 Jan 1991 00:00:00 GMT");
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
}

function terbilang($num) {
  $digits = array(
    0 => "nol",
    1 => "satu",
    2 => "dua",
    3 => "tiga",
    4 => "empat",
    5 => "lima",
    6 => "enam",
    7 => "tujuh",
    8 => "delapan",
    9 => "sembilan");
  $orders = array(
     0 => "",
     1 => "puluh",
     2 => "ratus",
     3 => "ribu",
     6 => "juta",
     9 => "miliar",
    12 => "triliun",
    15 => "kuadriliun");

  $is_neg = $num < 0; $num = "$num";

  //// angka di kiri desimal

  $int = ""; if (preg_match("/^[+-]?(\d+)/", $num, $m)) $int = $m[1];
  $mult = 0; $wint = "";

  // ambil ribuan/jutaan/dst
  while (preg_match('/(\d{1,3})$/', $int, $m)) {

    // ambil satuan, puluhan, dan ratusan
    $s = $m[1] % 10;
    $p = ($m[1] % 100 - $s)/10;
    $r = ($m[1] - $p*10 - $s)/100;

    // konversi ratusan
    if ($r==0) $g = "";
    elseif ($r==1) $g = "se$orders[2]";
    else $g = $digits[$r]." $orders[2]";

    // konversi puluhan dan satuan
    if ($p==0) {
      if ($s==0);
      elseif ($s==1) $g = ($g ? "$g ".$digits[$s] :
                                ($mult==0 ? $digits[1] : "se"));
      else $g = ($g ? "$g ":"") . $digits[$s];
    } elseif ($p==1) {
      if ($s==0) $g = ($g ? "$g ":"") . "se$orders[1]";
      elseif ($s==1) $g = ($g ? "$g ":"") . "sebelas";
      else $g = ($g ? "$g ":"") . $digits[$s] . " belas";
    } else {
      $g = ($g ? "$g ":"").$digits[$p]." puluh".
           ($s > 0 ? " ".$digits[$s] : "");
    }

    // gabungkan dengan hasil sebelumnya
    $wint = ($g ? $g.($g=="se" ? "":" ").$orders[$mult]:"").
            ($wint ? " $wint":"");

    // pangkas ribuan/jutaan/dsb yang sudah dikonversi
    $int = preg_replace('/\d{1,3}$/', '', $int);
    $mult+=3;
  }
  if (!$wint) $wint = $digits[0];

  //// angka di kanan desimal

  $frac = ""; if (preg_match("/\.(\d+)/", $num, $m)) $frac = $m[1];
  $wfrac = "";
  for ($i=0; $i<strlen($frac); $i++) {
    $wfrac .= ($wfrac ? " ":"").$digits[substr($frac,$i,1)];
  }

  return ($is_neg ? "minus ":"").$wint.($wfrac ? "koma $wfrac":"");
}

?>
