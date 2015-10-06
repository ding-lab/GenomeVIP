<?php
// --------------------------------------
// @name GenomeVIP Amazon S3 make bucket utility
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = trim($_POST['s3conf']);
  $b   = trim($_POST['b']);
  if(! preg_match('/^[0-9a-zA-Z_]+$/', $b)) {
    echo "INVALID";
  } else {
    $cmd = "s3cmd -c $cfg mb s3://$b 2>&1";
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