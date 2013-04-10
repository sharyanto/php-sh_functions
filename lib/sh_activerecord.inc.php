<?php

# isi $tv (template vars) dengan nilai dari $values (atau dari
# nilai default field menurut spec $activerecord, jika di $values
# tidak disebutkan)

function activerecord_fill(&$activerecord, &$tv, $values = null) {
  global $DOCUMENT_ROOT;

  $fields = $activerecord['fields'];
  if (!$values) $values = array();

  foreach ($fields as $f) {
    $t = $f['datatype'];
    $n = $f['name'];

    # khusus untuk form field file, save di session. NOTE:
    # nilai uploaded files tidak diambil dari $values melainkan
    # langsung dari $_FILES.

    if (isset($f['file']) && $f['file']) {
      if (!isset($_SESSION['_ar_form_files'])) $_SESSION['_ar_form_files'] = array();
      $uploaded = false;
      #print_r($_FILES);
      if (isset($values["{$n}_na"])) { # hapus gambar yg udah ada
        $_SESSION['_ar_form_files'][$n] = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x09\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00"; # dot
        $uploaded = true;
      } elseif (isset($_FILES[$n]) && $_FILES[$n]['size']) {
        $_SESSION['_ar_form_files'][$n] = file_get_contents($_FILES[$n]['tmp_name']);
        $uploaded = true;
      } elseif (isset($_SESSION['_ar_form_files'][$n])) {
        $uploaded = true;
      }

      $tv["{$n}_widget"] = "<input name=$n size=8 type=file>".($uploaded ? " <font color=blue>(".sprintf(_t("uploaded_F"), show_sizewithunit(strlen($_SESSION['_ar_form_files'][$n]))).")</font>" : "") .
        "<br><input type=checkbox name={$n}_na value=1> "._t("delete_uploaded_file");
    }

    if ($t == 'date') { # XXX belum mengenal default

      $w = "";

      if (isset($f['default']) && preg_match('/(\d\d\d\d)-(\d\d?)-(\d\d?)/', $f['default'], $m)) {
        $def_year = $m[1];
        $def_mon = $m[2];
        $def_day = $m[3];
      }

      # XXX urutan d-m-Y sesuai locale
      $w .= "<select name={$n}_day><option value=0>("._t("day").")";
      for ($i=1; $i<=31; $i++) $w .= "<option value=$i".
        (( isset($values["{$n}_day"]) && $values["{$n}_day"]==$i) ||
         (!isset($values["{$n}_day"]) && isset($def_day) && $def_day==$i) ? " selected":"").">$i";
      $w .= "</select>";

      $w .= "<select name={$n}_mon><option value=0>("._t("mon").")";
      for ($i=1; $i<=12; $i++) $w .= "<option value=$i".
        (( isset($values["{$n}_mon"]) && $values["{$n}_mon"]==$i) ||
         (!isset($values["{$n}_mon"]) && isset($def_mon) && $def_mon==$i) ? " selected":"").">"._t("mon$i");
      $w .= "</select>";

      $w .= "<select name={$n}_year><option value=0>("._t("year").")";
      if (!isset($f['yearstart'])) $f['yearstart'] = 1938;
      if (!isset($f['yearend'])) $f['yearend'] = 2037;
      for ($i=$f['yearstart']; $i<=$f['yearend']; $i++) $w .= "<option value=$i".
      ( ( isset($values["{$n}_year"]) && $values["{$n}_year"]==$i) ||
        (!isset($values["{$n}_year"]) && isset($def_year) && $def_year==$i) ? " selected":"").">$i";
      $w .= "</select>";

      $tv["{$n}_widget"] = $w;

    } elseif (isset($f['choices'])) {

      $w = "";

      $w .= "<select name=$n><option value=''>"._t("choose");
      foreach ($f['choices'] as $ck => $cv) $w .= "<option value='$ck'".
        (( isset($values[$n]) && $values[$n]==$ck && strlen($values[$n])==strlen($ck)) ||
         (!isset($values[$n]) && isset($f['default']) && $ck==$f['default'] && strlen($ck)==strlen($f['default'])) ? " selected":"").">$cv";
      $w .= "</select>";

      $tv["{$n}_widget"] = $w;

    } else {

      $tv[$n] = isset($values[$n]) ? $values[$n] : (isset($f['default']) ? $f['default'] : '');

    }

    if (!isset($tv["{$n}_error"])) $tv["{$n}_error"] = "";

  }
}

