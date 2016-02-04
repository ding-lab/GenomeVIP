<?php
// --------------------------------------
// @name GenomeVIP image 
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."versions.php");

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  echo "<p>This default GenomeVIP runtime imageID for computations launched by this server is <b>".$genomevip_imageID."</b>.</p>";
  echo "<p><br></p>";
  echo "<p>As bug fixes and feature enhancements are made, new images will appear among the AWS public AMIs, accessible from the AWS EC2 Dashboard (click through to Images -> AMIs -> Public images and search for GenomeVIP).</p>";
  echo "<p><br></p>";
}
?>