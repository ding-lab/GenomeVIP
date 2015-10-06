<?php
// --------------------------------------
// @name GenomeVIP StarCluster handling script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
//ini_set('display_errors',1);
//error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."resources_util.php");

$SC="starcluster";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cfg = $_POST['cfg'];
  $mode = $_POST['mode'];
  $resp="";
  $tmp_arr=array();
  $clname=array();
  $clstat=array();

  $tmp = shell_exec("$SC -c $cfg lc 2>&1");
  if(preg_match('/AuthFailure/',$tmp)) {
    echo "<div class='tdindent'><p><b>Error: Authentication failure. Please check your access credentials!</b></p></div>";
    exit;
  }
  $tmp = preg_replace('/nodes:\s+master/',"nodes: master", $tmp);
  $tmp_arr = array_filter(explode("\n",$tmp));
  $clname  = array_values(preg_grep('/\(security\ group:/', $tmp_arr));
  $clkeypr = array_values(preg_grep('/Keypair:/', $tmp_arr));

  $clstat  = array_values(preg_grep('/Cluster nodes:/', $tmp_arr));


  if( count($clname)!=count($clstat) || count($clname)!=count($clkeypr) ) {
    echo "<div class='tdindent'><p><b>Error: instances did not parse as expected!</b></p></div>";
    exit;
  } 


  if ($mode == 2) { // terminate
    $nm = $_POST['nm'];
    for($i = 0; $i < count($clname); $i++) {
      $a = preg_split('/\s+/', $clname[$i]);
      if( $a[0]==$nm  &&  preg_match('/r\ running/', $clstat[$i])) {
	

	$a = preg_split('/\s+/', $clkeypr[$i]);
	$keyfile = "/tmp/".$a[1].".rsa";
	
	if( file_exists($keyfile) ){
	  $this_sc = preg_replace('/rsa$/', 'sc', $keyfile);
	} else {
	  // TODO(rjm): subject to future refinement
	  $this_sc = preg_replace('/rsa$/', 'sc', $cfg); 
	}
	$cmd= "$SC -c $this_sc terminate -c -f $nm 2>&1";

	$log = shell_exec($cmd);
	if( preg_match('/Removing security group/', $log)) {
	  $resp='1';  // success
	} else {
	  $resp='0';
	}
      }
    }

  }  else {

    if (count($clname)==0) {
      $resp = "<div class='tdindent'><p>No instances found.</p></div>";
    } else {
      
      if ($mode == 0) {  // manage mode
	$resp="<div class='tdindent'><table id='mantab' class='clusttab'><tr><th>Resource Name</th><th>Command</th></tr>";
	$bFound=0;
	for($i = 0; $i < count($clname); $i++) {
	  if( preg_match('/master\ running/', $clstat[$i])) {
	    $bFound=1;
	    
	    $a = preg_split('/\s+/', $clkeypr[$i]);
	    $keyfile = "/tmp/".$a[1].".rsa";
	    $b = preg_split('/\s+/', $clname[$i]);
	    if( file_exists($keyfile) ){
	      $resp.="<tr><td align='center'>".$b[0]."</td><td align='center'><input type='button' name='End' id='end_clust' value='Terminate' onclick=\"javascript:do_end_clust('".$b[0]."');\"></td></tr>";
	    } else {
	      $resp.="<tr><td align='center'>".$b[0]."</td><td align='center'>Valid keypair not found. <input type='button' name='End' id='end_clust' value='Force Terminate anyway' onclick=\"javascript:do_end_clust('".$b[0]."');\"></td></tr>";
	    }
	    
	    
	  }
	}
	if(!$bFound){ 
	  $resp.="<tr><td align='center'>N/A</td><td>N/A</td></tr>";
	}
	$resp.="</table></div>";
	$resp.="<div class='tdindent'><input type='button' name='sc_refresh' value='Refresh' onclick=\"javascript:get_sc_running(0);\"/></div><br/>";
	
      } elseif ($mode == 1) { // display mode
	














	
	
	$resp="<input type='radio' name='clusters_l' value='nada' checked/>";
	
	
      } 
      
    }


  }

  echo trim($resp);
  
}
?>