<?php
// --------------------------------------
// @name GenomeVIP sessionID creation routine
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."resources_util.php");
include realpath(dirname(__FILE__)."/"."fileconfig.php");

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  global $s3template;
  $cmd   = $_POST['cmd'];
  $randlen=8;


  $toolsinfo_server = parse_ini_file('configsys/tools.info.server', true);
  $sc_cmd = $toolsinfo_server['starcluster']['path']."/".$toolsinfo_server['starcluster']['exe'];
  $TMPDIR = "/tmp";
  
  file_put_contents("testout", "sid command = $cmd\n", FILE_APPEND);
  if( $cmd == "create" )  {    // Create session
    // Try to generate valid filename
    $tries_left=5;
    $pass=-1;
    while($tries_left && $pass!=1) { 
      $stempid = generateRandomString($randlen);
      $tmp_sess = "$TMPDIR/$stempid.sc";
      if(file_exists($tmp_sess)) {
	$tries_left--;
	$pass=0;
      } else {
	$pass=1;
      }
    }
    if($pass==1) {
      $un    = trim($_POST['un']);
      $ak    = trim($_POST['ak']);
      $sk    = trim($_POST['sk']);
      $proto = trim($_POST['proto']);
      $enc   = trim($_POST['enc']);
      system("touch $tmp_sess && chmod 0600 $tmp_sess");
      $fp = fopen($tmp_sess, 'w');
      fwrite($fp, "[aws info]\n");
      fwrite($fp, "AWS_ACCESS_KEY_ID = $ak\n");
      fwrite($fp, "AWS_SECRET_ACCESS_KEY = $sk\n");
      fwrite($fp, "AWS_USER_ID = $un\n");
      fflush($fp);
      fclose($fp);
      
      $keyloc = "$TMPDIR/$stempid.rsa";
      $cmd = "$sc_cmd -c $tmp_sess createkey $stempid -o $keyloc 2>&1";
      $output = shell_exec($cmd);
      if(preg_match('/AuthFailure/', $output)) {
	echo 'AuthFailure';
	exit;
      }
      $fp = fopen($tmp_sess, 'a');
      fwrite($fp, "\n");
      fwrite($fp, "[key $stempid]\n");
      fwrite($fp, "KEY_LOCATION = $keyloc\n");
      fflush($fp);
      fclose($fp);
      echo $stempid;
      
      $s3cfg_loc = "$TMPDIR/$stempid.s3cfg";
      $template = file_get_contents( $s3template );
      $patterns = array('/MY_ACCESS_KEY/','/MY_SECRET_KEY/','/My_https_value/','/My_ssenc_value/');
      $replacements = array( $ak, $sk, $proto, $enc );
      $template = preg_replace( $patterns, $replacements, $template );
      file_put_contents($s3cfg_loc, $template);
      
    } else { // no pass 
      echo 'sess_no_pass';
    }

  } elseif ( $cmd == "check") { // Check previous session
    $id    = $_POST['id'];

    if(! preg_match('/[0-9a-zA-Z]/', $id)) {
      echo "badchar";
    } else {
      $f1 = "$TMPDIR/$id.sc";
      $f2 = "$TMPDIR/$id.rsa";
      $f3 = "$TMPDIR/$id.s3cfg";
      if(file_exists($f1) && file_exists($f2) && file_exists($f3)) {
	echo "ok";
      } else {
	echo "err";
      }
    }



  } elseif ( $cmd == "delete" ) {  // Remove session
    $id    = $_POST['id'];

    $this_cmd = "rm -f $TMPDIR/$id.sc $TMPDIR/$id.rsa $TMPDIR/$id.s3cfg";
    system($this_cmd);
    echo "ok";


  }

}
?>