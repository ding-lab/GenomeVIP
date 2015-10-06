<?php
// --------------------------------------
// @name GenomeVIP printing script for pindel common filters
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
//function print_pindel_common_filter(&$pp) {
//  include realpath(dirname(__FILE__)."/"."array_defs.php");


  // Pindel Filters
  $prefix = "pindel.filter";

  foreach ($pindel_gen_opts_f as $value) { 
    $key = "pin_filter_$value";
    array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
  }


  array_push($pp, "$prefix.mode = ".$_POST['pin_call_mode']."\n");
    
  array_push($pp, "$prefix.apply_filter = ");
  if( (! isset($_POST['pin_apply_filter']))) {

    array_push($pp, "false\n");

  } else {

    array_push($pp, "true\n");

    switch ($_POST['pin_call_mode']) {
    case "germline":
      foreach ($pindel_single_opts_f as $value) { 
	$key = "pin_gl_filter_$value";
	switch ($value) {
	case "require_balanced_reads":
	  array_push($pp, "$prefix.germline.$value = \"".$_POST[$key]."\"\n");
	  break;
	default:
	  array_push($pp, "$prefix.germline.$value = ".$_POST[$key]."\n");
	}
      }
      break;
      
    case "pooled":
      foreach ($pindel_pooled_opts_f as $value) {
	$key = "pin_pool_filter_$value";
	switch ($value) {
	default:
	  array_push($pp, "$prefix.germline.$value = ".$_POST[$key]."\n");
	}
      }
      break;
      
    case "somatic":
      foreach ($pindel_paired_opts_f as $value) { 
	$key = "pin_som_filter_$value";
	switch ($value) {
	case "require_balanced_reads":
	case "remove_complex_indels":
	  array_push($pp, "$prefix.somatic.$value = \"".$_POST[$key]."\"\n");
  	  break;
	default:
	  array_push($pp, "$prefix.somatic.$value = ".$_POST[$key]."\n");
	}
      }
      break;
      
    case "trio":
      foreach ($pindel_trio_opts_f as $value) {
	$key = "pin_trio_filter_$value";
	switch ($value) {
	case "require_balanced_reads":
	  array_push($pp, "$prefix.trio.$value = \"".$_POST[$key]."\"\n");
	  break;
	default:
	  array_push($pp, "$prefix.trio.$value = ".$_POST[$key]."\n");
	}
      }
      break;
    default:
      ;
    }
      
  }

?>