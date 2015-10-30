<?php
// --------------------------------------
// @name GenomeVIP StarCluster handling script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
//ini_set('display_errors',1);
//error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."resources_util.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = "/tmp/".$_POST['cfg'].".sc";
  $mode = $_POST['mode'];
  $resp="";
  $tmp_arr=array();
  $clname=array();
  $clstat=array();

  $toolsinfo_server = parse_ini_file('configsys/tools.info.server', true);
  $sc_cmd = $toolsinfo_server['starcluster']['path']."/".$toolsinfo_server['starcluster']['exe'];

  $tmp = shell_exec("$sc_cmd -c $cfg lc 2>&1");
  if(preg_match('/AuthFailure/',$tmp)) {
    echo "<div class='tdindent'><p><b>Error: Authentication failure. Please check your access credentials!</b></p></div>";
    exit;
  }
  $tmp = preg_replace('/nodes:\s+master/',"nodes: master", $tmp);
  $tmp_arr = array_filter(explode("\n",$tmp));
  $clname  = array_values(preg_grep('/\(security\ group:/', $tmp_arr));
  $clkeypr = array_values(preg_grep('/Keypair:/', $tmp_arr));

  $clstat  = array_values(preg_grep('/Cluster nodes:/', $tmp_arr));  // has master


  if( count($clname)!=count($clstat) || count($clname)!=count($clkeypr) ) {
    echo "<div class='tdindent'><p><b>Error: instances did not parse as expected!</b></p></div>";
    exit;
  } 


  if ($mode == 2) { // terminate
    $nm = $_POST['nm'];
    for($i = 0; $i < count($clname); $i++) {
      $a = preg_split('/\s+/', $clname[$i]);
      if( $a[0]==$nm  &&  preg_match('/master\ running/', $clstat[$i])) {
	
	file_put_contents("testout", "looking to terminate...\n", FILE_APPEND);
	$a = preg_split('/\s+/', $clkeypr[$i]);
	$keyfile = "/tmp/".$a[1].".rsa";
	
	if( file_exists($keyfile) ){
	  $this_sc = preg_replace('/rsa$/', 'sc', $keyfile);
	  
	  $cmd= "$sc_cmd -c $this_sc terminate -c -f $nm 2>&1";
	  file_put_contents("testout", $cmd."\n", FILE_APPEND);
	  $log = shell_exec($cmd);
	  if( preg_match('/Removing security group/', $log)) {
	    $resp='1';  // success
	  } else {
	    $resp='0';
	  }
	}
      }
    }
    
  }  elseif ($mode == 0) {  // manage or terminate mode
    
    $resp="<div class='tdindent'><table id='mantab' class='clusttab'><tr><th class='manw'>Cluster</th><th class='manw'>Status</th><th class='manw'>Command</th></tr>";
    $bFound=0;
    for($i = 0; $i < count($clname); $i++) {
      if( preg_match('/master\ (run|stop)/', $clstat[$i])) {
	$bFound=1;

	$a = preg_split('/\s+/', $clstat[$i]);
	$b = preg_split('/\s+/', $clname[$i]);
	$resp.="<tr><td>".$b[0]."</td><td>".$a[3]."</td><td><input type='button' name='End' id='end_clust' value='Terminate' onclick=\"javascript:do_end_clust('".$b[0]."');\"></td></tr>";
      }
    }
    
    if(!$bFound){ 
      $resp.="<tr><td>N/A</td><td>N/A</td><td>N/A</td></tr>";
    }
    $resp.="</table></div>";
    $resp.="<div class='tdindent'><input type='button' name='sc_refresh' value='Refresh' onclick=\"javascript:get_sc_running(0);\"/></div><br/>";
    
    
  } elseif ($mode == 1) { // display or run mode
    

    $resp="<div class='tdindents'><table id='mantab_l' class='clusttab'><tr><th>Select</th><th>Cluster</th></tr>";
    $bFound=0;
    if( count($clname) ) {
      for($i = 0; $i < count($clname); $i++) {
	if( preg_match('/master\ running/', $clstat[$i])) {
	  $a = preg_split('/\s+/', $clkeypr[$i]);
	  if( $cfg == "/tmp/".$a[1].".sc" ) { // current config only
	    $bFound=1;
	    $b = preg_split('/\s+/', $clname[$i]);
	    $resp.="<tr><td><input type='radio' name='clusters_l' value='".$b[0]."'/></td><td>".$b[0]."</td></tr>";
	  }
	}
      }
    }
    $resp.="<tr><td><input type='radio' name='clusters_l' value='nada' checked/></td><td><i>None</i></td></tr>";
    $resp.="</table></div>";
    $resp.="</p><br></p>";
    
  } 
  
  echo trim($resp);
  
}
?>