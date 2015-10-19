<?php
// --------------------------------------
// @name GenomeVIP web form processing script
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------
// Debug
ini_set('display_errors',1);
error_reporting(E_ALL & ~E_DEPRECATED);

//$do_html=1;

//$nobsub="#";
$nobsub="";
$do_timing=1;
//$s3cmd_public="";
$s3cmd_public=" "."--acl-public"." ";
$dlay=0;
$wc="*";
$qq="\"";
$SC="starcluster"; 

// Monitor SGE memory
$do_sgemem=1;
$sgemem_cmd="qstat -j \\\$JOB_ID >> qstat.\\\$JOB_ID";

// Functions
include realpath(dirname(__FILE__)."/"."resources_util.php");
include realpath(dirname(__FILE__)."/"."array_defs.php");




include realpath(dirname(__FILE__)."/"."pprofile_wrapper.php");
include realpath(dirname(__FILE__)."/"."bam_util.php");


function write_bamlist($fp,$list,$thefile)   {
  fwrite($fp, "cat > ./$thefile << EOF\n");

  foreach ($list as $i) { fwrite($fp, "\$RUNDIR/genomes/$i\n"); }
  fwrite($fp,"EOF\n");
}
function write_pindelbamlist($fp,$list,$sz,$mode)   {
  fwrite($fp, "cat > ./pindel.bam.inp << EOF\n");
  // format:  bamfile insert_size label
  // Add random tag in case sample name too long

  $idx=0;
  foreach ($list as $i) { 
    $mylen = (strlen($i) < 20) ? (strlen($i)) : 20;
    fwrite($fp, "\$RUNDIR/genomes/$i ".$sz." ");

    // Ensure systematic output order with prepend tag
    if ($mode=="somatic" || $mode=="trio" || $mode=="pooled" ){
      fwrite($fp, $idx."_");
      $tag="";
    } else {
      $tag = "_".generateRandomString(6);
    }

    fwrite($fp, str_replace(".","_",substr($i,0,$mylen).$tag)."\n"); 
    $idx++;
  }
  fwrite($fp,"EOF\n");
}


function check_aws_shell($fp) {
  if ($_POST['compute_target']=="AWS") { // good practices for SGE though we did not need it
    fwrite($fp, "#$ -S /bin/bash\n");
  }
}


function write_vs_preamble($fp, $toolsinfo_h) {
  global $toolsinfo_h, $do_timing;
  fwrite($fp, "#!/bin/bash\n");
  check_aws_shell($fp);
  if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
  fwrite($fp, "export SAMTOOLS_DIR=".$toolsinfo_h['samtools']['path']."\n");
  fwrite($fp, "export JAVA_HOME=".$toolsinfo_h['java']['path']."\n");
  fwrite($fp, "export JAVA_OPTS=".$toolsinfo_h['java']['opts']."\n");
  fwrite($fp, "export PATH=\\\${JAVA_HOME}/bin:\\\${PATH}\n");
  fwrite($fp, "if [[ -z \"\\\$LD_LIBRARY_PATH\" ]] ; then\n");
  fwrite($fp, "   export LD_LIBRARY_PATH=\\\${JAVA_HOME}/lib\n");
  fwrite($fp, "else\n");
  fwrite($fp, "   export LD_LIBRARY_PATH=\\\${JAVA_HOME}/lib:\\\${LD_LIBRARY_PATH}\n");
  fwrite($fp, "fi\n");
}


function check_sam($fp) {
  global $toolsinfo_h, $bNoSam;

  if ($bNoSam) {


    fwrite($fp, "export SAMTOOLS_DIR=".$toolsinfo_h['samtools']['path']."\n");

    $bNoSam=0;
  }
}


function create_fai($fp, $mylabel) {
  global $toolsinfo_h;


  check_sam($fp);
  $SAMTOOLS_EXE = $toolsinfo_h['samtools']['exe'];
  fwrite($fp, "if [[ ! -e \$".$mylabel."_fai ]] ; then\n");
  fwrite($fp, "   echo Creating fai...\n");
  fwrite($fp, "   \$SAMTOOLS_DIR/$SAMTOOLS_EXE faidx  \$".$mylabel."\n");
  fwrite($fp, "   echo Creating fai...done\n");
  fwrite($fp, "fi\n");


}

function write_fai($fp, $myref, $mylabel, $pathid, $action) {
  global $DNAM_VAR, $faipath_h, $retrieved, $DNAM_use;
  if (! array_key_exists( $myref, $retrieved)) {
    if (array_key_exists( $myref, $faipath_h)) {
      fwrite($fp, "if [[ ! -e \$".$mylabel."_fai ]] ; then\n");
      fwrite($fp, "   $action \$".$DNAM_VAR[$faipath_h[ $myref ]]."/\$".$mylabel."_fai  .\n");
      fwrite($fp, "fi\n");
      $DNAM_use[$faipath_h[ $myref ]] = 1;
    } 
    else {
      fwrite($fp, "if [[ ! -e \$".$mylabel."_fai ]] ; then\n");
      fwrite($fp, "   $action \$$DNAM_VAR[$pathid]/\$".$mylabel."_fai  .\n");
      fwrite($fp, "fi\n");
      $DNAM_use[$pathid] = 1;
    }

  }
}


function handle_fai($fp,$hasfai, $myref, $mylabel, $pathid, $action) {
  if ($hasfai) {  //     use it

    write_fai($fp, $myref, $mylabel, $pathid, $action);
  } else {  //   create it


    create_fai($fp, $mylabel);
  }
}

function gen_mem_str($compute_target, $size) {
  $tmp="";
  // Make multiple jobs fit so we can keep nice round numbers in the memory config file
  $size -= 10;
  switch ($compute_target) {
  case "AWS":
    $tmp="-l h_vmem=".$size."M";
    break;
  case "tgi":
    $tmp="-R 'select[mem>$size] rusage[mem=$size] span[hosts=1]' -M ".($size*1000);
  }
  return $tmp;
}


function write_sample_tuples($fp, $list_of_sorted_bams, $dn, $sz) {
  $tmp_arr = array();
  $bidx=0;
  $idx=0;
  if ($sz > 0) {

    while ($bidx < count($list_of_sorted_bams) ) {  // assumes ordered tuples for mpileups
      switch ($sz) {
        case 1:
	  $tmp_arr[0] = $list_of_sorted_bams[$bidx++];
	  break;
        case 2:
	  if ($dn == "varscan") {
	    $tmp_arr[1] = $list_of_sorted_bams[$bidx++];   // convert natural TCGA (t,n) sort order to (n,t) for varscan
	    $tmp_arr[0] = $list_of_sorted_bams[$bidx++];   // Order does not matter for BD or pindel per se, 
    	                                                   // but downstream processing needs to heed column order
 	    ksort($tmp_arr,1);                             // necessary
	  } else {
	    $tmp_arr[0] = $list_of_sorted_bams[$bidx++];   // natural TCGA (t,n) sort order
	    $tmp_arr[1] = $list_of_sorted_bams[$bidx++];
	  }
	  
	  break;
        case 3:
	  $tmp_arr[0] = $list_of_sorted_bams[$bidx++];   // triples (parent1, parent2, child) for mpileup 
	  $tmp_arr[1] = $list_of_sorted_bams[$bidx++];   // Varscan expects dad,mom,child
	  $tmp_arr[2] = $list_of_sorted_bams[$bidx++];  
	  break;
        default:
	  ;
      }

      fwrite($fp, "idx=$idx ; dir=\$RUNDIR/$dn/group\$idx ; mkdir -p \$dir ; cd \$dir\n");
      if ($dn=="pindel") {
	write_pindelbamlist($fp, $tmp_arr, $_POST['pin_insert_size'], $_POST['pin_call_mode']);
      } else {
	write_bamlist($fp, $tmp_arr,"bamfilelist.inp"); 
      }
      $idx++;
    }  //while

    fwrite($fp, "numgps=$idx\n");
  }


  if ($sz==0) {
    $numgps=1;
    fwrite($fp, "numgps=$numgps\n");
    fwrite($fp, "idx=$idx ; dir=\$RUNDIR/$dn/group\$idx ; mkdir -p \$dir ; cd \$dir\n");
    foreach ($list_of_sorted_bams as $my_j) {
      $tmp_arr[ $idx++ ] = $my_j;
    }
    if ($dn=="pindel") {
      write_pindelbamlist($fp, $tmp_arr, $_POST['pin_insert_size'], $_POST['pin_call_mode']);
    } else {
      write_bamlist($fp, $tmp_arr,"bamfilelist.inp"); 
    }
  }


}

function write_chromosomes($fp,$sel,$fai_str,$chrdef) {
  switch ($sel) {
  case "standard":
      fwrite($fp,"SEQS=\"".convert_list("1-22,X,Y")."\"\n");
    break;
  case "standard_plus_contigs":
    fwrite($fp,"SEQS=`cut -f1 \$RUNDIR/reference/\$".$fai_str." | egrep -e '^([1-9]|G[IL]|MT|NC)'`\n");
    break;
  case "user":
    if (trim($chrdef) != "" ) { // validation provided previously by javascript
      fwrite($fp,"SEQS=\"".convert_list($chrdef)."\"\n");
    }
    break;
  default:
    ;
  }
}


function generate_gs_config($fp) {
  global $gs_opts;

  fwrite($fp, "cat > ./genomestrip.input <<EOF\n");



  foreach ($gs_opts as $value) {
    $gs_value = str_replace('_', '.', $value);
    $key="gs_".$value;

    switch($value) {
    case "genotyping_modules":
      $mymodules = array();
      $gs_conf_list = array ("gs_genotyping_depth" => "depth",
			     "gs_genotyping_pairs" => "pairs",
			     "gs_genotyping_split" => "split",
			     );
      foreach ($gs_conf_list as $key => $value) {
	if( isset($_POST[$key]) ) { array_push( $mymodules, $value ); }
      }
      $mymodules_str = implode( ',' , $mymodules );
      fwrite($fp, $gs_value.": ".$mymodules_str."\n");
      break;

    default:
      fwrite($fp, $gs_value.": ".$_POST[$key]."\n");
    }
  }
  fwrite($fp,"EOF\n");
}












// Globals
$DNAM_VAR = array();
$DNAM_use = array();
$toolsinfo_h=array();
$faipath_h = array();
$retrieved = array();
$retrieved_pathid=array();
$availref_type_h = array();
$typematch = array();
$bNoSam=1;
$notify=array();
$randlen=8;













function gen_strelka_ini($fp, $fn, $strlk_opts) {
    fwrite($fp, "cat > ./".$fn." << EOF\n");
    fwrite($fp, "[user]\n");
    foreach ($strlk_opts as $tmpkey => $value) {
      $key = "strlk_$tmpkey";
      switch ($tmpkey) {
      case "skip_depth_filters":
      case "write_realignments":
	fwrite($fp, "$value = ".(($_POST[$key]=="true")?(1):(0))."\n");
        break;
      case "extra_arguments":
	fwrite($fp, "$value = ".$_POST[$key]."\n");   // no quotes here; bad for strelka
        break;
      default:
	fwrite($fp, "$value = ".$_POST[$key]."\n");
      }
    }
    fwrite($fp,"EOF\n");
}

































// Improved version: no explicit tests needed for filter inclusion
function write_vs_gl_merge ($fp, $vs_bMap, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix) {
  $find_cmd = "find . ";

  $vartype="snv";
  if( $vs_bMap[$vartype]) {
    $suffix = "gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf";
    $find_cmd .= " -size +0c -iname \\\*varscan.out.gl_$vartype.*.$suffix\\\* ";
  }
  $vartype="indel";
  if( $vs_bMap[$vartype]) {
    $suffix = "gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf";
    if ($vs_bMap['snv']) { $find_cmd .= " -o "; }
    $find_cmd .= " -size +0c -iname \\\*varscan.out.gl_$vartype.*.$suffix\\\* ";
  }

  $find_cmd .= " > ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");

  fwrite($fp, "if [ -s ./\\\$outlist ] ; then\n");
  fwrite($fp, "   \\\$VCFTOOLSDIR/bin/vcf-concat -f ./\\\$outlist | \\\$VCFTOOLSDIR/bin/vcf-sort -c  > ./varscan.out.gl_all.group\$gp.all.current_final.gvip.vcf\n"); 
  fwrite($fp, "else\n");
  fwrite($fp, "   touch ./varscan.out.gl_all.group\$gp.all.current_final.gvip.vcf\n");
  fwrite($fp, "fi\n");
}


function write_vs_som_merge ($fp, $vs_som_prefix, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix) {
  // Ignoring gl, LOH, and other for now
  $vartype="snv";
  $suffix = "gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf";
  $find_cmd = "find . -size +0c -iname \\\*varscan.out.som_$vartype.*.$suffix\\\*  > ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  $vartype="indel";
  $suffix = "gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf";
  $find_cmd = "find . -size +0c -iname \\\*varscan.out.som_$vartype.*.$suffix\\\*  >> ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  fwrite($fp, "if [ -s ./\\\$outlist ] ; then\n");
  fwrite($fp, "   \\\$VCFTOOLSDIR/bin/vcf-concat -f ./\\\$outlist | \\\$VCFTOOLSDIR/bin/vcf-sort -c  > ./varscan.out.som_all.group\$gp.all.current_final.gvip.Somatic.vcf\n"); 
  fwrite($fp, "else\n");
  fwrite($fp, "   touch ./varscan.out.som_all.group\$gp.all.current_final.gvip.Somatic.vcf\n"); 
  fwrite($fp, "fi\n");
}

function write_vs_trio_merge($fp, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix) {
  // Ignoring untransm, transm, mie, denovo_str10, denovo_other, other for now
  $vartype="snv";
  $suffix = "gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf"; 
  $find_cmd  = "find . -size +0c -iname \\\*varscan.out.trio.*.$vartype.$suffix\\\*  >  ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  $vartype="indel";
  $suffix = "gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf"; 
  $find_cmd  = "find . -size +0c -iname \\\*varscan.out.trio.*.$vartype.$suffix\\\*  >>  ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  fwrite($fp, "if [ -s ./\\\$outlist ] ; then\n");
  fwrite($fp, "   \\\$VCFTOOLSDIR/bin/vcf-concat -f ./\\\$outlist | \\\$VCFTOOLSDIR/bin/vcf-sort -c  > ./varscan.out.trio_all.group\$gp.all.current_final.gvip.denovo.vcf\n"); 
  fwrite($fp, "else\n");
  fwrite($fp, "   touch ./varscan.out.trio_all.group\$gp.all.current_final.gvip.denovo.vcf\n"); 
  fwrite($fp, "fi\n");
}

function write_strlk_merge($fp, $strlk_dbsnp_filter_prefix, $strlk_fpfilter_prefix) {
  $vartype="snv";
  $suffix = "passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass'].$strlk_fpfilter_prefix[$vartype]['pass']."vcf"; 
  $find_cmd  = "find ./strelka_out/results -size +0c -iname \\\*strelka.somatic.$vartype.*.$suffix\\\*  >  ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  $vartype="indel";
  $suffix = "passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass'].$strlk_fpfilter_prefix[$vartype]['pass']."vcf"; 
  $find_cmd  = "find ./strelka_out/results -size +0c -iname \\\*strelka.somatic.$vartype.*.$suffix\\\*  >>  ./\\\$outlist";
  fwrite($fp, "$find_cmd\n");
  fwrite($fp, "if [ -s ./\\\$outlist ] ; then\n");
  fwrite($fp, "   \\\$VCFTOOLSDIR/bin/vcf-concat -f ./\\\$outlist | \\\$VCFTOOLSDIR/bin/vcf-sort -c  > ./strelka.out.somatic_all.group\$gp.current_final.gvip.vcf\n"); 
  fwrite($fp, "else\n");
  fwrite($fp, "   touch ./strelka.out.somatic_all.group\$gp.current_final.gvip.vcf\n"); 
  fwrite($fp, "fi\n");
}


function write_vep_input_common($fp, $prefix) {
  global $toolsinfo_h;
  fwrite($fp, "$prefix.vep_cmd = ".$toolsinfo_h[$_POST['vep_version']]['installdir']."/".$toolsinfo_h[$_POST['vep_version']]['relpath']."/".$toolsinfo_h[$_POST['vep_version']]['vep_exe']."\n");
  fwrite($fp, "$prefix.cachedir = ".$toolsinfo_h[$_POST['vep_version']]['installdir']."/".$toolsinfo_h[$_POST['vep_version']]['cache_relpath']."\n");
  fwrite($fp, "$prefix.reffasta = ".$toolsinfo_h[$_POST['vep_version']]['installdir']."/".$toolsinfo_h[$_POST['vep_version']]['fasta_relpath']."/".$toolsinfo_h[$_POST['vep_version']]['reffasta']."\n");
  fwrite($fp, "$prefix.assembly = ".$toolsinfo_h[$_POST['vep_version']]['assembly']."\n");
}

      


