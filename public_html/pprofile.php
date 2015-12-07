<?php
// --------------------------------------
// @name GenomeVIP execution profile creation script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
ini_set('display_errors',1);
error_reporting(E_ALL);

include realpath(dirname(__FILE__)."/"."array_defs.php");
include realpath(dirname(__FILE__)."/"."versions.php");

$pp = array();

array_push($pp, "; GenomeVIP execution profile\n");
$title=trim($_POST['jobtitle']);
array_push($pp, "; $title\n");
array_push($pp, "\n");

$prefix="genomevip";
array_push($pp, "[ $prefix ]\n");
array_push($pp, "$prefix.created = ".date(DATE_ISO8601)."\n");
array_push($pp, "$prefix.version = $gvip_version\n");
array_push($pp, "\n");

$thetools = array();
array_push($pp, "; Supporting tools (global)\n");
if ($_POST['compute_target']=="AWS") {
  $thetools = parse_ini_file('configsys/tools.info.AWS', true);
} else {
  $thetools = parse_ini_file('configsys/tools.info.local', true);
}
array_push($pp, "; samtools.version = ".$thetools['samtools']['version']."\n");
array_push($pp, "; java.version = ".$thetools['java']['version']."\n");
array_push($pp, "\n");
unset($thetools);


if (isset($_POST['vs_cmd'])) {
  $prefix="varscan";
  array_push($pp, "[ $prefix ]\n");
  
  $vs_opts = array("version", 
		   "call_mode", 
		   "chrdef",
		   );
  foreach ($vs_opts as $value) {
    $key = "vs_$value";
    switch ($value){
    case "version":
      array_push($pp, "$prefix.$value = ".$vs_ver_map[$_POST[$key]]."\n");
      break;
    default:
      array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
    }
  }
  
  
  switch ($_POST['vs_call_mode']) {
    
    
  case "germline":
    $mode = $_POST['vs_call_mode'];
    array_push($pp, "varscan.$mode.calltype = ".$_POST['vs_gl_calltype']."\n");
    
    array_push($pp, "varscan.$mode.samples = ".$_POST['vs_gl_samples']."\n");
    
    $vs_opts = array("p_value",
		     "min_coverage",
		     "min_var_allele_freq",
		     "min_num_supporting_reads",
		     );
    if ($_POST['vs_gl_calltype']=="both" || $_POST['vs_gl_calltype']=="snv") {
      $prefix = "varscan.$mode.snv";
      foreach ($vs_opts as $value) { 
	$key = "vs_gl_snv_$value";
	array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }
    if ($_POST['vs_gl_calltype']=="both" || $_POST['vs_gl_calltype']=="indel") {
      $prefix = "varscan.$mode.indel";
      foreach ($vs_opts as $value) { 
	$key = "vs_gl_indel_$value";
	array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }
    
    $vs_opts = array("min_avg_base_qual",
		     "homozyg_min_var_allele_freq",
		     "apply_strand_filter",
		     "samtools_min_mapping_qual",
		     "samtools_min_base_qual",
		     "samtools_perform_BAQ",
		     );
    $prefix = "varscan.$mode";
    foreach ($vs_opts as $value) { 
      $key = "vs_gl_$value";
      switch ($value) {
      case "apply_strand_filter":  
      case "samtools_perform_BAQ":
	array_push($pp, "$prefix.$value = \"".$_POST[$key]."\"\n");
      break;
      default:
	array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }
    array_push($pp, "\n");
    
    if ( ! isset($_POST['vs_apply_high_confidence_filter']) ) {
      array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"false\"\n");
    } else {
      array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"true\"\n");
      
      if ($_POST['vs_gl_calltype']=="both" || $_POST['vs_gl_calltype']=="snv") {
	$prefix = "varscan.filter.$mode.snv";
	foreach ($vs_gl_opts_gen_f as $value) { 
	  $key = "vs_gl_filter_snv_$value";
	  array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
	}
      }
      if ($_POST['vs_gl_calltype']=="both" || $_POST['vs_gl_calltype']=="indel") {
	$prefix = "varscan.filter.$mode.indel";
	foreach ($vs_gl_opts_gen_f as $value) { 
	  $key = "vs_gl_filter_indel_$value";
	  array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
	}
      }
    }
    
    break;
    
    
    
    
  case "somatic": 
    $mode = $_POST['vs_call_mode'];
    
    $prefix = "varscan.$mode";
    foreach ( ($vs_som_opts + $vs_samtools_opts) as $tmpkey => $novalue ) {
      $key = "vs_som_$tmpkey";
      switch($key) {
      case "vs_som_apply_strand_filter":
      case "vs_som_report_validation":
      case "vs_som_samtools_perform_BAQ":
	array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
      break;
      default:
	array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }
    array_push($pp, "\n");
    
    if ( ! isset($_POST['vs_apply_high_confidence_filter']) ) {
      array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"false\"\n");
    } else {
      array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"true\"\n");
      
      $prefix = "varscan.filter.$mode";
      foreach ( ($vs_som_opts_hcf_snv + $vs_som_opts_hcf_indel + $vs_som_opts_som_f) as $tmpkey => $novalue) {
	$key = "vs_som_filter_$tmpkey";
	array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }
    break;
    
    
    
    
  case "trio":
    $mode = $_POST['vs_call_mode'];
    
    $prefix = "varscan.$mode";
    foreach ( ($vs_trio_opts + $vs_samtools_opts)  as  $tmpkey => $novalue ) {
      $key = "vs_trio_$tmpkey";
      switch($key) {
      case "vs_trio_apply_strand_filter":
      case "vs_trio_samtools_perform_BAQ":
	array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
      break;
      default:
	array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }
      array_push($pp, "\n");
      
      if ( ! isset($_POST['vs_apply_high_confidence_filter']) ) {
        array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"false\"\n");
      } else {
        array_push($pp, "varscan.$mode.apply_high_confidence_filter = \"true\"\n");

        $prefix = "varscan.filter.$mode";
        foreach ($vs_trio_opts_hcf  as $tmpkey => $novalue) {
          $key = "vs_trio_filter_$tmpkey";
          array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
        }
      }
      break;


    default:
      ;


    }   // mode switch

    array_push($pp, "\n");



    // dbSNP filter
    if( ! isset($_POST['vs_apply_dbsnp_filter'])) {
      array_push($pp, "varscan.apply_dbsnp_filter = \"false\"\n");
    } else {
      array_push($pp, "varscan.apply_dbsnp_filter = \"true\"\n");
      
      //      $prefix = "varscan.dbsnp";
      //      $key = "dbsnp_version";
      //      array_push($pp, "$prefix.$key = ".$_POST[$key]."\n");
    }
    array_push($pp, "\n");


    // VarScan false positives filter
    if( ! isset($_POST['vs_apply_false_positives_filter']) ) {
        array_push($pp, "varscan.apply_false_positives_filter = \"false\"\n");
      } else {
        array_push($pp, "varscan.apply_false_positives_filter = \"true\"\n");

	//	$prefix = "varscan.fpfilter";
	//	foreach ($vs_opts_fpfilter as $value) {
	//	  $key = "vs_fp_$value";
	//	  array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
	//	}
      
    }
    array_push($pp, "\n");





  }  // end vs
  
  // ------------------------------------------------------------

  if( isset($_POST['strlk_cmd'])) {
    array_push($pp, "\n");

    $prefix = "strelka";
    array_push($pp, "[ $prefix ]\n");

    array_push($pp, "$prefix.version = ".$strlk_ver_map[$_POST['strlk_version']]."\n");

    foreach ($strlk_opts as $tmpkey => $novalue) {
      $key = "strlk_$tmpkey";
      switch($tmpkey) {
      case "skip_depth_filters":
      case "write_realignments":
	//case "extra_arguments": // no quotes here; bad for strelka
	array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
        break;
      default:
	array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }

    array_push($pp, "\n"); 

    // dbSNP filter
    if( ! isset($_POST['strlk_apply_dbsnp_filter'])) {
      array_push($pp, "strelka.apply_dbsnp_filter = \"false\"\n");
    } else {
      array_push($pp, "strelka.apply_dbsnp_filter = \"true\"\n");
    }
    array_push($pp, "\n");


    // strelka false positives filter
    if( ! isset($_POST['strlk_apply_false_positives_filter']) ) {
      array_push($pp, "strelka.apply_false_positives_filter = \"false\"\n");
    } else {
      array_push($pp, "strelka.apply_false_positives_filter = \"true\"\n");
    }
    array_push($pp, "\n");
    



  }  // end strelka

  // ------------------------------------------------------------


  if(isset($_POST['bd_cmd'])) {
    array_push($pp, "\n");

    $prefix = "breakdancer";
    array_push($pp, "[ $prefix ]\n");

    $bd_opts = array("version", 
		     "call_mode",
		     "chrdef",
		     );
    foreach ($bd_opts as $value) {
      $key = "bd_$value";
      switch ($value) {
      case "version":
	array_push($pp, "$prefix.$value = ".$bd_ver_map[$_POST['bd_version']]."\n");
	break;
      default:
	array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }
    

    $prefix = "breakdancer.bamcfg";
    foreach ($bd_bamcfg_opts as $tmpkey => $value) {
      $key = "bd_bamcfg_$tmpkey";
      switch($tmpkey) {
      case "output_mapping_flag_distn":
      case "create_insert_size_histo":
      case "use_mapping_qual":
      case "system_type":
        array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
        break;
      default:
        array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }

    
    $prefix = "breakdancer";
    foreach ($bd_opts_2 as $tmpkey => $value) {
      $key = "bd_$tmpkey";
      switch($tmpkey) {
      case "translocation_calltype":
      case "fastq_outfile_prefix_of_supporting_reads":
      case "dump_SVs_and_supporting_reads":
      case "analyze_long_insert":
      case "count_support_mode":
      case "print_allele_freq_column":
	array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
        break;
      default:
        array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }
    array_push($pp, "\n");


    // BreakDancer filtering (basically, somatic or trio only) 
    $prefix = "breakdancer";
    foreach ($bd_opts_f as $tmpkey => $value) {
      $key = "bd_$tmpkey";
      switch ($tmpkey) {
      case "apply_bam_filter":
	if (! isset($_POST[$key])) {
	  array_push($pp, "$prefix.$tmpkey = \"false\"\n");
	} else {
	  array_push($pp, "$prefix.$tmpkey = \"true\"\n");
	}
      }
    }
    array_push($pp, "\n");



  } // bder 

  // ------------------------------------------------------------

  if(isset($_POST['pin_cmd'])) {
    array_push($pp, "\n");

    $prefix = "pindel";
    array_push($pp, "[ ".$prefix." ]\n");

    $pin_opts_local = array("version", 
			    "call_mode", 
			    "chrdef",
			    );
    foreach ($pin_opts_local as $value) {
      $key = "pin_$value";
      switch ($value){
      case "pin_version":
	array_push($pp, "$prefix.$value = ".$pin_ver_map[$_POST[$key]]."\n");
	break;
      default:
	array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }
    


    foreach ( ($pindel_opts_more +  $pindel_opts) as $tmpkey => $value) { 
      $key = "pin_$tmpkey";
      switch ($tmpkey) {
      case "do_inversions":  
      case "do_tandem_dups":  
        array_push($pp, "$prefix.$tmpkey = \"".(isset($_POST[$key])?("true"):("false"))."\"\n");
        break;
      case "include_breakdancer":  
	//case "do_mobile_insertions": 
	//case "pin_logfile_prefix":
        array_push($pp, "$prefix.$tmpkey = \"".$_POST[$key]."\"\n");
        break;
      case "pindel_chr": 
	break;
      default:
        array_push($pp, "$prefix.$tmpkey = ".$_POST[$key]."\n");
      }
    }
    array_push($pp, "\n");


    // Pindel Filters - method to avoid dependency issues
    // print_pindel_common_filter();
    include realpath(dirname(__FILE__)."/"."write_filter.php");
    
    array_push($pp, "\n");

  } // pindel



  // ------------------------------------------------------------


  if(isset($_POST['gs_cmd'])) {
    array_push($pp, "[ genomestrip ]\n");

    $prefix = "genomestrip";
    array_push($pp, "$prefix.version = ".$gs_ver_map[$_POST['version_gs']]."\n");

    $gs_opts = array("run_mode",
		     "samples",
		     "chrdef",
		     );
    $prefix = "genomestrip";
    foreach ($gs_opts as $value) {
      $key = "gs_$value";
      array_push($pp, "$prefix.$value = \"".$_POST[$key]."\"\n");
    }

		     // informational only
    //	*	     gs_sel_genome
    //	*	     gs_sel_svmask
    //	*	     gs_genermap
    //	*	     gs_ploidymap
    //	  * gs_sizerange:  using values of "default" and "large"

    array_push($pp, "; user-modifiable GenomeSTRiP options\n");
    $prefix = "genomestrip";
    foreach ($gs_opts1 as $value) {
      $key = "gs_$value";
      switch ($value) {
      case "genotyping_modules":
	$opts = array();
	if(isset($_POST['gs_genotyping_depth'])) { array_push($opts,"depth"); }
	if(isset($_POST['gs_genotyping_pairs'])) { array_push($opts,"pairs"); }
	if(isset($_POST['gs_genotyping_split'])) { array_push($opts,"split"); }
        array_push($pp, "$prefix.$value = \"".implode(',', $opts)."\"\n");
	break;
      case "depth_useGCNormalization":   // true false
      case "coherence_writeCoherenceDataFile":
      case "depth_writeSampleCountFile":
      case "depth_writeSampleCoverageFile":
      case "depth_writeReadCounts":
      case "depth_writeExpectedCounts":
      case "depth_writeNormalization":
      case "depth_writeModels":
      case "pairs_writePairCounts":
      case "pairs_writeReadPairs":
      case "split_writeSplitReads":
      case "split_writeSplitReadInfoFile":
        array_push($pp, "$prefix.$value = \"".$_POST[$key]."\"\n");
        break;
      case "cluster_clusterOrientations":  // LR or *
        array_push($pp, "$prefix.$value = \"".(($_POST[$key]=="LR")?("LR"):("*"))."\"\n");
        break;
      default:
        array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }

    array_push($pp, "; static GenomeSTRiP options (recommended)\n");
    $prefix = "genomestrip";
    foreach ($gs_opts_fixed as $value) {
      $key = "gs_$value";
      switch ($value) {
      case "split_ignoreReferenceMatches":
      case "output_writeDepthProbs":
      case "output_writeReadPairProbs":
      case "output_writeSplitReadProbs":
      case "metadata_writeArrayIntensityFile":
      case "depth_mixtureModel":                                            
      case "depth_readReadCounts":
      case "pairs_excludeJunctionReads":
	array_push($pp, "$prefix.$value = \"".$_POST[$key]."\"\n");
        break;
      default:
        array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
      }
    }

    array_push($pp, "\n");

  } // gs

  // ------------------------------------------------------------

  // Need only if tools specify
  // Using varscan opts as global
  if( isset($_POST['vs_apply_false_positives_filter']) || isset($_POST['strlk_apply_false_positives_filter']) ) {
    array_push($pp, "[ false_positives_filter ]\n"); 

    $prefix = "fpfilter";
    foreach ($vs_opts_fpfilter as $value) {
      $key = "vs_fp_$value";
      array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
    }

    array_push($pp, "\n");
  }    


  // ------------------------------------------------------------

  // Need only if tools specify
  if( isset($_POST['vs_apply_dbsnp_filter']) ||  isset($_POST['strlk_apply_dbsnp_filter']) ) {
    array_push($pp, "[ dbsnp ]\n"); 
    $prefix = "dbsnp";
    $value  = "dbsnp_version";
    $key    = "dbsnp_version";
    array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");

    array_push($pp, "\n");
  }
    


  // ------------------------------------------------------------

  if( isset($_POST['vep_cmd']) ) {
    array_push($pp, "[ variant_effect_predictor ]\n"); 

    $prefix = "vep";
    $value = "vep_version";
    $key    = $value;
    array_push($pp, "$prefix.$value = ".$_POST[$key]."\n");
  }

  array_push($pp, "\n");


  // ------------------------------------------------------------

echo json_encode($pp);

?>