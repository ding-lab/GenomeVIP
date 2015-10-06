<?php
// --------------------------------------
// @name GenomeVIP Amazon handler script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu> 
// --------------------------------------
//ini_set('display_errors',1);
//error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."resources_util.php");


if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $cmd = $_POST['cmd'];
  $tgt = $_POST['tgt'];
  $scconf = $_POST['scconf'];
  $known=array();
  $types=array();
  $results=array();
  $myresults=array();




  if ($cmd == "s3bucket") {
    switch ($tgt) {
    case 0: // aws                 
      $tmp = getid($cmd, $results, $scconf);
      break;
    case 1: // tgi
      $results[0] = "tgi";
    }
  } elseif ($cmd == "cluster") {
    switch ($tgt) {
    case 0: // aws


      // get known cluster types
      $tmp = getawstypes($types);
      $clustdb=array();
      $k=0;
      foreach ($types as $sc_type=>$info) {
	$myresults[$k++] = array('ec2'  => $sc_type, 
				 'vcpu' => $info['vcpu'],
				 'ecpu' => $info['ecpu'], 
				 'mem'  => $info['mem' ],
				 'name' => $info['name'],
				 'odp' => $info['odp'],
				 );
	$clustdb[$sc_type]=$info['name'];
      }
      usort( $myresults, function ($item1, $item2) {  return $item1['mem'] - $item2['mem'];});
      $results=array('myresults'=>$myresults, 'clustdb'=> json_encode($clustdb));
      break;


    case 1: // tgi
      $results[0] = array( 'name' => "tgi");




    }
  } else {
  }

  echo json_encode($results);
}

?>