# cek $values apakah sesuai dengan spesifikasi $activerecord.
# jika ada yang tidak sesuai, akan set "*_error" elements di
# $tv. returns number of errors.

function activerecord_check(&$activerecord, &$tv, $values, $is_adding=true) { # jika is_adding false, maka id field harus disebutkan
  $fields = $activerecord['fields'];
  $id_field = $activerecord['id_field'];
  $num_errors = 0;

  # validator fase 1 dipanggil sebelum semua field diperiksa. diberi
  # argumen (&$activerecord, &$tv, $values). return true jika
  # berhasil, false jika gagal. set $tv['_phase1_error'] jika ada error.

  if (isset($activerecord['phase1_validator_func'])) {
    if (!$activerecord['phase1_validator_func'](&$activerecord, &$tv, $values)) $num_errors++;
  }

  foreach ($fields as $f) {
    $t = $f['datatype'];
    $n = $f['name'];
    $v = null;
    if (isset($values[$n])) $v = $values[$n];
    $value_specified = isset($v) && $v !== "";
    $isfile = isset($f['file']) && $f['file'];
    $autogen = false;

    # khusus untuk form field file, save di session. NOTE:
    # nilai uploaded files tidak diambil dari $values melainkan
    # langsung dari $_FILES.

    if (isset($f['file']) && $f['file']) {
      if (!isset($_SESSION['_ar_form_files'])) $_SESSION['_ar_form_files'] = array();
      if (isset($_FILES[$n]) && $_FILES[$n]['size']) {
        $_SESSION['_ar_form_files'][$n] = file_get_contents($_FILES[$n]['tmp_name']);
      }
    }

    if ($n == $id_field) {
      if ($is_adding && (isset($f['autogenerated']) && $f['autogenerated'])) {
        $autogen = true;
        #if ($value_specified) {
        #  $num_errors++; $tv["{$n}_error"] = _t("err_must_not_be_specified");
        #}
      } elseif (!$is_adding && !$value_specified) {
        $num_errors++; $tv["{$n}_error"] = _t("err_required");
      }
    }

    if ($t == 'date') {
      if ($f['required'] && (!isset($values["{$n}_day"]) || !$values["{$n}_day"] ||
                             !isset($values["{$n}_mon"]) || !$values["{$n}_mon"] ||
                             !isset($values["{$n}_year"]) || !$values["{$n}_year"])) {
        $num_errors++; $tv["{$n}_error"] = _t("err_required");
      }
    } else {
      #echo "DEBUG:n=$n<br>";
      if ($isfile && (isset($f['required']) && $f['required']) && !isset($_SESSION['_ar_form_files'][$n])) { $num_errors++; $tv["{$n}_error"] = _t("err_upload_required"); continue; }
      if (!$autogen && !$isfile && (isset($f['required']) && $f['required']) && !$value_specified) { $num_errors++; $tv["{$n}_error"] = _t("err_required"); continue; }
      if ($value_specified) {
        #echo "DEBUG:regex=$f[regex]<br>";
        if ($t == 'integer') {
          if (!preg_match('/^[+-]?\d+$/', $v)) { $num_errors++; $tv["{$n}_error"] = _t("err_integer_expected"); continue; }
          if (isset($f['min_value']) && $v < $f['min_value']) { $num_errors++; $tv["{$n}_error"] = sprintf(_t("err_too_small_S"), $f['min_value']); continue; }
          if (isset($f['max_value']) && $v > $f['max_value']) { $num_errors++; $tv["{$n}_error"] = sprintf(_t("err_too_large_S"), $f['max_value']); continue; }
        } elseif ($t == 'float') {
          if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $v)) { $num_errors++; $tv["{$n}_error"] = _t("err_decimal_expected"); continue; }
          if (isset($f['min_value']) && $v < $f['min_value']) { $num_errors++; $tv["{$n}_error"] = sprintf(_t("err_too_small_S"), $f['min_value']); continue; }
          if (isset($f['max_value']) && $v > $f['max_value']) { $num_errors++; $tv["{$n}_error"] = sprintf(_t("err_too_large_S"), $f['max_value']); continue; }
        }
        if (isset($f['regex']) && $f['regex'] && !preg_match($f['regex'], $v)) { $num_errors++; $tv["{$n}_error"] = _t("err_incorrect_value"); continue; }
        if (isset($f['choices']) && !isset($f['choices'][$v])) { $num_errors++; $tv["{$n}_error"] = _t("err_must_be_selected"); continue; }
      }
    }
  } # foreach $fields

  # validator fase 2 dipanggil setelah semua field diperiksa. diberi
  # argumen (&$activerecord, &$tv, $values). return true jika
  # berhasil, false jika gagal. set $tv['_phase2_error'] jika ada error.

  if ($num_errors == 0 && isset($activerecord['phase2_validator_func'])) {
    #echo "DEBUG:calling phase2_validator_func:$activerecord[phase2_validator_func]()<br>";
    if (!$activerecord['phase2_validator_func'](&$activerecord, &$tv, $values)) $num_errors++;
  }

  return $num_errors;
}

