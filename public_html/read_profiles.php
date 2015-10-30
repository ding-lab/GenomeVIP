<?php
// --------------------------------------
// @name GenomeVIP profiles handling script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."array_defs.php");

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $result  = array();
  $presult = array();
  $dict    = array();

  if (isset($_POST['p'])) {
    $p = $_POST['p'];
    if ($p==0 || $p==1) {
      switch ($p) {
      case 0:  // mode
	$pps = parse_ini_file("configsys/run_modes/modes.conf", true);
	break;
      case 1:  // ep 
	$pps = parse_ini_file("configsys/profiles/profiles.conf", true);
	break;
      }
      for($k=0; $k < count($pps); $k++) {
	$result[$k] = array("name" => $pps['profile'.$k]['name'],
			    "path" => $pps['profile'.$k]['path'],
			    "desc" => $pps['profile'.$k]['desc'],
			    "msg"  => $pps['profile'.$k]['msg'],
			    );
	$dict[ $pps['profile'.$k]['name'] ] = $k;
      }
    }
  }

  if (isset($_POST['c'])) {
    $c = $_POST['c'];

  // Retrieve this profile and exit
  if ($c==0) {
    echo json_encode($result);
    return;
  }

  // Retrieve other
  if ($c==1) {
    $idx = $dict[ $_POST['fn'] ];
    $ppfn = $result[ $idx ]['path'];
    //    $desc = $result[ $idx ]['desc'];
  }
  if ($c==2) {
    $ppfn = json_decode($_POST['fn']);
  }

  $lines=array();   
  $lines=array_filter(explode("\n", file_get_contents($ppfn)));
  end($lines);  // nonsequential indices



  $do_cmds = array();
  foreach ($cmd_names as $key => $value) { $do_cmds[$key] = 0; }

  foreach ($lines as $i => $value) {
    $value = trim($value);
    if(preg_match('/^\s*[#;]/', $value)) { continue; }
    if(preg_match('/genomevip/',   $value)) { continue; }
    
    // Check for block header
    if(preg_match('/^\[([a-z_ ]+?)\]/', $value, $matches)) { 

      if ($c==2) {
	$key = trim($matches[1]);
	if(array_key_exists($key, $cmd_names)) {  // is a command
	  array_push( $presult, array("n" => $key.".cmd", "v" => "true") );
	  $do_cmds[$key]=1;
	} 
      }
      
    } else {

      $b = preg_split('/=/', preg_replace('/\"/','',  $value)); // remove doublequotes

      array_push( $presult, array("n" => trim($b[0]), "v" => trim($b[1])) );
    }


  } // lines

  // deselect remaining tools 
  if ($c==2) {
    foreach ($cmd_names as $key => $value) {
      if($do_cmds[$key] == 0) {
	array_push( $presult, array("n" => $key.".cmd", "v" => "false") );
      }
    }
  }



  echo json_encode($presult);

  }

}
?>