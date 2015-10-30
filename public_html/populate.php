<?php
// --------------------------------------
// @name GenomeVIP another filename processing script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
function populate(&$data) {
  $fline = array_filter(explode("\n", $data));
  
  // Get possible paths
  for ($k=0; $k < $fline[0]; $k++) {

    echo  "<input type='hidden' name='pathdb[" . $k . "]' value='" . $fline[ ($k+1) ] . "'/>\n";      //	for $("#pathdb_div").append
  }
  
  $h_bam = array();  $h_bai  = array(); $h_bam_p = array();  $h_bai_p  = array();    
  $h_ref = array();  $h_refi = array(); $h_ref_p = array();  $h_refi_p = array();

  $h_mask = array(); $h_maski = array();   $h_mask_p = array(); $h_maski_p = array(); 
  $h_genmap  = array();                    $h_genmap_p = array();
  $h_cnmap  = array();                     $h_cnmap_p = array();
  $h_dict  = array();                      $h_dict_p = array();
  $h_ploidymap = array();                  $h_ploidymap_p = array();

  // Bin file types
  for ($k=($fline[0]+1) ; $k < count($fline); $k++) {       // CHECK INDEX RANGE
    $cols = explode("\t", $fline[$k]);
    if (count($cols)  == 2 ) {  // prevents typeerror undefined
      $idx = $cols[0];
      $realfile = $cols[1];
      if (strlen($realfile) > 0 ) {
	if (preg_match('/bam\z/',    $realfile)) {
	  $h_bam[ $realfile ] = 0; $h_bam_p[ $realfile ] = $idx;
	} else if (preg_match('/bai\z/', $realfile)) {
	  $h_bai[ $realfile ] = 0; $h_bai_p[ $realfile ] = $idx;

	} else if (preg_match('/mask.*\.fa(sta)?(\.gz)?(\.bz2)?\z/i', $realfile)) {
	  $h_mask[ $realfile ] = 0; $h_mask_p[ $realfile ] = $idx;

	} else if (preg_match('/mask.*(\.gz)?(\.bz2)?\.fai\z/i', $realfile)) {
	  $h_maski[ $realfile ] = 0; $h_maski_p[ $realfile ] = $idx;



	} else if (preg_match('/\.fa(sta)?(\.gz)?(\.bz2)?\z/', $realfile) ) {
	  $h_ref[ $realfile ] = 0; $h_ref_p[ $realfile ] = $idx;
	} else if ( preg_match('/(\.gz)?(\.bz2)?\.fai\z/', $realfile) ) {
	  $h_refi[ $realfile ] = 0; $h_refi_p[ $realfile ] = $idx;

	} else if (preg_match('/(gender.*map)|(map.*gender)/', $realfile)) {
	  $h_genmap[ $realfile ] = 0; $h_genmap_p[ $realfile ] = $idx;

	} else if (preg_match('/(ploidy.*map)|(map.*ploidy)/', $realfile)) {
	  $h_ploidymap[ $realfile ] = 0; $h_ploidymap_p[ $realfile ] = $idx;

	} else if (preg_match('/(ploidy.*map)|(map.*ploidy)/', $realfile)) {
	  $h_ploidymap[ $realfile ] = 0; $h_ploidymap_p[ $realfile ] = $idx;

	} else if (preg_match('/\.dict\z/', $realfile)) {   // assume end of word
	  $h_dict[ $realfile ] = 0;       $h_dict_p[ $realfile ] = $idx;



	}

      }}}

  
  if (count($h_bam)) {
    // Process bais
    $iter = 0;
    foreach ($h_bam as $key => $value) {
      if (array_key_exists( $key.".bai", $h_bai )) {
	$h_bam[$key] = 1;  // match found
	if ( $h_bai_p[ $key.".bai" ] != $h_bam_p[$key]) { // check locations
	  
	  echo "<input type='hidden' name='baipath[" . $iter . "]' value='" .$key.".bai" . "|" . $h_bai_p[ $key.".bai" ] .  "'/>\n";  // for $("#bai_div").append
	  $iter++;
	} } }
    
    // Process bams
    $iter = 0;
    foreach ($h_bam as $key => $value) {
      // (rjm) Insert using multiselect2side's id instead of our select id
      $tag = $iter . "|" . $h_bam_p[$key] . "|" . $key . "|" . $h_bam[$key];  // selectid, path, name, bai_exists 
      echo "<option class='isbamX " . ( preg_match('/TCGA-\w{2}-\w{4}-0/',$key) ?("tumor "):("")) . (preg_match('/TCGA-\w{2}-\w{4}-1/',$key) ?("norm "):("")) . "' value='" . $tag . "'>" . $key . "</option>\n";  // for $("#searchable").append
      echo "<option class='isbamY " . ( (!$h_bam[$key]) ? ("nobai ") : ("")) . ( preg_match('/TCGA-\w{2}-\w{4}-0/',$key) ?("tumor "):("")) . (preg_match('/TCGA-\w{2}-\w{4}-1/',$key) ?("norm "):("")) . "' value='" . $tag . "'>" . $key . "</option>\n";  // for $("#bamfilesms2side__sx").append
      $iter++;
    }
  }
  
  // Process fai
  $iter = 0;
  if (count($h_ref)) {
  foreach ($h_ref as $key => $value) {
    if (array_key_exists( $key.".fai", $h_refi) )  {
      $h_ref[$key] = 1;
      if ($h_refi_p[ $key.".fai"] != $h_ref_p[$key]) {

	echo "<input type='hidden' name='faipath[" . $iter . "]' value='" .$key.".fai" . "|" . $h_refi_p[ $key.".fai" ] . "'/>\n";  // for $("#fai_div").append
	$iter++;
      } } }
  }
  // Process fa
  $iter = 0;  
  if (count($h_ref_p)) {
    foreach ($h_ref_p as $key => $value) {  
      $tag = $h_ref_p[$key] . "|" . $key . "|" . $h_ref[$key];  // path, name, exist
      echo  "<option class='isref " . ( (!$h_ref[$key]) ? ("norefi"):("")) . "' value='" . $iter . "|" . $tag . "'>" . $key . "</option>\n"; // for $("#sel_genome").append
      echo "<input type='hidden' name='reffiles[" . $iter . "]' value='".$tag."'/>\n";  // for $("#ref_div").append
      $iter++;
    }
  }

  // Process maski
  $iter = 0;
  if (count($h_mask)) { 
  foreach ($h_mask as $key => $value) {
    if (array_key_exists( $key.".bai", $h_maski )) {
      $h_mask[$key] = 1;  // match found
      if ( $h_maski_p[ $key.".bai" ] != $h_mask_p[$key]) { // check locations
	echo "<input type='hidden' name='maskipath[" . $iter . "]' value='" .$key.".bai" . "|" . $h_maski_p[ $key.".bai" ] .  "'/>\n";  
	$iter++;
      } } }
  }

  // Process mask
  $iter = 0;  
  if (count($h_mask_p)) {
  foreach ($h_mask_p as $key => $value) {  
    $tag = $h_mask_p[$key] . "|" . $key . "|" . $h_mask[$key];  // path, name, exist
    echo  "<option class='ismask " . ( (!$h_mask[$key]) ? ("nomaski"):("")) . "' value='" . $iter . "|" . $tag . "'>" . $key . "</option>\n"; 
    echo "<input type='hidden' name='maskfiles[" . $iter . "]' value='".$tag."'/>\n";
    $iter++;
  }
  }
  // Process gender
  $iter = 0;
  if (count($h_genmap)) {
  foreach ($h_genmap as $key => $value) {
    $tag = $h_genmap_p[$key] . "|" . $key ;  // path, name
    echo  "<option class='isgender " .  "' value='" . $iter . "|" . $tag . "'>" . $key . "</option>\n"; 
    $iter++;
  }
  }
  // Process ploidy
  $iter = 0;
  if (count($h_ploidymap)) {
  foreach ($h_ploidymap as $key => $value) {
    $tag = $h_ploidymap_p[$key] . "|" . $key ;  // path, name
    echo  "<option class='isploidy " .  "' value='" . $iter . "|" . $tag . "'>" . $key . "</option>\n"; 
    $iter++;
  }
  }
  // Process dict
  $iter = 0;  
  if (count($h_dict_p)) {
  foreach ($h_dict_p as $key => $value) {  
    $tag = $h_dict_p[$key] . "|" . $key ;  // path, name
    echo "<input type='hidden' name='dictfiles[" . $iter . "]' value='".$tag."'/>\n";
    $iter++;
  }
  }

  
}

?>