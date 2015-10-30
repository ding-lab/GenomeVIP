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
  $scconf = "/tmp/".$_POST['cfg'].".sc";
  $known=array();
  $types=array();
  $results=array();
  $myresults=array();
  //  file_put_contents("testout", "get_clusters cmd ".$cmd."\n", FILE_APPEND);
  //  file_put_contents("testout", "get_clusters tgt ".$tgt."\n", FILE_APPEND);


  if ($cmd == "s3bucket") {
    switch ($tgt) {
    case 0: // aws                 
      $tmp = getid($cmd, $results, $scconf);
      break;
    case 1: // local
      $results[0] = "local";
    }
  } elseif ($cmd == "cluster") {
    switch ($tgt) {
    case 0: // aws
      // get fully configured sc clusters
      // $tmp = getid2($cmd, $known, $scconf);
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
				 'odp'  => $info['odp'],
				 );
	$clustdb[$sc_type]=$info['name'];
      }
      usort( $myresults, function ($item1, $item2) {  return $item1['mem'] - $item2['mem'];});
      
      // Cluster
      $cluster="<div class='tdindents'><table id='clusttab' class='clusttab'><tr><th>Select</th><th>Type</th><th>n(VCPU)</th><th>n(ECPU)</th><th>Memory(GB)</th></tr>";
      for ($k=0; $k < count($myresults); $k++) {
	$cluster .= "<tr><td align='center'><input type='radio' name='clusters' value='".$myresults[$k]['ec2']."'/></td>";
	$cluster .= "<td align='center'>".$myresults[$k]['name']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['vcpu']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['ecpu']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['mem']."</td></tr>";
      }
      $cluster .= "<tr><td align='center'><input type='radio' name='clusters' value='nada' checked/></td><td align='center'><i>None</i></td><td></td><td></td><td></td></tr>";
      $cluster .= "</table></div>";
      $cluster .= "<p><br></p>";
      
      // Instances
      $myinst  = "<p class='tdindent' style='display:none;'>Enter number of instances in the cluster:&nbsp;";
      $myinst .= "<select id='nclust' name='nclust' class='tb1dmenu'>";
      for($k=1;$k<=20;$k++) { $myinst .= "<option class='menu1' value='".$k."'>".$k."</option>"; }
      $myinst .="</select></p>";
      $myinst .="<p><br></p>";
      
      $results=array('ctab' => $cluster, 'clustdb' => json_encode($clustdb), 'myinst' => $myinst);
      break;


    case 1: // local

      $k=0;
      $myresults[$k] = array('ec2'  => "local",
			     'vcpu' => "n/a",
			     'ecpu' => "n/a",
			     'mem'  => "n/a",
			     'name' => "local",
			     'odp'  => "n/a",
			     );
      $cluster = "<div class='tdindents'><table id='clusttab2' class='clusttab'><tr><th>Select</th><th>Type</th><th>n(VCPU)</th><th>n(ECPU)</th><th>Memory(GB)</th></tr>";
      for ($k=0; $k < count($myresults); $k++) {
	$cluster .= "<tr><td align='center'><input type='radio' name='clusters2' value='".$myresults[$k]['ec2']."' ".(($k==0)?("checked"):(""))."/></td>";
        $cluster .= "<td align='center'>".$myresults[$k]['name']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['vcpu']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['ecpu']."</td>";
	$cluster .= "<td align='center'>".$myresults[$k]['mem']."</td></tr>";
      }
      $cluster .= "</table></div>";
      $cluster .= "<p><br></p>";


      $results=array('ctab' => $cluster);
      break;


    }
  } else {
  }

  echo json_encode($results);
}

?>