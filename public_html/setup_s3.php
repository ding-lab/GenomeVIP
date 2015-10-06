<?php
// --------------------------------------
// @name GenomeVIP Amazon handler script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
// Debug
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

include realpath(dirname(__FILE__)."/"."resources_util.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $the_s3cfg = prepare_s3($_POST['src'], $_POST['aws_ak'],$_POST['aws_sk'],
			  $_POST['aws_https'],$_POST['aws_ssenc']);
  echo json_encode($the_s3cfg);
}
?>