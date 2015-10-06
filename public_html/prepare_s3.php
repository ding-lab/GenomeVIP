<?php
// --------------------------------------
// @name GenomeVIP utility routines
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------

include realpath(dirname(__FILE__)."/"."fileconfig.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  global $s3template;

  $tmp_sc = trim($_POST['s3conf']);
  $ak   = $_POST['aws_ak'];
  $sk   = $_POST['aws_sk'];
  $proto= $_POST['aws_https'];
  $enc  = $_POST['aws_ssenc'];

  if(! file_exists($tmp_sc) ) {


    system("touch $tmp_sc && chmod 0600 $tmp_sc");
  } 
  $template = file_get_contents( $s3template );
  $patterns = array('/MY_ACCESS_KEY/','/MY_SECRET_KEY/','/My_https_value/','/My_ssenc_value/');
  $replacements = array( trim($ak), trim($sk), $proto,$enc );
  $template = preg_replace( $patterns, $replacements, $template );

}
?>