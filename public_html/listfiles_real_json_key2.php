<?php
// --------------------------------------
// @name GenomeVIP remote file listing retrieval script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."populate.php");
include realpath(dirname(__FILE__)."/"."resources_util.php");


function get_list($myhost, $usern, $passw, $mypath, &$data) {  
    if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
    if (!($conn = ssh2_connect( $myhost ,22))) {
      echo "fail: unable to establish connection\n";
    } else {
      if (!ssh2_auth_password($conn, $usern, $passw)) {
	echo "fail: unable to authenticate\n";
      } else {
	if (!($stream = ssh2_exec($conn, "ls -1 ".$mypath))) {
	  echo "fail: unable to execute command\n";
	} else {
	  stream_set_blocking($stream, true); // allow command to finish
	  $data = "";
	  while ($buf = fread($stream, 4096)) { $data .= $buf; }
	  fclose($stream);
	}
      }
    }
    ssh2_exec($conn, 'exit');
}


if ($_SERVER['REQUEST_METHOD'] == "POST") {
      $usern = $_POST['username'];
      $passw = $_POST['phrase'];
      $fpath = array();
      $fpath = json_decode($_POST['filepath']);

      $myc_arr = "";
      
      // output map and data
      $myc = count($fpath)."\n";
      $myc_arr .= $myc;

      for ($i=0; $i < count($fpath); $i++) {
	$myc = $fpath[$i]."\n";
	$myc_arr .= $myc;
      }
       
      $myhost = $_POST['gw'];

      foreach ($fpath as $key => $value) {
	$tmp_data="";
	get_list($myhost, $usern, $passw, $value, $tmp_data);
	foreach (array_filter(explode("\n", $tmp_data)) as $i ) {
	  $myc = $key."\t".$i."\n";
	  $myc_arr .= $myc;
	}
	
      }
      
      populate($myc_arr);

}


?>