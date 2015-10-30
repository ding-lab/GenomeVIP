<?php
// --------------------------------------
// @name GenomeVIP wrapper script for execution profile
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

function another_wrapper() {
  include realpath(dirname(__FILE__)."/"."pprofile.php");
}


// Dump to file
function print_profile_file($tmp_pp) {
  ob_start();
  another_wrapper();
  $msg = ob_get_contents();
  ob_end_clean();

  $profile = array();
  $profile = json_decode($msg, true);
  //  $tmp_pp = tempnam("/tmp", "");
  $opf = fopen($tmp_pp,'w');
  foreach ($profile as $value) { fwrite($opf, $value); }
  fclose($opf);
}
?>