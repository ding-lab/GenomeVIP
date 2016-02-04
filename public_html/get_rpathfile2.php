<?php
// --------------------------------------
// @name GenomeVIP filename processing script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."populate.php");
include realpath(dirname(__FILE__)."/"."resources_util.php");


function get_file_contents($myhost, $usern, $passw, $mypath, &$data) {  
    if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
    if (!($conn = ssh2_connect( $myhost ,22))) {
      echo "fail: unable to establish connection\n";
    } else {
      if (!ssh2_auth_password($conn, $usern, $passw)) {
	echo "fail: unable to authenticate\n";
      } else {
	if (!($stream = ssh2_exec($conn, "cat ".$mypath))) {
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


function get_s3_file($mypath, &$data, $cfg) {
  $toolsinfo_server=parse_ini_file("configsys/tools.info.server",true);
  $s3cmd = $toolsinfo_server['s3cmd']['path']."/".$toolsinfo_server['s3cmd']['exe'];
  $tmpf = tempnam('/tmp','');
  
  if (! preg_match("/^s3:\/\//", $mypath)) {
    $data="";
  } else {
    $cmd = "$s3cmd -q -c $cfg -f get $mypath $tmpf";
    system($cmd);
    $tmpdata = file_get_contents($tmpf);
    $cmd = "rm $tmpf";
    system($cmd);
    $data = implode("\n",  array_filter(  explode("\n", $tmpdata)));
  }

}



if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $usern = $_POST['username'];
  $passw = $_POST['phrase'];
  $tgt   = $_POST['target']; 
  $fpath = json_decode($_POST['filepath']);
  $cfg   = "/tmp/".$_POST['cfg'].".s3cfg";
  

  $tmp_data="";
  if ($tgt=="1") {
    // Currently, we expect only element 0
    get_s3_file($fpath, $tmp_data, $cfg);
  } else {
    $myhost = $_POST['host'];
    get_file_contents($myhost, $usern, $passw, $fpath, $tmp_data);
  }

  

  if ($tmp_data != "") {
    $myc_arr = "";
    $tmp_data_arr = array_filter(explode("\n", $tmp_data));

   // create map
    $realkey_cnt   = 0;
    $paths_map = array();
    for ($i=0; $i < count($tmp_data_arr); $i++) {
      $f = $tmp_data_arr[$i];

      // Clean data where data is in first column
      if( preg_match( '/\t/', $f)) {
	$tmp_split_arr = preg_split('/\t/', $f);
	$f = trim($tmp_split_arr[0]);
	$tmp_data_arr[$i] = $f;
      }

      $dn = dirname($f);
      if (! array_key_exists($dn, $paths_map)) {
	$paths_map[$dn] = $realkey_cnt;
	$realkey_cnt++;
      }
    }
    $mymap = array_flip($paths_map);
    
    // output map and data
    $myc = $realkey_cnt."\n";
    $myc_arr .= $myc;
    for ($i=0; $i < $realkey_cnt; $i++) {
      $myc = $mymap[$i]."\n";
      $myc_arr .= $myc;
    }
    foreach ($tmp_data_arr as $f ) {
      $dn = dirname($f);
      $bn = basename($f);
      $realkey = $paths_map[$dn];
      $myc = $realkey."\t".$bn."\n";
      $myc_arr .= $myc;
    }
    
    populate($myc_arr);

  }
  
}


?>