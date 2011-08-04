<?php

function grid_genhtml(&$grid) {
  return _grid($grid, 'genhtml');
}

function grid_exportcsv(&$grid) {
  return _grid($grid, 'exportcsv');
}

function grid_urlprefix(&$grid) {
  return _grid($grid, 'urlprefix');
}

# XXX copy-pasted from mkey
function _csvq($str) {
  $str = preg_replace('/([\015\012\011])+/', ' ', $str);
  $str = preg_replace('/"/', "'", $str);
  return "\"$str\"";
}

# func: genhtml, exportcsv, urlprefix
function _grid(&$grid, $func) {

  # utk template substitution
  $grid_vars = array();

  $extra_columns = array();

  # tentukan sort field and order yang akan dipakai
  if (isset($grid['default_sort_column'])) {
    $sortf = $grid['default_sort_column'];
    $sorto = (isset($grid['default_sort_column_order']) && $grid['default_sort_column_order']=='DESC') ? 'DESC' : 'ASC';
  } else {
    $sortf = '';
    $sorto = 'ASC';
  }
  if (isset($grid['default_sort2_column'])) {
    $sort2f = $grid['default_sort2_column'];
    $sort2o = (isset($grid['default_sort2_column_order']) && $grid['default_sort2_column_order']=='DESC') ? 'DESC' : 'ASC';
  } else {
    $sort2f = '';
    $sort2o = 'ASC';
  }
  if (isset($grid['default_sort3_column'])) {
    $sort3f = $grid['default_sort3_column'];
    $sort3o = (isset($grid['default_sort3_column_order']) && $grid['default_sort3_column_order']=='DESC') ? 'DESC' : 'ASC';
  } else {
    $sort3f = '';
    $sort3o = 'ASC';
  }

  $sort_fields = array(); # available sort fields
  $sort_fields_re = "";
  foreach ($grid['columns'] as $col) {
    if (isset($col['dbcolumn']) && isset($col['sortable']) && $col['sortable']) {
      $sort_fields[] = $col['dbcolumn'];
      $sort_fields_re .= (strlen($sort_fields_re) ? "|" : "") . preg_quote($col['dbcolumn']); # XXX mestinya sih disort by the longest. image if there's 'id' and 'idea'. but argh, php sucks, doesn't have map/collect.
    }
  }
  $sort_fields_re = "/^(-?)($sort_fields_re)\$/";

  if (isset($_REQUEST['_sort']) && preg_match($sort_fields_re, $_REQUEST['_sort'], $m)) {
    $sortf = $m[2];
    $sorto = $m[1] ? 'DESC' : 'ASC';
  }
  if (isset($_REQUEST['_sort2']) && preg_match($sort_fields_re, $_REQUEST['_sort2'], $m)) {
    $sort2f = $m[2];
    $sort2o = $m[1] ? 'DESC' : 'ASC';
  }
  if (isset($_REQUEST['_sort3']) && preg_match($sort_fields_re, $_REQUEST['_sort3'], $m)) {
    $sort3f = $m[2];
    $sort3o = $m[1] ? 'DESC' : 'ASC';
  }
  $grid_vars['_sort']  = $sort  = ($sorto  == 'DESC' ? '-' : '').$sortf;
  $grid_vars['_sort2'] = $sort2 = ($sort2o == 'DESC' ? '-' : '').$sort2f;
  $grid_vars['_sort3'] = $sort3 = ($sort3o == 'DESC' ? '-' : '').$sort3f;

  # null sorting (NULLs last or NULLs first [default])
  $sortn = ''; $sort2n = ''; $sort3n = '';
  foreach ($grid['columns'] as $col) {
    if ($sortf && isset($col['dbcolumn']) && $col['dbcolumn'] == $sortf && isset($col['null_sorting']) && $col['null_sorting'] == 'last') {
      $sortn = "IF($sortf IS NULL,1,0) $sorto,";
      #$extra_columns[] = "IF($sortf IS NULL,1,0) AS _grdsoft_$sortf";
    }
    if ($sort2f && isset($col['dbcolumn']) && $col['dbcolumn'] == $sort2f && isset($col['null_sorting']) && $col['null_sorting'] == 'last') {
      $sort2n = "IF($sort2f IS NULL,1,0) $sort2o,";
      #$extra_columns[] = "IF($sort2f IS NULL,1,0) AS _grdsoft_$sort2f";
    }
    if ($sort3f && isset($col['dbcolumn']) && $col['dbcolumn'] == $sort3f && isset($col['null_sorting']) && $col['null_sorting'] == 'last') {
      $sort3n = "IF($sort3f IS NULL,1,0) $sort3o,";
      #$extra_columns[] = "IF($sort3f IS NULL,1,0) AS _grdsoft_$sort3f";
    }
  }

  $time1 = microtime(true);

  $self = isset($grid['self']) ? $grid['self'] : $_SERVER['PHP_SELF'];
  $form_name = isset($grid['form_name']) ? $grid['form_name'] : 'form1';

  # url prefix
  $page_size = isset($grid['page_size']) ? $grid['page_size'] : 25;
  $page = isset($_REQUEST['_page']) ? floor($_REQUEST['_page']+0) : 1;

  $filters_urlp = "";
  if (isset($grid['filters'])) { foreach ($grid['filters'] as $filter) {
    $n = $filter['name'];
    $filters_urlp .= (isset($_REQUEST[$n]) ? "&" . urlencode($n) . "=" . urlencode($_REQUEST[$n]) : "");
  }}
  $ba_urlp = isset($grid['browse_action']) ? "&_action=".urlencode($grid['browse_action']) : "";
  $urlprefix = $grid_vars["_urlprefix"] = "$self?_page=$page&_sort=$sort$filters_urlp$ba_urlp";

  if ($func == 'urlprefix') return $urlprefix;

  $html_ajax_status_bar = "<div id={$form_name}_statusBar class=\"statusBar\"></div>";

  $update_freq = 1000 * (isset($grid['ajax_update_frequency']) ? $grid['ajax_update_frequency'] : 0);

  $html_script1 = '
<!-- BEGIN grid '.$form_name.' -->
<script>

var '.$form_name.'_selected_ids = Array()
var '.$form_name.'_dialog_ok

// update the checked state of select-all checkbox, depending whether there are some unchecked buttons
function '.$form_name.'_update_cball_state() {
  has_rows = false; some_off = false
  $("#'.$form_name.'_dataTable .dataRow input[name^=_cb_]").each(function(i) {
    has_rows = true; if (!this.checked) some_off = true
  });
  $("#'.$form_name.'_dataTable .headerRow input[name=_cball]").each(function(i){ this.checked=(has_rows && !some_off) })
}

function '.$form_name.'_some_cb_selected() {
  res = false
  $("#'.$form_name.'_dataTable .dataRow input[name^=_cb_]").each(function(i) {
    has_rows = true; if (this.checked) res = true;
  });
  return res;
}

// update the checked state of checkboxes
function '.$form_name.'_update_cboxes_state() {
  $("#'.$form_name.'_dataTable .dataRow input[name^=\'_cb_\']").each(function(i){
    '.$form_name.'_select_unselect_row(this.value, '.$form_name.'_selected_ids[this.value])
  })
}

function '.$form_name.'_select_unselect_row(id, c, ex) {
  $("#'.$form_name.'_dataTable .dataRow input[name=\'_cb_"+id+"\']").each(function(i){
    this.checked = c
    if (c) '.$form_name.'_selected_ids[id] = true; else delete('.$form_name.'_selected_ids[id])
    j=$("#'.$form_name.'_dataTable tr:has(input[name=\'_cb_"+id+"\'])")
    if (c) j.addClass("selected"); else j.removeClass("selected")
    if (!ex) '.$form_name.'_update_cball_state()
  })
}

function '.$form_name.'_status_bar(text, duration) {
  //alert("DEBUG:status bar: "+text)
  j = $("#'.$form_name.'_statusBar")
  j.html(text)
  j.fadeIn(1)
  if (duration > 0) {
    setTimeout(function() { j.fadeOut("slow") }, duration)
  }
}

function '.$form_name.'_select_unselect_all(c) {
  $("#'.$form_name.'_dataTable .dataRow input[name^=_cb_]").each(function(i){
    '.$form_name.'_select_unselect_row(this.value, c, 1) // using ex=1 speeds up a bit
  })
}

function '.$form_name.'_submit2url(button) {
  var url = document.'.$form_name.'.action
  if (!url) url = document.location.href
  var glue = url.indexOf("?") == -1 ? "?" : "&"
  var els = document.'.$form_name.'.elements
  var i
  for (i in els) {
    var el = els[i]
    if (!el) continue
    var name = el.name; var value = el.value; var t = el.type
    if (typeof(name)=="undefined" || typeof(value)=="undefined") continue
    if (t=="submit") continue
    if (t=="checkbox" && !el.checked) continue
    url += (glue + name + "=" + escape(value))
    glue = "&"
  }
  if (button) url += (glue + button.name + "=" + escape(button.value))
  url += "&.rand="+Math.random()
  return url
}

var '.$form_name.'_ajax_submitting = false
function '.$form_name.'_ajax_submit(button) {
  if ('.$form_name.'_ajax_submitting) return
  '.$form_name.'_status_bar("'._t("updating").'", 0)
  '.$form_name.'_ajax_submitting = true
  data = {}
  els = document.'.$form_name.'.elements
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
  data["_row_count"] = '.$form_name.'_row_count
  data["_row_start"] = '.$form_name.'_row_start
  data["_row_checksums"] = '.$form_name.'_row_checksums.join(",")
  data["_sorts"] = '.$form_name.'_sorts
  data["_filters"] = '.$form_name.'_filters
  //$.post("coba/printreq.php", data, function (d) { $("#result").html(d) }); return
  url = document.'.$form_name.'.action
  if (!url) url = document.location.href
  $.post(url, data, function (resp) {
    // in certain browser, json already parsed
    var cmds
    if (resp.constructor == Array) {
      cmds = resp
    } else {
      try {
        eval("cmds = "+resp)
      } catch(err) {
        alert("ajax response error, resp is not valid json:\n\n"+resp);
        cmds = Array()
      }
    }
    '.$form_name.'_exec_commands(cmds)
    '.$form_name.'_status_bar("'._t("success").'", 3000)
    '.$form_name.'_ajax_submitting = false
  })
}

var '.$form_name.'_ajax_submitting = false
function '.$form_name.'_update_grid(url) {
  '.$form_name.'_status_bar("'._t("updating").'", 0)
  if (!url) url="'.$urlprefix.'"
  $.getJSON(url+
      "&_ajax=1"+
      "&_row_count="+'.$form_name.'_row_count+
      "&_row_start="+'.$form_name.'_row_start+
      "&_row_checksums="+'.$form_name.'_row_checksums.join(",")+
      "&_sorts="+'.$form_name.'_sorts,
    function (data) {
      '.$form_name.'_exec_commands(data)
      '.$form_name.'_status_bar("'._t("success").'", 3000)
    })
}

var '.$form_name.'_fvals = {}
var '.$form_name.'_isearch_sto = 0
function '.$form_name.'_start_incsearch(el, ms, minlen) {
  '.$form_name.'_fvals[el.name] = el.value
  //el.autocomplete = false//doesnt work
  //$("#debug").append("incsearch started. ")
  el.onkeyup = function() {
    //$("#debug").append("key pressed. ")
    if ('.$form_name.'_fvals[this.name] != this.value) {
      //$("#debug").append("changed. ")
      var doit = (this.value.length >= minlen) // || '.$form_name.'_fvals[this.name].length >= minlen)
      '.$form_name.'_fvals[this.name] = this.value
      if (doit) {
        //$("#debug").append("install sto. ")
        if ('.$form_name.'_isearch_sto > 0) clearTimeout('.$form_name.'_isearch_sto)
        '.$form_name.'_isearch_sto = setTimeout('.$form_name.'_ajax_submit, ms)
      }
    }
  }
}

function '.$form_name.'_end_incsearch(el) {
  //$("#debug").append("incsearch ended<br>\n")
  //el.autocomplete = true // XXX shouldve restored to original autocomplete setting // doesnt work
  el.onkeyup = function () {}
  if ('.$form_name.'_isearch_sto > 0) {
    clearTimeout('.$form_name.'_isearch_sto)
    '.$form_name.'_isearch_sto = 0
  }
}

function '.$form_name.'_incsearch(el, ms) {
  if (x && '.$form_name.'_fvals[el.name] == el.value) { x = false }
  //alert("DEBUG:el = "+el+" x = "+x+" fvals="+'.$form_name.'_fvals[el.name]+" elv="+el.value)
}

function '.$form_name.'_exec_commands(cmds) {
  rows_changed = false
  for (j in cmds) {
    var d = cmds[j]
    var cmd; var args
    if (d.constructor == Array) { cmd = d[0]; args = d.splice(1, d.length-1) }
    else { cmd = d; args = [] }
    //alert("DEBUG:cmd #"+j+": "+cmd+", args="+args[0])
    // js sucks. XXX how to flatten args?
    // cmd = "'.$form_name.'_" + cmd
    // window[cmd](args)
    if      (cmd == "update_top_paging_bar")    $("#'.$form_name.'_topPagingBar").replaceWith(args[0])
    else if (cmd == "update_bottom_paging_bar") $("#'.$form_name.'_bottomPagingBar").replaceWith(args[0])
    else if (cmd == "update_filter_bar")        $("#'.$form_name.'_filterBar").replaceWith(args[0])
    else if (cmd == "update_row_count")         '.$form_name.'_row_count = args[0]
    else if (cmd == "update_sorts")             {
      document.'.$form_name.'._sort.value = args[0]
      document.'.$form_name.'._sort2.value = args[1]
      document.'.$form_name.'._sort3.value = args[2]
      '.$form_name.'_sorts = args[0]+","+args[1]+","+args[2]
    } else if (cmd == "update_filters")         {
      '.$form_name.'_filters = args[0]
    } else if (cmd == "update_row_start")       {
      '.$form_name.'_row_start = args[0]
      document.'.$form_name.'._page.value = Math.max(Math.floor(args[0]/'.$page_size.')+1, 1)
    } else if (cmd == "update_header")          {
      //alert("DEBUG:updating header ...")
      $("#'.$form_name.'_dataTable .headerRow").replaceWith(args[0])
    } else if (cmd == "update_row")             {
      rows_changed = true; i = args[0]
      //alert("DEBUG:update row "+i+": "+args[2])
      '.$form_name.'_row_checksums[i] = args[1]
      $("#'.$form_name.'_row_"+i).replaceWith(args[2])
    } else if (cmd == "debug_alert")            {
      alert("DEBUG:"+args[0])
    }
  }
  if (rows_changed) '.$form_name.'_update_cboxes_state()
}

function '.$form_name.'_dialog(url) {
  '.$form_name.'_dialog_ok = 0
  url += ((url.indexOf("?") ? "&" : "?") + "_ajax=dialog")
  $.modal("<iframe src=\'"+url+"\' width=100% height=500 style=\'border:0\'></iframe>",
         {minWidth: "90%",
          minHeight: "90%",
          width: "90%",
          height: "90%",
          onClose: function (dlg) {
            //alert("DEBUG:dialog_ok? "+'.$form_name.'_dialog_ok)
            $.modal.close()
            if ('.$form_name.'_dialog_ok) '.$form_name.'_ajax_submit()
          }})
}
</script>'."\n";

  # tentukan klausa WHERE (dari default clause specified + filters)
  $sql_wheres = array();
  if (isset($grid['sql_wheres']) && count($grid['sql_wheres'])) $sql_wheres = $grid['sql_wheres'];
  #print_r($_REQUEST);
  $all = isset($_REQUEST['_all']) && $_REQUEST['_all']; # _all=1 means turn off all defaults for nonhidden filters
  if (isset($grid['filters'])) { foreach ($grid['filters'] as $filter) {
    $n = $filter['name'];
    $f = $filter['sql_wherefunc'];
    $h = isset($filter['hidden']) && $filter['hidden'];
    $v = isset($_REQUEST[$n]) ?
           $_REQUEST[$n] :
           (isset($filter['default']) && ($h || (!$h && !$all)) ? $filter['default'] : "");
    $grid_vars[$n] = $v;
    $res = $f($v);
    if ($res !== "") $sql_wheres[] = "($res)";
  }}

  $no_paging = $func == 'exportcsv' ? true : false;

  if ((!isset($grid['simple_paging']) || !$grid['simple_paging']) && !$no_paging) {
    if (isset($grid['row_count_func'])) {
      $row_count = $grid['row_count_func']($_REQUEST);
    } else {
      if (isset($grid['sql_customcountfunc'])) {
        $sql = $grid['sql_customcountfunc']($sql_wheres);
      } else {
        $sql = "SELECT COUNT(1) FROM $grid[sql_table]".
          (count($sql_wheres) ? " WHERE ".join(" AND ", $sql_wheres) : "");
      }
      if (isset($grid['debug_showsql']) && $grid['debug_showsql']) echo $sql,"<br>";
      $time1 = microtime(true);
      $res = mysql_query($sql) or die("ERR20041123A[show_grid]: ".mysql_error().", SQL=$sql");
      $time2 = microtime(true);
      if (isset($grid['debug_timesql']) && $grid['debug_timesql']) echo "DEBUG: sql (count) time=",sprintf("%.3fs", $time2-$time1),"<br>";
      list ($row_count) = mysql_fetch_row($res);
    }
    $grid_vars['_row_count'] = isset($row_count) ? $row_count : null;
  }

  # page number
  if ($no_paging) {
    $page_count = 1;
  } elseif (isset($row_count)) {
    $page_count = ceil($row_count / $page_size);
    if ($page > $page_count) $page = $page_count;
  }
  if ($page < 1) $page = 1;
  $grid_vars['_page'] = $page;
  $grid_vars['_page_count'] = $page;

  # for "Showing item X to Y (of Z)"
  if ($no_paging) {
    $item_start = 1;
  } elseif (isset($row_count)) {
    if ($row_count) {
      $item_start = ($page-1)*$page_size+1;
      $item_end = min($row_count, $page*$page_size);
    } else {
      $item_start = 0;
      $item_end = 0;
    }
    $limit_num = $page_size;
  } else {
    $item_start = ($page-1)*$page_size+1;
    $limit_num = $page_size+1;
  }
  $limit_start = max($item_start-1, 0);

  if (isset($grid['row_func'])) {
    $rows = $grid['row_func']($_REQUEST, $sortf, $sorto, $limit_start, $limit_num, $sort2f, $sort2o, $sort3f, $sort3o);
  } else {
    if (isset($grid['sql_customfunc'])) {
      $sql = $grid['sql_customfunc']($sql_wheres, $sortf, $sorto, $limit_start, $limit_num, $sort2f, $sort2o, $sort3f, $sort3o);
    } else {
      $orderbyclause = "";
      if ($sortf) $orderbyclause .= "ORDER BY $sortn$sortf $sorto";
      if ($sort2f && $sort2f != $sortf) $orderbyclause .= ",$sort2n$sort2f $sort2o";
      if ($sort3f && $sort3f != $sortf && $sort3f != $sort2f) $orderbyclause .= ",$sort3n$sort3f $sort3o";

      # XXX escape column names
      $sql = "SELECT $grid[sql_columns]".(count($extra_columns) ? ",".join(", ", $extra_columns) : "")." FROM $grid[sql_table]".
             (count($sql_wheres) ? " WHERE ".join(" AND ", $sql_wheres) : "").
             " ".$orderbyclause.
             ($no_paging ? "" : " LIMIT $limit_start,$limit_num");
    }
    $time1 = microtime(true);
    if (isset($grid['debug_showsql']) && $grid['debug_showsql']) echo $sql,"<br>";
    $res = mysql_query($sql) or die("ERR20041123B[show_grid]: ".mysql_error().", SQL=$sql");
    $time2 = microtime(true);
    if (isset($grid['debug_timesql']) && $grid['debug_timesql']) echo "DEBUG: sql (get rows) time=",sprintf("%.3fs", $time2-$time1),"<br>";
    $rows = array();
    while ($row = mysql_fetch_assoc($res)) {
      if (isset($grid['sqlresult_postprocfunc'])) $grid['sqlresult_postprocfunc'](&$row);
      $rows[] = $row;
    }
    #print_r($rows);
  }

  if ($no_paging) {
    $has_next_page = false;
    $item_end = count($rows);
  } elseif (isset($row_count)) {
    $has_next_page = $page < $page_count;
  } else {
    $has_next_page = false;
    if (count($rows) > $page_size) {
      $has_next_page = true;
      array_pop($rows);
    }
    $item_end = $item_start + (count($rows) ? count($rows)-1 : 0);
  }

  $grid_vars['_item_start'] = $item_start;
  $grid_vars['_item_end'] = $item_end;

  $show_csv_export_link = isset($grid['show_csv_export_link']) ? $grid['show_csv_export_link'] : false;

# build display

  # set/store colors
  $filterbar_bgcolor = isset($grid['filterbar_bgcolor']) ? $grid['filterbar_bgcolor'] : '';
  $filterbar_color   = isset($grid['filterbar_color'])   ? $grid['filterbar_color']   : '';
  $rowheader_bgcolor = isset($grid['rowheader_bgcolor']) ? $grid['rowheader_bgcolor'] : '';
  $rowheader_color   = isset($grid['rowheader_color'])   ? $grid['rowheader_color']   : '';
  $totrow_bgcolor    = isset($grid['totrow_bgcolor'])    ? $grid['totrow_bgcolor']    : '';
  $totrow_color      = isset($grid['totrow_color'])      ? $grid['totrow_color']      : '';
  $avgrow_bgcolor    = isset($grid['avgrow_bgcolor'])    ? $grid['avgrow_bgcolor']    : '';
  $avgrow_color      = isset($grid['avgrow_color'])      ? $grid['avgrow_color']      : '';
  $row_hilitebgcolor = isset($grid['row_hilitebgcolor']) ? $grid['row_hilitebgcolor'] : '';
  $row_bgcolor1      = isset($grid['row_bgcolor1'])      ? $grid['row_bgcolor1']      : '';
  $row_bgcolor2      = isset($grid['row_bgcolor2'])      ? $grid['row_bgcolor2']      : '';
  $row_color         = isset($grid['row_color'])         ? $grid['row_color']         : '';
  $row_bgcolors = array();
  $row_colors   = array();
  $js_row_bgcolors_def = "";
  $i = 1;
  foreach ($rows as $row) {
    $bgc = isset($grid['row_bgcolor_func']) ? $grid['row_bgcolor_func']($row, $i) : ($i % 2 == 0 ? $row_bgcolor2 : $row_bgcolor1);
    $row_bgcolors[] = $bgc;
    $c = isset($grid['row_color_func']) ? $grid['row_color_func']($row) : $row_color;
    $row_colors[] = $c;
    $i++;
    $js_row_bgcolors_def .= (strlen($js_row_bgcolors_def) ? ", " : "") . "'$bgc'";
  }
  $js_row_bgcolors_def = "var {$form_name}_row_bgcolors = new Array($js_row_bgcolors_def);";

  $html_form_open = "<form action=\"".htmlentities($self, ENT_COMPAT, "UTF-8")."\" name=$form_name method=POST>
<input type=hidden name=_page value=$page>
<input type=hidden name=_sort value=$sort>
<input type=hidden name=_sort2 value=$sort2>
<input type=hidden name=_sort3 value=$sort3>\n\n";

  # -- paging (+export link) bar
  $ajax = isset($grid['ajax_paging']) && $grid['ajax_paging'];
  $_html_paging_bar  = "<tr><td align=left>";
  if ($show_csv_export_link) {
    $_html_paging_bar .= "<a href=$self?_action=exportcsv&_sort=$sort&_sort2=$sort2&_sort3=$sort3$filters_urlp$ba_urlp>"._t("export_to_csv")."</a>";
  }
  $_html_paging_bar .= "</td><td align=right>";
  $_html_paging_bar .= (isset($row_count) ?
    sprintf(_t("item_X_Y_of_Z"), $item_start, $item_end, $row_count) :
    sprintf(_t("item_X_Y"), $item_start, $item_end)
                        ).
    "&nbsp;&nbsp;&nbsp;";

  $url = "$self?_page=1&_sort=$sort&_sort2=$sort2&_sort3=$sort3$filters_urlp$ba_urlp";
  $text = _t("first");
  if ($page != 1) {
    $_html_paging_bar .= "<a".
    ($ajax ? " class=ajaxAction onClick=\"{$form_name}_update_grid(this.href);return false;\"" : "").
    " href=$url>$text</a>";
  } else {
    $_html_paging_bar .= $text;
  }
  $_html_paging_bar .= " | ";

  $url = "$self?_page=".($page-1)."&_sort=$sort&_sort2=$sort2&_sort3=$sort3$filters_urlp$ba_urlp";
  $text = _t("previous");
  if ($page != 1) {
    $_html_paging_bar .= "<a".
    ($ajax ? " class=ajaxAction onClick=\"{$form_name}_update_grid(this.href);return false;\"" : "").
    " href=$url>$text</a>";
  } else {
    $_html_paging_bar .= $text;
  }
  $_html_paging_bar .= " | ";

  $url = "$self?_page=".($page+1)."&_sort=$sort&_sort2=$sort2&_sort3=$sort3$filters_urlp$ba_urlp";
  $text = _t("next");
  if ($has_next_page) {
    $_html_paging_bar .= "<a".
    ($ajax ? " class=ajaxAction onClick=\"{$form_name}_update_grid(this.href);return false;\"" : "").
    " href=$url>$text</a>";
  } else {
    $_html_paging_bar .= $text;
  }

  if (isset($row_count)) {
    $_html_paging_bar .= " | ";
    $url = "$self?_page=$page_count&_sort=$sort&_sort2=$sort2&_sort3=$sort3$filters_urlp$ba_urlp";
    $text = _t("last");
    if ($page < $page_count) {
      $_html_paging_bar .= "<a".
        ($ajax ? " class=ajaxAction onClick=\"{$form_name}_update_grid(this.href);return false;\"" : "").
        " href=$url>$text</a>";
    } else {
      $_html_paging_bar .= $text;
    }
  }

  $_html_paging_bar .= "</td></tr>";

  $html_paging_bar_top    = "<table id={$form_name}_topPagingBar    width=100%>$_html_paging_bar</table>\n\n";
  $html_paging_bar_bottom = "<table id={$form_name}_bottomPagingBar width=100%>$_html_paging_bar</table>\n\n";

  # -- actions & filters bar
  $ajax = isset($grid['ajax_filter']) && $grid['ajax_filter'];
  $html_af_bar = "<table id={$form_name}_filterBar width=100% cellpadding=5 cellspacing=0>\n  <tr".
    ($filterbar_bgcolor ? " bgcolor='$filterbar_bgcolor'" : "").">\n";

  $filter_active = false; # is there at least one filter being active?
  $num_nonhidden_filters = 0;
  if (isset($grid['filters'])) { foreach ($grid['filters'] as $filter) {
    $n = $filter['name'];
    $t = "string"; # $filter['datatype']

    if (isset($filter['hidden']) && $filter['hidden']) {
      if (isset($_REQUEST[$n])) {
        $html_form_open .= "<input type=hidden name=$n value=\"".htmlentities($_REQUEST[$n], ENT_COMPAT, "UTF-8")."\">\n\n";
      }
      continue;
    }
    $num_nonhidden_filters++;

    $html_af_bar .= "    <td>".
      ($filterbar_color ? "<font color='$filterbar_color'>" : "");
    if ($t == 'date') { # XXX belum mengenal default

      # XXX

    } elseif (isset($filter['choices'])) {

      $ajax = isset($grid['ajax_filter']) && $grid['ajax_filter'];
      $direct = isset($filter['jsdirect']) && $filter['jsdirect'];
      $w = "";
      $w .= "$filter[title]:&nbsp;<select name=$n".
        ($ajax ? " class=ajaxAction":"").
        ($direct && $ajax ? " onChange=\"{$form_name}_ajax_submit();\"":"").
        ($direct ? " onChange=\"document.$form_name.submit();\"":"").
        ">";
      foreach ($filter['choices'] as $ck => $cv) {
        #echo "$ck, $cv, $grid_vars[$n]<br>";
        if (isset($grid_vars[$n]) && $grid_vars[$n]==$ck && strlen($grid_vars[$n])==strlen($ck)) {
          $w .= "<option value='$ck' selected>$cv";
          $filter_active = true;
        } else {
          $w .= "<option value='$ck'>$cv";
        }
      }
      $w .= "</select>";
      $html_af_bar .= $w;

    } else {

      $w = "";
      $val = "";
      $ajax = isset($grid['ajax_filter']) && $grid['ajax_filter'];
      $incremental = isset($filter['incremental']) && $filter['incremental'] > 0;
      $incminlen = isset($filter['incremental_minlen']) ? $filter['incremental_minlen'] : 0;
      if (isset($grid_vars[$n]) && $grid_vars[$n] !== "") { $filter_active = true; $val = htmlentities($grid_vars[$n], ENT_COMPAT, "UTF-8"); }
      $w .= "$filter[title]:&nbsp;<input name=$n size=10".
      ($ajax ? " class=ajaxAction" : "").
      ($incremental ? " onFocus=\"{$form_name}_start_incsearch(this, $filter[incremental], $incminlen)\"".
                      "  onBlur=\"{$form_name}_end_incsearch(this)\""
                    : "").
      " value=\"$val\">";
      $html_af_bar .= $w;

    }
    $html_af_bar .= ($filterbar_color ? "</font>" : "") .
      "</td>\n";
  }} # foreach filters ...
  #echo "num_nonhidden_filters=$num_nonhidden_filters\n";
  if ($num_nonhidden_filters) $html_af_bar .= "    <td><input type=submit".($ajax ? " class=ajaxAction onClick=\"{$form_name}_ajax_submit();return false\"":"")." name=_action:filter value=\""._t("filter")."\"></td>\n";
  #XXX didisabled dulu
  #if ($filter_active) $html_af_bar .= "&nbsp;&nbsp;<input type=submit name=_action value=\"View All\">";

  if (isset($grid['grid_actions'])) { foreach ($grid['grid_actions'] as $action) {
    $ajax = isset($action['ajax']) ? $action['ajax'] : '';
    $classes = array();
    if ($ajax) $classes[] = "ajaxAction";
    if (isset($action['class'])) $classes[] = $action['class'];
    $html_af_bar .= "    <td>";
    $html_af_bar .= "<input type=submit".
      (count($classes) ? " class=\"".join(" ", $classes)."\"" : "").
      (isset($action['name']) ? " name=_action:$action[name]" : " name=_action").
      " value=\"$action[title]\" onClick=\"".
      (isset($action['target']) ? "this.form.target='$action[target]';" : "this.form.target='';").
      (isset($action['need_rows']) && $action['need_rows'] ? "if(!{$form_name}_some_cb_selected()){alert('"._t("need_rows")."');return false}" : "").
      (isset($action['jsconfirm']) && $action['jsconfirm'] ? "if(!confirm(".jsstring_quote(isset($action['confirm_text']) ? $action['confirm_text'] : _t("are_you_sure"))."))return false;" : "").
      # XXX pre_ajax_commands
      ($ajax == 'update' ? "{$form_name}_ajax_submit(this);" : "").
      ($ajax == 'dialog' ? "{$form_name}_dialog({$form_name}_submit2url(this));" : "").
      ($ajax ? "return false" : "return true").
      "\">";
    $html_af_bar .= "</td>\n";
  }}
  $ajax = isset($grid['ajax_more_actions']) && $grid['ajax_more_actions'];
  if (isset($grid['grid_more_actions']) && count($grid['grid_more_actions'])) {
    $html_af_bar .= "    <td><select name=_more_action".
      ($ajax ? " class=ajaxAction" : "").
      " onChange=\"if(this.selectedIndex==0)return false;".
      ($ajax ? "{$form_name}_ajax_submit();this.selectedIndex=0;return false;" : "document.$form_name.submit();").
      "\">".
      "<option value=''>("._t("more_actions").")";
    foreach ($grid['grid_more_actions'] as $ga) {
      $html_af_bar .= (isset($ga['name']) ? "<option value=$ga[name]>" : "<option>").$ga['title'];
    }
    $html_af_bar .= "</select></td>\n";
  }

  $html_af_bar .= "    <input type=hidden name=_done value=\"".htmlentities($urlprefix, ENT_COMPAT, "UTF-8")."\">\n";
  $html_af_bar .= "  </tr>\n</table>\n\n";


  # -- header
  $html_table_open = "<table width=100% id={$form_name}_dataTable border=1 cellpadding=2 cellspacing=0 style='border-collapse: collapse'".
    ($rowheader_bgcolor ? " bordercolor='$rowheader_bgcolor'" : "").">\n";

  $html_header_row = "  <tr class=headerRow".
    ($rowheader_bgcolor ? " bgcolor='$rowheader_bgcolor'" : "").">";
  if (!isset($grid['hide_checkboxes']) || !$grid['hide_checkboxes'])
    $html_header_row .= "<td>".
      ($rowheader_color ? "<font color=$rowheader_color>" : "").
      "<input type=checkbox name=_cball onClick=\"{$form_name}_select_unselect_all(this.checked);\" value=1>".
      ($rowheader_color ? "</font>" : "").
      "</td>";
  $csv_header_row = "";
  foreach ($grid['columns'] as $col) {
    if (isset($col['hidden']) && $col['hidden']) continue;
    $n = isset($col['dbcolumn']) ? $col['dbcolumn'] : '';
    $html_header_row .= "<td".
      ($rowheader_color ? " bgcolor=$rowheader_bgcolor" : "").
      (isset($col['width']) ? " width=\"$col[width]\"" : "").
      (isset($col['nowrap_header']) && $col['nowrap_header'] ? " nowrap" : "").">".
      ($rowheader_color ? "<font color=$rowheader_color>" : "");
    if (isset($col['sortable']) && $col['sortable']) {
      $default_desc = isset($col['default_sort_order']) && $col['default_sort_order'] == 'DESC';
      if ($default_desc) {
        if ($sort == "-$n") $newsort = "$n"; else $newsort = "-$n";
      } else {
        if ($sort == $n) $newsort = "-$n"; else $newsort = $n;
      }
      if ($n != $sortf) {
        $newsort2 = $sort;
        if ($newsort2 != $sort2) {
          $newsort3 = $sort2;
        } else {
          $newsort3 = $sort3;
        }
      } else {
        $newsort2 = $sort2;
        $newsort3 = $sort3;
      }
      $ajax = isset($grid['ajax_sort']) && $grid['ajax_sort'];
      $url = "$self?_page=$page&_sort=$newsort&_sort2=$newsort2&_sort3=$newsort3$filters_urlp$ba_urlp";
      $html_header_row .= "<a".
        ($ajax ? " class=ajaxAction onClick=\"{$form_name}_update_grid(this.href);return false;\"" : "").
        " href=$url><b>$col[title]</b></a>";
      if ($sort == $n)         $html_header_row .= "&nbsp;&#187;";
      elseif ($sort == "-$n")  $html_header_row .= "&nbsp;&#171;";
      elseif ($sort2 == $n)    $html_header_row .= "&nbsp;&#187;&nbsp;&#187;";
      elseif ($sort2 == "-$n") $html_header_row .= "&nbsp;&#171;&nbsp;&#171;";
      elseif ($sort3 == $n)    $html_header_row .= "&nbsp;&#187;&nbsp;&#187;&nbsp;&#187;";
      elseif ($sort3 == "-$n") $html_header_row .= "&nbsp;&#171;&nbsp;&#171;&nbsp;&#171;";
    } else {
      $html_header_row .= "<b>$col[title]</b>";
    }
    $html_header_row .= ($rowheader_color ? "</font>" : "").
      "</td>";
    $csv_header_row .= (strlen($csv_header_row) ? ";" : "") . _csvq($col['title']);
  }
  if (!isset($grid['hide_rowactions']) || !$grid['hide_rowactions'])
    $html_header_row .= "<td".
      ($rowheader_bgcolor ? " bgcolor=$rowheader_bgcolor" : "").">".
      "&nbsp;".
      "</td>";
  $html_header_row .= "</tr>\n";

  $i=0;
  # -- data rows
  $tots = array();
  $checksums = array();
  $html_data_rows = array();
  $csv_data_rows = array();
  foreach ($rows as $row) {
    # utk template substitution
    $row_vars = array_merge($grid_vars, $row);

    $row_vars['_done'] = $urlprefix; # url untuk kembali ke view yang sama
    $row_vars['_rownum'] = $i;

    # row actions
    if (isset($grid['row_actions'])) { foreach ($grid['row_actions'] as $ra) {
      $row_vars["ra_$ra[name]_title"] = $ra['title'];
      if (isset($ra['url_template'])) {
        $row_vars["ra_$ra[name]_url"] = filltemplate($ra['url_template'], $row_vars);
      } else {
        # php sucks so bad...
        $args = null;
        if (isset($ra['args'])) {
          $args = "";
          foreach ($ra['args'] as $k=>$v) { $args .= ($args ? "&" : "") . urlencode($k)."=".urlencode($v); }
        }
        $row_vars["ra_$ra[name]_url"] = "$urlprefix&_action=$ra[name]".
          (isset($args) && $args ? "&$args" : "").
          "&{$grid['id_column']}={$row[$grid['id_column']]}&_done=".urlencode($row_vars['_done']);
      }
    }}

    $html_data_row = "  <tr class=\"dataRow".(isset($row_vars['__css_class']) ? " $row_vars[__css_class]":"")."\" id={$form_name}_row_$i>";
    $csv_data_row = "";
    if (!isset($grid['hide_checkboxes']) || !$grid['hide_checkboxes'])
      $html_data_row .= "<td".
        ($row_bgcolors[$i] ? " bgcolor='".$row_bgcolors[$i]."'" : "").">".
        "<input type=checkbox name=_cb_{$row[$grid['id_column']]} value={$row[$grid['id_column']]} onClick=\"{$form_name}_select_unselect_row({$row[$grid['id_column']]}, this.checked)\"></td>";

    foreach ($grid['columns'] as $col) {
      $cn = isset($col['dbcolumn']) ? $col['dbcolumn'] : '';
      # tentukan warna
      $color = isset($col['color']) ?
        ($row_colors[$i] ? mix_2_rgb_colors($col['color'], $row_colors[$i]) : $col['color']) :
        $row_colors[$i];
      $bgcolor0 = isset($col['bgcolor']) ? $col['bgcolor'] :
        ($cn && ($sort ==$cn || $sort =="-$cn") ? "#e0e0e0" :
        ($cn && ($sort2==$cn || $sort2=="-$cn") ? "#e8e8e8" :
        ($cn && ($sort3==$cn || $sort3=="-$cn") ? "#f0f0f0" : null)));
      $bgcolor = isset($bgcolor0) ?
        mix_2_rgb_colors($bgcolor0, $row_bgcolors[$i]) : $row_bgcolors[$i];

      if (isset($col['hidden']) && $col['hidden']) continue;
      $html_data_row .= "<td".(isset($col['nowrap_data']) && $col['nowrap_data'] ? " nowrap" : "").
                   (isset($col['align_data']) ? " align=\"$col[align_data]\"" : "").
                   (isset($bgcolor) && $bgcolor ? " bgcolor=\"$bgcolor\"" : "").
                   ">".
             (isset($color) && $color ? "<font color=\"$color\">" : "");
      $csv_data_row .= strlen($csv_data_row) ? ";" : "";

      # print value
      if (isset($col['value_func'])) {
        if ($func != 'exportcsv') $html_data_row .=       $col['value_func']($row_vars, "html");
        if ($func == 'exportcsv') $csv_data_row  .= _csvq($col['value_func']($row_vars, "csv" ));
      } elseif (isset($col['value_template'])) {
        if ($func != 'exportcsv') $html_data_row .=       filltemplate($col['value_template'], $row_vars);
        if ($func == 'exportcsv') $csv_data_row  .= _csvq(filltemplate($col['value_template'], $row_vars, true));
      } else {
        if ($func != 'exportcsv') $html_data_row .=       $row_vars[$col['dbcolumn']];
        if ($func == 'exportcsv') $csv_data_row  .= _csvq($row_vars[$col['dbcolumn']]);
      }

      # collect total
      if ($cn &&
          ((isset($col['calc_total']) && $col['calc_total']) || (isset($col['calc_average']) && $col['calc_average']))) {
        if (!isset($tots[$cn])) $tots[$cn] = 0;
        #echo "[collecting total:i=$i,cn=$cn,tot=".$tots[$cn]."+".$row_vars[$cn]."]";
        $tots[$cn] += $row_vars[$cn];
      }

      $html_data_row .= ($row_colors[$i] ? "</font>" : "").
        "</td>";
    }

    # calculate checksum
    $checksums[] = $row[$grid['id_column']] . '_' . substr(md5(json_encode($row)),0,8);

    # tampilkan row actions
    if (!isset($grid['hide_rowactions']) || !$grid['hide_rowactions']) {
      $html_data_row .= "<td class=rowActions".
        (isset($grid['nowrap_rowactions']) && $grid['nowrap_rowactions'] ?" nowrap" : "").
        ($row_bgcolors[$i] ? " bgcolor='".$row_bgcolors[$i]."'" : "").">";
      if (isset($grid['row_actions'])) { foreach ($grid['row_actions'] as $ra) {
        if (isset($ra['hidden']) && $ra['hidden']) continue;
        if (isset($ra['condfunc']) && !($ra['condfunc']($row_vars))) continue;

        $ajax = isset($ra['ajax']) ? $ra['ajax'] : '';
        $classes = array();
        if ($ajax) $classes[] = "ajaxAction";
        if (isset($ra['class'])) $classes[] = $ra['class'];
        $html_data_row .= "<a href=".$row_vars["ra_{$ra['name']}_url"].
          (count($classes) ? " class=\"".join(" ", $classes)."\"" : "").
          " onClick=\"".
          (isset($ra['jsconfirm']) && $ra['jsconfirm'] ? "if(!confirm(".jsstring_quote(isset($ra['confirm_text']) ? $ra['confirm_text'] : _t("are_you_sure"))."))return false;" : "").
          # XXX pre_ajax_commands
          ($ajax=='update' ? "{$form_name}_update_grid(this.href);":"").
          ($ajax=='dialog' ? "{$form_name}_dialog(this.href);":"").
          ($ajax ? "return false;" : "return true;").
          "\">".(isset($ra['icon']) ? "<img src=\"$ra[icon]\" title=\"$ra[title]\" alt=\"$ra[title]\">" : $ra['title'])."</a> ";
      }}
      $html_data_row .= "</td>";
    }
    $html_data_row .= "</tr>\n";
    $csv_data_row  .= "\n";

    $html_data_rows[] = $html_data_row;
    $csv_data_rows[]  = $csv_data_row;
    $i++;
  }

  if ($func == 'exportcsv')
    return $csv_header_row . "\n" . join("", $csv_data_rows);

  while ($i < $page_size) {
    $html_data_rows[] = "  <tr style='display: none' class=dataRow id={$form_name}_row_$i></tr>\n";
    $i++;
  }

  $html_tot_row = "";
  $html_avg_row = "";
  if ((!isset($grid['hide_totavgrow']) || !$grid['hide_totavgrow']) && count($tots)) {
  # -- total row
  $do_tot = false; foreach ($grid['columns'] as $col) { if (isset($col['calc_total']) && $col['calc_total']) { $do_tot = true; break; } }
  if ($do_tot) {
  $row_vars = array_merge($grid_vars, $tots);
  $row_vars['_done'] = $urlprefix;
  $html_tot_row .= "  <tr class=totRow>";
  if (!isset($grid['hide_checkboxes']) || !$grid['hide_checkboxes'])
    $html_tot_row .= "<td".($totrow_bgcolor ? " bgcolor='$totrow_bgcolor'" : "").">".
    ($totrow_color   ? "<font color=\"$totrow_color\">" : "").
    _t("T_total").
    ($totrow_color   ? "</font>" : "").
    "</td>";
  foreach ($grid['columns'] as $col) {
    $cn = isset($col['dbcolumn']) ? $col['dbcolumn'] : '';
    if (isset($col['hidden']) && $col['hidden']) continue;
    $html_tot_row .= "<td".(isset($col['nowrap_data']) && $col['nowrap_data'] ? " nowrap" : "").
                 (isset($col['align_data']) ? " align=\"$col[align_data]\"" : "").
                 (isset($totrow_bgcolor) && $totrow_bgcolor ? " bgcolor=\"$totrow_bgcolor\"" : "").
                 ">".
           (isset($totrow_color) && $totrow_color ? "<font color=\"$totrow_color\">" : "");
    # print value
    if (isset($col['calc_total']) && $col['calc_total']) {
      if (isset($col['value_func'])) {
        $html_tot_row .= $col['value_func']($row_vars, "html");
      } elseif (isset($col['value_template'])) {
        $html_tot_row .= filltemplate($col['value_template'], $row_vars);
      } else {
        $html_tot_row .= $tots[$cn];
      }
    } else {
      $html_tot_row .= " ";
    }
    $html_tot_row .= ($totrow_color ? "</font>" : "").
      "</td>";
  }
  if (!isset($grid['hide_rowactions']) || !$grid['hide_rowactions'])
    $html_tot_row .= "<td".
      ($totrow_bgcolor ? " bgcolor=$totrow_bgcolor" : "").">".
      ($totrow_color   ? "<font color=\"$totrow_color\">" : "").
      "TOTAL".
      ($totrow_color   ? "</font>" : "").
      "</td>";
  $html_tot_row .= "</tr>\n";
  } # do tot?

  # -- avg row
  $do_avg = false; foreach ($grid['columns'] as $col) { if (isset($col['calc_average']) && $col['calc_average']) { $do_avg = true; break; } }
  if ($do_avg) {
  $avgs = array(); foreach ($tots as $k=>$v) { $avgs[$k] = $v / count($rows); }
  $row_vars = array_merge($grid_vars, $avgs);
  $row_vars['_done'] = $urlprefix;
  $html_avg_row .= "  <tr class=avgRow>";
  if (!isset($grid['hide_checkboxes']) || !$grid['hide_checkboxes'])
    $html_avg_row .= "<td".($avgrow_bgcolor ? " bgcolor='$avgrow_bgcolor'" : "").">".
      ($avgrow_color   ? "<font color=\"$avgrow_color\">" : "").
      _t("A_average").
      ($avgrow_color   ? "</font>" : "").
      "</td>";
  foreach ($grid['columns'] as $col) {
    $cn = isset($col['dbcolumn']) ? $col['dbcolumn'] : '';
    if (isset($col['hidden']) && $col['hidden']) continue;
    $html_avg_row .= "<td".(isset($col['nowrap_data']) && $col['nowrap_data'] ? " nowrap" : "").
                 (isset($col['align_data']) ? " align=\"$col[align_data]\"" : "").
                 (isset($avgrow_bgcolor) && $avgrow_bgcolor ? " bgcolor=\"$avgrow_bgcolor\"" : "").
                 ">".
           (isset($avgrow_color) && $avgrow_color ? "<font color=\"$avgrow_color\">" : "");
    # print value
    if (isset($col['calc_average']) && $col['calc_average']) {
      if (isset($col['value_func'])) {
        $html_avg_row .= $col['value_func']($row_vars);
      } elseif (isset($col['value_template'])) {
        $html_avg_row .= filltemplate($col['value_template'], $row_vars);
      } else {
        $html_avg_row .= $avgs[$cn];
      }
    } else {
      $html_avg_row .= " ";
    }
    $html_avg_row .= ($avgrow_color ? "</font>" : "").
      "</td>";
  }
  if (!isset($grid['hide_rowactions']) || !$grid['hide_rowactions'])
    $html_avg_row .= "<td".
      ($avgrow_bgcolor ? " bgcolor=$avgrow_bgcolor" : "").">".
      ($avgrow_color   ? "<font color=\"$avgrow_color\">" : "").
      "AVERAGE".
      ($avgrow_color   ? "</font>" : "").
      "</td>";
  $html_avg_row .= "</tr>\n";
  } # show avg?
  } # show totavgrow?

  # browser requests update
  $row_start = $page_size*($page-1)+1;
  if (isset($_REQUEST['_ajax']) && $_REQUEST['_ajax']) {
    $old_row_count = isset($_REQUEST['_row_count']) ? $_REQUEST['_row_count']+0 : 0;
    $old_row_start = isset($_REQUEST['_row_start']) ? $_REQUEST['_row_start']+0 : 1;
    $old_checksums = preg_split('/,/', (isset($_REQUEST['_row_checksums']) ? $_REQUEST['_row_checksums'] : ''), -1, PREG_SPLIT_NO_EMPTY);
    $old_sorts     = isset($_REQUEST['_sorts']) ? $_REQUEST['_sorts'] : '';
    $old_filters   = isset($_REQUEST['_filters']) ? $_REQUEST['_filters'] : '';
    $res = array();
    if (!isset($row_count) || $row_count != $old_row_count) $res[] = array('update_row_count', isset($row_count) ? $row_count : count($rows));
    if ($row_start != $old_row_start) $res[] = array('update_row_start', $row_start);
    if (!isset($row_count) || $row_count != $old_row_count || $row_start != $old_row_start) {
      $res[] = array('update_top_paging_bar', $html_paging_bar_top);
      $res[] = array('update_bottom_paging_bar', $html_paging_bar_bottom);
    }
    $headers_updated=false;
    if ($old_sorts != "$sort,$sort2,$sort3") {
      if (!$headers_updated) {
        $res[] = array('update_header', $html_header_row);
        $headers_updated=true;
      }
      $res[] = array('update_sorts', $sort, $sort2, $sort3);
    }
    if ($old_filters != $filters_urlp) {
      if (!$headers_updated) {
        $res[] = array('update_header', $html_header_row);
        $headers_updated=true;
      }
      $res[] = array('update_filters', $filters_urlp);
    }
    for ($i=0; $i<$page_size; $i++) {
      if ((!isset($old_checksums[$i]) || $old_checksums[$i]==0) && !isset($checksums[$i])) { continue; }
      if (!isset($checksums[$i])) { $res[] = array('update_row', $i, 0, $html_data_rows[$i]); continue; }
      if ((!isset($old_checksums[$i]) &&  isset($checksums[$i])) ||
          $old_checksums[$i] != $checksums[$i]) {
        $res[] = array('update_row', $i, $checksums[$i], $html_data_rows[$i]);
      }
    }
    #$res[] = array('debug_alert', json_encode($res));
    return json_encode($res);
  }

  $html_table_close = "</table>\n\n";

  $html_form_close = "</form>\n\n";

  # -- total rows, starting row, and row checksums in javascript variables, for ajax update
  $html_script2 = "<script>
  var {$form_name}_row_count = ".(isset($row_count) ? $row_count : 'undefined')."
  var {$form_name}_row_start = $row_start
  var {$form_name}_page_size = $page_size
  var {$form_name}_row_checksums = ".json_encode($checksums)."
  var {$form_name}_sorts = \"$sort,$sort2,$sort3\"
  var {$form_name}_filters = \"$filters_urlp\"

";

  if ($update_freq > 0) $html_script2 .= '
function '.$form_name.'_ajax_update() {
  '.$form_name.'_ajax_submit()
  setTimeout('.$form_name.'_ajax_update, '.$update_freq.')
}

$(function() {
  setTimeout('.$form_name.'_ajax_update, '.$update_freq.')
});
';
  $html_script2 .= "</script>\n<!-- END grid $form_name -->\n";

  $time2 = microtime(true);
  if (isset($grid['debug_timegen']) && $grid['debug_timegen']) echo "DEBUG: genhtml time=",sprintf("%.3fs", $time2-$time1),"<br>";

  return
    $html_ajax_status_bar .
    $html_script1 .
    $html_form_open .
    (!isset($grid['hide_pagingbar']) || !$grid['hide_pagingbar'] ? $html_paging_bar_top : '').
    (!isset($grid['hide_actionbar']) || !$grid['hide_actionbar'] ? $html_af_bar : '').
    $html_table_open .
    $html_header_row .
    join("", $html_data_rows) .
    $html_table_close .
    (!isset($grid['hide_pagingbar']) || !$grid['hide_pagingbar'] ? $html_paging_bar_bottom : '').
    $html_form_close .
    $html_script2;
}

?>
