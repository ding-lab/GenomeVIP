<?php
// --------------------------------------
// @name GenomeVIP catenation script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $fpath = json_decode($_POST['filepath']);
  $tmp_data = file_get_contents($fpath);
  echo $tmp_data;
}



?>