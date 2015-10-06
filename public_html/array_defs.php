<?php
// --------------------------------------
// @name GenomeVIP array definitions
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
// Debug
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);


$cmd_names = array(
 "varscan"                  =>"",
 "strelka"                  =>"",
 "breakdancer"              =>"",
 "pindel"                   =>"",
 "genomestrip"              =>"",
 "variant_effect_predictor" =>"",
);


$vs_gl_opts_type_f = array(
 "snv"   => array("snv_min_coverage"               => " --min-coverage ", 
		  "snv_min_var_allele_freq"        => " --min-var-freq ", 
		  "snv_min_num_supporting_reads"   => " --min-reads2 "  ,   
		  "snv_min_num_strands"            => " --min-strands2 ", 
		  "snv_min_avg_base_qual"          => " --min-avg-qual ",
		  "snv_p_value"                    => " --p-value ",    
		  ),
 "indel" => array("indel_min_coverage"             => " --min-coverage ", 
		  "indel_min_var_allele_freq"      => " --min-var-freq ", 
		  "indel_min_num_supporting_reads" => " --min-reads2 "  ,   
		  "indel_min_num_strands"          => " --min-strands2 ", 
		  "indel_min_avg_base_qual"        => " --min-avg-qual ",
		  "indel_p_value"                  => " --p-value ",    
		  ),
);

$vs_gl_opts_gen_f = array(
 "min_coverage",
 "min_var_allele_freq",
 "min_num_supporting_reads",
 "min_num_strands",
 "min_avg_base_qual",
 "p_value",
 );


// use varscan-tagged fpfilter options as global
$vs_opts_fpfilter = array(
 "min_mapping_qual",
 "min_base_qual",
 "min_num_var_supporting_reads", 
 "min_var_allele_freq",          
 "min_avg_rel_read_position",    
 "min_avg_rel_dist_to_3prime_end", 
 "min_var_strandedness",           
 "min_allele_depth_for_testing_strandedness", 
 "min_ref_allele_avg_base_qual",     
 "min_var_allele_avg_base_qual",     
 "max_rel_read_length_difference",   
 "max_mismatch_qual_sum_for_var_reads", 
 "max_avg_mismatch_qual_sum_difference", 
 "min_ref_allele_avg_mapping_qual",     
 "min_var_allele_avg_mapping_qual",    
 "max_avg_mapping_qual_difference",    
 );

$vs_samtools_opts = array(
 "samtools_min_mapping_qual" => " -q ",
 "samtools_min_base_qual"    => " -Q ",
 "samtools_perform_BAQ"      => " -B ",
);

// ----------------------------------------------------------------------------

$vs_som_opts = array(
 "heterozyg_p_value"             => " --p-value ",
 "calling_p_value"               => " --somatic-p-value ",
 "min_normal_coverage"           => " --min-coverage-normal ",
 "min_tumor_coverage"            => " --min-coverage-tumor ",
 "heterozyg_min_var_allele_freq" => " --min-var-freq ",
 "homozyg_min_var_allele_freq"   => " --min-freq-for-hom ",
 "normal_purity"                 => " --normal-purity ",
 "tumor_purity"                  => " --tumor-purity ",
 "apply_strand_filter"           => " --strand-filter ",    // boolean                                                                                     
 "min_avg_base_qual"             => " --min-avg-qual ",
 "report_validation"             => " --validation ",       // key only                                                                                    
);

$vs_som_opts_hcf_snv = array(
 "snv_min_tumor_var_allele_freq"    => " --min-tumor-freq ",
 "snv_max_normal_var_allele_freq"   => " --max-normal-freq ",
 "snv_p_value"                      => " --p-value ",
);
$vs_som_opts_hcf_indel = array(
 "indel_min_tumor_var_allele_freq"  => " --min-tumor-freq ",
 "indel_max_normal_var_allele_freq" => " --max-normal-freq ",
 "indel_p_value"                    => " --p-value ",
);
$vs_som_opts_som_f = array(
 "min_coverage"             => " --min-coverage ",
 "min_num_supporting_reads" => " --min-reads2 ",
 "min_num_strands"          => " --min-strands2 ",
 "min_avg_base_qual"        => " --min-avg-qual ",
 "min_var_allele_freq"      => " --min-var-freq ",
 "p_value"                  => " --p-value ",
);

// ----------------------------------------------------------------------------                                                                                                                  
$vs_trio_opts = array(
 "min_coverages"               => " --min-coverage ",
 "child_var_allele_freq"       => " --min-var-freq ",
 "p_value"                     => " --p-value ",
 "adj_child_var_allele_freq"   => " --adj-var-freq ",
 "adj_p_value"                 => " --adj-p-value ",
 "min_num_supporting_reads"    => " --min-reads2 ",
 "min_avg_base_qual"           => " --min-avg-qual ",
 "homozyg_min_var_allele_freq" => " --min-freq-for-hom ",
 "apply_strand_filter"         => " --strand-filter ",
);

