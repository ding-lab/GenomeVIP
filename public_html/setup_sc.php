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



if ($_SERVER['REQUEST_METHOD'] == "POST") {
  global $ebsprefix;
  $tmp_sc  = "/tmp/".$_POST['cfg'].".sc";
  $tmp_key = "/tmp/".$_POST['cfg'].".rsa";
  $tmp_s3f = "/tmp/".$_POST['cfg'].".s3cfg";
  $cmd     = $_POST['cmd'];
  $types   = array();
  $randlen = 8;

  $bDebug=1;

  $toolsinfo_server = parse_ini_file('configsys/tools.info.server', true);
  $sc_cmd = $toolsinfo_server['starcluster']['path']."/".$toolsinfo_server['starcluster']['exe'];

  if($bDebug) {
    file_put_contents("testout", "In setup_sc...\n", FILE_APPEND);
    file_put_contents("testout", "tmp_sc=$tmp_sc\n", FILE_APPEND);
    file_put_contents("testout", "cmd=$cmd\n", FILE_APPEND);
  }

  // ($cmd=="init") function moved
  if ($cmd=="reuse") {

    $clustname = $_POST['obj']; // specific name  

    if(! file_exists($tmp_key) || ! file_exists($tmp_sc) || ! file_exists($tmp_s3f)) { 
      echo json_encode(array("msg"=>"FileMissing","name"=>$clustname));
      exit;

    } else {
      // (current version) Re-used clusters should still be running
      echo json_encode(array("msg"=>"OK","name"=>$clustname));
      exit;
      
      // TODO: Implement resume
    }

  } elseif ($cmd=="new") {

    $type    = $_POST['obj'];  // type
    $size    = $_POST['size'];

    $v_db    = array();
    $volspec = array();
    if( isset($_POST['awsinfovol']) ){
      $v_db    = json_decode($_POST['awsinfovol'],true); 
      $volspec = json_decode($_POST['awsvolspec']); 
    }

    // Get key name and common name
    $p = parse_ini_file($tmp_sc, true);
    foreach( array_keys($p) as $i) { if( preg_match("/^key/", $i) > 0) { $a = preg_split('/\s+/', $i); $keyname = $a[1]; } }
    $tmp = getawstypes($types_dict);
    foreach ($types_dict as $sc_type=>$info) { if($type == $sc_type) { $clustbasename = $info['name']; $odp = $info['odp']; break; } }

    $clust_tag = generateRandomString(4); 
    $real_cluster = $clustbasename."_".$clust_tag;

    // Proposed configuration
    $buffer0 = array();

    array_push ($buffer0, "\n");
    array_push ($buffer0, "[cluster $real_cluster]\n");
    array_push ($buffer0, "KEYNAME = $keyname\n");
    array_push ($buffer0, "CLUSTER_SIZE = $size\n");
    array_push ($buffer0, "CLUSTER_USER = sgeadmin\n");
    array_push ($buffer0, "CLUSTER_SHELL = bash\n");
    array_push ($buffer0, "NODE_IMAGE_ID = $genomevip_imageID\n");
    array_push ($buffer0, "NODE_INSTANCE_TYPE = $type\n");
    array_push ($buffer0, "SPOT_BID = $odp\n");

    // Add specified volumes
    $volmap  = array(); 
    $zonemap = array(); 
    $mylist  = array();
    $nameless_idx   = 0;
    $bMismatchZones = 0;

    $c = count( $volspec );
    if( $c ) {  // At least 1 with required work vol
      $buffer  = array();

      // Enhash vol db
      if( count($v_db)) {
	for($i=0; $i < count($v_db); $i++) { 
	  $volmap[$v_db[$i]['volid']] = $v_db[$i]['voln']; 
	  $zonemap[$v_db[$i]['volid']] = $v_db[$i]['az']; 
	}
      }
      // Check for same zones
      $bAllNone=1;
      for($i=0; $i<$c; $i++) { if($volspec[$i] != "none") { $first_zone=$zonemap[$volspec[$i]]; $bAllNone=0; break; } }
      if($bAllNone) {
	echo json_encode(array("msg"=>"NoZone","name"=>$real_cluster)); 
	exit;
      }
      
      for($i=0; $i<$c; $i++) {
	if($volspec[$i] != "none") { 
	  if($zonemap[$volspec[$i]] != $first_zone) { $bMismatchZones=1; break; }
	  $my_n = $volmap[$volspec[$i]]; 
	  if($my_n=="") { $my_n = "unnamed".($nameless_idx++); }
	  $my_n .= "_".$clust_tag;
	  array_push ($mylist, $my_n);
	  array_push ($buffer, "\n");
	  array_push ($buffer, "[volume ".$my_n."]\n");
	  array_push ($buffer, "VOLUME_ID = ".$volspec[$i]."\n");
	  array_push ($buffer, "MOUNT_PATH = $ebsprefix$i\n");
	} 
      }
      if( $bMismatchZones ) {
	echo json_encode(array("msg"=>"MismatchedZones","name"=>$real_cluster));
	exit;
      } else {
	array_push ($buffer0, "VOLUMES = ".implode(",", $mylist)."\n");
	foreach($buffer as $i) { array_push ($buffer0, $i); }
      }
    }  // end count
    
    // Update config
    $sc_fp = fopen($tmp_sc, 'a');
    foreach($buffer0 as $i) { fwrite($sc_fp, $i); }
    fflush($sc_fp);
    fclose($sc_fp);

    // Start cluster (moved back from parse_real.php)
    $cmd = "$sc_cmd -c $tmp_sc start -c $real_cluster $real_cluster 2>&1";
    if($bDebug) {  file_put_contents("testout", "$cmd\n", FILE_APPEND); }
    $cmdout = shell_exec($cmd);
    if($bDebug) {
      file_put_contents("testout", "**Start of new cluster log**\n", FILE_APPEND);
      file_put_contents("testout", $cmdout."<br>\n", FILE_APPEND);
      file_put_contents("testout", "**End of new cluster log**\n", FILE_APPEND);
    }
    // Checks
    if(preg_match('/must specify the filesystem type/', $cmdout)) {
      echo json_encode(array("msg"=>"NoFS", "name"=>$real_cluster));  
      exit;
    }
    if(preg_match('/status: in-use/', $cmdout)) {
      echo json_encode( array("msg"  => "InUse",
			      "name" => $real_cluster,
			      "txt"  => implode("; ", preg_grep("/in-use/", explode("\n", $cmdout))),
			      )
			);
      exit;
    }
    if(! preg_match('/ready to use/', $cmdout)) {
      echo json_encode(array("msg"=>"NotReady","name"=>$real_cluster));
      exit;
    }
    // <mr_burns>Excellent</mr_burns>
    echo json_encode(array("msg"=>"OK","name"=>$real_cluster));

  } // elseif new
  
}  // if POST
?>