function activerecord_gensql_insert(&$activerecord, $values, $sqlcommand="INSERT") { # INSERT IGNORE, REPLACE
  $fields = $activerecord['fields'];
  $id_field = $activerecord['id_field'];

  $sqlvars = array();
  $sqlvals = array();
  $sql = "$sqlcommand INTO $activerecord[table] ";

  foreach ($fields as $f) {
    $t = $f['datatype'];
    $n = $f['name'];

    if (isset($f['dbcolumn']) && $f['dbcolumn'] === false) continue; # not a db column

    # XXX untuk yang 'file', ada opsi utk jangan insert ke DB melainkan ke file?

    # untuk kolom autoincrement, jangan set eksplisit
    if ($n == $id_field && (isset($f['autogenerated']) && $f['autogenerated'])) continue;

    if ($t == 'date') {
      $v = ($values["{$n}_year"]+0)."-".($values["{$n}_mon"]+0)."-".($values["{$n}_day"]+0);
    } else {
      $v = $values[$n];
    }

    $sqlvars[] = $n; $sqlvals[] = $v;
  }

  $sql .= "(" . join(", ", $sqlvars) . ") VALUES (";
  for ($i=0; $i < count($sqlvals); $i++) $sql .= ($i > 0 ? ", ":"") . "'" . mysql_escape_string($sqlvals[$i]) . "'";
  $sql .= ")";

  return $sql;
}

# mengembalikan empty string jika tidak ada value yang ingin diubah
# (atau id tidak disebutkan)

function activerecord_gensql_update(&$activerecord, $values, $allow_id_change=false) {
  $fields = $activerecord['fields'];
  $id_field = $activerecord['id_field'];

  $sqlvals = array();
  $sql = "UPDATE $activerecord[table] SET ";

  if (!isset($values[$id_field]) || $values[$id_field] === '') return "";

  foreach ($fields as $f) {
    $t = $f['datatype'];
    $n = $f['name'];

    if (isset($f['dbcolumn']) && $f['dbcolumn'] === false) continue; # not a db column

    # XXX untuk yang 'file', ada opsi utk jangan update ke DB melainkan ke file?

    # untuk kolom autoincrement, jangan set eksplisit (jika id tidak boleh diubah)
    if ($n == $id_field && !$allow_id_change) continue;

    if ($t == 'date') {
      if ($n == $id_field) {
        $v = ($values["new_{$n}_year"]+0)."-".($values["new_{$n}_mon"]+0)."-".($values["new_{$n}_day"]+0);
      } else {
        $v = ($values["{$n}_year"]+0)."-".($values["{$n}_mon"]+0)."-".($values["{$n}_day"]+0);
      }
    } else {
      if ($n == $id_field) {
        $v = $values["new_$n"];
      } else {
        $v = $values[$n];
      }
    }

    $sqlvals[$n] = "'".mysql_escape_string($v)."'";
  }

  if (!count($sqlvals[$n])) return "";

  $i = 0;
  foreach ($sqlvals as $k => $v) {
    $sql .= ($i ? ", " : "") . "$k=$v";
    $i++;
  }

  $sql .= " WHERE $id_field='".mysql_escape_string($values[$id_field])."'";

  return $sql;
}

# mencari nilai "cb_\d+" di dalam $values lalu menghasilkan perintah
# SQL DELETE (atau string kosong '' jika tidak ada id yang ditemukan

