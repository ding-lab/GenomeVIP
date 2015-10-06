<?php
// --------------------------------------
// @name GenomeVIP StarCluster setup script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
// Debug
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

include realpath(dirname(__FILE__)."/"."resources_util.php");
include realpath(dirname(__FILE__)."/"."versions.php");
include realpath(dirname(__FILE__)."/"."fileconfig.php");


$SC="starcluster";
$bDebug=0;
$randlen=8;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  global $ebsprefix;
  $tmp_sc = $_POST['src'];
  $cmd    = $_POST['cmd'];
  $types  = array();
  $output = "";






  if ($cmd=="init") {
    //    if( $tmp_sc=="none" || ! file_exists($tmp_sc) ) {
    //      $tmp_sc = tempnam("/tmp", "");
    //      chmod($tmp_sc, 0600);
    //    } 
    if( $tmp_sc=="none" || $tmp_sc=="" || ! file_exists($tmp_sc)) {
      $stempid = generateRandomString($randlen);
      $tmp_sc = "/tmp/$stempid.sc";
      system("touch $tmp_sc && chmod 0600 $tmp_sc");
    } else  {
      $stempid = preg_replace('/\.sc$/','', basename($tmp_sc));
    }
    $sc_fp = fopen($tmp_sc, 'w');
    fwrite($sc_fp, "[aws info]\n");
    fwrite($sc_fp, "AWS_ACCESS_KEY_ID = ".$_POST['aws_ak']."\n");
    fwrite($sc_fp, "AWS_SECRET_ACCESS_KEY = ".$_POST['aws_sk']."\n");
    fwrite($sc_fp, "AWS_USER_ID = ".$_POST['aws_un']."\n");
    //$keyname = "key_".generateRandomString($randlen);
    $keyname = $stempid;
    $keyloc = "/tmp/$keyname.rsa";
    $cmd = "$SC -c $tmp_sc createkey $keyname -o $keyloc 2>&1";

    $output = shell_exec($cmd);
    if(preg_match('/AuthFailure/', $output)) {
      echo 'AuthFailure';
      exit;
    }
    fwrite($sc_fp, "\n");
    fwrite($sc_fp, "[key $keyname]\n");
    fwrite($sc_fp, "KEY_LOCATION = $keyloc\n");
    fflush($sc_fp);
    fclose($sc_fp);
    echo json_encode($tmp_sc);
    
  } elseif ($cmd=="resume") {

    if($bDebug) { $output .= "In resume routine<br>"; } 
    $name = $_POST['name'];
    $cmd = "$SC -c $tmp_sc start -x $name";
    $output .= $cmd;
    $output .= shell_exec($cmd);
    $output .= "<br>";
    echo json_encode($output);

  } elseif ($cmd=="new") {
    $name = $_POST['name'];
    $size = $_POST['size'];
    $c = $_POST['c'];
    $v_db = array();
    $volspec = array();
    if( isset($_POST['awsinfovol']) ){
      $v_db = json_decode($_POST['awsinfovol'],true); 
      $volspec = json_decode($_POST['awsvolspec']); 




    }
    $clustername="";





    // Get key name and common name
    $p = parse_ini_file($tmp_sc, true);
    foreach( array_keys($p) as $i) { if( preg_match("/^key/", $i) > 0) { $a = preg_split('/\s+/', $i); $keyname = $a[1]; } }
    $tmp = getawstypes($types);
    foreach ($types as $sc_type=>$info) { if($sc_type == $name) { $clustername = $info['name']; $odp = $info['odp']; break; } }

    // Update sc 
    $sc_fp = fopen($tmp_sc, 'a');
    fwrite($sc_fp, "\n");
    fwrite($sc_fp, "[cluster $clustername]\n");
    fwrite($sc_fp, "KEYNAME = $keyname\n");
    fwrite($sc_fp, "CLUSTER_SIZE = $size\n");
    fwrite($sc_fp, "CLUSTER_USER = sgeadmin\n");
    fwrite($sc_fp, "CLUSTER_SHELL = bash\n");
    fwrite($sc_fp, "NODE_IMAGE_ID = $genomevip_imageID\n");
    fwrite($sc_fp, "NODE_INSTANCE_TYPE = $name\n");
    fwrite($sc_fp, "SPOT_BID = $odp\n");

    // Add specified volumes
    $volmap = array(); 
    $zonemap = array(); 
    $mylist=array();
    $buffer=array();
    $nameless_idx=0;
    $bMismatchZones=0;
    if ($c) { 
      // Enhash vol db
      for($i=0; $i < count($v_db); $i++) { 
	$volmap[$v_db[$i]['volid']] = $v_db[$i]['voln']; 
	$zonemap[$v_db[$i]['volid']] = $v_db[$i]['az']; 
      }
      // Check for real volumes
      $bAllNone=1;
      for($i=0; $i<$c; $i++) { if($volspec[$i] != "none") { $the_zone=$zonemap[$volspec[$i]]; $bAllNone=0; break; } }
      if($bAllNone) {echo 'OK'; exit;}
      
      for($i=0; $i<$c; $i++) {
	if($volspec[$i] != "none") { 
	  if($zonemap[$volspec[$i]] != $the_zone) { $bMismatchZones=1; break; }
	  $my_n = $volmap[$volspec[$i]]; if($my_n=="") {$my_n="tmpname".($nameless_idx++); }
	  array_push($mylist, $my_n);
	  array_push($buffer, "\n");
	  array_push($buffer, "[volume ".$my_n."]\n");
	  array_push($buffer, "VOLUME_ID = ".$volspec[$i]."\n");
	  array_push($buffer, "MOUNT_PATH = $ebsprefix$i\n");
	} 
      }
      if( $bMismatchZones ) {
	fflush($sc_fp);
	fclose($sc_fp);
	echo 'Mismatched Zones\n';
      } else {
	fwrite($sc_fp, "VOLUMES = ".implode(",", $mylist)."\n");
	foreach($buffer as $i) { fwrite($sc_fp, $i); }
	fflush($sc_fp);
	fclose($sc_fp);
	echo 'OK\n';
      }
    }
    
    echo 'OK';

















  } // new

  
}  // if POST
?>