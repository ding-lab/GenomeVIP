<?php
// --------------------------------------
// @name GenomeVIP get versions
// @version
// @author R. Jay Mashl <rmashl@wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $ini_map = array(
		   0 => "configsys/tools.info.AWS",
		   1 => "configsys/tools.info.local",
		   );
  $tgt     = $_POST['tgt'];
  $cl_str  = $_POST['cl_str'];
  $pattern = "/^".$_POST['cmd']."/";
  $resp    = "";

  if ($tgt == -1) {
    $resp = "<option class=\"".$cl_str."\" value=\"0\">"."Select an account to view options"."</option>";
  } else {
    $cfg = parse_ini_file( $ini_map[$tgt], true);
    foreach ($cfg as $k => $v) {
      if (preg_match($pattern, $k)) {
	$resp .= "<option class=\"".$cl_str."\" value=\"".$k."\">".$v['desc']."</option>";
      }
    }
    $user_str = $_POST['cmd']."_user";
    $resp .= "<option class=\"".$cl_str."\" value=\"".$user_str."\">"."version user"."</option>";
  }
  echo $resp;
}
?>