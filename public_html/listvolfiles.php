<?php
// --------------------------------------
// @name GenomeVIP Amazon data volume script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."populate.php");
include realpath(dirname(__FILE__)."/"."fileconfig.php");
include realpath(dirname(__FILE__)."/"."resources_util.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  global $ebsprefix;
  $awsinfo = json_decode($_POST['src'], true);
  $k = count($awsinfo);

  // create map
  $myc_arr="";
  $realkey_cnt = 0;
  $paths_map = array();
  for ($i=0; $i < $k; $i++) {
    foreach (array_filter(explode("\n", $awsinfo[$i]["files"])) as $f) {
      if( preg_match('/^\//',$f)) {
	$dn = dirname("$ebsprefix$i$f");
      } else {
	$dn = dirname("$ebsprefix$i/$f");
      }
      if (! array_key_exists($dn, $paths_map)) {
	$paths_map[$dn] = $realkey_cnt++;
      }
    }
  }
  $mymap = array_flip($paths_map);

  // output map and data
  $myc = $realkey_cnt."\n";
  $myc_arr .= $myc;
  for ($i=0; $i < $realkey_cnt; $i++) {
    $myc = $mymap[$i]."\n";
    $myc_arr .= $myc;
  }
  for ($i=0; $i < $k; $i++) {
    foreach (array_filter(explode("\n", $awsinfo[$i]["files"])) as $f) {
      if( preg_match('/^\//', $f)) {
	$tmpstr = "$ebsprefix$i$f";
      } else {
	$tmpstr = "$ebsprefix$i/$f";
      }
      $dn = dirname($tmpstr);
      $bn = basename($tmpstr);
      $realkey = $paths_map[$dn];
      $myc = $realkey."\t".$bn."\n";
      $myc_arr .= $myc;
    }
  }

  populate($myc_arr);

}

?>