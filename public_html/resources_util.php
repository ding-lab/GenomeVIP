<?php
// --------------------------------------
// @name GenomeVIP utility routines
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------

include realpath(dirname(__FILE__)."/"."fileconfig.php");






function getid($cmd, &$q, $scconf) {

  $p = parse_ini_file($scconf,true);
  foreach (array_keys($p) as $i) {
    if(preg_match("/^$cmd/", $i) > 0){
      $a = preg_split('/\s+/', $i);
      array_push($q, $a[1]);
    }
  }

}


function getid2($cmd, &$q, $scconf) {


  $p = parse_ini_file($scconf,true);

  foreach (array_keys($p) as $i) {  
    if(preg_match("/^cluster/", $i) > 0){
      $a = preg_split('/\s+/', $i);
      $q[$a[1]] = $p[$i]['NODE_INSTANCE_TYPE'];
    }
  }
}
function getawstypes(&$q) {
  global $ec2types;
  $p = parse_ini_file($ec2types, true);
  foreach (array_keys($p) as $i) {
    if(preg_match("/^instance/" , $i) > 0){
      $a = preg_split('/\s+/', $i);
      $q[$a[1]]['vcpu'] = $p[$i]['VCPU'];
      $q[$a[1]]['ecpu'] = $p[$i]['ECPU'];
      $q[$a[1]]['mem'] = $p[$i]['MEM'];
      $q[$a[1]]['name'] = $p[$i]['NAME'];
      $q[$a[1]]['odp'] = $p[$i]['ODP'];
    }
  }
  
}
function getvalue($obj, $id, $tag, $scconf) {

  $p = parse_ini_file($scconf,true);
  foreach (array_keys($p) as $i) {
    if(preg_match("/\s*$obj\s+$id\s*/", $i) > 0){
      return $p[$i][$tag];  //  return first match
    }
  }
  
}
function generateRandomString($length) {
  // batch queue names usually may not begin with a number (certainly true for SGE)
  $characters1 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $characters2 = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $randomString = '';
  $randomString .= $characters1[mt_rand(0, strlen($characters1) - 1)];
  for ($i = 0; $i < $length-1; $i++) {
    $randomString .= $characters2[mt_rand(0, strlen($characters2) - 1)];
  }
  return $randomString;
}

function generateRandomStringNum($length) {
  $characters1 = '0123456789';
  $characters2 = '0123456789';
  $randomString = '';
  $randomString .= $characters1[mt_rand(0, strlen($characters1) - 1)];
  for ($i = 0; $i < $length-1; $i++) {
    $randomString .= $characters2[mt_rand(0, strlen($characters2) - 1)];
  }
  return $randomString;
}

function convert_list($s) {
  // For convenience, rely on client-side validation
  $build = array();
  $arr = array();
  $c_str = trim($s);

  if(preg_match('/[:-]/',$c_str) && preg_match('/\n/',$c_str)) { // mixed formats
    $c_str = preg_replace('/\n/',',', $c_str);
  }
  if(preg_match('/[,:-]/',$c_str)) {  // list or interval
    $arr = array_filter(preg_split('/,/', preg_replace('/\s+/','', $c_str)));
    foreach ($arr as $item) {
      if(preg_match('/:/', $item)) {    // use as is
	array_push($build, $item);
      } else {
	if(preg_match('/-/', $item)) {  // is a range
	  $bhaschr = ( preg_match('/chr/', $item)?(1):(0) );
	  preg_match('/(\d+)\-(\d+)/', preg_replace('/chr/','', $item), $m);
	  for ($k=$m[1]; $k <= $m[2]; $k++) {
	    array_push($build, (($bhaschr)?("chr"):("")).$k);
	  }
	} else {                        // is a single contig
	  array_push($build, $item);
	}
      }
    }
  } else { // single chr or list of triads
    $lines = array_filter(preg_split('/\n/', $c_str));
    if( count($lines) > 1) {
      foreach ($lines as $a) {
	$arr  =  preg_split('/\s+/', trim($a));
	array_push($build, $arr[0].":".$arr[1]."-".$arr[2]);
      }
    } else { // chr 
      foreach ($lines as $a) {
	array_push($build, trim($a));
      }
    }
  }
  
  return implode(" ",$build);
}
  
?>