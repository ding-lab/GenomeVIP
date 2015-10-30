<?php
// --------------------------------------
// @name GenomeVIP StarCluster resource handler script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = "/tmp/".$_POST['cfg'].".sc";
  $resource = $_POST['res'];
  
  $toolsinfo_server = parse_ini_file('configsys/tools.info.server', true);
  $sc_cmd = $toolsinfo_server['starcluster']['path']."/".$toolsinfo_server['starcluster']['exe'];
 
  $cmd="$sc_cmd -c $cfg $resource";
  $output = "waiting";
  while(preg_match('/waiting/i', $output)) {
    sleep(2);
    $output = shell_exec($cmd);
  }

  $r = array_filter(explode("\n", $output));

  $stor="";
  switch($resource) {

  case "lb":
    if(count($r)==0) {
      $stor .= "<div class='tdindents'><p>None found.</p></div>";
    } else {
      for($k=0; $k < count($r); $k++) {
	$stor .= "<div class='tdindents'><input type='radio' class='rindent' name='s3buckets' value='".$r[$k]."' ".(($k==0)?("checked='checked'"):(""))."/>".$r[$k]."</div>";
      }
      $stor .= "<p><br></p><p class='tdindent1'><input type='button' onclick='mkbucket();' value='Create a new bucket'/></p><p><br></p>";
    }
    echo json_encode($stor);
    break;
    

  case "lv":   // Get volumes
    // AWS
    $aws_vols = array();
    $idx=-1;
    foreach($r as $line) {
      if( preg_match('/^volume_id/', $line)) {
	array_push($aws_vols, "[vol".(++$idx)."]");
	array_push($aws_vols, preg_replace('/:/', "=", $line));
      } elseif( preg_match('/^(status|avail|tags)/', $line)) {
	array_push($aws_vols, preg_replace('/:/', "=", preg_replace('/Name=/', '', $line)));
      }
    }
    // Find assigned
    $curr_used = array();
    foreach(parse_ini_file($cfg, true) as $key=>$value) {
      if( preg_match('/^volume/', $key)) {
    	array_push( $curr_used, $value['VOLUME_ID']);
      }
    }
    // Process: return previously unused volumes
    $idx=0;
    $data = array();
    foreach(parse_ini_string(implode("\n", $aws_vols),true) as $vol=>$parms) {
      //      file_put_contents("testout", $vol."\n", FILE_APPEND);
      //      file_put_contents("testout", "ini vol=$vol\n", FILE_APPEND);
      file_put_contents("testout", "ini volid=".$parms['volume_id']."\n", FILE_APPEND);
      file_put_contents("testout", "ini status=".$parms['status']."\n", FILE_APPEND);
      if (in_array($parms['volume_id'], $curr_used) ){
	file_put_contents("testout", "   is listed in another cluster configuration\n", FILE_APPEND);
      }
      $tmp_tag="";
      if(array_key_exists('tags', $parms)) { $tmp_tag = $parms['tags']; }
      array_push($data, array('volid' => $parms['volume_id'],
			      'voln'  => $tmp_tag,
			      'az'    => $parms['availability_zone'],
			      ));
      file_put_contents("testout", "vol pushed(id,name): ".$parms['volume_id'].",".$tmp_tag."\n", FILE_APPEND);

    }
    echo json_encode($data);
    break;

  case "lz": // Get zones
    $aws_zones = array();
    $idx=-1;
    foreach ($r as $line) {
      if( preg_match('/^name/', $line)) {
	array_push($aws_zones, "[zone".(++$idx)."]");
	array_push($aws_zones, preg_replace('/:/', "=", $line));
      } elseif( preg_match('/^status/', $line)) {
	array_push($aws_zones, preg_replace('/:/', "=", $line));
      }
    }
    $data=array();
    foreach(parse_ini_string(implode("\n",$aws_zones),true) as $zone=>$parms) {
      if($parms['status'] == "available") {
	array_push($data, $parms['name']);
      }
    }
    echo json_encode($data);
    break;

  default:
  }

}
?>