$vs_trio_opts_hcf = array(
 "parents_max_num_supporting_reads" => "",
);

// ----------------------------------------


$strlk_opts = array(
 "skip_depth_filters"                       =>  "isSkipDepthFilters",
 "max_input_depth"                          =>  "maxInputDepth",
 "depth_filter_multiple"                    =>  "depthFilterMultiple",
 "snv_max_filtered_basecall_frac"           =>  "snvMaxFilteredBasecallFrac",
 "snv_max_spanning_deletion_frac"           =>  "snvMaxSpanningDeletionFrac",
 "indel_max_ref_repeat"                     =>  "indelMaxRefRepeat",
 "indel_max_window_filtered_basecall_frac"  =>  "indelMaxWindowFilteredBasecallFrac",
 "indel_max_interrupted_homopolymer_length" =>  "indelMaxIntHpolLength",
 "somatic_snv_prior_prob"                   =>  "ssnvPrior",
 "somatic_indel_prior_prob"                 =>  "sindelPrior",
 "snv_noise_prob"                           =>  "ssnvNoise",
 "indel_noise_prob"                         =>  "sindelNoise",
 "snv_noise_strand_bias_frac"               =>  "ssnvNoiseStrandBiasFrac",
 "min_mapping_qual_tier1"                   =>  "minTier1Mapq",
 "min_mapping_qual_tier2"                   =>  "minTier2Mapq",
 "somatic_snv_quality_lower_bound"          =>  "ssnvQuality_LowerBound",
 "somatic_indel_quality_lower_bound"        =>  "sindelQuality_LowerBound",
 "write_realignments"                       =>  "isWriteRealignedBam",
 "max_segment_size"                         =>  "binSize",
 "extra_arguments"                          =>  "extraStrelkaArguments",
);



// ----------------------------------------


$bd_bamcfg_opts = array(
 "output_mapping_flag_distn"  => " -g ",
 "create_insert_size_histo"   => " -h ",
 "min_mapping_qual"           => " -q ",
 "use_mapping_qual"           => " -m ",
 "insert_size"                => " -s ",   // note: current systemwide TGI script complains on options s or v but warning is safe
 "system_type"                => " -C ",
 "stdev_cutoff"               => " -c ",
 "coeffs_variation_cutoff"    => " -v ",
 "num_observations_for_stats" => " -n ",
 "num_bins"                   => " -b ",
 );


$bd_opts_2 = array(
 "translocation_calltype"                   => "",
 "fastq_outfile_prefix_of_supporting_reads" => "",
 "dump_SVs_and_supporting_reads"            => "",
 
 "min_region_length"                   => " -s ",
 "num_stdevs_for_cutoff"               => " -c ",
 "max_sv_size"                         => " -m ",
 "min_alt_mapping_qual"                => " -q ",
 "min_num_read_pairs"                  => " -r ",
 "max_coverage_for_ignoring_region"    => " -x ",
 "connection_buffer_size"              => " -b ",
 
 "analyze_long_insert"                 => " -l ", // nop
 "count_support_mode"                  => " -a ", // nop
 "print_allele_freq_column"            => " -h ", // nop
 "min_score_to_output"                 => " -y ",
);

$bd_opts_f = array(
 "apply_bam_filter" => "",
);

// ----------------------------------------

$pindel_opts = array(
 "sv_max_size_index"        => " -x ",
 "window_size"              => " -w ",
 "additional_mismatch"      => " -a ",
 "min_num_matching_bases"   => " -m ",
 "min_inversion_size"       => " -v ",
 "min_num_mappable_bases"   => " -d ",
 "balance_cutoff"           => " -B ",
 "anchor_qual"              => " -A ",
 "min_num_supporting_reads" => " -M ",
 "seq_err_rate"             => " -e ",
 "max_mismatch_rate"        => " -u ",
 "num_threads"              => " -T ",
 );

$pindel_opts_more = array(
 "do_inversions"        => " -r ",
 "do_tandem_dups"       => " -t ",
 //   "do_mobile_insertions" => " -q ",  // currently unsuppported                  
 "include_breakdancer"  => " -b ",
 "insert_size"          => "",
 "pindel_chr"           => " -c ",
 //  "logfile_prefix" => "",     // currently unsupported
 );

