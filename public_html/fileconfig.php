<?php
// --------------------------------------
// @name GenomeVIP file configuration
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------

$s3template   = "configsys/s3cfg--NoEnc.templ";
$ec2types     = "configsys/aws_ec2types_suggested.conf";
$ebsprefix    = "/ebsdata_";
$homecurl     = "http://ding-lab.ddns.net/phoneHomeOperator.php";
$homemail     = "genomevip@genome.wustl.edu";
$homesubject  = "GenomeVIP: Usage ET";
$homeheaders  = "From: User <".$homemail.">";
$tool         = "GenomeVIP";

?>
