<?php

function _id_date_sub($char, $time) {
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

function id_date($format, $timestamp=-1) {
  if ($timestamp==-1 || $timestamp=="") $timestamp = time();
  return preg_replace('/([YMdFHisDl])/e', "_id_date_sub('\\1',$timestamp)", $format);
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

# just like number_format() but with hardcoded dec_point & thousand_sep for indonesian
function id_number_format($num, $dec=0) {
  return number_format($num, $dec, ",", ".");
}
