<?php
// --------------------------------------
// @name GenomeVIP filename processing script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include('./populate.php');

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $fpath = json_decode($_POST['filepath']);


  $tmp_data = file_get_contents($fpath);

    
  // create map
  $realkey_cnt   = 0;
  $paths_map = array();
  foreach (array_filter(explode("\n", $tmp_data)) as $i ) {
    $dn = dirname($i);
    if (! array_key_exists($dn, $paths_map)) {
      $paths_map[$dn] = $realkey_cnt;
      $realkey_cnt++;
    }
    $mymap = array_flip($paths_map);
  }

  $myc_arr = "";

  // output map and data
  $myc = $realkey_cnt."\n";

  $myc_arr .= $myc;

  for ($i=0; $i < $realkey_cnt; $i++) {

    $myc = $mymap[$i]."\n";
    $myc_arr .= $myc;

  }
  foreach (array_filter(explode("\n", $tmp_data)) as $i ) {
    $dn = dirname($i);
    $bn = basename($i);
    $realkey = $paths_map[$dn];
    $myc = $realkey."\t".$bn."\n";

    $myc_arr .= $myc;

  }

  populate($myc_arr);


}



?>