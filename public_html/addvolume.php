<?php
// --------------------------------------
// @name GenomeVIP add volume script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $c = $_POST['c'];
  $voldb = array();
  $voldb = json_decode($_POST['voldb'],true); // valid voln


  $data = "<div class='vspanel'><table class='tdindents'><tr><td class='volw'>Volume ID (name):</td><td><select class='tb3menu' id='awsvolmenu".$c."'>"; 
  $data .= "<option value='none'>-- No Selection --</option>";

  for($i=0; $i < count($voldb); $i++) {
    $data .= "<option value='".$voldb[$i]['volid']."'>".$voldb[$i]['volid'];
    if($voldb[$i]['voln']!="") {
      $data .= " (name: ".$voldb[$i]['voln'].")</option>";
    }
  }

  $data .= "</select><td><input type='radio' name='awsworkvols' value='".$c."' id='awsworkvol".$c."'/> Check here if volume is provides the temporary working area</td></tr><tr><td>File list:</td><td><textarea class='voltb' id='awsvfi".$c."' name='awsvolfiles[]'/></td><td><iframe class='frame' src='volform.html' id='volform".$c."'><\/iframe></td></tr></table></div>";

  
  echo $data;

}
?>