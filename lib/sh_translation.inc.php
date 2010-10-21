<?

$_Lang_Dir = null;
function set_lang_dir($path) {
  global $_Lang_Dir;
  $_Lang_Dir = $path;
}

$_Lang = null;
function set_lang($lang) {
  global $_Lang;
  $_Lang = $lang;
}

$_Trans_Table = array(); # array(LANG => array(absname=>translation, absname=>translation), 'index'=>..., )
function load_lang_file($rel_path) {
  global $_Trans_Table, $_Lang, $_Lang_Dir;

  $path = "$_Lang_Dir/$_Lang/$rel_path";
  if (!file_exists($path)) die("FATAL: Can't load language file for: lang=$_Lang, rel_path=$rel_path, path=$path");
  if (!extension_loaded("syck")) dl("syck.so");

  # load index first. XXX not yet implemented.
  if (!isset($_Trans_Table['index'])) {
  }

  if (!isset($_Trans_Table[$_Lang])) $_Trans_Table[$_Lang] = array();
  $table = syck_load(file_get_contents($path));
  if (is_array($table)) {
    foreach ($table as $k => $v) {
      # check in index. XXX not yet implemented.
      $k = _abs_trans_name($rel_path, $k);
      $_Trans_Table[$_Lang][$k] = $v;
      # XXX function not yet implemented.
    }
  }
}

$_Lang_Context = "";
function set_lang_context($ctx) {
  global $_Lang_Context;
  $_Lang_Context = $ctx;
}

# get translation!
function _t($name) {
  global $_Lang, $_Trans_Table;

  # XXX search using file system. not yet implemented
  # XXX search using context. not yet implemented

  # search upwards
  foreach ($_Trans_Table[$_Lang] as $k=>$v) {
    if (preg_match('#/'.$name.'$#', $k)) return $v;
  }

  # XXX configurable behaviour if translation is not found
  return "[[translation:$_Lang:$name]]";
}

# XXX needs better implementation
function _abs_trans_name($a, $b) {
  $p = "/$a/$b";
  $p = preg_replace('#([^/]+)/\.\.#', '', $p);
  $p = preg_replace('#//+#', '/', $p);
  return $p;
}

?>
