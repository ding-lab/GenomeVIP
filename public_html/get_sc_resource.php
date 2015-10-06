<?php
// --------------------------------------
// @name GenomeVIP StarCluster resource handler script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = $_POST['cfg'];
  $resource = $_POST['res'];
  
  $cmd="starcluster -c $cfg $resource";
  $output = "waiting";
  while(preg_match('/waiting/i', $output)) {
    sleep(2);
    $output = shell_exec($cmd);
  }
  $r = array_filter(preg_split('/\n/', $output));


  switch($resource) {
  case "lb":
    echo json_encode($r);
    break;

  case "lv":
    $my_r = array();
    $idx=-1;
    foreach($r as $line) {
      if( preg_match('/^volume_id/', $line)) {
	array_push($my_r, "[vol".(++$idx)."]");
	array_push($my_r, preg_replace('/:/', "=", $line));
      }
      elseif( preg_match('/^(status|avail|tags)/', $line)) {
	array_push($my_r, preg_replace('/:/', "=", preg_replace('/Name=/', '', $line)));
      }
    }
    
    $ini = parse_ini_string(implode("\n", $my_r),true);
    $idx=0;
    $data = array();
    foreach($ini as $vol=>$parms) {



      
      if( trim($parms['status'])=="available") {
	$tmp_tag="";
	if(array_key_exists('tags', $parms)) { $tmp_tag = trim($parms['tags']); }
	array_push($data, array('volid' => trim($parms['volume_id']),
				'voln'  => $tmp_tag,
				'az'    => trim($parms['availability_zone']),
				));
      }
    }
    echo json_encode($data);
    break;
    

  default:
  }

}
?>