function activerecord_gensql_delete(&$activerecord, $values) {
  $fields = $activerecord['fields'];

  # XXX untuk yang bertipe 'file', ada opsi utk juga hapus filenya?

  $ids = array();
  foreach ($values as $k => $v) {
    if (preg_match('/^cb_\d+$/', $k)) $ids[] = "'".mysql_escape_string($v)."'";
  }

  if (count($ids)) {
    return "DELETE FROM $activerecord[table] WHERE $activerecord[id_field] IN (".join(", ",$ids).")";
  } else {
    return "";
  }
}

# return an assoc array as row
function activerecord_selectrow(&$activerecord, $wheres) {
  $fields = $activerecord['fields'];
  $flds = array();
  foreach ($fields as $f) {
    if (isset($f['dbcolumn']) && $f['dbcolumn'] === false) continue; # not a db column
    $n = $f['name'];
    $flds[] = $n;
    if ($f['datatype'] == 'date') {
      $flds[] = "DAYOFMONTH($n) AS {$n}_day";
      $flds[] = "MONTH($n) AS {$n}_mon";
      $flds[] = "YEAR($n) AS {$n}_year";
    }
  }

  # XXX untuk yang bertipe 'file', ada opsi utk load dari file?

  $sql = "SELECT ".join(",",$flds)." FROM $activerecord[table]";
  if ($wheres) $sql .= " WHERE (".join(") AND (", $wheres).")";

  $res = mysql_query($sql) or die("ERR20041125F: ".mysql_error().", SQL=$sql");
  $row = mysql_fetch_assoc($res);

  return $row;
}

# membersihkan session data utk menyimpan uploaded files

function activerecord_cleanuphtml_form(&$activerecord) {
  if (!isset($_SESSION['_ar_form_files'])) return;

  foreach ($activerecord['fields'] as $f) {
    if (!isset($f['file']) || !$f['file']) continue;
    unset($_SESSION['_ar_form_files'][$f['name']]);
  }
}

function _activerecord_genhtml_form_errmsg($msg) {
  return "<font color=#cc0000><b>$msg</b></font>";
}

