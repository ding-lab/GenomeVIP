<?php
// --------------------------------------
// @name GenomeVIP Amazon S3 make bucket utility
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = "/tmp/".$_POST['cfg'].".s3cfg";
  $b   = trim($_POST['b']);

  $toolsinfo_server=parse_ini_file("configsys/tools.info.server",true);
  $s3cmd = $toolsinfo_server['s3cmd']['path']."/".$toolsinfo_server['s3cmd']['exe'];

  if(! preg_match('/^[0-9a-zA-Z_]+$/', $b)) {
    echo "INVALID";
  } else {
    $cmd = "$s3cmd -c $cfg mb s3://$b";
    $log =shell_exec($cmd);
    if(preg_match('/created\s+$/',$log)){
      echo "OK";
    } elseif(preg_match('/exists\s+$/',$log)){
      echo "EXISTS";
    } else {
      echo "FAIL";
    }
  }
}
?>