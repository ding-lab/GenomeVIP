<?php
// --------------------------------------
// @name GenomeVIP Loader for Amazon's 1000 Genomes Project file trees
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."populate.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {

  // Use with processed lists
  $mymap=array(1=>"1000G.pilot_phase.lst.processed",
	       2=>"1000G.phase1.low_cov.lst.processed",
	       3=>"1000G.phase1.exome.lst.processed",
	       4=>"1000G.phase3.low_cov.lst.processed",
	       5=>"1000G.phase3.exome.lst.processed",
	       6=>"1000G.phase3.high_cov.lst.processed",
	       );
  populate(file_get_contents("configsys/1000G_tree/".$mymap[$_POST['n']]));
}
?>