function activerecord_genhtml_form(&$activerecord, $values, $is_adding=true) { # is_adding=true utk add form, false utk edit form
  global $ENV;

  $ajax = isset($_REQUEST['_ajax']) && $_REQUEST['_ajax'] == 'dialog';

  $fields = $activerecord['fields'];
  $tv = array(); activerecord_fill($activerecord, $tv, $values);
  $issubmit = isset($values['_submit']) && $values['_submit']; if ($issubmit) activerecord_check($activerecord, $tv, $values, $is_adding);

  $containfiles = false;
  foreach ($fields as $f) {
    if (isset($f['file']) && $f['file']) { $containfiles = true; break; }
  }
  if ($containfiles) {
    if (!isset($_SESSION['_ar_form_files'])) $_SESSION['_ar_form_files'] = array();
  }

  if (isset($activerecord['self'])) $self = $activerecord['self']; else $self = $_SERVER['PHP_SELF'];

  $fname = isset($activerecord['name']) ? $activerecord['name'] : 'form1a';

  $html = "";
  #$html .= "issubmit = $issubmit, values = <pre>".print_r($values, true)."</pre>, tv = <pre>".print_r($tv, true)."</pre>";
  $html .= '
<script>
function '.$fname.'_status_bar(text, duration) {
  //alert("DEBUG:status bar: "+text)
  var j = $("#'.$fname.'_statusBar")
  j.html(text)
  j.fadeIn(1)
  if (duration > 0) {
    setTimeout(function() { j.fadeOut("slow") }, duration)
  }
}

var '.$fname.'_ajax_submitting = false
function '.$fname.'_ajax_submit(button) {
  if ('.$fname.'_ajax_submitting) return
  '.$fname.'_status_bar("'._t("please_wait").'", 0)
  '.$fname.'_ajax_submitting = true
  var data = {}
  var els = document.'.$fname.'.elements
  var i
  for (i in els) {
    el = els[i]
    if (!el) continue
    name = el.name; value = el.value; t = el.type
    if (typeof(name)=="undefined" || typeof(value)=="undefined") continue
    if (t=="submit") continue
    if (t=="checkbox" && !el.checked) continue
    data[name] = value
  }
  if (button) data[button.name] = button.value
  data["_ajax"] = 1
  url = document.'.$fname.'.action
  if (!url) url = document.location.href
  $.ajax({
    type: "post",
    url: url,
    data: data,
    success: function (resp) {
      // differs in grid, this time in activerecord we directly accept javascript
      var success=true
      try {
        eval("cmds = "+resp)
      } catch(err) {
        alert("eval ajax response error, err:"+err+", resp:\n\n"+resp)
        success=false
      }
      '.$fname.'_status_bar((success ? "'._t("success").'" : "'._t("failed").'"), 3000)
      '.$fname.'_ajax_submitting = false
    },
    error: function(xhr, ajaxOptions, thrownError) {
      alert(xhr.status + ": " + xhr.responseText)
      '.$fname.'_status_bar("'._t("failed").'", 3000)
    }
  })
}
</script>
';

  $html .= "<form id=$fname name=$fname action=\"".htmlentities($self, ENT_COMPAT, "UTF-8")."\" method=POST".($containfiles ? " enctype=multipart/form-data" : "").">\n";
  if ($ajax) $html .= "  <input type=hidden name=_ajax value=dialog>\n";
  $html .= "  <input type=hidden name=_submit value=1>\n";
  $html .= "  <input type=hidden name=_done value=\"".(isset($_REQUEST['_done']) ? $_REQUEST['_done'] : ($ENV['HTTP_REFERER'] ? $ENV['HTTP_REFERER'] : $self))."\">\n";
  $html .= "  <input type=hidden name=_action value=\"".(isset($values['_action']) ? $values['_action'] : (isset($_REQUEST['_action']) ? $_REQUEST['_action'] : ""))."\">\n";

  # sebutkan dulu semua hidden field, biar convenient aja ada di luar <table>
  foreach ($fields as $f) {
    $n = $f['name'];
    $v = isset($values[$n]) ? $values[$n] : (isset($f['default']) ? $f['default'] : "");

    if (isset($f['hidden']) && $f['hidden']) {
      if (is_array($v)) {
        foreach ($v as $vi) {
          $html .= "  <input type=hidden name=\"$n".'[]'."\" value=\"".htmlentities($vi, ENT_COMPAT, "UTF-8")."\">\n";
        }
      } else {
        $html .= "  <input type=hidden name=$n value=\"".htmlentities($v, ENT_COMPAT, "UTF-8")."\">\n";
      }
      if (isset($tv["{$n}_error"]) && $tv["{$n}_error"]) $html .= _activerecord_genhtml_form_errmsg("$n: ".$tv["{$n}_error"])."<br>\n";
    }
  }

  $html .= "  <table border=".(isset($activerecord['html_table_border']) ? $activerecord['html_table_border'] : 0)." style='border-collapse: collapse' bordercolor=#111111 cellpadding=3>\n";
  if (isset($tv['_phase1_error'])) $html .= "<tr><td colspan=2>"._activerecord_genhtml_form_errmsg($tv['_phase1_error'])."</td></tr>\n";
  if (isset($tv['_phase2_error'])) $html .= "<tr><td colspan=2>"._activerecord_genhtml_form_errmsg($tv['_phase2_error'])."</td></tr>\n";

  $i = 0;
  $focus_info = null;
  for ($i=0; $i<count($fields); $i++) {
    $f = $fields[$i];
    if (isset($f['hidden']) && $f['hidden']) continue;

    $n = $f['name'];
    $fieldn = $n;
    $t = $f['datatype'];
    $v = isset($values[$n]) ? $values[$n] : (isset($f['default']) ? $f['default'] : "");
    $title = $f['title'];
    $iserror = $issubmit && isset($tv["{$n}_error"]) && $tv["{$n}_error"];
    $is_samerow = isset($f['samerow']) && $f['samerow'];

    $html .= "   ".
      ($is_samerow ? "&nbsp;&nbsp;&nbsp;" : "<tr><td>").
      $title.(isset($f['required']) && $f['required'] ? "<font color=#cc0000>*</font>":"").
      ($is_samerow ? "&nbsp;" : "</td><td>");

    if (is_array($v)) {
      $vv = $v;
      $fieldn .= '[]';
    } else {
      $vv = array($v);
    }

    $j = 0;
    foreach ($vv as $v) {
      $j++;
      if (isset($tv["{$n}_widget"])) { # e.g. date fields or fields with choices (<select>), for which activerecord_fill() has made an html widget for us...
        $html .= $tv["{$n}_widget"];
        if ($iserror) $html .= " "._activerecord_genhtml_form_errmsg($tv["{$n}_error"]);
      } else { # normal <input> or <textarea>
        if (isset($f['textarea_width']) && isset($f['textarea_height'])) {
          if ($iserror) $html .= _activerecord_genhtml_form_errmsg($tv["{$n}_error"])."<br>";
          $html .= "<textarea name=$n id=$n wrap=off ".
                    (isset($f['read_only']) ? " readonly" : "").
                    "cols=$f[textarea_width] rows=$f[textarea_height]>".htmlentities($v, ENT_COMPAT, "UTF-8")."</textarea>";
        } else {
          $isfile = isset($f['file']) && $f['file'] ? true : false;
          $html .= "<input ".($isfile ? "type=file" : (isset($f['password']) && $f['password'] ? "type=password":"")).
                    " name=$fieldn".
                    (!$isfile ? " value=\"".htmlentities($v, ENT_COMPAT, "UTF-8")."\"" : "").
                    (isset($f['read_only']) ? " readonly" : "").
                    (isset($f['input_width']) ? " size=$f[input_width]" : "").
                    (!$isfile && isset($f['max_length']) ? " maxlength=$f[max_length]" : "").
                    ">";
          if ($isfile && isset($_SESSION['_ar_form_files'][$n])) $html .= " <font color=blue>(".sprintf(_t("uploaded_F"), show_sizewithunit(strlen($_SESSION['_ar_form_files'][$n]))).")</font>";
          if ($iserror && $j==1) $html .= " "._activerecord_genhtml_form_errmsg($tv["{$n}_error"]);
        }
        if (isset($f['jsfocus']) && $f['jsfocus'] && !isset($focus_info)) $focus_info = array($n, $f['jsfocus']);
      }
      if (isset($f['description']))
        $html .= "<br>$f[description]";
      if ($j < count($vv)) $html .= "<br>\n";
    }

    $will_samerow = false;
    for ($j=$i+1; $j<count($fields); $j++) {
      $f2 = $fields[$j];
      $hidden2 = isset($f2['hidden']) && $f2['hidden'];
      $samerow2 = isset($f2['samerow']) && $f2['samerow'];
      if (!$hidden2 && $samerow2) { $will_samerow = true; break; }
    }
    if (!$will_samerow) $html .= "</td></tr>\n";
  }

  if (isset($activerecord['actions'])) $actions = $activerecord['actions'];
  else $actions = array(array('name'=>'', 'title'=>'Submit'));

  $html .= "    <tr><td>&nbsp;</td><td>";
  foreach ($actions as $a) {
    $html .= "<input type=submit".
      (strlen($a['name']) ? " name=\"$a[name]\" " : "").
      (isset($a['ajax']) && $a['ajax'] == 'submit' ? " onClick=\"".(isset($a['confirm']) ? 'if(!confirm('.jsstring_quote($a['confirm']).'))return false;':'')."${fname}_ajax_submit(this); return false\"" : "").
      " value=\"".htmlentities($a['title'])."\">&nbsp;";
  }
  $html .=
    ($ajax ? ' <input type=button class=ajaxAction value=Cancel onClick="parent.$.modal.close();return false">' : "").
    "<span id={$fname}_statusBar class=\"statusBar\"></span>".
    "</td></tr>\n";
  $html .= "  </table>\n";
  $html .= "  </form>\n";

  if (isset($focus_info)) {
    $html .= '<script>$(function(){';
    list ($elem, $info) = $focus_info;
    if (is_array($info)) {
      list ($st, $end) = $info;
      $html .= '
  el=document.'.$fname.'.'.$elem.'
  if(el.setSelectionRange) {
    el.focus()
    el.setSelectionRange('.$st.', '.$end.')
  } else {
    if(el.createTextRange) {
      range=el.createTextRange()
      range.collapse(true)
      range.moveEnd("character",'.$end.')
      range.moveStart("character",'.$st.')
     range.select()
    }
  }
';
    } else {
      $html .= 'document.'.$fname.'.'.$elem.'.focus()';
    }
    $html .= '})</script>'."\n";
  }

  return $html;
}

?>