if ($_SERVER['REQUEST_METHOD'] == "POST") {
	require_once "et.php";
	$_POST['bam_count'] = count( $list_of_sorted_bams );
	$ok = callHome();

  //  if($do_html==1) {
  //  }

  // --------------------------------------------------------------------------------
  // SETUP ENVIRONMENT

  $compute_target = $_POST['compute_target'];
  switch ($compute_target) {
  case 'AWS':
    $workdir    = generateRandomString($randlen);
    $myjob      = $workdir;
    $resultsdir = $workdir;
    $scconf     = $_POST['scconf'];

    $toolsinfo_h = parse_ini_file('configsys/tools.info.AWS', true);
    
    $s3_cmd = $toolsinfo_h['s3cmd']['path']."/".$toolsinfo_h['s3cmd']['exe'];
    $put_cmd = "\"\$s3_cmd $s3cmd_public put\"";
    $get_cmd = "\"\$s3_cmd get\"";
    $del_cmd = "\"\$s3_cmd del\"";
    
    $batch = array('cmd'          => 'qsub -V -b n -cwd', 
		   'name_opt'     => '-N',
		   'res_opt'      => '-l',
		   'dep_opt'      => '-hold_jid',
		   'dep_opt_pre'  => '',
		   'dep_opt_post' => '',
		   'q_opt'        => '',
		   'nproc'        => '-pe orte',
		   'limitgr'      => '',
		  );

    break;

  case 'tgi':
    $workdir    = trim($_POST['workdir']);
    // Add explicit homedir if needed; we remove it later when transferring files
    if ($workdir=="") {
      $workdir="~/mydir";  // default
    } 
    if (!preg_match('#^[/~]#', $workdir)) {
      $workdir = "~/$workdir"; // assume homedir
    }
    




    // Generated jobname
    $myjob = generateRandomString($randlen);
    
    




    // Leave this hardcoded?
    $resultsdir="$workdir/results";  // default
    
    
    $toolsinfo_h = parse_ini_file('configsys/tools.info.tgi', true);
    
    $put_cmd = "\"ln -s\"";
    $del_cmd = "\"rm -f\"";
    
    $clust_qn = trim($_POST['clust_qn']);
    $clust_lg = trim($_POST['clust_lg']);

    $batch = array('cmd'          => 'bsub'  ,  
		       'name_opt'     => '-J'     ,
		       'res_opt'      => '-R'     ,
		       'dep_opt'      => '-w'     ,
		       'dep_opt_pre'  => '"done(' ,
		       'dep_opt_post' => ')"'     ,
		       'q_opt'        => '-q'.' '.$clust_qn,
		       'nproc'        => '-n',      
		       'limitgr'      => (($clust_lg=="")?(""):("-g $clust_lg")),
		       );


  } // switch

  $del_local = "\"rm -f\"";
  $toolmem_h = parse_ini_file('configsys/tools.mem', true);
  $GENOMEVIP_SCRIPTS=$toolsinfo_h['genomevip_scripts']['path'];
  


  
  





  


  
  //--------------------------------------------------------------------------------
  // SETUP BINARIES MACROS and PATHS
  
  // Randomly named goal script
  $tmpjob = "/tmp/$myjob.sh";
  $tmp_ep = "/tmp/$myjob.ep";

  // First create just the main commands to be merged later
  system("touch $tmpjob.main && chmod 0600 $tmpjob.main");
  $fp = fopen("$tmpjob.main", 'w');


  
  // Bam and reference paths
  $paths_h = $_POST['pathdb'];

  
  foreach ($paths_h as $key => $value) {


    $DNAM_use[$key] = 0;
    $DNAM = "DIR".$key;
    $DNAM_VAR[$key] = $DNAM;







    
  }
  // Cluster-specific path stuff
  switch ($compute_target) {
  case "AWS":









    // NOTE: Assumed for now that an EBS volume is a required work disk
    // $RUNDIR = "/mnt/$workdir";
    $RUNDIR = $ebsprefix.$_POST['awsworkvols']."/$workdir";


    $RESULTSDIR = "s3://".$_POST['s3buckets']."/".$resultsdir;
    
    fwrite($fp, "s3_cmd=$s3_cmd\n");
    fwrite($fp, "get_cmd=$get_cmd\n");
    $s3_action="\$get_cmd";
    $action="ln -s";
    break;
    
  case "tgi":
    $RUNDIR = $workdir;
    $RESULTSDIR = $resultsdir;
    $action="ln -s";
  }
  fwrite($fp, "put_cmd=$put_cmd\n");
  fwrite($fp, "del_cmd=$del_cmd\n");
  fwrite($fp, "del_local=$del_local\n");
  fwrite($fp, "RUNDIR=$RUNDIR\n");
  fwrite($fp, "mkdir -p \$RUNDIR/{genomes,reference,status}\n");
  fwrite($fp, "touch \$RUNDIR/status/LOG\n");

  fwrite($fp, "RESULTSDIR=$RESULTSDIR\n");
  if ($compute_target=="tgi") {
    fwrite($fp, "mkdir -p \$RESULTSDIR/status\n");
  }
  
  
  // --------------------------------------------------------------------------------
  // Utility functions:  moved to bam_util.php
  // --------------------------------------------------------------------------------
  if ($compute_target=="AWS") {
    write_check_aws_file($fp);
    write_check_bai_please($fp, $compute_target);
    write_check_aws_file_int($fp);
  }

  // --------------------------------------------------------------------------------
  // Set up genomes
  // --------------------------------------------------------------------------------
  // Buffer output until we know if write_do_prep_bam() is needed
  $fp_buf = array();
  
  array_push( $fp_buf, "echo Preparing genomes...\n");
  array_push( $fp_buf, "cd \$RUNDIR/genomes\n");
  $list_of_sorted_bams=array();
  $baipath_h = array();


  $prepare_bam=array();
  if (isset($_POST['baipath'])) {
    $tmp_h = $_POST['baipath'];
    foreach ($tmp_h as $kk) {
      list($i, $pathid) = preg_split('/\|/', $kk);   // encoding: file, pathid
      $baipath_h[$i] = $pathid;
    }
  }
  $tmp_h = $_POST['bamfiles'];   // this is subset of $bamlist_arr 
  



  
  foreach ($tmp_h as $kk) {    // bams

    

    // Multiple dir version
    list($sid, $pathid, $i, $hasbai) = preg_split('/\|/', $kk);   // encoding: selectid, pathid, file, bai match
    array_push($list_of_sorted_bams, $i);    // put this here for expected n-t order
    
    // Adding bam check in case of discrepancies between filesystem and current tree file, particularly useful for S3
    if ($hasbai) {
      array_push( $fp_buf, "this_bam=\"$i\"\n");
      array_push( $fp_buf, "if [[ ! -e  \"\$this_bam\" ]]; then\n");
      array_push( $fp_buf, "   echo Retrieving \$this_bam ...\n");
      if ($compute_target!="AWS") {
	array_push( $fp_buf, "   $action \$$DNAM_VAR[$pathid]/\$this_bam\n");
      } else {


	if(preg_match('#^s3://#', $paths_h[$pathid])){
	  array_push( $fp_buf, "   msg=`$s3_action \$$DNAM_VAR[$pathid]/\$this_bam 2>&1`\n");
	} else {
	  array_push( $fp_buf, "   msg=`$action \$$DNAM_VAR[$pathid]/\$this_bam 2>&1`\n");
	}
	array_push( $fp_buf, "   check_aws_file \$msg \$this_bam\n");
      }
      array_push( $fp_buf, "fi\n");
      $DNAM_use[$pathid] = 1;

      // bai should imply already-sorted bam
      $tmp_bai = "$i.bai";
      if (array_key_exists( $tmp_bai, $baipath_h )) {
	// link in alt dir
	array_push( $fp_buf, "this_bai=\$this_bam.bai\n");
	array_push( $fp_buf, "if [[ ! -e \$this_bai ]]; then\n");
	array_push( $fp_buf, "   echo Retrieving \$this_bai ...\n");

      if ($compute_target!="AWS") {
	array_push( $fp_buf, "   $action \$".$DNAM_VAR[$baipath_h[$tmp_bai]]."/\$this_bai\n");
      } else {


	if(preg_match('#^s3://#', $paths_h[$baipath_h[$tmp_bai]])) {

	  array_push( $fp_buf, "   msg=`$s3_action \$".$DNAM_VAR[$baipath_h[$tmp_bai]]."/\$this_bai 2>&1`\n");
	} else {
	  array_push( $fp_buf, "   msg=`$action \$".$DNAM_VAR[$baipath_h[$tmp_bai]]."/\$this_bai 2>&1`\n");
	}

	array_push( $fp_buf, "   check_bai_please \$msg \$this_bai\n");

      }
	array_push( $fp_buf, "fi\n");
	
	$DNAM_use[$baipath_h[$tmp_bai]] = 1;
      } else {
	// link same dir
	array_push( $fp_buf, "this_bai=\$this_bam.bai\n");
	array_push( $fp_buf, "if [[ ! -e \$this_bai ]]; then\n");
	array_push( $fp_buf, "   echo Retrieving \$this_bai ...\n");

      if ($compute_target!="AWS") {
	array_push( $fp_buf, "   $action \$$DNAM_VAR[$pathid]/\$this_bai\n");
      } else {




	if(preg_match('#^s3://#', $paths_h[$pathid])){
	  array_push( $fp_buf, "   msg=`$s3_action \$$DNAM_VAR[$pathid]/\$this_bai 2>&1`\n");
	} else {
	  array_push( $fp_buf, "   msg=`$action \$$DNAM_VAR[$pathid]/\$this_bai 2>&1`\n");
	}

        array_push( $fp_buf, "   check_bai_please \$msg \$this_bai\n");
      }

      array_push( $fp_buf, "fi\n");
      
      $DNAM_use[$pathid] = 1;
      }
    } else {

      // AWS test was not in original ; may be untested
      array_push( $fp_buf, "if [[ ! -e  ".basename($i,".bam").".orig.bam ]]; then\n");

      if ($compute_target!="AWS") {
	array_push( $fp_buf, "   $action \$$DNAM_VAR[$pathid]/$i ".basename($i,".bam").".orig.bam\n");
      } else {



	if(preg_match('#^s3://#', $paths_h[$pathid])){
	  array_push( $fp_buf, "   $s3_action \$$DNAM_VAR[$pathid]/$i ".basename($i,".bam").".orig.bam\n");
	} else {
	  array_push( $fp_buf, "   $action \$$DNAM_VAR[$pathid]/$i ".basename($i,".bam").".orig.bam\n");
	}
      }
      array_push( $fp_buf, "fi\n");

      $DNAM_use[$pathid] = 1;
      
      array_push($prepare_bam, $kk);
   }
  }

  // Update output
  if (count($prepare_bam) > 0 ) {
    write_do_prep_bam($fp,$compute_target);
  }
  foreach ($fp_buf as $value) { fwrite($fp, $value); }
  unset($fp_buf);

  // Prepare any bams
  if (count($prepare_bam) > 0 ) {
    array_push($notify, "The following bams will be sorted and indexed as needed.");
    array_push($notify, "Please refer to the contents of the genomes/ folder under your results folder.\n");

    check_sam($fp);
    $SAMTOOLS_EXE=$toolsinfo_h['samtools']['exe'];

    fwrite($fp, "cat > prepare_bam.lst <<EOF\n");
    foreach ($prepare_bam as $kk) {
      list($sid, $pathid, $i, $hasbai) = preg_split('/\|/', $kk);  

      fwrite($fp, basename($i,".bam").".orig.bam\n");
      $DNAM_use[$pathid] = 1;
    }
    fwrite($fp, "EOF\n");
    fwrite($fp, "for i in `cat prepare_bam.lst` ; do\n");
    fwrite($fp, "   do_prep_bam  \"$i\"\n");
    fwrite($fp, "done\n");

  }




  fwrite($fp, "echo Preparing genomes...done\n");
  fwrite($fp, "\n");

  // --------------------------------------------------------------------------------
  // Link(s) to reference genome
  // --------------------------------------------------------------------------------
  fwrite($fp, "echo Preparing references...\n");
  fwrite($fp, "cd \$RUNDIR/reference\n"); 
  if (isset($_POST['faipath'])) {
    $tmp_h = $_POST['faipath'];
    foreach ($tmp_h as $kk) {
      list($i, $pathid) = preg_split('/\|/', $kk);
      $faipath_h[$i] = $pathid;
    }
  }
  list($sid, $pathid, $REF, $hasfai) = preg_split('/\|/', $_POST['refgenome']);   // encoding: selectid, pathid, file, has fai

  // NEW CODE //////////////////////////////////////////////////////////////////////////////////////////////

  fwrite($fp, "\n");

  // Last chance to define samtools
  check_sam($fp);  // writing samtools_dir not really needed here
  
  // Save log
  fwrite($fp, "\$put_cmd  \$RUNDIR/status/LOG  \$RESULTSDIR/status/LOG\n");






    
  
  
  fwrite($fp, "echo Preparing references...done\n");
  fwrite($fp, "\n");
  fwrite($fp, "#------------------------------\n");
  

  // --------------------------------------------------------------------------------
  // Initial tasks
  // --------------------------------------------------------------------------------
  fwrite($fp, "cd \$RUNDIR\n");
  $prog_cmds = array("vs_cmd"     => "varscan",
		     "strlk_cmd"  => "strelka",
		     "bd_cmd"     => "breakdancer",
		     "pin_cmd"    => "pindel",
		     "gs_cmd"     => "genomestrip",
		     );
  foreach ($prog_cmds as $key => $value) {
    if(isset($_POST[$key])) {


      fwrite($fp, "mkdir -p $value\n");
    }
  }
  fwrite($fp, "\n");
  
  // Save profile
  fwrite($fp, "\$put_cmd  `pwd`/*.ep \$RESULTSDIR/\n");
  
    
  // --------------------------------------------------------------------------------
  // RUN VARSCAN
  // --------------------------------------------------------------------------------

  if (isset($_POST['vs_cmd'])) {



    fwrite($fp, "#------------------------------\n");



    

    if ($_POST['vs_call_mode'] == "germline" ) {

      $vs_opts = array("vs_gl_min_avg_base_qual"           => " --min-avg-qual ",     
		       "vs_gl_homozyg_min_var_allele_freq" => " --min-freq-for-hom ",  
		       "vs_gl_apply_strand_filter"         => " --strand-filter ",     
		       "vs_gl_output_vcf"                  => " --output-vcf ",
		       );

      $vs_opts_type = array("snv"   => array("vs_gl_snv_p_value"                     => " --p-value ",       
					     "vs_gl_snv_min_coverage"                => " --min-coverage ",  
					     "vs_gl_snv_min_var_allele_freq"         => " --min-var-freq ",  
					     "vs_gl_snv_min_num_supporting_reads"    => " --min-reads2 ",     
					     ),
			    "indel" => array("vs_gl_indel_p_value"                   => " --p-value ",        
					     "vs_gl_indel_min_coverage"              => " --min-coverage ",   
					     "vs_gl_indel_min_var_allele_freq"       => " --min-var-freq ",   
					     "vs_gl_indel_min_num_supporting_reads"  => " --min-reads2 ",      
					     )
			    );




      $vs_bMap = array("snv"   => (($_POST['vs_gl_calltype'] == "both" || $_POST['vs_gl_calltype'] == "snv"  )?(1):(0)),
		       "indel" => (($_POST['vs_gl_calltype'] == "both" || $_POST['vs_gl_calltype'] == "indel")?(1):(0))
		       );
      



      $sam_opts_cmd="";
      foreach ($vs_samtools_opts as $tmpkey => $value) { 
	$key = "vs_gl_$tmpkey";
	switch ($key) {
	case "vs_gl_samtools_perform_BAQ":
	  if ($_POST[$key]=="disabled") {$sam_opts_cmd .= " ".$value." ";  }
	  break;
	default:
	  $sam_opts_cmd .= " ".$value." ".$_POST[$key]." "; 
	}
      }
	  
      // set up dirs and samples
      if ($_POST['vs_gl_samples'] == "single") {    // individual
	write_sample_tuples($fp, $list_of_sorted_bams, "varscan", 1);
      } else {                                    // pooled
	write_sample_tuples($fp, $list_of_sorted_bams, "varscan", 0);
      }
      
      // Chromosome
      write_chromosomes($fp,$_POST['vs_chrdef'], "VS_REF_fai", $_POST['vs_chrdef_str'] );



      fwrite($fp,"# varscan germline\n");
      fwrite($fp,"cd \$RUNDIR/varscan\n");
      fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");

      if ($compute_target != "AWS") {  fwrite($fp, "   mkdir -p \$RESULTSDIR/varscan/group\$gp\n"); }
      fwrite($fp, "   \$put_cmd  \$RUNDIR/varscan/group\$gp/bamfilelist.inp \$RESULTSDIR/varscan/group\$gp/bamfilelist.group\$gp.inp\n");
      

      fwrite($fp, "   statfile_gl_g=incomplete.vs_postrun.group\$gp\n");
      fwrite($fp, "   localstatus_gl_g=\$RUNDIR/status/\$statfile_gl_g\n");
      fwrite($fp, "   remotestatus_gl_g=\$RESULTSDIR/\$statfile_gl_g\n");
      fwrite($fp, "   touch \$localstatus_gl_g\n");
      fwrite($fp, "   ".str_replace("\"","",$put_cmd)." "."\$localstatus_gl_g  \$remotestatus_gl_g\n");



      fwrite($fp, "   tag_vs=\$(cat /dev/urandom | tr -dc 'a-zA-Z' | fold -w 6 | head -n 1)\n");
      fwrite($fp, "   for chr in \$SEQS; do\n");
      fwrite($fp, "      chralt=\${chr/:/_}\n");
      fwrite($fp, "      dir=group\$gp/\$chralt\n");
      fwrite($fp, "      mkdir -p \$RUNDIR/varscan/\$dir\n");
      fwrite($fp, "      cat > \$RUNDIR/varscan/\$dir/varscan.sh <<EOF\n");
      write_vs_preamble($fp, $toolsinfo_h);
      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "export VARSCAN_DIR=".$toolsinfo_h[$_POST['vs_version']]['path']."\n");
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "VS_REF=\\\$RUNDIR/reference/$VS_REF\n");
      fwrite($fp, "statfile=incomplete.varscan.group\$gp.chr\$chralt\n");
      fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
      fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
      fwrite($fp, "touch \\\$localstatus\n");
      fwrite($fp, "\$put_cmd \\\$localstatus  \\\$remotestatus\n");
      
      fwrite($fp, "cd \\\$RUNDIR/varscan/\$dir\n");
      $SAMTOOLS_EXE = $toolsinfo_h['samtools']['exe'];
      
      
      // Set up calcs
      foreach ($vs_bMap as $vartype => $vartype_bool) {
	if ( $vartype_bool ) {
	  if ($vartype=="snv") {
	    $vs_mpileup_cmd=" mpileup2"."snp"." ";
	  } else {
	    $vs_mpileup_cmd=" mpileup2".$vartype." ";
	  }
	  $vs_mpileup_out="varscan.out.gl_".$vartype;
	  $vs_mpileup_log="varscan.log.gl_".$vartype;
	  $vs_opts_cmd="";
	  foreach ($vs_opts_type[$vartype] as $key => $value) { $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; }
	  foreach ($vs_opts  as $key => $value) { 
	    switch($key) {
	    case "vs_gl_apply_strand_filter":
	      $vs_opts_cmd .= " ".$value." ". (($_POST[$key] == "true") ? (1) : (0)) ." "; 
	      break;
	    case "vs_gl_output_vcf":
	      if($_POST[$key] == "true") { $vs_opts_cmd .= " ".$value." 1 ";}
	      break;
	    default:
	      $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; 
	    }
	  }
	  fwrite($fp, "out=$vs_mpileup_out.group\$gp.chr\$chralt.orig.vcf\n");
	  fwrite($fp, "log=$vs_mpileup_log.group\$gp.chr\$chralt\n");
	  fwrite($fp, "\\\$SAMTOOLS_DIR/$SAMTOOLS_EXE mpileup $sam_opts_cmd -f \\\$VS_REF -r \$chr -b \\\$RUNDIR/varscan/group\$gp/bamfilelist.inp  | java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." $vs_mpileup_cmd ". " - $vs_opts_cmd  > ./\\\$out  2> ./\\\$log\n");

	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$out  ./\\\${out/%orig.vcf/gvip.vcf}\n");
	  fwrite($fp, "\$del_local ./\\\$out\n");

	  fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
	  if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
	  //	  fwrite($fp, "\$put_cmd  \`pwd\`/$vs_mpileup_out.group\$gp.chr\$chralt.gvip.vcf  \`pwd\`/$vs_mpileup_log.group\$gp.chr\$chralt \\\$tgt/\n");
	  fwrite($fp, "\$put_cmd   \`pwd\`/\\\$log  \\\$tgt/\n");
	}
      }

      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n"); 
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_VARSCAN_DISCOVERY=\\\$scr_t0,\\\$scr_dt\n");
	//
	fwrite($fp, "scr_t0=\\\$scr_tf\n"); 
      }


      // Run HC filter
      $vs_hc_filter_prefix['snv']['pass']   = "";
      $vs_hc_filter_prefix['snv']['fail']   = "";
      $vs_hc_filter_prefix['indel']['pass'] = "";
      $vs_hc_filter_prefix['indel']['fail'] = "";
      if (isset($_POST['vs_apply_high_confidence_filter'])) {         // TODO: break out into separate script like trio case
	foreach( $vs_bMap as $vartype => $vartype_bool) {
	  if ( $vartype_bool ) {
	    $vs_hc_filter_prefix[$vartype]['pass'] = "hc_pass.";
	    $vs_hc_filter_prefix[$vartype]['fail'] = "hc_fail.";
	    $vs_opts_cmd="";
	    foreach ($vs_gl_opts_type_f[$vartype] as $tmpkey => $value) { $key="vs_gl_filter_$tmpkey";  $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; }
	    if ( $vartype=="snv" &&  $vs_bMap['indel']) {  $vs_opts_cmd .= " --indel-file ./varscan.out.gl_indel.group\$gp.chr\$chralt.gvip.vcf ";  }
	    fwrite($fp, "echo 'APPLY NATIVE VARSCAN $vartype FILTER:' >> ./varscan.log.gl_$vartype.group\$gp.chr\$chralt\n");
	    fwrite($fp, "myorig=varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.vcf\n");
	    fwrite($fp, "mypass=\\\${myorig/%vcf/".$vs_hc_filter_prefix[$vartype]['pass']."vcf}\n");
	    fwrite($fp, "myfail=\\\${myorig/%vcf/".$vs_hc_filter_prefix[$vartype]['fail']."vcf}\n");
	    fwrite($fp, "java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." filter  ./\\\$myorig  $vs_opts_cmd  --output-file \\\$mypass   2>> ./varscan.log.gl_$vartype.group\$gp.chr\$chralt\n");
	    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/extract_fail.sh  ./\\\$myorig  ./\\\$mypass  ./\\\$myfail\n");
	    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/set_vcf_filter_label.sh  ./\\\$myfail  hc_fail\n");
	    fwrite($fp, "# \$del_local  \\\$myorig\n");  // Keep original
	  }
	}
      }
      
      // Run dbSNP filter
      $vs_dbsnp_filter_prefix['snv']['pass']   = "";
      $vs_dbsnp_filter_prefix['snv']['fail']   = "";
      $vs_dbsnp_filter_prefix['indel']['pass'] = "";
      $vs_dbsnp_filter_prefix['indel']['fail'] = "";
      if (isset($_POST['vs_apply_dbsnp_filter'])) {
	foreach( $vs_bMap as $vartype => $vartype_bool) { 
	  if ( $vartype_bool ) {
	    $vs_dbsnp_filter_prefix[$vartype]['pass'] = "dbsnp_pass.";
	    $vs_dbsnp_filter_prefix[$vartype]['fail'] = "dbsnp_present.";
	    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/dbsnp_filter.pl  ./vs_dbsnp_filter.$vartype.input\n");
	    if( $vs_hc_filter_prefix[$vartype]['pass'] != "" ) {
	      fwrite($fp, "\$del_local  ./varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	    }
	  }
	}
      }

      // Run false-positives filter (only for snvs at this time)
      $vs_fpfilter_prefix['snv']['pass']   = "";
      $vs_fpfilter_prefix['snv']['fail']   = "";
      $vs_fpfilter_prefix['indel']['pass'] = "";
      $vs_fpfilter_prefix['indel']['fail'] = "";
      if (isset($_POST['vs_apply_false_positives_filter'])) {
	$vartype="snv";
        if ( $vs_bMap[$vartype] ) {
	  $vs_fpfilter_prefix[$vartype]['pass'] = "fp_pass.";
	  $vs_fpfilter_prefix[$vartype]['fail'] = "fp_fail.";
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/snv_filter.pl  ./vs_fpfilter.$vartype.input\n");
	  if ($vs_dbsnp_filter_prefix[$vartype]['pass'] != "") { 
	    fwrite($fp, "\$del_local  ./varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n"); 
	  } elseif ($vs_hc_filter_prefix[$vartype]['pass'] != "") {
	    fwrite($fp, "\$del_local  ./varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n"); 
	  }
	}
      }
      

      fwrite($fp, "\$del_cmd  \\\$remotestatus\n");
      fwrite($fp, "\$del_local \\\$localstatus\n");
      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n"); 
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_VARSCAN_FILTERING=\\\$scr_t0,\\\$scr_dt\n");
      }      
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp,"EOF\n");


      // Generate input files
      foreach ($vs_bMap as $vartype => $vartype_bool) {
	if( $vartype_bool ) {

	  if (isset($_POST['vs_apply_dbsnp_filter'])) {
	    $prefix="varscan.dbsnp.$vartype";
	    fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_dbsnp_filter.$vartype.input <<EOF\n");  
	    fwrite($fp, "$prefix.annotator = ".$toolsinfo_h['snpsift']['path']."/".$toolsinfo_h['snpsift']['exe']."\n");
	    fwrite($fp, "$prefix.db = ".$toolsinfo_h[$_POST['dbsnp_version']]['path']."/".$toolsinfo_h[$_POST['dbsnp_version']]['file']."\n");     
	    fwrite($fp, "$prefix.rawvcf = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.passfile = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.dbsnpfile = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['fail']."vcf\n");
	    fwrite($fp, "EOF\n");
	  }
	  
	  if (isset($_POST['vs_apply_false_positives_filter'])) {
	    if ( $vartype=="snv" ) {  //  only for snvs at this time
	      $prefix="varscan.fpfilter.$vartype";
	      fwrite($fp, "FP_BAM=`awk '{if(NR==1){print \$1}}' \$RUNDIR/varscan/group\$gp/bamfilelist.inp`\n");
	      fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_fpfilter.$vartype.input <<EOF\n");
	      fwrite($fp, "$prefix.bam_readcount = ".$toolsinfo_h['readcount']['path']."/".$toolsinfo_h['readcount']['exe']."\n");
	      fwrite($fp, "$prefix.bam_file = \$FP_BAM\n");
	      fwrite($fp, "$prefix.REF = \$RUNDIR/reference/\$VS_REF\n");
	      fwrite($fp, "$prefix.variants_file = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	      fwrite($fp, "$prefix.passfile = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf\n");
	      fwrite($fp, "$prefix.failfile = \$RUNDIR/varscan/group\$gp/\$chralt/varscan.out.gl_$vartype.group\$gp.chr\$chralt.gvip.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['fail']."vcf\n");
	      foreach ($vs_opts_fpfilter as $value) { $key = "vs_fp_".$value; fwrite($fp, "$prefix.$value = ".$_POST[$key]."\n"); }
	      fwrite($fp, "EOF\n");
	    }
	  }
      
	}
      }

      fwrite($fp, "cd \$RUNDIR/varscan/\$dir ; chmod +x ./varscan.sh\n");

      $mem_opt = gen_mem_str($compute_target, $toolmem_h['varscan']['mem_default']);

      $job_name = $batch['name_opt']." "."\$tag_vs.vs_gl.group\$gp.chr";
      $ERRARG = "-e ./stderr.varscan.group\$gp.chr\$chralt";
      $OUTARG = "-o ./stdout.varscan.group\$gp.chr\$chralt";
      $EXEARG = "./varscan.sh";
      fwrite($fp,"$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." ".$job_name." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");


      fwrite($fp,"# done chr\n"); 
      fwrite($fp,"   done\n");  //chr
	  

      // Gather group results and annotate
      fwrite($fp, " cat > \$RUNDIR/varscan/group\$gp/varscan_postrun.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");
      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "VCFTOOLSDIR=".preg_replace('/\/bin$/', "", $toolsinfo_h['vcftools']['path'])."\n");
      fwrite($fp, "export PERL5LIB=\\\$VCFTOOLSDIR/lib/perl5/site_perl:\\\$PERL5LIB\n");
      //TODO:  if AWS
      //      fwrite($fp, "S3CMD=$s3cmd\n");
      fwrite($fp, "put_cmd=$put_cmd\n");
      fwrite($fp, "del_cmd=$del_cmd\n");
      fwrite($fp, "del_local=$del_local\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
      fwrite($fp, "statfile_gl_g=incomplete.vs_postrun.group\$gp\n");
      fwrite($fp, "localstatus_gl_g=\\\$RUNDIR/status/\\\$statfile_gl_g\n");
      fwrite($fp, "remotestatus_gl_g=\\\$RESULTSDIR/\\\$statfile_gl_g\n");
      fwrite($fp, "cd \\\$RUNDIR/varscan/group\$gp\n");
      fwrite($fp, "outlist=varscan.out.gl_all.group\$gp.all.filelist\n");
      write_vs_gl_merge($fp, $vs_bMap, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix);

      if (isset($_POST['vep_cmd'])) {
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/vep_annotator.pl ./vs_vep.input >& ./vs_vep.log\n");
	fwrite($fp, "\$del_local ./varscan.out.gl_all.group\$gp.all.current_final.gvip.vcf\n");
      }
      // TODO
      //      fwrite($fp, "\$del_cmd  \\\$remotestatus_gl_g\n");
      //      fwrite($fp, "\$del_local \\\$localstatus_gl_g\n");

      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_VARSCAN_GATHER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");

      // Generate VEP input
      if (isset($_POST['vep_cmd'])) {
	$prefix="varscan.vep";
	fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/vs_vep.input <<EOF\n");
	fwrite($fp, "$prefix.vcf = ./varscan.out.gl_all.group\$gp.all.current_final.gvip.vcf\n");
	fwrite($fp, "$prefix.output = ./varscan.out.gl_all.group\$gp.all.current_final.gvip.VEP.vcf\n");
	write_vep_input_common($fp, $prefix);
	fwrite($fp, "EOF\n");
      }
	  

      fwrite($fp, "cd \$RUNDIR/varscan/group\$gp ;  chmod +x ./varscan_postrun.sh\n");
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['gather']['mem_default']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\$tag_vs.vs_gl.group\$gp.$wc".$batch['dep_opt_post'];
      $job_name = $batch['name_opt']." "."vs_postrun.group\$gp";
      $ERRARG = "-e ./stderr.varscan.group\$gp.postrun";
      $OUTARG = "-o ./stdout.varscan.group\$gp.postrun";
      $EXEARG = "./varscan_postrun.sh";
      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "\n");




      fwrite($fp,"# done group\n"); 
      fwrite($fp, "done\n");  // group
      fwrite($fp, "\n");

      
    }  // gl


    elseif ($_POST['vs_call_mode'] == "somatic" ) { // somatic 




      
      $sam_som_opts_cmd="";
      foreach ($vs_samtools_opts as $tmpkey => $value) { 
	$key = "vs_som_$tmpkey";
	switch ($key) {
	case "vs_som_samtools_perform_BAQ":
	  if ($_POST[$key]=="disabled") {$sam_som_opts_cmd .= " $value "; }
	  break;
	default:
	  $sam_som_opts_cmd .= " ".$value." ".$_POST[$key]." "; 
	}
      }
      

      
      $vs_som_opts_extra = array("output_vcf" => " --output-vcf ",);

      $vs_som_opts_cmd="";

      foreach ( ($vs_som_opts + $vs_som_opts_extra) as $tmpkey => $value)  {
	$key = "vs_som_$tmpkey";
	switch ($key) {
	case "vs_som_apply_strand_filter":
	  $vs_som_opts_cmd .= " ".$value." ". (($_POST[$key] == "true") ? (1) : (0)) ." ";
	  break;
	case "vs_som_report_validation":
	  if ($_POST[$key]=="true") { $vs_som_opts_cmd .= " ".$value." "; }
	  break;
	case "vs_som_output_vcf":
	  if($_POST[$key]=="true") { $vs_som_opts_cmd .= " ".$value." 1 ";}
	  break;
	default:
	  $vs_som_opts_cmd .= " ".$value." ".$_POST[$key]." ";
	}
      }
      









	// --------------------------------------------------------------------------------
	// Set up dirs and samples

	write_sample_tuples($fp, $list_of_sorted_bams, "varscan", 2);
	
	// Chromosome
	write_chromosomes($fp,$_POST['vs_chrdef'], "VS_REF_fai", $_POST['vs_chrdef_str'] );



	fwrite($fp,"# varscan somatic snvindels\n");
	fwrite($fp,"cd \$RUNDIR/varscan\n");
	fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");

	if ($compute_target != "AWS") {  fwrite($fp, "   mkdir -p \$RESULTSDIR/varscan/group\$gp\n"); }
	fwrite($fp, "   \$put_cmd  \$RUNDIR/varscan/group\$gp/bamfilelist.inp \$RESULTSDIR/varscan/group\$gp/bamfilelist.group\$gp.inp\n");
	
	fwrite($fp, "   statfile_gl_g=incomplete.vs_postrun.group\$gp\n");
	fwrite($fp, "   localstatus_gl_g=\$RUNDIR/status/\$statfile_gl_g\n");
	fwrite($fp, "   remotestatus_gl_g=\$RESULTSDIR/\$statfile_gl_g\n");
	fwrite($fp, "   touch \$localstatus_gl_g\n");
	fwrite($fp, "   ".str_replace("\"","",$put_cmd)." "."\$localstatus_gl_g  \$remotestatus_gl_g\n");
	
	fwrite($fp, "   tag_vs=\$(cat /dev/urandom | tr -dc 'a-zA-Z' | fold -w 6 | head -n 1)\n");
	fwrite($fp, "   for chr in \$SEQS; do\n");
	fwrite($fp,"    chralt=\${chr/:/_}\n");
	fwrite($fp, "      dir=group\$gp/\$chralt\n");
	fwrite($fp, "      mkdir -p \$RUNDIR/varscan/\$dir\n");
	fwrite($fp, "      cat > \$RUNDIR/varscan/\$dir/varscan.sh <<EOF\n");
	write_vs_preamble($fp, $toolsinfo_h);
	fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
	fwrite($fp, "export VARSCAN_DIR=".$toolsinfo_h[$_POST['vs_version']]['path']."\n");
	fwrite($fp, "RUNDIR=\$RUNDIR\n");
	fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
	fwrite($fp, "VS_REF=\\\$RUNDIR/reference/$VS_REF\n");
	fwrite($fp, "statfile=incomplete.vs_som_snvindels.group\$gp.chr\$chralt\n");
	fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
	fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
	fwrite($fp, "touch \\\$localstatus\n");
	fwrite($fp, "\$put_cmd \\\$localstatus  \\\$remotestatus\n");

	fwrite($fp, "cd \\\$RUNDIR/varscan/\$dir\n");
	$SAMTOOLS_EXE = $toolsinfo_h['samtools']['exe'];


	fwrite($fp, "TMPBASE=./varscan.out.som\n");
	fwrite($fp, "LOG=\\\$TMPBASE.group\$gp.chr\$chralt.log\n");

	fwrite($fp, "snvoutbase=\\\${TMPBASE}_snv.group\$gp.chr\$chralt\n");
	fwrite($fp, "indeloutbase=\\\${TMPBASE}_indel.group\$gp.chr\$chralt\n");

	fwrite($fp, "\\\$SAMTOOLS_DIR/$SAMTOOLS_EXE mpileup $sam_som_opts_cmd  -f \\\$VS_REF -r \$chr -b \\\$RUNDIR/varscan/group\$gp/bamfilelist.inp | java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." somatic -  \\\${TMPBASE}.group\$gp.chr\$chralt  --mpileup 1  $vs_som_opts_cmd --output-snp \\\$snvoutbase --output-indel \\\$indeloutbase &> \\\$LOG\n");
	// Basic results here
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$snvoutbase.vcf   ./\\\$snvoutbase.gvip.vcf\n");
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$indeloutbase.vcf ./\\\$indeloutbase.gvip.vcf\n");
	fwrite($fp, "\$del_local ./\\\$snvoutbase.vcf  ./\\\$indeloutbase.vcf\n");
	

	// TODO: while validation is useful, we disabled it in the interface; need to check whether including it overrides expected output filenames
	if ($_POST['vs_som_report_validation']=="true") { 
	  fwrite($fp, "validoutbase=\\\${TMPBASE}.group\$gp.chr\$chralt.validation\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$validoutbase  ./\\\$validoutbase.gvip.vcf\n");
	  fwrite($fp, "\$del_local ./\\\$validoutbase\n");
	}

	fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
	if ($compute_target!="AWS") {	fwrite($fp, "mkdir -p \\\$tgt \n"); }
	fwrite($fp, "\$put_cmd  \`pwd\`/\\\${TMPBASE}_{snv,indel}.group\$gp.chr\$chralt.vcf  \`pwd\`/\\\$TMPBASE.group\$gp.chr\$chralt.log \\\$tgt/\n");
	fwrite($fp, "\$put_cmd  \\\$RUNDIR/varscan/group\$gp/bamfilelist.inp \\\$tgt/bamfilelist.varscan_somatic.group\$gp.chr\$chralt.inp\n");
	if ($_POST['vs_som_report_validation']=="true") {
	  fwrite($fp, "\$put_cmd  \`pwd\`/\\\$TMPBASE.chr\$chralt.validation \\\$tgt/\n");
	}



	// Somatic setup
	$vs_som_prefix = "Somatic.";

	// Run HC (somatic) filtering
	$vs_hc_filter_prefix['snv']['pass']   = "";
	$vs_hc_filter_prefix['snv']['fail']   = "";
	$vs_hc_filter_prefix['indel']['pass'] = "";
	$vs_hc_filter_prefix['indel']['fail'] = "";

	if (isset($_POST['vs_apply_high_confidence_filter'])) {
	  $vs_opts_cmd="";
	  foreach ($vs_som_opts_hcf_snv as $tmpkey => $value) { $key="vs_som_filter_$tmpkey"; $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; }
	  fwrite($fp, "echo 'APPLYING PROCESS FILTER TO SOMATIC SNVS:' &>> \\\$LOG\n");
	  fwrite($fp, "mysnvorig=./\\\$snvoutbase.gvip.vcf\n");
	  fwrite($fp, "java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." processSomatic \\\$mysnvorig  $vs_opts_cmd &>> \\\$LOG\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/extract_somatic_other.pl <  \\\$mysnvorig  >  \\\${mysnvorig/%vcf/other.vcf}\n");
 	  fwrite($fp, "# \$del_local  \\\$mysnvorig\n");
	  fwrite($fp, "for kk in Somatic Germline LOH ; do\n");
	  fwrite($fp, "   thisorig=\\\${mysnvorig/%vcf/\\\$kk.vcf}\n");
	  fwrite($fp, "   thispass=\\\${mysnvorig/%vcf/\\\$kk.hc.vcf}\n");
	  fwrite($fp, "   thisfail=\\\${mysnvorig/%vcf/\\\$kk.lc.vcf}\n");
	  fwrite($fp, "   \\\$GENOMEVIP_SCRIPTS/extract_fail.sh  ./\\\$thisorig  ./\\\$thispass  ./\\\$thisfail\n");
	  fwrite($fp, "   \\\$GENOMEVIP_SCRIPTS/set_vcf_filter_label.sh  ./\\\$thisfail  hc_fail\n");
	  fwrite($fp, "   \$del_local  ./\\\$thisorig\n");
	  fwrite($fp, "done\n");
	  $vs_opts_cmd="";
	  foreach ($vs_som_opts_hcf_indel as $tmpkey => $value) { $key="vs_som_filter_$tmpkey"; $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; }
	  fwrite($fp, "echo 'APPLYING PROCESS FILTER TO SOMATIC INDELS:' &>> \\\$LOG\n");
	  fwrite($fp, "myindelorig=./\\\$indeloutbase.gvip.vcf\n");
	  fwrite($fp, "java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." processSomatic \\\$myindelorig $vs_opts_cmd &>> \\\$LOG\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/extract_somatic_other.pl <  \\\$myindelorig  >  \\\${myindelorig/%vcf/other.vcf}\n");
	  // Note: Whereas the raw snv was deleted, keep the original raw pindel since it is needed in somatic filter

	  // TODO: possibly merge this part into above
	  fwrite($fp, "for kk in Somatic Germline LOH ; do\n");
	  fwrite($fp, "   thisorig=\\\${myindelorig/%vcf/\\\$kk.vcf}\n");
	  fwrite($fp, "   thispass=\\\${myindelorig/%vcf/\\\$kk.hc.vcf}\n");
	  fwrite($fp, "   thisfail=\\\${myindelorig/%vcf/\\\$kk.lc.vcf}\n");
	  fwrite($fp, "   \\\$GENOMEVIP_SCRIPTS/extract_fail.sh  ./\\\$thisorig  ./\\\$thispass  ./\\\$thisfail\n");
	  fwrite($fp, "   \\\$GENOMEVIP_SCRIPTS/set_vcf_filter_label.sh  ./\\\$thisfail  hc_fail\n");
	  fwrite($fp, "   \$del_local  ./\\\$thisorig\n");
	  fwrite($fp, "done\n");


	  if($do_timing) {
	    fwrite($fp, "scr_tf=\`date +%s\`\n");
	    fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	    fwrite($fp, "echo GVIP_TIMING_VARSCAN_DISCOVERY=\\\$scr_t0,\\\$scr_dt\n");
	    //
	    fwrite($fp, "scr_t0=\\\$scr_tf\n");
	  }

	  $vs_opts_cmd="";
	  foreach ($vs_som_opts_som_f as $tmpkey => $value) { $key="vs_som_filter_$tmpkey"; $vs_opts_cmd .= " ".$value." ".$_POST[$key]." "; }
	  fwrite($fp, "echo 'APPLYING SOMATIC FILTER:' &>> \\\$LOG\n");
	  fwrite($fp, "thissnvorig=\\\$snvoutbase.gvip.".$vs_som_prefix."hc.vcf\n");
	  fwrite($fp, "myindelorig=\\\$indeloutbase.gvip.vcf\n");    // unfiltered result

	  $vs_hc_filter_prefix['snv']['pass']   = "hc.somfilter_pass.";
	  $vs_hc_filter_prefix['snv']['fail']   = "hc.somfilter_fail.";
	  $vs_hc_filter_prefix['indel']['pass'] = "hc.";
	  $vs_hc_filter_prefix['indel']['fail'] = "lc.";

	  fwrite($fp, "thissnvpass=\\\$snvoutbase.gvip.".$vs_som_prefix.$vs_hc_filter_prefix['snv']['pass']."vcf\n");
	  fwrite($fp, "thissnvfail=\\\$snvoutbase.gvip.".$vs_som_prefix.$vs_hc_filter_prefix['snv']['fail']."vcf\n");
	  fwrite($fp, "java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." somaticFilter  ./\\\$thissnvorig   $vs_opts_cmd  --indel-file  ./\\\$myindelorig --output-file  ./\\\$thissnvpass  &>> \\\$LOG\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/extract_fail.sh          ./\\\$thissnvorig  ./\\\$thissnvpass   ./\\\$thissnvfail\n"); 
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/set_vcf_filter_label.sh  ./\\\$thissnvfail   somfilter_fail\n");
	  fwrite($fp, "\$del_local  ./\\\$thissnvorig\n");
	  fwrite($fp, "# \$del_local  ./\\\$myindelorig\n");    // can remove orig indel now if desired

	} else {
	  // Perform somatic separation
	  fwrite($fp, "echo 'SEPARATING OUT SOMATIC AND OTHER CALLS:' &>> \\\$LOG\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/split_vs_somatic.pl ./\\\$snvoutbase.gvip.vcf\n"); 
	  fwrite($fp, "# \$del_local  ./\\\$snvoutbase.gvip.vcf\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/split_vs_somatic.pl ./\\\$indeloutbase.gvip.vcf\n"); 	
	  fwrite($fp, "# \$del_local  ./\\\$indeloutbase.gvip.vcf\n");


	  if($do_timing) {
	    fwrite($fp, "scr_tf=\`date +%s\`\n");
	    fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	    fwrite($fp, "echo GVIP_TIMING_VARSCAN_DISCOVERY=\\\$scr_t0,\\\$scr_dt\n");
	    //
	    fwrite($fp, "scr_t0=\\\$scr_tf\n");
	  }

	}


	// Run dbSNP filter
	$vs_dbsnp_filter_prefix['snv']['pass']   = "";
	$vs_dbsnp_filter_prefix['snv']['fail']   = "";
	$vs_dbsnp_filter_prefix['indel']['pass'] = "";
	$vs_dbsnp_filter_prefix['indel']['fail'] = "";
	if (isset($_POST['vs_apply_dbsnp_filter'])) {     
	  foreach( array('snv','indel') as $vartype) {
	    $vs_dbsnp_filter_prefix[$vartype]['pass']   = "dbsnp_pass.";
	    $vs_dbsnp_filter_prefix[$vartype]['fail']   = "dbsnp_present.";
	    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/dbsnp_filter.pl  \\\$RUNDIR/varscan/group\$gp/\$chralt/vs_dbsnp_filter.$vartype.input\n"); 
	    if( $vs_hc_filter_prefix[$vartype]['pass'] != "") {
	      fwrite($fp, "\$del_local ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass']."vcf\n"); 
	    } else {
	      fwrite($fp, "\$del_local ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix."vcf\n"); 
	    }
	  }
	  
	}

	
	// Run false-positives filter (only for snvs at this time)
	$vs_fpfilter_prefix['snv']['pass']   = "";
	$vs_fpfilter_prefix['snv']['fail']   = "";
	$vs_fpfilter_prefix['indel']['pass'] = "";
	$vs_fpfilter_prefix['indel']['fail'] = "";
	if (isset($_POST['vs_apply_false_positives_filter'])) {
	  $vartype="snv";
	  $vs_fpfilter_prefix[$vartype]['pass'] = "fp_pass.";
	  $vs_fpfilter_prefix[$vartype]['fail'] = "fp_fail.";
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/snv_filter.pl  \\\$RUNDIR/varscan/group\$gp/\$chralt/vs_fpfilter.somatic.$vartype.input\n");
	  if( $vs_dbsnp_filter_prefix[$vartype]['pass'] != "" ) {
	    fwrite($fp, "\$del_local  ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	  } elseif ( $vs_hc_filter_prefix[$vartype]['pass'] != "") {
	    fwrite($fp, "\$del_local  ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	  } else {
	    fwrite($fp, "\$del_local  ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix."vcf\n"); 
	  }
	}

	// TODO
	//	fwrite($fp, "\$del_cmd \\\$remotestatus\n");
	//	fwrite($fp, "\$del_local \\\$localstatus\n");
	if($do_timing) {
	  fwrite($fp, "scr_tf=\`date +%s\`\n");
	  fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	  fwrite($fp, "echo GVIP_TIMING_VARSCAN_FILTERING=\\\$scr_t0,\\\$scr_dt\n");
	}
	if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
	fwrite($fp, "EOF\n");


	// Generate dbSNP input files
	if (isset($_POST['vs_apply_dbsnp_filter'])) {
	  foreach( array('snv','indel') as $vartype) {
	    $prefix="varscan.dbsnp.$vartype";
	    fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_dbsnp_filter.$vartype.input <<EOF\n");  
	    fwrite($fp, "$prefix.annotator = ".$toolsinfo_h['snpsift']['path']."/".$toolsinfo_h['snpsift']['exe']."\n");
	    fwrite($fp, "$prefix.db = ".$toolsinfo_h[$_POST['dbsnp_version']]['path']."/".$toolsinfo_h[$_POST['dbsnp_version']]['file']."\n");     
	    fwrite($fp, "$prefix.rawvcf    = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.passfile  = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.dbsnpfile = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['fail']."vcf\n");
	    fwrite($fp, "EOF\n");
	  }
	}
	

	// Generate false-positives input file (only for snvs at this time)
	if (isset($_POST['vs_apply_false_positives_filter'])) {
	  $vartype="snv";
	  $prefix="varscan.fpfilter.$vartype";
	  fwrite($fp, "FP_BAM=`awk '{if(NR==2){print \$1}}' \$RUNDIR/varscan/group\$gp/bamfilelist.inp`\n");
	  fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_fpfilter.somatic.$vartype.input <<EOF\n");
	  fwrite($fp, "$prefix.bam_readcount = ".$toolsinfo_h['readcount']['path']."/".$toolsinfo_h['readcount']['exe']."\n");
	  fwrite($fp, "$prefix.bam_file = \$FP_BAM\n");
	  fwrite($fp, "$prefix.REF = \$RUNDIR/reference/\$VS_REF\n");
	  fwrite($fp, "$prefix.variants_file = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	  fwrite($fp, "$prefix.passfile = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf\n");
	  fwrite($fp, "$prefix.failfile = ./varscan.out.som_$vartype.group\$gp.chr\$chralt.gvip.".$vs_som_prefix.$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['fail']."vcf\n");
	  foreach ($vs_opts_fpfilter as $value) { $key = "vs_fp_".$value; fwrite($fp, "$prefix.$value = ".$_POST[$key]."\n"); }
	  fwrite($fp, "EOF\n");
	}


	fwrite($fp, "cd \$RUNDIR/varscan/\$dir ; chmod +x ./varscan.sh\n");
	//	$tmp_mem = $toolmem_h['varscan']['mem_default'];
	if ($_POST['vs_som_report_validation']=="true") {
	  $mem_opt = gen_mem_str($compute_target, $toolmem_h['varscan_som_validation']['mem_default']);
	} else {
	  $mem_opt = gen_mem_str($compute_target, $toolmem_h['varscan']['mem_default']);
	}
	fwrite($fp, "chralt=\${chr/:/_}\n");
	$job_name = $batch['name_opt']." "."\$tag_vs.vs_som.group\$gp";
	$ERRARG = "-e ./stderr.varscan.group\$gp.chr\$chralt"; 
	$OUTARG = "-o ./stdout.varscan.group\$gp.chr\$chralt";
	$EXEARG = "./varscan.sh";
	fwrite($fp,"$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." ".$job_name." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
	fwrite($fp,"sleep $dlay\n");



      fwrite($fp,"#done chr\n"); 
      fwrite($fp,"   done\n"); 



      // Gather group results and annotate
      fwrite($fp, " cat > \$RUNDIR/varscan/group\$gp/varscan_postrun.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");
      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "VCFTOOLSDIR=".preg_replace('/\/bin$/', "", $toolsinfo_h['vcftools']['path'])."\n");
      fwrite($fp, "export PERL5LIB=\\\$VCFTOOLSDIR/lib/perl5/site_perl:\\\$PERL5LIB\n");
      fwrite($fp, "put_cmd=$put_cmd\n");
      fwrite($fp, "del_cmd=$del_cmd\n");
      fwrite($fp, "del_local=$del_local\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
      fwrite($fp, "statfile_gl_s=incomplete.vs_postrun.group\$gp\n");
      fwrite($fp, "localstatus_gl_s=\\\$RUNDIR/status/\\\$statfile_gl_s\n");
      fwrite($fp, "remotestatus_gl_s=\\\$RESULTSDIR/\\\$statfile_gl_s\n");
      fwrite($fp, "cd \\\$RUNDIR/varscan/group\$gp\n");
      fwrite($fp, "outlist=varscan.out.som_all.group\$gp.all.filelist\n");
      write_vs_som_merge($fp,  $vs_som_prefix, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix);

      if (isset($_POST['vep_cmd'])) {
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/vep_annotator.pl ./vs_vep.input >& ./vs_vep.log\n");
	fwrite($fp, "\$del_local ./varscan.out.som_all.group\$gp.all.current_final.gvip.Somatic.vcf\n");
      }

      // TODO
      //fwrite($fp, "\$del_local \\\$tmplist \\\$tmpunsorted\n");
      //fwrite($fp, "\$del_cmd  \\\$remotestatus_gl_s\n");
      //fwrite($fp, "\$del_local \\\$localstatus_gl_s\n");

      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_VARSCAN_GATHER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");

      // Generate VEP input
      if (isset($_POST['vep_cmd'])) {
	$prefix="varscan.vep";
	fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/vs_vep.input <<EOF\n");
	fwrite($fp, "$prefix.vcf = ./varscan.out.som_all.group\$gp.all.current_final.gvip.Somatic.vcf\n");
	fwrite($fp, "$prefix.output = ./varscan.out.som_all.group\$gp.all.current_final.gvip.Somatic.VEP.vcf\n");
	write_vep_input_common($fp, $prefix);
	fwrite($fp, "EOF\n");
      }
	  


      fwrite($fp, "cd \$RUNDIR/varscan/group\$gp ;  chmod +x ./varscan_postrun.sh\n");
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['gather']['mem_default']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\$tag_vs.vs_som.group\$gp".$batch['dep_opt_post'];
      $job_name = $batch['name_opt']." "."vs_postrun.group\$gp";
      $ERRARG = "-e ./stderr.varscan.group\$gp.postrun";
      $OUTARG = "-o ./stdout.varscan.group\$gp.postrun";
      $EXEARG = "./varscan_postrun.sh";
      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "\n");


      fwrite($fp,"# done group\n"); 
      fwrite($fp,"done\n");
      fwrite($fp,"\n");

    } // som




    else {  // trio
      

      
      $sam_trio_opts_cmd="";
      foreach ($vs_samtools_opts as $tmpkey => $value) { 
	$key = "vs_trio_$tmpkey";
	switch ($key) {
	case "vs_trio_samtools_perform_BAQ":
	  if ($_POST[$key]=="disabled") {$sam_trio_opts_cmd .= " ".$value." "; }
	  break;
	default:
	  $sam_trio_opts_cmd .= " ".$value." ".$_POST[$key]." "; 
	}
      }
      


      // Note: VarScan automatically forces output in VCF format.
      $vs_trio_opts_cmd="";
      foreach ($vs_trio_opts as $tmpkey => $value) { 
	$key = "vs_trio_$tmpkey";
	switch($key) {
	case "vs_trio_apply_strand_filter":
	  $vs_trio_opts_cmd .= " ".$value." ". (($_POST[$key] == "true") ? (1) : (0)) ." ";
	  break;
	default:
	  $vs_trio_opts_cmd .= " ".$value." ".$_POST[$key]." ";
	}
      }

	
      // Set up dirs and samples
      write_sample_tuples($fp, $list_of_sorted_bams, "varscan", 3);
      
      // Chromosome
      write_chromosomes($fp,$_POST['vs_chrdef'], "VS_REF_fai", $_POST['vs_chrdef_str'] );

      
      fwrite($fp,"# varscan de novo\n");
      fwrite($fp,"cd \$RUNDIR/varscan\n");
      fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");
      fwrite($fp, "   tag_vs=\$(cat /dev/urandom | tr -dc 'a-zA-Z' | fold -w 6 | head -n 1)\n");
      fwrite($fp, "   for chr in \$SEQS; do\n");
      fwrite($fp,"    chralt=\${chr/:/_}\n");
      fwrite($fp, "      dir=group\$gp/\$chralt\n");
      fwrite($fp, "      mkdir -p \$RUNDIR/varscan/\$dir\n");
      fwrite($fp, "      cat > \$RUNDIR/varscan/\$dir/varscan.sh <<EOF\n");
      write_vs_preamble($fp, $toolsinfo_h);

      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "export VARSCAN_DIR=".$toolsinfo_h[$_POST['vs_version']]['path']."\n");
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "VS_REF=\\\$RUNDIR/reference/$VS_REF\n");
      fwrite($fp, "statfile=incomplete.varscan.group\$gp.chr\$chralt\n");
      fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
      fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
      fwrite($fp, "touch \\\$localstatus\n");
      fwrite($fp, "\$put_cmd \\\$localstatus  \\\$remotestatus\n");
      fwrite($fp, "cd \\\$RUNDIR/varscan/\$dir\n");
      fwrite($fp, "TMPBASE=varscan.out.trio.group\$gp.chr\$chralt\n");
      fwrite($fp, "LOG=\\\$TMPBASE.log\n");

      $SAMTOOLS_EXE = $toolsinfo_h['samtools']['exe'];
      fwrite($fp, "\\\$SAMTOOLS_DIR/$SAMTOOLS_EXE mpileup $sam_trio_opts_cmd  -f \\\$VS_REF -r \$chr -b \\\$RUNDIR/varscan/group\$gp/bamfilelist.inp | java \\\$JAVA_OPTS -jar \\\$VARSCAN_DIR/".$toolsinfo_h[$_POST['vs_version']]['exe']." trio  -  ./\\\$TMPBASE   $vs_trio_opts_cmd  &> \\\$LOG\n");

      $vartype='snv';
      fwrite($fp, "cat ./\\\$TMPBASE.snp.vcf  > ./\\\$TMPBASE.$vartype.vcf\n");
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$TMPBASE.$vartype.vcf  ./\\\$TMPBASE.$vartype.gvip.vcf\n");
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/split_trio.pl ./\\\$TMPBASE.$vartype.gvip.vcf\n");
      fwrite($fp, "\$del_local  ./\\\$TMPBASE.snp.vcf ./\\\$TMPBASE.$vartype.vcf \n");
      fwrite($fp, "# \$del_local  ./\\\$TMPBASE.$vartype.gvip.vcf\n"); // keep orig
      $vartype='indel';
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl VarScan ./\\\$TMPBASE.$vartype.vcf  ./\\\$TMPBASE.$vartype.gvip.vcf\n");
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/split_trio.pl ./\\\$TMPBASE.$vartype.gvip.vcf\n");
      fwrite($fp, "\$del_local  ./\\\$TMPBASE.$vartype.vcf \n");
      fwrite($fp, "# \$del_local  ./\\\$TMPBASE.$vartype.gvip.vcf\n"); // keep orig


      fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
      if ($compute_target!="AWS") {	fwrite($fp, "mkdir -p \\\$tgt \n"); }
      fwrite($fp, "\$put_cmd  \`pwd\`/\\\$TMPBASE.chr\${chralt}.{snv,indel,snv.denovo_pass}.vcf  \`pwd\`/\\\$TMPBASE.log.chr{\$chralt,\$chralt.log} \\\$tgt/\n");
      fwrite($fp, "\$put_cmd  \\\$RUNDIR/varscan/group\$gp/bamfilelist.inp \\\$tgt/bamfilelist.varscan.group\$gp.chr\$chralt.inp\n");

      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_VARSCAN_DISCOVERY=\\\$scr_t0,\\\$scr_dt\n");
	//
        fwrite($fp, "scr_t0=\\\$scr_tf\n");
      }


      // Run HC filter for trio
      $vs_hc_filter_prefix['snv']['pass']   = "";
      $vs_hc_filter_prefix['snv']['fail']   = "";
      $vs_hc_filter_prefix['indel']['pass'] = "";
      $vs_hc_filter_prefix['indel']['fail'] = "";
      if(isset($_POST['vs_apply_high_confidence_filter'])) {  
	foreach( array('snv','indel') as $vartype) {
	  $vs_hc_filter_prefix[$vartype]['pass'] = "hc_pass.";
	  $vs_hc_filter_prefix[$vartype]['fail'] = "hc_fail.";
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/trio_hcfilter.pl  ./vs_hcfilter.$vartype.input\n");
	  fwrite($fp, "# \$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass.vcf\n");  // keep original
	}
      }
    
      // Run dbSNP filter for trio
      $vs_dbsnp_filter_prefix['snv']['pass']   = "";
      $vs_dbsnp_filter_prefix['snv']['fail']   = "";
      $vs_dbsnp_filter_prefix['indel']['pass'] = "";
      $vs_dbsnp_filter_prefix['indel']['fail'] = "";
      if (isset($_POST['vs_apply_dbsnp_filter'])) {     
	foreach( array('snv','indel') as $vartype) {
	  $vs_dbsnp_filter_prefix[$vartype]['pass'] = "dbsnp_pass.";
	  $vs_dbsnp_filter_prefix[$vartype]['fail'] = "dbsnp_present.";
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/dbsnp_filter.pl  ./vs_dbsnp_filter.$vartype.input\n");
	  if( $vs_hc_filter_prefix[$vartype]['pass'] != "" ) {
	    fwrite($fp, "\$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	  } else {
	    fwrite($fp, "\$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass."."vcf\n");
	  }
	}
      }

      // Run false-positives file (only for snvs at this time) for trio
      $vs_fpfilter_prefix['snv']['pass']   = "";
      $vs_fpfilter_prefix['snv']['fail']   = "";
      $vs_fpfilter_prefix['indel']['pass'] = "";
      $vs_fpfilter_prefix['indel']['fail'] = "";
      if (isset($_POST['vs_apply_false_positives_filter'])) {
	$vartype="snv";
	$vs_fpfilter_prefix[$vartype]['pass'] = "fp_pass.";
	$vs_fpfilter_prefix[$vartype]['fail'] = "fp_fail.";
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/snv_filter.pl  ./vs_fpfilter.$vartype.input\n");
	if ( $vs_dbsnp_filter_prefix[$vartype]['pass'] != "") {
	  fwrite($fp, "\$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	} elseif ( $vs_hc_filter_prefix[$vartype]['pass'] != "") {
	  fwrite($fp, "\$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	} else {
	  fwrite($fp, "\$del_local   \\\$TMPBASE.$vartype.gvip.denovo_pass."."vcf\n");
	}
      }

      // TODO
      //fwrite($fp, "\$del_cmd \\\$remotestatus\n");
      //fwrite($fp, "\$del_local \\\$localstatus\n");
      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_VARSCAN_FILTERING=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");


      // Generate input files
      foreach( array('snv','indel') as $vartype) {
	if(isset($_POST['vs_apply_high_confidence_filter'])) {
	  $prefix="varscan.hcfilter.$vartype";
	  fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_hcfilter.$vartype.input <<EOF\n");
	  fwrite($fp, "$prefix.parents_max_supporting_reads = ".$_POST['vs_trio_filter_parents_max_num_supporting_reads']."\n");
	  fwrite($fp, "$prefix.variants_file = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.vcf\n");
	  fwrite($fp, "$prefix.passfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	  fwrite($fp, "$prefix.failfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['fail']."vcf\n");
	  fwrite($fp, "EOF\n");
	}
	if (isset($_POST['vs_apply_dbsnp_filter'])) {
	  $prefix="varscan.dbsnp.$vartype";
	  fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_dbsnp_filter.$vartype.input <<EOF\n");  
	  fwrite($fp, "$prefix.annotator = ".$toolsinfo_h['snpsift']['path']."/".$toolsinfo_h['snpsift']['exe']."\n");
	  fwrite($fp, "$prefix.db = ".$toolsinfo_h[$_POST['dbsnp_version']]['path']."/".$toolsinfo_h[$_POST['dbsnp_version']]['file']."\n");     
	  fwrite($fp, "$prefix.rawvcf = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass']."vcf\n");
	  fwrite($fp, "$prefix.passfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	  fwrite($fp, "$prefix.dbsnpfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['fail']."vcf\n");
	  fwrite($fp, "EOF\n");
	}
	if (isset($_POST['vs_apply_false_positives_filter'])) { 
	  if ($vartype=="snv") {    // only for snvs at this time
	    $prefix="varscan.fpfilter.$vartype";
	    fwrite($fp, "FP_BAM=`awk '{if(NR==3){print \$1}}' \$RUNDIR/varscan/group\$gp/bamfilelist.inp`\n");
	    fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/\$chralt/vs_fpfilter.$vartype.input <<EOF\n");
	    fwrite($fp, "$prefix.bam_readcount = ".$toolsinfo_h['readcount']['path']."/".$toolsinfo_h['readcount']['exe']."\n");
	    fwrite($fp, "$prefix.bam_file = \$FP_BAM\n");
	    fwrite($fp, "$prefix.REF = \$RUNDIR/reference/\$VS_REF\n");
	    fwrite($fp, "$prefix.variants_file = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.passfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.failfile = ./varscan.out.trio.group\$gp.chr\$chralt.$vartype.gvip.denovo_pass.".$vs_hc_filter_prefix[$vartype]['pass'].$vs_dbsnp_filter_prefix[$vartype]['pass'].$vs_fpfilter_prefix[$vartype]['fail']."vcf\n");
	    foreach ($vs_opts_fpfilter as $value) { $key = "vs_fp_".$value; fwrite($fp, "$prefix.$value = ".$_POST[$key]."\n"); }
	    fwrite($fp, "EOF\n");
	  }
	}
      }


      fwrite($fp, "cd \$RUNDIR/varscan/\$dir ; chmod +x ./varscan.sh\n");

      $mem_opt = gen_mem_str($compute_target, $toolmem_h['varscan']['mem_default']);

      fwrite($fp, "chralt=\${chr/:/_}\n");
      $job_name = $batch['name_opt']." "."\$tag_vs.vs_trio.group\$gp";
      $ERRARG = "-e ./stderr.varscan.group\$gp.chr\$chralt"; 
      $OUTARG = "-o ./stdout.varscan.group\$gp.chr\$chralt";
      $EXEARG = "./varscan.sh";
      fwrite($fp,"$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." ".$job_name." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");



      fwrite($fp,"#done chr\n");
      fwrite($fp,"   done\n");



      // Gather group results and annotate
      fwrite($fp, " cat > \$RUNDIR/varscan/group\$gp/varscan_postrun.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");
      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "VCFTOOLSDIR=".preg_replace('/\/bin$/', "", $toolsinfo_h['vcftools']['path'])."\n");
      fwrite($fp, "export PERL5LIB=\\\$VCFTOOLSDIR/lib/perl5/site_perl:\\\$PERL5LIB\n");
      fwrite($fp, "put_cmd=$put_cmd\n");
      fwrite($fp, "del_cmd=$del_cmd\n");
      fwrite($fp, "del_local=$del_local\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/varscan/group\$gp\n");
      fwrite($fp, "statfile_gl_s=incomplete.vs_postrun.group\$gp\n");
      fwrite($fp, "localstatus_gl_s=\\\$RUNDIR/status/\\\$statfile_gl_s\n");
      fwrite($fp, "remotestatus_gl_s=\\\$RESULTSDIR/\\\$statfile_gl_s\n");
      fwrite($fp, "cd \\\$RUNDIR/varscan/group\$gp\n");
      fwrite($fp, "outlist=varscan.out.trio_all.group\$gp.all.filelist\n");
      write_vs_trio_merge ($fp, $vs_hc_filter_prefix, $vs_dbsnp_filter_prefix, $vs_fpfilter_prefix);

      if (isset($_POST['vep_cmd'])) {
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/vep_annotator.pl ./vs_vep.input >& ./vs_vep.log\n");
	fwrite($fp, "\$del_local ./varscan.out.trio_all.group\$gp.all.current_final.gvip.denovo.vcf\n");
      }

      // TODO
      //fwrite($fp, "\$del_local \\\$tmplist \\\$tmpunsorted\n");
      //fwrite($fp, "\$del_cmd  \\\$remotestatus_gl_s\n");
      //fwrite($fp, "\$del_local \\\$localstatus_gl_s\n");

      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_VARSCAN_GATHER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");

      // Generate VEP input
      if (isset($_POST['vep_cmd'])) {
	$prefix="varscan.vep";
	fwrite($fp, "cat > \$RUNDIR/varscan/group\$gp/vs_vep.input <<EOF\n");
	fwrite($fp, "$prefix.vcf = ./varscan.out.trio_all.group\$gp.all.current_final.gvip.denovo.vcf\n");
	fwrite($fp, "$prefix.output = ./varscan.out.trio_all.group\$gp.all.current_final.gvip.denovo.VEP.vcf\n");
	write_vep_input_common($fp, $prefix);
	fwrite($fp, "EOF\n");
      }
	  


      fwrite($fp, "cd \$RUNDIR/varscan/group\$gp ;  chmod +x ./varscan_postrun.sh\n");
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['gather']['mem_default']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\$tag_vs.vs_trio.group\$gp".$batch['dep_opt_post'];
      $job_name = $batch['name_opt']." "."vs_postrun.group\$gp";
      $ERRARG = "-e ./stderr.varscan.group\$gp.postrun";
      $OUTARG = "-o ./stdout.varscan.group\$gp.postrun";
      $EXEARG = "./varscan_postrun.sh";
      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "\n");



      fwrite($fp,"#done group\n");
      fwrite($fp,"done\n");
      fwrite($fp,"\n");

    } // end vs trio

    
    fwrite($fp,"\n");



  } // if vs_cmd

  // ---------------------------------------------------------------------------
  // RUN STRELKA
  // --------------------------------------------------------------------------------
  if (isset($_POST['strlk_cmd'])) {

    write_sample_tuples($fp, $list_of_sorted_bams, "strelka", 2);

    // Strelka automatically runs by subchromosome bins



    fwrite($fp, "# strelka somatic\n");
    fwrite($fp, "cd \$RUNDIR/strelka\n");
    gen_strelka_ini($fp, "strelka.ini", $strlk_opts);
    fwrite($fp, "\$put_cmd \$RUNDIR/strelka/strelka.ini \$RESULTSDIR/strelka/strelka.ini\n");

    fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");
    fwrite($fp, "   mkdir -p \$RESULTSDIR/strelka/group\$gp\n");
    fwrite($fp, "   statfile_g=incomplete.strelka.group\$gp\n");
    fwrite($fp, "   localstatus_g=\$RUNDIR/status/\$statfile_g\n");
    fwrite($fp, "   remotestatus_g=\$RESULTSDIR/\$statfile_g\n");
    fwrite($fp, "   touch \$localstatus_g\n");
    fwrite($fp, "   ".str_replace("\"","",$put_cmd)." "."\$localstatus_g \$remotestatus_g\n");

    fwrite($fp, "   tag_strlk=\$(cat /dev/urandom | tr -dc 'a-zA-Z' | fold -w 6 | head -n 1)\n");
    fwrite($fp, "    dir=group\$gp\n");
    fwrite($fp, "    mkdir -p \$RUNDIR/strelka/\$dir\n");
    fwrite($fp, "      cat > \$RUNDIR/strelka/\$dir/strelka.sh <<EOF\n");
    fwrite($fp, "#!/bin/bash\n");

    if (isset($_POST['strlk_apply_dbsnp_filter'])) {
      write_vs_preamble($fp, $toolsinfo_h);               // borrow java
    } else {
      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
    }

    fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
    fwrite($fp, "STRELKA_DIR=".$toolsinfo_h[$_POST['strlk_version']]['path']."\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "STRELKA_REF=\\\$RUNDIR/reference/$STRELKA_REF\n");
    fwrite($fp, "statfile=incomplete.strelka.group\$gp\n");
    fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
    fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
    fwrite($fp, "touch \\\$localstatus\n");
    fwrite($fp, "\$put_cmd \\\$localstatus \\\$remotestatus\n");
    fwrite($fp, "SG_DIR=\\\$RUNDIR/strelka/group\$gp\n");
    fwrite($fp, "cd \\\$SG_DIR\n");
    fwrite($fp, "TBAM=\\`awk '{if(NR==1){print \\$0}}' \\\$SG_DIR/bamfilelist.inp\\`\n"); // (tumor,normal) order
    fwrite($fp, "NBAM=\\`awk '{if(NR==2){print \\$0}}' \\\$SG_DIR/bamfilelist.inp\\`\n");
    fwrite($fp, "\\\$STRELKA_DIR/".$toolsinfo_h[$_POST['strlk_version']]['exe']." "."--normal \\\$NBAM --tumor \\\$TBAM --ref \\\$STRELKA_REF --config \\\$RUNDIR/strelka/strelka.ini --output-dir \\\$SG_DIR/strelka_out\n"); 
    fwrite($fp, "cd \\\$SG_DIR/strelka_out\n");
    fwrite($fp, "make -j 4\n");
    fwrite($fp, "cd \\\$SG_DIR/strelka_out/results\n");

    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Strelka ./all.somatic.snvs.vcf   ./strelka.somatic.snv.group\$gp.gvip.vcf\n");
    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Strelka ./all.somatic.indels.vcf ./strelka.somatic.indel.group\$gp.gvip.vcf\n");

    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Strelka ./passed.somatic.snvs.vcf   ./strelka.somatic.snv.group\$gp.passed.gvip.vcf\n");
    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Strelka ./passed.somatic.indels.vcf ./strelka.somatic.indel.group\$gp.passed.gvip.vcf\n");

    fwrite($fp, "\$del_local ./{all,passed}.somatic.{snv,indel}s.vcf\n");

    // TODO
    //    fwrite($fp, "tgt=\\\$RESULTSDIR/pindel/group\$gp\n");
    //    if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
    //    fwrite($fp, "\$put_cmd \`pwd\`/{pindel.log,stdout.pindel,stderr.pindel}.group\$gp.chr\$chralt   \\\$tgt/\n");
    //    fwrite($fp, "\$put_cmd \`pwd\`/pindel.out.group\$gp.chr\${chralt}_*  \\\$tgt/\n");
    //    fwrite($fp, "\$del_cmd \\\$remotestatus\n");
    //    fwrite($fp, "\$del_local \\\$localstatus\n");

    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_STRELKA_DISCOVERY=\\\$scr_t0,\\\$scr_dt\n");
      //
      fwrite($fp, "scr_t0=\\\$scr_tf\n");
    }


    // Run dbSNP filter
    $strlk_dbsnp_filter_prefix['snv']['pass']   = "";
    $strlk_dbsnp_filter_prefix['snv']['fail']   = "";
    $strlk_dbsnp_filter_prefix['indel']['pass'] = "";
    $strlk_dbsnp_filter_prefix['indel']['fail'] = "";
    if (isset($_POST['strlk_apply_dbsnp_filter'])) {
      foreach( array('snv','indel') as $vartype) {
	$strlk_dbsnp_filter_prefix[$vartype]['pass'] = "dbsnp_pass.";
	$strlk_dbsnp_filter_prefix[$vartype]['fail'] = "dbsnp_present.";
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/dbsnp_filter.pl  \\\$RUNDIR/strelka/group\$gp/strelka_dbsnp_filter.$vartype.input\n");
      }
    }
    

    // Run false-positives filter (only for snvs at this time)
    $strlk_fpfilter_prefix['snv']['pass']   = "";
    $strlk_fpfilter_prefix['snv']['fail']   = "";
    $strlk_fpfilter_prefix['indel']['pass'] = "";
    $strlk_fpfilter_prefix['indel']['fail'] = "";
    if (isset($_POST['strlk_apply_false_positives_filter'])) {
      $vartype="snv";
      $strlk_fpfilter_prefix[$vartype]['pass'] = "fp_pass.";
      $strlk_fpfilter_prefix[$vartype]['fail'] = "fp_fail.";
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/snv_filter.pl  \\\$RUNDIR/strelka/group\$gp/strelka_fpfilter.$vartype.input\n");
      if (isset($_POST['strlk_apply_dbsnp_filter'])) {
	fwrite($fp, "\$del_local ./strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
      }
    }

    // TODO
    //      fwrite($fp, "cd \\\$RUNDIR/varscan/group\$gp\n"); 
    //fwrite($fp, "\$del_cmd  \\\$remotestatus\n");
    //fwrite($fp, "\$del_local \\\$localstatus\n");
    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_STRELKA_FILTERING=\\\$scr_t0,\\\$scr_dt\n");
    }
    if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
    fwrite($fp, "EOF\n");



    // Generate input files
    foreach (array('snv','indel') as $vartype) {

      if (isset($_POST['strlk_apply_dbsnp_filter'])) {
	    $prefix="strelka.dbsnp.$vartype";
	    fwrite($fp, "cat > \$RUNDIR/strelka/group\$gp/strelka_dbsnp_filter.$vartype.input <<EOF\n");  
	    fwrite($fp, "$prefix.annotator = ".$toolsinfo_h['snpsift']['path']."/".$toolsinfo_h['snpsift']['exe']."\n");
	    fwrite($fp, "$prefix.db = ".$toolsinfo_h[$_POST['dbsnp_version']]['path']."/".$toolsinfo_h[$_POST['dbsnp_version']]['file']."\n");     
	    fwrite($fp, "$prefix.rawvcf = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.vcf\n");
	    fwrite($fp, "$prefix.passfile = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	    fwrite($fp, "$prefix.dbsnpfile = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['fail']."vcf\n");
	    fwrite($fp, "EOF\n");
	  }
	  
	  if (isset($_POST['strlk_apply_false_positives_filter'])) {
	    if ( $vartype=="snv" ) {  //  only for snvs at this time
	      $prefix="strelka.fpfilter.$vartype";
	      fwrite($fp, "FP_BAM=`awk '{if(NR==1){print \$1}}' \$RUNDIR/strelka/group\$gp/bamfilelist.inp`\n");
	      fwrite($fp, "cat > \$RUNDIR/strelka/group\$gp/strelka_fpfilter.$vartype.input <<EOF\n");
	      fwrite($fp, "$prefix.bam_readcount = ".$toolsinfo_h['readcount']['path']."/".$toolsinfo_h['readcount']['exe']."\n");
	      fwrite($fp, "$prefix.bam_file = \$FP_BAM\n");
	      fwrite($fp, "$prefix.REF = \$RUNDIR/reference/\$STRELKA_REF\n");
	      fwrite($fp, "$prefix.variants_file = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass']."vcf\n");
	      fwrite($fp, "$prefix.passfile = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass'].$strlk_fpfilter_prefix[$vartype]['pass']."vcf\n");
	      fwrite($fp, "$prefix.failfile = \$RUNDIR/strelka/group\$gp/strelka_out/results/strelka.somatic.$vartype.group\$gp.passed.gvip.".$strlk_dbsnp_filter_prefix[$vartype]['pass'].$strlk_fpfilter_prefix[$vartype]['fail']."vcf\n");
	      foreach ($vs_opts_fpfilter as $value) { $key = "vs_fp_".$value; fwrite($fp, "$prefix.$value = ".$_POST[$key]."\n"); }
	      fwrite($fp, "EOF\n");
	    }
	  }
    }


    fwrite($fp, "cd \$RUNDIR/strelka/\$dir ; chmod +x ./strelka.sh\n");
    // configure memory
    $mem_opt = gen_mem_str($compute_target, $toolmem_h['strelka']['mem_default']);
 
    $ncpu     = $batch['nproc']." 4";
    // TODO: Recheck to enable for SGE
    if ($compute_target == "AWS") { $ncpu = "" ; }

    $jobdeps="";
    $job_name = $batch['name_opt']." "."\$tag_strlk.strelka.group\$gp";
    $ERRARG = "-e ./stderr.strelka.group\$gp"; 
    $OUTARG = "-o ./stdout.strelka.group\$gp";
    $EXEARG = "./strelka.sh";
    fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$ncpu." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
    fwrite($fp,"sleep $dlay\n");
    fwrite($fp,"\n");
    





    // Gather results and annotate
      fwrite($fp, " cat > \$RUNDIR/strelka/group\$gp/strelka_postrun.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");
      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "VCFTOOLSDIR=".preg_replace('/\/bin$/', "", $toolsinfo_h['vcftools']['path'])."\n");
      fwrite($fp, "export PERL5LIB=\\\$VCFTOOLSDIR/lib/perl5/site_perl:\\\$PERL5LIB\n");
      //TODO:  if AWS
      //      fwrite($fp, "S3CMD=$s3cmd\n");
      fwrite($fp, "put_cmd=$put_cmd\n");
      fwrite($fp, "del_cmd=$del_cmd\n");
      fwrite($fp, "del_local=$del_local\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/strelka/group\$gp\n");
      fwrite($fp, "statfile_gl_g=incomplete.strlk_postrun.group\$gp\n");
      fwrite($fp, "localstatus_gl_g=\\\$RUNDIR/status/\\\$statfile_gl_g\n");
      fwrite($fp, "remotestatus_gl_g=\\\$RESULTSDIR/\\\$statfile_gl_g\n");
      fwrite($fp, "cd \\\$RUNDIR/strelka/group\$gp\n");
      fwrite($fp, "outlist=strelka.out.somatic_all.group\$gp.filelist\n");
      write_strlk_merge($fp, $strlk_dbsnp_filter_prefix, $strlk_fpfilter_prefix);

      if (isset($_POST['vep_cmd'])) {
	fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/vep_annotator.pl ./strelka_vep.input >& ./strelka_vep.log\n");
	fwrite($fp, "\$del_local ./strelka.out.somatic_all.group\$gp.current_final.gvip.vcf\n");
      }
      // TODO
      //      fwrite($fp, "\$del_cmd  \\\$remotestatus_gl_g\n");
      //      fwrite($fp, "\$del_local \\\$localstatus_gl_g\n");

      if($do_timing) {
        fwrite($fp, "scr_tf=\`date +%s\`\n");
        fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
        fwrite($fp, "echo GVIP_TIMING_STRELKA_GATHER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");

      // Generate VEP input
      if (isset($_POST['vep_cmd'])) {
	$prefix="strelka.vep";
	fwrite($fp, "cat > \$RUNDIR/strelka/group\$gp/strelka_vep.input <<EOF\n");
	fwrite($fp, "$prefix.vcf = ./strelka.out.somatic_all.group\$gp.current_final.gvip.vcf\n");
	fwrite($fp, "$prefix.output = ./strelka.out.somatic_all.group\$gp.current_final.gvip.VEP.vcf\n");
	write_vep_input_common($fp, $prefix);
	fwrite($fp, "EOF\n");
      }

      fwrite($fp, "cd \$RUNDIR/strelka/group\$gp ;  chmod +x ./strelka_postrun.sh\n");
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['gather']['mem_default']);
      // TODO
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\$tag_strlk.strelka.group\$gp".$batch['dep_opt_post'];
      $job_name = $batch['name_opt']." "."strelka_postrun.group\$gp";
      $ERRARG = "-e ./stderr.strelka.group\$gp.postrun";
      $OUTARG = "-o ./stdout.strelka.group\$gp.postrun";
      $EXEARG = "./strelka_postrun.sh";
      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "\n");


      fwrite($fp,"# done group\n"); 
      fwrite($fp, "done\n");  // group



    fwrite($fp, "\n");



  } // is strlk cmd


  // ---------------------------------------------------------------------------
  // RUN BREAKDANCER
  // --------------------------------------------------------------------------------


  if (isset($_POST['bd_cmd'])) {
    fwrite($fp, "#------------------------------\n");
    $BREAKDANCER_DIR1=$toolsinfo_h[$_POST['bd_version']."_1"]['path'];
    $BREAKDANCER_DIR2=$toolsinfo_h[$_POST['bd_version']."_2"]['path'];
    fwrite($fp, "BREAKDANCER_DIR1=$BREAKDANCER_DIR1\n");
    fwrite($fp, "BREAKDANCER_DIR2=$BREAKDANCER_DIR2\n");
    fwrite($fp, "\n");
    fwrite($fp, "cd \$RUNDIR/breakdancer\n");
    $BDEXE_1=$toolsinfo_h[$_POST['bd_version'].'_1']['exe'];
    $BDEXE_2=$toolsinfo_h[$_POST['bd_version'].'_2']['exe'];
   
    // Set up bam2cfg


    $working_cmd_cfg="";
    foreach ($bd_bamcfg_opts as $tmpkey => $value) { 
      $key = "bd_bamcfg_$tmpkey";
      switch($tmpkey) {
      case "system_type":
	if ($_POST[$key]=="solid") { $working_cmd_cfg .= " ".$value." "; }
	break;
      case "use_mapping_qual":
      case "output_mapping_flag_distn":
      case "create_insert_size_histo":
	if ($_POST[$key]=="true")  { $working_cmd_cfg .= " ".$value." "; } 
        break;

    default:
      $working_cmd_cfg .= " ".$value." ".$_POST[$key]." "; 
      }
    }


    // Set up breakdancer-max
    $working_cmd_bd = "";
    foreach ($bd_opts_2 as $tmpkey => $value) { 
      $key = "bd_$tmpkey";
      switch ($tmpkey) {
      case "count_support_mode": //nop
	// if ( $_POST[$key] == "library" ) { $working_cmd_bd .= " ".$value." "; }
        break;
      case "analyze_long_insert": //nop
      case "print_allele_freq_column": //nop
	// if ( $_POST[$key] == "true" ) { $working_cmd_bd .= " ".$value." "; }
	break;
      case "translocation_calltype":
      case "fastq_outfile_prefix_of_suppt_reads":
      case "dump_SVs_and_supporting_reads":      
	break;
      default:
	$working_cmd_bd .= " ".$value." ".$_POST[$key]." ";  
      }
    }



    // Setup dirs and samples
    switch ($_POST['bd_call_mode']) {

    case "germline":
      	write_sample_tuples($fp, $list_of_sorted_bams, "breakdancer", 1);
	break;
    case "pooled":
      	write_sample_tuples($fp, $list_of_sorted_bams, "breakdancer", 0);
	break;
    case "somatic":
      	write_sample_tuples($fp, $list_of_sorted_bams, "breakdancer", 2);
	break;
    case "trio":
      	write_sample_tuples($fp, $list_of_sorted_bams, "breakdancer", 3);
	break;
    default:
      ;
    }

    fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");
    $tag_bd    = generateRandomString($randlen);
    fwrite($fp, "   cd \$RUNDIR/breakdancer/group\$gp\n");

    // Step: avoid library name conflicts
    fwrite($fp, "   cat > ./bd_prepare.group\$gp.sh <<EOF\n");
    fwrite($fp, "#!/bin/bash\n");
    check_aws_shell($fp);
    if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }                                         
    fwrite($fp, "SAMTOOLS_DIR=".$toolsinfo_h['samtools']['path']."\n");
    fwrite($fp, "export PATH=\\\${SAMTOOLS_DIR}:\\\${PATH}\n");
    fwrite($fp, "BREAKDANCER_DIR1=\$BREAKDANCER_DIR1\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "statfile_p=incomplete.breakdancer.prepare.group\$gp\n");
    fwrite($fp, "localstatus_p=\\\$RUNDIR/status/\\\$statfile_p\n");
    fwrite($fp, "remotestatus_p=\\\$RESULTSDIR/\\\$statfile_p\n");
    fwrite($fp, "touch \\\$localstatus_p\n");
    fwrite($fp, "\$put_cmd \\\$localstatus_p  \\\$remotestatus_p\n");
    fwrite($fp, "cd \\\$RUNDIR/breakdancer/group\$gp\n");

    // Collect all itx results. Excluding ctx for now since some tools may not use it (i.e., pindel)
    if ($_POST['bd_translocation_calltype'] != "ctx") {
    fwrite($fp, "statfile_g=incomplete.breakdancer.gather.group\$gp\n");
    fwrite($fp, "localstatus_g=\\\$RUNDIR/status/\\\$statfile_g\n");
    fwrite($fp, "remotestatus_g=\\\$RESULTSDIR/\\\$statfile_g\n");
    fwrite($fp, "touch \\\$localstatus_g\n");
    fwrite($fp, "\$put_cmd \\\$localstatus_g  \\\$remotestatus_g\n");
    }
    fwrite($fp, "export idx=0\n");
    fwrite($fp, "while read sample ; do\n");
    fwrite($fp, "   \\\$BREAKDANCER_DIR1/$BDEXE_1 $working_cmd_cfg  \\\$sample > ./breakdancer.group\$gp.sample\\\$idx.tmpcfg  2> ./breakdancer.group\$gp.sample\\\$idx.log\n");
    fwrite($fp, "   perl -i -lane '\\\$F[4].=\".sample\\\$ENV{idx}\";print join(\"\\t\",@F)' ./breakdancer.group\$gp.sample\\\$idx.tmpcfg\n");
    fwrite($fp, "   idx=\\\$((idx + 1))\n");
    fwrite($fp, "done < \\\$RUNDIR/breakdancer/group\$gp/bamfilelist.inp\n");
    fwrite($fp, "cat ./breakdancer.group\$gp.sample*.tmpcfg > ./breakdancer.group\$gp.cfg\n");

    fwrite($fp, "tgt=\\\$RESULTSDIR/breakdancer/group\$gp\n");
    if ($compute_target != "AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
    fwrite($fp, "\$put_cmd  \`pwd\`/breakdancer.group\$gp.cfg \\\$tgt/\n");
    fwrite($fp, "\$put_cmd  \`pwd\`/bamfilelist.inp   \\\$tgt/bamfilelist.group\$gp.inp\n");
    fwrite($fp, "\$del_cmd \\\$remotestatus_p\n");
    fwrite($fp, "\$del_local \\\$localstatus_p\n");
    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_BREAKDANCER_PREPARE=\\\$scr_t0,\\\$scr_dt\n");
    }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
    fwrite($fp, "EOF\n");
    fwrite($fp, "   cd \$RUNDIR/breakdancer/group\$gp ; chmod +x ./bd_prepare.group\$gp.sh\n");

    $mem_opt = gen_mem_str($compute_target, $toolmem_h['bd_prepare']['mem_default']);

    $job_name = $batch['name_opt']." "."$tag_bd.bdcfg.group\$gp";
    $ERRARG = "-e ./stderr.bd_prepare.group\$gp";
    $OUTARG = "-o ./stdout.bd_prepare.group\$gp";
      $EXEARG = "./bd_prepare.group\$gp.sh";

    fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." ".$job_name." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
    fwrite($fp, "\n");


    // Step: CTX
    if ($_POST['bd_translocation_calltype'] != "itx") {
      $working_ctx_cmd = $working_cmd_bd . " -t ";

      if ( $_POST['bd_dump_SVs_and_supporting_reads']=="true" && $_POST['bd_dump_SVs_GBrowse_string']!="" ) { 
	// NOP
	// $working_ctx_cmd .= " -g ".$_POST['bd_dump_SVs_GBrowse_string']." ";
      }

      if (trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']) != "") {
	$working_ctx_cmd .= " -d ".trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads'])." ";
      }

      fwrite($fp, "   dir=CTX\n");
      fwrite($fp, "   mkdir -p \$dir ; cd \$dir\n");
      fwrite($fp, "   cat > ./bd_ctx.group\$gp.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");

      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }

      fwrite($fp, "BREAKDANCER_DIR2=\$BREAKDANCER_DIR2\n");
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "cd \\\$RUNDIR/breakdancer/group\$gp/\$dir\n");
      fwrite($fp, "statfile_ctx=incomplete.breakdancer.group\$gp.ctx\n");
      fwrite($fp, "localstatus_ctx=\\\$RUNDIR/status/\\\$statfile_ctx\n");
      fwrite($fp, "remotestatus_ctx=\\\$RESULTSDIR/\\\$statfile_ctx\n");
      fwrite($fp, "touch \\\$localstatus_ctx\n");
      fwrite($fp, "\$put_cmd \\\$localstatus_ctx \\\$remotestatus_ctx\n");

      fwrite($fp, "\\\$BREAKDANCER_DIR2/$BDEXE_2  $working_ctx_cmd  \\\$RUNDIR/breakdancer/group\$gp/breakdancer.group\$gp.cfg > ./breakdancer.group\$gp.ctx\n");

      fwrite($fp, "tgt=\\\$RESULTSDIR/breakdancer/group\$gp\n");
      if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
      fwrite($fp, "\$put_cmd \`pwd\`/breakdancer.group\$gp.ctx \\\$tgt/\n");

      if (trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']) != "") {
	fwrite($fp, "for j in \`ls ".trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']).".*.fastq\` ; do\n");
	fwrite($fp, "   \$put_cmd  \`pwd\`/\\\$j \\\$tgt/\\\${j/%fastq/ctx.fastq}\n");
	fwrite($fp, "done\n");
      }

      fwrite($fp, "\$del_cmd  \\\$remotestatus\n");
      fwrite($fp, "\$del_local \\\$localstatus\n");
      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n");
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_BREAKDANCER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");
      fwrite($fp, "   chmod +x ./bd_ctx.group\$gp.sh\n");


      $mem_opt = gen_mem_str($compute_target, $toolmem_h['breakdancer']['mem_ctx_min']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."$tag_bd.bdcfg.group\$gp".$batch['dep_opt_post'];

      $job_name = $batch['name_opt']." "."$tag_bd.bdtx.group\$gp.ctx";
      $ERRARG = "-e ./stderr.bd_ctx.group\$gp";
      $OUTARG = "-o ./stdout.bd_ctx.group\$gp";
      $EXEARG = "./bd_ctx.group\$gp.sh";

      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");

      if ($_POST['bd_translocation_calltype'] == "ctx") { fwrite($fp, "done\n"); }
      fwrite($fp, "\n");
    }  // ctx

    // Step: ITX
    if ($_POST['bd_translocation_calltype'] != "ctx") {

      // Chromosome
      write_chromosomes($fp,$_POST['bd_chrdef'], "BREAKDANCER_REF_fai", $_POST['bd_chrdef_str'] );


      $working_itx_cmd = $working_cmd_bd;
      
      if (trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']) != "") {
	$working_itx_cmd .= " -d ".trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads'])." ";
      }
      fwrite($fp, "   for chr in \$SEQS; do\n");
      fwrite($fp, "      chralt=\${chr/:/_}\n");
      fwrite($fp, "      cd \$RUNDIR/breakdancer/group\$gp\n");
      fwrite($fp, "      dir=ITX/\$chralt\n");
      fwrite($fp, "      mkdir -p \$dir\n");
      fwrite($fp, "      cat > \$dir/bd_itx.group\$gp.chr\$chralt.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");

      check_aws_shell($fp);
      fwrite($fp, "scr_t0=\`date +%s\`\n");


      fwrite($fp, "BREAKDANCER_DIR2=\$BREAKDANCER_DIR2\n");
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "cd \\\$RUNDIR/breakdancer/group\$gp/\$dir\n");

      fwrite($fp, "statfile_itx=incomplete.breakdancer.group\$gp.chr\$chralt.itx\n");
      fwrite($fp, "localstatus_itx=\\\$RUNDIR/status/\\\$statfile_itx\n");
      fwrite($fp, "remotestatus_itx=\\\$RESULTSDIR/\\\$statfile_itx\n");
      fwrite($fp, "touch \\\$localstatus_itx\n");
      fwrite($fp, "\$put_cmd \\\$localstatus_itx \\\$remotestatus_itx\n");

      fwrite($fp, "\\\$BREAKDANCER_DIR2/$BDEXE_2 -o \$chr  $working_itx_cmd  \\\$RUNDIR/breakdancer/group\$gp/breakdancer.group\$gp.cfg > ./breakdancer.group\$gp.chr\$chralt.itx\n"); 

      //      fwrite($fp, "mkdir -p \\\$RESULTSDIR/breakdancer/group\$gp ; cp -a ./breakdancer.group\$gp.chr\$chr.itx \\\$RESULTSDIR/breakdancer/group\$gp\n");

      if (trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']) != "") {
	fwrite($fp, "for j in \`ls ".trim($_POST['bd_fastq_outfile_prefix_of_supporting_reads']).".*.fastq\` ; do\n");
	fwrite($fp, "   \$put_cmd \`pwd\`/\\\$j \\\$RESULTSDIR/breakdancer/group\$gp/\\\${j/%fastq/chr\$chralt.itx.fastq}\n");
	fwrite($fp, "done\n");
      }
      fwrite($fp, "\$del_cmd  \\\$remotestatus_itx\n");
      fwrite($fp, "\$del_local \\\$localstatus_itx\n");
      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n");
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_BREAKDANCER=\\\$scr_t0,\\\$scr_dt\n");
      }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");
      fwrite($fp, "      cd \$dir ; chmod +x ./bd_itx.group\$gp.chr\$chralt.sh\n");




      $mem_opt = gen_mem_str($compute_target, $toolmem_h['breakdancer']['mem_itx_min']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."$tag_bd.bdcfg.group\$gp".$batch['dep_opt_post'];

      fwrite($fp, "      chralt=\${chr/:/_}\n");
      $job_name = $batch['name_opt']." "."$tag_bd.bdtx.group\$gp.itx";
      $ERRARG = "-e ./stderr.bd_itx.group\$gp.chr\$chralt";
      $OUTARG = "-o ./stdout.bd_itx.group\$gp.chr\$chralt";
        $EXEARG = "./bd_itx.group\$gp.chr\$chralt.sh";

      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "   done\n");
      fwrite($fp, "\n");

    }  // itx


    // BreakDancer postrun
    fwrite($fp, "   cd \$RUNDIR/breakdancer/group\$gp\n");
    fwrite($fp, "   cat > ./bd_postrun.group\$gp.sh <<EOF\n");
    fwrite($fp, "#!/bin/bash\n");
    check_aws_shell($fp);
    fwrite($fp, "scr_t0=\`date +%s\`\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
    fwrite($fp, "statfile_g=incomplete.breakdancer.postrun.group\$gp\n");
    fwrite($fp, "localstatus_g=\\\$RUNDIR/status/\\\$statfile_g\n");
    fwrite($fp, "remotestatus_g=\\\$RESULTSDIR/\\\$statfile_g\n");
    // Gather
    if ($_POST['bd_translocation_calltype'] != "ctx") { // has ITX
      fwrite($fp, "cd \\\$RUNDIR/breakdancer/group\$gp/ITX\n");
      fwrite($fp, "gather_out=breakdancer.group\$gp.all.itx\n");
      fwrite($fp, "find . -mindepth 2 -type f -size +0c -iname 'breakdancer.group*itx' > ./breakdancer.group\$gp.all.itx.filelist\n");
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/gather_itx.sh  \\\$RUNDIR/breakdancer/group\$gp/ITX  breakdancer.group\$gp.all.itx.filelist \\\$gather_out\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/breakdancer/group\$gp\n");
      if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
      fwrite($fp, "\$put_cmd  \`pwd\`/ITX/\\\$gather_out  \\\$tgt/\n");
      //fwrite($fp, "\$del_cmd \\\$remotestatus_g\n");
      //fwrite($fp, "\$del_local \\\$localstatus_g\n");
    }
    // Filtering 
    if( isset($_POST['bd_apply_bam_filter']) ) { 
      if ($_POST['bd_call_mode'] == "somatic") {
	fwrite($fp, "BAMLIST=\\\$RUNDIR/breakdancer/group\$gp/bamfilelist.inp\n");
	if ($_POST['bd_translocation_calltype'] != "ctx") {   // has ITX
	  fwrite($fp, "VARFILE=\\\$RUNDIR/breakdancer/group\$gp/ITX/\\\$gather_out\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/bam_filter.sh  somatic \\\$BAMLIST \\\$VARFILE\n");
	}
	if ($_POST['bd_translocation_calltype'] != "itx") { // has CTX
	  fwrite($fp, "VARFILE=\\\$RUNDIR/breakdancer/group\$gp/CTX/breakdancer.group\$gp.ctx\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/bam_filter.sh  somatic \\\$BAMLIST \\\$VARFILE\n");
	}
      }
      if ($_POST['bd_call_mode'] == "trio") {
	fwrite($fp, "BAMLIST=\\\$RUNDIR/breakdancer/group\$gp/bamfilelist.inp\n");
	if ($_POST['bd_translocation_calltype'] != "ctx") {   // has ITX
	  fwrite($fp, "VARFILE=\\\$RUNDIR/breakdancer/group\$gp/ITX/\\\$gather_out\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/bam_filter.sh  trio  \\\$BAMLIST \\\$VARFILE\n");
	}
	if ($_POST['bd_translocation_calltype'] != "itx") { // has CTX
	  fwrite($fp, "VARFILE=\\\$RUNDIR/breakdancer/group\$gp/CTX/breakdancer.group\$gp.ctx\n");
	  fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/bam_filter.sh  trio  \\\$BAMLIST \\\$VARFILE\n");
	}
      }
    } 
    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_BREAKDANCER_POSTRUN=\\\$scr_t0,\\\$scr_dt\n");
    }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
    fwrite($fp, "EOF\n");
    fwrite($fp, "   chmod +x ./bd_postrun.group\$gp.sh\n");
    $mem_opt = gen_mem_str($compute_target, $toolmem_h['bd_gather']['mem_default']);

    $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."$tag_bd.bdtx.group\$gp.$wc".$batch['dep_opt_post'];
    $job_name = $batch['name_opt']." "."$tag_bd.bd_postrun.group\$gp";
    $ERRARG = "-e ./stderr.bd_postrun.group\$gp";
    $OUTARG = "-o ./stdout.bd_postrun.group\$gp";
    $EXEARG = "./bd_postrun.group\$gp.sh";
    fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
    fwrite($fp,"sleep $dlay\n");
    fwrite($fp, "\n");


    fwrite($fp, "#done group\n"); 
    fwrite($fp, "done\n"); 
    fwrite($fp, "\n");

    fwrite($fp,"\n");


    
  } // if bd_cmd
  
  // ---------------------------------------------------------------------------
  // RUN PINDEL
  // There are new outputs going from 0.2.4 to 0.2.5
  // --------------------------------------------------------------------------------

  if (isset($_POST['pin_cmd'])) {
    fwrite($fp, "#------------------------------\n");
    $PINDEL_DIR=$toolsinfo_h[$_POST['pin_version']]['path'];
    fwrite($fp, "PINDEL_DIR=$PINDEL_DIR\n");
    $PINDEL_EXE=$toolsinfo_h[$_POST['pin_version']]['exe'];

    $pindel_cmd = "";

    foreach ($pindel_opts as $tmpkey => $value) { 
      $key = "pin_$tmpkey";
      $pindel_cmd .= " ".$value." ".$_POST[$key]; 
    }

    foreach ($pindel_opts_more as $tmpkey => $value) { 
      $key = "pin_$tmpkey";
      switch ($key) {
      case "pin_do_inversions":
      case "pin_do_tandem_dups":
	if (isset($_POST[$key])) { $pindel_cmd .= " ".$value." ";}
        break;
	// case "pin_do_long_insertions":    // currently unsuppported
	// case "pin_do_breakpoints":        // currently unsuppported
	// case "pin_do_mobile_insertions":  // currently unsuppported
	//	break;
      default:
	;
      }
    }

    // Set up labels for output for requested variants
    $pindel_report_arr = array();
    foreach ($pindel_opts_more as $tmpkey => $value) { 
      $key = "pin_$tmpkey";  
      switch ($key) {
      case "pin_do_inversions":
	if (isset($_POST[$key])) { array_push( $pindel_report_arr, "INV"); } ;	break;
      case "pin_do_tandem_dups":
	if (isset($_POST[$key])) { array_push( $pindel_report_arr, "TD"); } ; break;
	// case "pin_do_long_insertions":   // currently unsuppported
	//	if (isset($_POST[$key])) { array_push( $pindel_report_arr, "LI"); } ; break;
      default:
	;
      }
    }
    $pindel_report_conv = "D_SI";
    if (count($pindel_report_arr) > 0) {
      $pindel_report_conv .= "_".implode("_", $pindel_report_arr);
    }


    // Setup dirs and samples
    switch ($_POST['pin_call_mode']) {
    case "germline":
      write_sample_tuples($fp, $list_of_sorted_bams, "pindel", 1);
      break;
    case "pooled":
      write_sample_tuples($fp, $list_of_sorted_bams, "pindel", 0);
      break;
    case "somatic":
      write_sample_tuples($fp, $list_of_sorted_bams, "pindel", 2);
      break;
    case "trio":
      write_sample_tuples($fp, $list_of_sorted_bams, "pindel", 3);
      break;
    default:
      ;
    }

    // Chromosomes
    write_chromosomes($fp,$_POST['pin_chrdef'], "PINDEL_REF_fai", $_POST['pin_chrdef_str'] );

    

    fwrite($fp, "# pindel\n");
    fwrite($fp, "cd \$RUNDIR/pindel\n");
    fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");

    fwrite($fp, "   mkdir -p \$RESULTSDIR/pindel/group\$gp\n");
    fwrite($fp, "   \$put_cmd \$RUNDIR/pindel/group\$gp/pindel.bam.inp  \$RESULTSDIR/pindel/group\$gp/pindel.bam.group\$gp.inp\n");

    fwrite($fp, "   statfile_g=incomplete.pindel_postrun.group\$gp\n");
    fwrite($fp, "   localstatus_g=\$RUNDIR/status/\$statfile_g\n");
    fwrite($fp, "   remotestatus_g=\$RESULTSDIR/\$statfile_g\n");
    fwrite($fp, "   touch \$localstatus_g\n");
    fwrite($fp, "   ".str_replace("\"","",$put_cmd)." "."\$localstatus_g \$remotestatus_g\n");

    fwrite($fp, "   tag_pin=\$(cat /dev/urandom | tr -dc 'a-zA-Z' | fold -w 6 | head -n 1)\n");
    fwrite($fp, "   for chr in \$SEQS; do\n");
    fwrite($fp, "      chralt=\${chr/:/_}\n");
    fwrite($fp, "      dir=group\$gp/\$chralt\n");
    fwrite($fp, "      mkdir -p \$RUNDIR/pindel/\$dir\n");
    fwrite($fp, "      cat > \$RUNDIR/pindel/\$dir/pindel.sh <<EOF\n");
    fwrite($fp, "#!/bin/bash\n");

    check_aws_shell($fp);
    if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }

    fwrite($fp, "PINDEL_DIR=\$PINDEL_DIR\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "PINDEL_REF=\\\$RUNDIR/reference/\$PINDEL_REF\n");
    fwrite($fp, "statfile=incomplete.pindel.group\$gp.chr\$chralt\n");
    fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
    fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
    fwrite($fp, "touch \\\$localstatus\n");
    fwrite($fp, "\$put_cmd \\\$localstatus \\\$remotestatus\n");

    fwrite($fp, "cd \\\$RUNDIR/pindel/\$dir\n"); 


    // unsupported
    //    if ($_POST['pin_logfile_prefix'] != "") {
    //      $logdepfile="./".$_POST['pin_logfile_prefix'].".group\$gp.chr\$chralt";
    //    } else {
      $_POST['pin_logfile_prefix'] = "pindel.log";
      $logdepfile="./pindel.log.group\$gp.chr\$chralt"; // default
      //    }
    $pindel_cmd .= " -L $logdepfile ";


    $bddeps="";
    if ($_POST['pin_include_breakdancer'] == "true") {
      $bddeps=" ".$pindel_opts_more['include_breakdancer']." "."\$RUNDIR/breakdancer/group\$gp/ITX/\$chralt/breakdancer.group\$gp.chr\$chralt.itx"." ";
    }
    $pindel_cmd .= " ".$bddeps." "."-o ./pindel.out.group\$gp.chr\$chralt";

    fwrite($fp, "\\\$PINDEL_DIR/$PINDEL_EXE  ".$pindel_opts_more['pindel_chr']." \$chr $pindel_cmd  -f \\\$PINDEL_REF -i \\\$RUNDIR/pindel/group\$gp/pindel.bam.inp\n");





    fwrite($fp, "tgt=\\\$RESULTSDIR/pindel/group\$gp\n");
    if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
    fwrite($fp, "\$put_cmd \`pwd\`/{pindel.log,stdout.pindel,stderr.pindel}.group\$gp.chr\$chralt   \\\$tgt/\n");
    fwrite($fp, "\$put_cmd \`pwd\`/pindel.out.group\$gp.chr\${chralt}_*  \\\$tgt/\n");

    fwrite($fp, "\$del_cmd \\\$remotestatus\n");
    fwrite($fp, "\$del_local \\\$localstatus\n");

    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_PINDEL=\\\$scr_t0,\\\$scr_dt\n");
    }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
    fwrite($fp, "EOF\n"); 

    fwrite($fp, "      cd \$RUNDIR/pindel/\$dir ; chmod +x ./pindel.sh\n");


    if ($compute_target=="AWS") { 

      $mem_opt = gen_mem_str($compute_target, $toolmem_h['pindel']['mem_min'] );
      $ncpu    = $batch['nproc']." "."1";

      // TODO: Recheck to enable for SGE
      $ncpu = "" ;

    } else {
      //$mem_opt = gen_mem_str($compute_target, $toolmem_h['pindel']['mem_default'] * $_POST['pin_num_threads']);
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['pindel']['mem_default'] );
      $ncpu    = $batch['nproc']." ".$_POST['pin_num_threads'];
    }


    $jobdeps="";
    if (isset($_POST['bd_cmd'])  &&  $_POST['pin_include_breakdancer'] == "true") {

      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."$tag_bd.bdtx.group\$gp.itx.chr\$chralt".$batch['dep_opt_post'];
    }
    fwrite($fp, "chralt=\${chr/:/_}\n");
    $job_name = $batch['name_opt']." "."\$tag_pin.pindel.group\$gp.chr";
    $ERRARG = "-e ./stderr.pindel.group\$gp.chr\$chralt"; 
    $OUTARG = "-o ./stdout.pindel.group\$gp.chr\$chralt";
    $EXEARG = "./pindel.sh";

    fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$ncpu $job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
          fwrite($fp,"sleep $dlay\n");

    fwrite($fp, "   done\n");   // chr




    // Filtering (does not support pooled mode) and/or conversion to VCF using filter framework
    $prefix="pindel.filter";
    fwrite($fp, "cat > \$RUNDIR/pindel/group\$gp/pindel_filter.input <<EOF\n");
    fwrite($fp, "$prefix.pindel2vcf = \$PINDEL_DIR/pindel2vcf\n");
    fwrite($fp, "$prefix.variants_file = \$RUNDIR/pindel/group\$gp/pindel.out.group\$gp.raw\n"); 
    fwrite($fp, "$prefix.REF = \$RUNDIR/reference/\$PINDEL_REF\n");
    fwrite($fp, "$prefix.date = 000000\n");
    // Omitting $prefix.output since it is computed by filter


    $pp = array();
    include realpath(dirname(__FILE__)."/"."write_filter.php");
    foreach($pp as $value) { fwrite($fp, $value); }
    unset($pp);

    fwrite($fp, "EOF\n");



    // Gather group and filter
    fwrite($fp, "cat > \$RUNDIR/pindel/group\$gp/pindel_postrun.sh <<EOF\n");
    fwrite($fp, "#!/bin/bash\n");
    check_aws_shell($fp);
    if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); } 
    fwrite($fp, "PINDEL_DIR=\$PINDEL_DIR\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "PINDEL_REF=\\\$RUNDIR/reference/\$PINDEL_REF\n");
    fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
    fwrite($fp, "statfile=incomplete.pindel.group\$gp.postrun\n");
    fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
    fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
    fwrite($fp, "touch \\\$localstatus\n");
    fwrite($fp, "\$put_cmd \\\$localstatus \\\$remotestatus\n");
    fwrite($fp, "cd \\\$RUNDIR/pindel/group\$gp\n"); 
    fwrite($fp, "find . -name '*_D' -o -name '*_SI' ");
    if (isset($_POST['pin_do_inversions']))  { fwrite($fp, "-o -name '*_INV' "); }
    if (isset($_POST['pin_do_tandem_dups'])) { fwrite($fp, "-o -name '*_TD' ");  }
    fwrite($fp, " > ./pindel.out.group\$gp.filelist\n");
    fwrite($fp, "list=\\\$(xargs -a  ./pindel.out.group\$gp.filelist)\n");
    fwrite($fp, "cat \\\$list | grep ChrID > ./pindel.out.group\$gp.raw\n");
    fwrite($fp, "if [ \`echo \\\$?\` -eq 0 ] ; then /bin/rm -f \\\$list ; fi\n");
    fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/pindel_filter.pl  ./pindel_filter.input\n");
    fwrite($fp, "out=pindel.out.group\$gp.raw.filter1_pass.filter2_all.vcf\n");
    fwrite($fp, "if [ -e ./\\\$out ] ; then\n");
    fwrite($fp, "   \\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Pindel  ./\\\$out   ./pindel.out.group\$gp.all_final.gvip.vcf\n");
    fwrite($fp, "#   rm -f ./\\\$out\n");
    fwrite($fp, "fi\n");
    fwrite($fp, "out=pindel.out.group\$gp.raw.filter1_pass.filter2_pass.vcf\n");
    switch ($_POST['pin_call_mode']) {
    case "germline":
    case "pooled":
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Pindel  ./\\\$out   ./pindel.out.group\$gp.current_final.gvip.vcf\n");
    break;
    case "somatic":
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Pindel  ./\\\$out   ./pindel.out.group\$gp.current_final.gvip.Somatic.vcf\n");
      break;
    case "trio":
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl Pindel  ./\\\$out   ./pindel.out.group\$gp.current_final.gvip.denovo.vcf\n");
      break;
    }
    fwrite($fp, "#rm -f ./\\\$out\n");

    if (isset($_POST['vep_cmd'])) {
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/vep_annotator.pl ./pindel_vep.input >& ./pindel_vep.log\n");
      switch ($_POST['pin_call_mode']) {
      case "germline":
      case "pooled":
	fwrite($fp, "\$del_local ./pindel.out.group\$gp.current_final.gvip.vcf\n");
      break;
      case "somatic":
	fwrite($fp, "\$del_local ./pindel.out.group\$gp.current_final.gvip.Somatic.vcf\n");
	break;
      case "trio":
	fwrite($fp, "\$del_local ./pindel.out.group\$gp.current_final.gvip.denovo.vcf\n");
	break;
      }
    }


    // TODO
    //    fwrite($fp, "tgt=\\\$RESULTSDIR/pindel/group\$gp\n");
    //    if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }
    //    fwrite($fp, "\$put_cmd \`pwd\`/{pindel.log,stdout.pindel,stderr.pindel}.group\$gp.chr\$chralt   \\\$tgt/\n");
    //    fwrite($fp, "\$put_cmd \`pwd\`/pindel.out.group\$gp.chr\${chralt}_*  \\\$tgt/\n");
    fwrite($fp, "\$del_cmd \\\$remotestatus\n");
    fwrite($fp, "\$del_local \\\$localstatus\n");
    if($do_timing) {
      fwrite($fp, "scr_tf=\`date +%s\`\n");
      fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
      fwrite($fp, "echo GVIP_TIMING_PINDEL_POSTRUN=\\\$scr_t0,\\\$scr_dt\n");
    }
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
    fwrite($fp,"EOF\n");


    // Generate VEP input
    if (isset($_POST['vep_cmd'])) {
      $prefix="pindel.vep";
      fwrite($fp, "cat > \$RUNDIR/pindel/group\$gp/pindel_vep.input <<EOF\n");
      switch ($_POST['pin_call_mode']) {
      case "germline":
      case "pooled":
	fwrite($fp, "$prefix.vcf = ./pindel.out.group\$gp.current_final.gvip.vcf\n");
        fwrite($fp, "$prefix.output = ./pindel.out.group\$gp.current_final.gvip.VEP.vcf\n");
	break;
      case "somatic":
	fwrite($fp, "$prefix.vcf = ./pindel.out.group\$gp.current_final.gvip.Somatic.vcf\n");
	fwrite($fp, "$prefix.output = ./pindel.out.group\$gp.current_final.gvip.Somatic.VEP.vcf\n");
	break;
      case "trio":
	fwrite($fp, "$prefix.vcf = ./pindel.out.group\$gp.current_final.gvip.denovo.vcf\n");
	fwrite($fp, "$prefix.output = ./pindel.out.group\$gp.current_final.gvip.denovo.VEP.vcf\n");
	break;
      }
      write_vep_input_common($fp, $prefix);
      fwrite($fp, "EOF\n");
    }
    

    fwrite($fp, "cd \$RUNDIR/pindel/group\$gp ; chmod +x ./pindel_postrun.sh\n");
    $tmp_mem = $toolmem_h['pindel']['mem_p2v'];

    $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\$tag_pin.pindel.group\$gp.$wc".$batch['dep_opt_post'];

    $job_name = $batch['name_opt']." "."pindel_postrun.group\$gp";
    $ERRARG = "-e ./stderr.pindel.group\$gp.postrun"; 
    $OUTARG = "-o ./stdout.pindel.group\$gp.postrun";
    $EXEARG = "./pindel_postrun.sh";
    fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
    fwrite($fp,"sleep $dlay\n");
    fwrite($fp, "\n");
    

    fwrite($fp,"# done group\n"); 
    fwrite($fp, "done\n");  // group
    fwrite($fp, "\n");

        
  } // if pindel_cmd



  // ---------------------------------------------------------------------------
  // RUN GENOMESTRIP
  // --------------------------------------------------------------------------------
  if (isset($_POST['gs_cmd'])) {
    fwrite($fp, "#------------------------------\n");
    $GENOMESTRIP_DIR=$toolsinfo_h[$_POST['version_gs']]['path'];


    // Checkboxes
    $gs_conf_list = array ("gs_genotyping_depth" => "depth",
			   "gs_genotyping_pairs" => "pairs",
			   "gs_genotyping_split" => "split",
			   );

    // set up dirs and samples
    if ($_POST['gs_samples'] == "single") { // individual
	write_sample_tuples($fp, $list_of_sorted_bams, "genomestrip", 1);

    } else {                              // pooled
	write_sample_tuples($fp, $list_of_sorted_bams, "genomestrip", 0);
    }

  
    // Chromosome 
      write_chromosomes($fp,$_POST['gs_chrdef'], "GENOMESTRIP_REF_fai", $_POST['gs_chrdef_str'] );



    $tag_gs = generateRandomString($randlen);
    fwrite($fp, "# genomestrip\n");
    fwrite($fp, "cd \$RUNDIR/genomestrip\n");
    
    generate_gs_config($fp);

    fwrite($fp, "for gp in `seq 0 \$((numgps - 1))`; do\n");

    // TODO: generalize from  precomputed metadata
    $metadatafile="metadata.22_36.13-36.70M.tar.gz";
    fwrite($fp, "       metadatafile=$metadatafile\n");
    if ($_POST['compute_target'] != "AWS") { 
      fwrite($fp, "      ( cd  \$RUNDIR/genomestrip/group\$gp\n");
      fwrite($fp, "        tar zxf  /gscmnt/gc2525/dinglab/rmashl/\$metadatafile  ) \n");
    } else {
      fwrite($fp, "      ( cd  \$RUNDIR/genomestrip/group\$gp\n");
      fwrite($fp, "        if [[ ! -e \$metadatafile ]] ; then \n");
      fwrite($fp, "            \$get_cmd s3://washu_ashg_demo/genomestrip_support/\$metadatafile\n");
      fwrite($fp, "            tar zxf  \$metadatafile \n");
      fwrite($fp, "        fi )\n");
    }


    fwrite($fp, "   for chr in \$SEQS; do\n");
    fwrite($fp, "      chralt=\${chr/:/_}\n");
    fwrite($fp, "      dir=group\$gp/\$chralt\n");
    fwrite($fp, "      mkdir -p \$RUNDIR/genomestrip/\$dir\n");


    fwrite($fp, "      cat > \$RUNDIR/genomestrip/\$dir/genomestrip.sh <<EOF\n");
    write_vs_preamble($fp, $toolsinfo_h);  // samtools, java path stuff
    if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }
    fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
    fwrite($fp, "RUNDIR=\$RUNDIR\n");
    fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
    fwrite($fp, "GS_REF=\\\$RUNDIR/reference/\$GENOMESTRIP_REF\n");
    fwrite($fp, "GS_SV_MASK=\\\$RUNDIR/reference/\$GENOMESTRIP_SV_MASK\n");
    fwrite($fp, "GS_GENDER_MAP=\\\$RUNDIR/reference/\$GENOMESTRIP_GENDER_MAP\n");
    fwrite($fp, "GS_PLOIDY_MAP=\\\$RUNDIR/reference/\$GENOMESTRIP_PLOIDY_MAP\n");
    if ($_POST['gs_depth_useGCNormalization'] == "true") {
    fwrite($fp, "GS_CN_MASK=\\\$RUNDIR/reference/\$GENOMESTRIP_CN_MASK\n");
    }

    //    fwrite($fp, "statfile=incomplete.genomestrip.group\$gp.chr\$chralt\n");
    //    fwrite($fp, "localstatus=\\\$RUNDIR/status/\\\$statfile\n");
    //    fwrite($fp, "remotestatus=\\\$RESULTSDIR/\\\$statfile\n");
    //    fwrite($fp, "touch \\\$localstatus\n");
    //    fwrite($fp, "\$put_cmd \\\$localstatus \\\$remotestatus\n");
    fwrite($fp, "cd \\\$RUNDIR/genomestrip/\$dir\n"); 
    fwrite($fp, "export SV_DIR=$GENOMESTRIP_DIR\n");



    fwrite($fp, "export PATH=\\\${SV_DIR}/bwa:\\\${PATH}\n");
    fwrite($fp, "export LD_LIBRARY_PATH=\\\${SV_DIR}/bwa:\\\${LD_LIBRARY_PATH}\n");
    fwrite($fp, "export SV_CLASSPATH=\\\${SV_DIR}/lib/SVToolkit.jar:\\\${SV_DIR}/lib/gatk/GenomeAnalysisTK.jar:\\\${SV_DIR}/lib/gatk/Queue.jar\n");


    fwrite($fp, "export SV_CONF=\\\${RUNDIR}/genomestrip/genomestrip.input\n");


    fwrite($fp, "mkdir -p ./tmpdir ./logs \n");

    // Common vars
    fwrite($fp, "JAVA_OPTS_2=".$toolsinfo_h[$_POST['version_gs']]['opts']."\n");
    fwrite($fp, "GS_COMMON_1=\"java -cp \\\${SV_CLASSPATH} \\\$JAVA_OPTS_2  org.broadinstitute.sting.queue.QCommandLine\"\n");
    fwrite($fp, "GS_COMMON_2=\"-S \\\${SV_DIR}/qscript/SVQScript.q  -gatk \\\${SV_DIR}/lib/gatk/GenomeAnalysisTK.jar  --disableJobReport  -cp \\\${SV_CLASSPATH}  -configFile \\\${SV_CONF}\"\n");
    fwrite($fp, "GS_COMMON_3=\"-tempDir ./tmpdir  -R \\\$GS_REF  -genomeMaskFile \\\$GS_SV_MASK  -genderMapFile \\\$GS_GENDER_MAP\"\n");
    fwrite($fp, "GS_COMMON_4=\"-runDirectory .  -md \$RUNDIR/genomestrip/group\$gp/metadata  -disableGATKTraversal   -jobLogDir ./logs\"\n");


    // Pre-processing
    // ploidymap is required. Seems we cannot have, e.g., X in it if ref does not have it (bug?)
    fwrite($fp, "# pre-processing\n");

    // demo mode: SKIP
    //    fwrite($fp, "# pre-processing skipped due to demo mode\n");
    
    $GS_PP_CMD="\\\$GS_COMMON_1 -S \\\${SV_DIR}/qscript/SVPreprocess.q  \\\$GS_COMMON_2 \\\$GS_COMMON_3 \\\$GS_COMMON_4 -I \\\$RUNDIR/genomestrip/group\$gp/bamfilelist.inp -computeSizesInterval \$chr  -run";
    // Option -computeSizesInterval is correct for tool version 1441. Bob says in 1443, option has changed to -computeMetadataOverInterval.


    if ($_POST['gs_depth_useGCNormalization'] == "true") {
      // per the code, GCprofiles needs cn mask
      $GS_PP_CMD .= " "."-computeGCProfiles -copyNumberMaskFile \\\$GS_CN_MASK";
    }
    // ploidymap workaround for sex chr

    // demo mode: SKIP
    fwrite($fp, "# pre-processing step replaced by pre-computed metadata\n");
    //    fwrite($fp, "if [[ \"\${chr:0:1}\" -eq \"X\" || \"\${chr:0:1}\" -eq \"Y\" ]] ; then\n");
    //    fwrite($fp, "   $GS_PP_CMD -ploidyMapFile \\\$GS_PLOIDY_MAP\n");
    //    fwrite($fp, "else\n");
    //    fwrite($fp, "   $GS_PP_CMD -ploidyMapFile \\\$GS_PLOIDY_MAP.autosome\n");
    //    fwrite($fp, "fi\n");



    // Discovery
    fwrite($fp, "# discovery\n");
    $GS_DISC_CMD = "\\\$GS_COMMON_1  -S \\\${SV_DIR}/qscript/SVDiscovery.q \\\$GS_COMMON_2 \\\$GS_COMMON_3";
    switch ($_POST['gs_sizerange']) {     // User-specified variant size range
    case "default":  // 100 - 100k
      $GS_DISC_CMD .= " "."-minimumSize    100 -maximumSize   100000 -windowSize  3000000 -windowPadding 10000";
      break;
    case "large": // 100k - 10M 
      $GS_DISC_CMD .= " "."-minimumSize 100001 -maximumSize 10000000 -windowSize 30000000 -windowPadding 10000";
    default:
      ;
    }
    $GS_DISC_CMD .= " "."\\\$GS_COMMON_4  -I \\\$RUNDIR/genomestrip/group\$gp/bamfilelist.inp -L \$chr -suppressVCFCommandLines  -O discovery.vcf  -run";
    fwrite($fp, "$GS_DISC_CMD\n");

    
    // Genotyping
    // It appears all info in discovery vcf is also present in genotype vcf
    fwrite($fp, "# genotyping\n");
    if ($_POST['gs_run_mode'] == "discovery_and_genotyping") {
      $GS_GTYP_CMD = "\\\$GS_COMMON_1  -S \\\${SV_DIR}/qscript/SVGenotyper.q  \\\$GS_COMMON_2 \\\$GS_COMMON_3  \\\$GS_COMMON_4   -I \\\$RUNDIR/genomestrip/group\$gp/bamfilelist.inp  -vcf  discovery.vcf  -O  genotypes.vcf  -run";
      fwrite($fp, "$GS_GTYP_CMD\n");
    }

       //      fwrite($fp, "tgt=\\\$RESULTSDIR/genomestrip/group\$gp\n");
    //if ($compute_target!="AWS") { fwrite($fp, "mkdir -p \\\$tgt\n"); }

      //      fwrite($fp, "\$put_cmd  \`pwd\`/$vs_mpileup_out.group\$gp.chr\$chralt  \\\$tgt/\n");
      //      fwrite($fp, "cd \\\$RUNDIR/genomestrip/group\$gp\n"); 
      //      fwrite($fp, "\$put_cmd  \`pwd\`/bamfilelist.inp \\\$tgt/bamfilelist.group\$gp.inp\n");
      //      fwrite($fp, "\$del_cmd  \\\$remotestatus\n");
      //      fwrite($fp, "\$del_local \\\$localstatus\n");

      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n"); 
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_GENOMESTRIP=\\\$scr_t0,\\\$scr_dt\n");
      }      
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp,"EOF\n");
      fwrite($fp, "cd \$RUNDIR/genomestrip/\$dir ; chmod +x ./genomestrip.sh\n");


      $mem_opt = gen_mem_str($compute_target, $toolmem_h['genomestrip']['mem_default']+$toolmem_h['genomestrip']['q_mempad']);

      fwrite($fp, "chralt=\${chr/:/_}\n");
      $job_name = $batch['name_opt']." "."$tag_gs.gs.group\$gp";
      $ERRARG = "-e ./stderr.genomestrip.group\$gp.chr\$chralt";
      $OUTARG = "-o ./stdout.genomestrip.group\$gp.chr\$chralt";
      $EXEARG = "./genomestrip.sh";
      
      fwrite($fp,"$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." ".$job_name." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "   done\n");  // seqs


    // Collect each group
      fwrite($fp, " cat > \$RUNDIR/genomestrip/group\$gp/gs_postrun.group\$gp.sh <<EOF\n");
      fwrite($fp, "#!/bin/bash\n");

      check_aws_shell($fp);
      if($do_timing) {fwrite($fp, "scr_t0=\`date +%s\`\n"); }

      fwrite($fp, "GENOMEVIP_SCRIPTS=$GENOMEVIP_SCRIPTS\n");
      fwrite($fp, "RUNDIR=\$RUNDIR\n");
      fwrite($fp, "RESULTSDIR=\$RESULTSDIR\n");
      fwrite($fp, "put_cmd=$put_cmd\n");
      fwrite($fp, "del_cmd=$del_cmd\n");
      fwrite($fp, "del_local=$del_local\n");
      fwrite($fp, "tgt=\\\$RESULTSDIR/genomestrip/group\$gp\n");
      fwrite($fp, "tmp=\`tempfile\`\n");      
      fwrite($fp, "cd \$RUNDIR/genomestrip/group\$gp\n");
      // It appears all info in discovery vcf is also present in genotype vcf
      if ($_POST['gs_run_mode'] == "discovery_and_genotyping") {
	fwrite($fp, "out=genomestrip.genotypes.group\$gp.all.orig.vcf\n");
	fwrite($fp, "find . -type f -size +0c -iname 'genotypes.vcf' -exec cat {} \; >> \\\$tmp\n");
      } else {
	fwrite($fp, "out=genomestrip.discovery.group\$gp.all.orig.vcf\n");
	fwrite($fp, "find . -type f -size +0c -iname 'discovery.vcf' -exec cat {} \; >> \\\$tmp\n");
      }

      //      write_sort_vcf_output_cmd($fp);

      // convert and save
      fwrite($fp, "\\\$GENOMEVIP_SCRIPTS/genomevip_label.pl GenomeSTRiP ./\\\$out  ./\\\${out/%orig.vcf/gvip.vcf}\n");
      fwrite($fp, "\$put_cmd  \`pwd\`/\\\${out/%orig.vcf/gvip.vcf}  \\\$tgt/\n");
      // fwrite($fp, "\$del_cmd  \\\$remotestatus\n");

      if($do_timing) {
	fwrite($fp, "scr_tf=\`date +%s\`\n"); 
	fwrite($fp, "scr_dt=\\\$((scr_tf - scr_t0))\n");
	fwrite($fp, "echo GVIP_TIMING_GENOMESTRIP_POSTRUN=\\\$scr_t0,\\\$scr_dt\n");
      }      
      if($compute_target=="AWS") { fwrite($fp, "$sgemem_cmd\n"); }
      fwrite($fp, "EOF\n");
      fwrite($fp, "cd \$RUNDIR/genomestrip/group\$gp ;  chmod +x ./gs_postrun.group\$gp.sh\n");
      $mem_opt = gen_mem_str($compute_target, $toolmem_h['gather']['mem_default']);
      $jobdeps = $batch['dep_opt']." ".$batch['dep_opt_pre']."\"$tag_gs.gs.group\$gp.$wc".$batch['dep_opt_post'];
      $job_name = $batch['name_opt']." "."$tag_gs.gs_postrun.group\$gp";
      $ERRARG = "-e ./stderr.gs_postrun.group\$gp";
      $OUTARG = "-o ./stdout.gs_postrun.group\$gp";
      $EXEARG = "./gs_postrun.group\$gp.sh";
      fwrite($fp, "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name $jobdeps"." ".$batch['q_opt']." "."$ERRARG $OUTARG $mem_opt $EXEARG\n");
      fwrite($fp,"sleep $dlay\n");
      fwrite($fp, "\n");

      fwrite($fp, "done\n");  // gp
        fwrite($fp, "\n");

  } // if gs_cmd


  // --------------------------------------------------------------------------------
  // TODO: Post-processing of run







  // --------------------------------------------------------------------------------

  fclose($fp); // close main cmds

  // --------------------------------------------------------------------------------
  // Dump resource dirs to be accessed
  // Assume unix relative homedir path convention
  $fp = fopen($tmpjob,'w');
  fwrite($fp, "#!/bin/bash\n");

  check_aws_shell($fp);

  foreach ($paths_h as $key => $value) {
    if ($DNAM_use[$key]) {
      $prefix="";	
      if (!preg_match('#^/#', $value) && !preg_match('#^s3\://#',$value) && !preg_match('#^~/#',$value)) {
	$prefix="~/";	
      }
      fwrite($fp, "$DNAM_VAR[$key]=$prefix$value\n");
    }
  }
  fclose($fp);

  // Merge dirs with main
  $main_content = file_get_contents("$tmpjob.main");
  file_put_contents($tmpjob, $main_content, FILE_APPEND);
  system("rm -f $tmpjob.main");

  // --------------------------------------------------------------------------------
  
  
  //if($do_html==1) {
  //  echo "</body></html>";
  //}
  //echo "Setting up output<br>";
  
  // print execution profile to separate file

  print_profile_file($tmp_ep);


switch ($compute_target) {
case 'AWS':
  $new_cl=$_POST['clusters'];

  // TODO: future upgrade pending
  //  $old_cl=$_POST['clusters_l'];
  $old_cl="nada";

  $the_cluster="";
  if($new_cl!="nada" && $old_cl!="nada") {
    echo "Error: Choice of AWS compute cluster is not well defined. Aborting.<br>";
    exit;
  }
  $output="";
  if($old_cl!="nada") { // existing  (TODO)
    //    echo "Resuming cluster ".$the_cluster."...<br>";
    echo "Error:  Resuming clusters is not currently supported. Aborting.<br>";
    $the_cluster = $old_cl;
    $cmd = "$SC -c $scconf start -x ".$the_cluster;
    //$output = shell_exec($cmd);
    //echo $output;
    exit;
  } elseif ($new_cl!="nada") { // new 
    $tmp_c = array();
    $tmp_c = json_decode($_POST['clustdb'], true);
    $the_cluster = $tmp_c[$new_cl];


    // ----------------------------------------
    // Control operations -- I have ideas for improving this
    $normal_ops = 1;
    //$normal_ops = 0;

    if($normal_ops) { // NORMAL OPERATION
      $real_cluster = $the_cluster."_".generateRandomString(4);
      //$real_cluster = $the_cluster."_"."0000";
      echo "Creating cluster '".$real_cluster."'...<br>";
      $cmd = "$SC -c $scconf start -c $the_cluster $real_cluster 2>&1";
      file_put_contents("testout", "$cmd\n", FILE_APPEND);
      $myoutput = shell_exec($cmd);
      
    } else {  // DEBUG

    $real_cluster = $the_cluster."_"."0000";    // Add string manually
    echo "Re-using cluster '".$real_cluster."'...<br>";
    $myoutput = "Overriding cluster creation. ready to use";
    }
    // ----------------------------------------
    // (rjm) possibly just stop here
    //       exit;



    if(preg_match('/already exists/', $myoutput)) {
      $output .= "Error: A cluster named '".$real_cluster."' already exists. GenomeVIP currently requires you to terminate the cluster before resubmitting.<br/>";
      echo $output;
      exit;
    } else {
      $output .= $myoutput."<br/>";
      file_put_contents("testout", "**Start of new cluster log**\n", FILE_APPEND);
      file_put_contents("testout", "$output\n", FILE_APPEND);
      file_put_contents("testout", "**End of new cluster log**\n", FILE_APPEND);
      // echo $output;
    }
  }

  // Check
  if(preg_match('/must specify the filesystem type/', $output)) {
    echo "Error: filesystem type unknown (has a disk not yet been formatted?). Aborting.";
    exit;
  }
  if(! preg_match('/ready to use/', $output)) {
    echo "Unknown error occurred while starting the cluster. Aborting.";
    exit;
  }
  echo "...created. Cluster is ready to use.<br>";


  echo "Transmitting files to cluster...<br>";
  // set up S3 -- now done elsewhere

  $the_s3cfg = trim($_POST['s3conf']);

  $cmd = "$SC -c $scconf put $real_cluster $the_s3cfg ~/.s3cfg";
  $output = shell_exec($cmd);
  $cmd = "$SC -c $scconf sshmaster $real_cluster \"chmod 0600 ~/.s3cfg\"";
  $output = shell_exec($cmd);
  $cmd = "$SC -c $scconf sshmaster $real_cluster \"mkdir -p $RUNDIR\"";  
  $output = shell_exec($cmd);
  $cmd = "$SC -c $scconf put  $real_cluster $tmp_ep   $RUNDIR/$myjob.ep"; 
  $output = shell_exec($cmd);
  $cmd = "$SC -c $scconf put  $real_cluster $tmpjob   $RUNDIR/$myjob.sh"; 
  $output = shell_exec($cmd);
  $cmd = "$SC -c $scconf sshmaster $real_cluster \"chmod 0755 $RUNDIR/$myjob.sh\""; 
  $output = shell_exec($cmd);
  echo "...transmitted.<br>";

  echo "Launching computations...<br>";
  $cmd = "$SC -c $scconf sshmaster $real_cluster $RUNDIR/$myjob.sh";
  $output = shell_exec("$cmd > /dev/null 2>/dev/null &");
  echo "...launched on cluster '".$real_cluster."'.<br>";
  echo "<br>";

  // messages
  $s3path = preg_replace('#^s3://#', '', $RESULTSDIR);
  $s3addr = "http://s3.amazonaws.com/$s3path";
  echo "Results and status updates can be found in your Amazon bucket $s3addr or from within your AWS S3 Console under ".preg_replace('/\//', " -> ",  $s3path)."<br>";
  break;

  
case 'tgi':



  $usern = trim($_POST['username']);
  $passw = trim($_POST['phrase']);
  $host  = trim($_POST['clust_gw']);



  $conn = ssh2_connect($host, 22);
  $auth = ssh2_auth_password($conn, $usern, $passw);

  //  echo "Transferring data...<br>";
  ssh2_exec($conn, "mkdir -m 0755 -p $workdir");

  // scp_send has stopped interpreting initial tilde (~) in path. Employ workaround.
  // $tmpworkdir = preg_replace('#^~(/)?#','',$workdir);
  // (rjm): this should solve the cases
  $tmpworkdir = preg_replace('#^~(('.preg_quote($usern).'/|/))?#','',$workdir); 

  if(file_exists($tmp_ep)) {
    ssh2_scp_send($conn, "$tmp_ep", "$tmpworkdir/$myjob.ep", 0644);
  } else {
    echo "Warning: Profile file does not exist for transfer.<br>";
  }
  if(! file_exists($tmpjob)) {
    echo "Error: Run script does not exist for transfer.<br>";
  } else {
    ssh2_scp_send($conn, "$tmpjob", "$tmpworkdir/$myjob.sh", 0755);
    ssh2_exec($conn, "chmod 0755 $workdir/$myjob.sh");












    


    
    $job_name = $batch['name_opt']." \"$myjob\"";
    $EXEARG = "$RUNDIR/$myjob.sh";
    $ERRARG = "$RUNDIR/stdout.master";
    $OUTARG = "$RUNDIR/stdout.master";
    
    $cmd = "$nobsub"." ".$batch['cmd']." ".$batch['limitgr']." "."$job_name"." ".$batch['q_opt']." "."-e $ERRARG -o $OUTARG $EXEARG";
    




    



    



    

    // Comment to test the sending of script and not run it
    $bExe=0;
    if(preg_match('/execute/', $_POST['subaction'])) {
      ssh2_exec($conn, $cmd);
      $bExe=1;
    }


    unset($conn);
    
    // Output
    echo "SUMMARY:<br>";
    echo "Script $myjob.sh and execution profile $myjob.ep were sent to host $host and placed into the directory $workdir.<br>";
    echo "The script ".(($bExe)?("was"):("was not"))." submitted to the job scheduler.<br>";
    if(! $bExe) { echo "The script may be run manually from your user account.<br>"; }
  }

  
}  // end switch compute target



}


 // if POST



?>