$pindel_single_opts_f = array(
 "min_coverages",
 "min_var_allele_freq",
 "require_balanced_reads",
 "max_num_homopolymer_repeat_units",
);
$pindel_pooled_opts_f = array(
 "max_num_homopolymer_repeat_units",
);
$pindel_paired_opts_f = array(
 "min_coverages",
 "min_var_allele_freq",
 "require_balanced_reads",
 "remove_complex_indels",
 "max_num_homopolymer_repeat_units",
);
$pindel_trio_opts_f = array(
 "min_coverages",
 "child_var_allele_freq",
 "parents_max_num_supporting_reads",
 "require_balanced_reads",
 "max_num_homopolymer_repeat_units",
);

$pindel_gen_opts_f = array(
 "heterozyg_min_var_allele_freq",
 "homozyg_min_var_allele_freq",
);


// ----------------------------------------

// gs_opts from parse_real.php, generate_gs_config()
$gs_opts = array(
 "genotyping_modules",
 "split_genotypingModel",
 "depth_parityCorrectionThreshold",
 "depth_useGCNormalization",
 "depth_effectiveLengthThreshold",
 "pairs_fixedErrorLikelihood",
 "split_ignoreReferenceMatches",
 "output_writeDepthProbs",
 "output_writeReadPairProbs",
 "output_writeSplitReadProbs",
 "metadata_writeArrayIntensityFile",
 "select_minimumInsertSize",
 "select_minimumInsertSizeStandardDeviations",
 "select_minimumPairMappingQuality",
 "cluster_clusterOrientations",
 "cluster_minimumClusterPairs",
 "coherence_windowSize",
 "coherence_windowOffset",
 "coherence_writeCoherenceDataFile",
 "membership_minimumSampleSpanCoverage",
 "depth_mixtureModel",  
 "depth_minimumMappingQuality",
 "depth_readCountCacheSize",
 "depth_readCountBinSize",
 "depth_writeSampleCountFile",
 "depth_writeSampleCoverageFile",
 "depth_minimumUnobservedSampleSpanCoverage",
 "depth_readReadCounts",
 "depth_writeReadCounts",
 "depth_writeExpectedCounts",
 "depth_writeNormalization",
 "depth_writeModels",
 "pairs_minimumMappingQuality",
 "pairs_aberrantInsertSizeRadius",
 "pairs_excludeJunctionReads",
 "pairs_alternativeHomeSearchRadius",
 "pairs_alternativeHomeMismatchThreshold",
  "pairs_mateWindowSize",
  "pairs_writePairCounts",
  "pairs_writeReadPairs",
  "split_minimumMappingQuality",
  "split_maximumAlternateAlleleScore",
  "split_mateWindowSize",
  "split_unmappedReadMappingQuality",
  "split_writeSplitReads",
  "split_writeSplitReadInfoFile",
); 

$gs_opts1 = array(
  "genotyping_modules",
  "depth_parityCorrectionThreshold",
  "depth_useGCNormalization",
  "depth_effectiveLengthThreshold",
  "pairs_fixedErrorLikelihood",
  "select_minimumInsertSize",
  "select_minimumInsertSizeStandardDeviations",
  "select_minimumPairMappingQuality",
  "cluster_clusterOrientations",
  "cluster_minimumClusterPairs",
  "coherence_writeCoherenceDataFile",
  "depth_minimumMappingQuality",
  "depth_writeSampleCountFile",
  "depth_writeSampleCoverageFile",
  "depth_writeReadCounts",
  "depth_writeExpectedCounts",
  "depth_writeNormalization",
  "depth_writeModels",
  "pairs_minimumMappingQuality",
  "pairs_aberrantInsertSizeRadius",
  "pairs_alternativeHomeSearchRadius",
  "pairs_alternativeHomeMismatchThreshold",
  "pairs_mateWindowSize",
  "pairs_writePairCounts",
  "pairs_writeReadPairs",
  "split_minimumMappingQuality",
  "split_maximumAlternateAlleleScore",
  "split_mateWindowSize",
  "split_unmappedReadMappingQuality",
  "split_writeSplitReads",
  "split_writeSplitReadInfoFile",
);

$gs_opts_fixed = array(
  "split_genotypingModel",  // 2
  "split_ignoreReferenceMatches", // false
  "output_writeDepthProbs", // true
  "output_writeReadPairProbs", // true
  "output_writeSplitReadProbs", // true
  "metadata_writeArrayIntensityFile", // true
  "coherence_windowSize",   // 1000
  "coherence_windowOffset",  // 100
  "membership_minimumSampleSpanCoverage", // 1.0
  "depth_mixtureModel",   // GMM
  "depth_readCountCacheSize",//  1000000
  "depth_readCountBinSize", // 1000
  "depth_minimumUnobservedSampleSpanCoverage", // 1.0
  "depth_readReadCounts", // false
  "pairs_excludeJunctionReads", // false
);

// --------------------


?>