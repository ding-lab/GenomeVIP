<?php
// --------------------------------------
// @name GenomeVIP file upload processing script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
function report($stat,$msg,$f) {
  print $stat."\n";
  print $msg."\n";
  print $f."\n";
}


if (array_key_exists("myfile", $_FILES)) {
  if ($_FILES["myfile"]["size"] === 0) {
    report(0, "Error: file size was zero",""); 
  } else if ($_FILES["myfile"]["error"] !== UPLOAD_ERR_OK) {
    report(0, "Error during upload",""); 
  } else {
    $phptmp=$_FILES["myfile"]["tmp_name"];
    $newfn =$phptmp . "." .  $_FILES["myfile"]["name"];
    if (move_uploaded_file($phptmp, $newfn)) {
      chmod ($newfn, 0600);
      report(1, "Success", $newfn);
    } else {
      report(0, "Error: could not save file", "");
    }
  }
}

//echo $newfn;

?>