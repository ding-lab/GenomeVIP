<?php
// --------------------------------------
// @name GenomeVIP utility functions
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------

function write_check_aws_file($fp) {
  fwrite($fp, "check_aws_file () {\n");  
  fwrite($fp, "   if [[ \"$@\" =~ .*Not\ Found*. ]] ; then\n");
  fwrite($fp, "      w=($@)\n");
  fwrite($fp, "      myfile=\${w[\${#w}]}\n");
  fwrite($fp, "      cat >> \$RUNDIR/status/LOG << EOF\n");
  fwrite($fp, "        File \$myfile does not exist for retrieval. This run has been stopped.\n");
  fwrite($fp, "        Please deselect offending file before resubmitting.\n");
  fwrite($fp, "EOF\n");
  fwrite($fp, "      exit\n");
  fwrite($fp, "   fi\n");
  fwrite($fp, "}\n");
}
function write_check_aws_file_int($fp) {
  fwrite($fp, "check_aws_file_int () {\n");  
  fwrite($fp, "   local myresult\n");
  fwrite($fp, "   if [[ \"$@\" =~ .*Not\ Found*. ]] ; then\n");
  fwrite($fp, "      myresult=1\n");
  fwrite($fp, "   else\n");
  fwrite($fp, "      myresult=0\n");
  fwrite($fp, "   fi\n");
  fwrite($fp, " echo \$myresult\n");
  fwrite($fp, "}\n");
}

function write_check_bai_please($fp, $compute_target) {
  global $toolsinfo_h;
  fwrite($fp, "check_bai_please () {\n");  
  fwrite($fp, "   if [[ \"$@\" =~ .*Not\ Found*. ]] ; then\n");
  fwrite($fp, "      w=($@)\n");
  fwrite($fp, "      myfile=\${w[\${#w}]}\n");
  fwrite($fp, "      srcbam=`basename \$myfile .bai`\n");
  fwrite($fp, "      if [[ -e  \$srcbam  ]] ; then\n");
  fwrite($fp, "         echo \"Bam index for \$srcbam unavailable...\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "         SAMTOOLS_DIR=".$toolsinfo_h['samtools']['path']."\n");
  fwrite($fp, "         SAMTOOLS_EXE=".$toolsinfo_h['samtools']['exe']."\n");
  fwrite($fp, "         \$SAMTOOLS_DIR/\$SAMTOOLS_EXE index \$srcbam\n");
  fwrite($fp, "         echo \"   ...Done constructing replacement bam index.\" >> \$RUNDIR/status/LOG\n");
  if($_POST['save_gen']==1) {
    if ($compute_target != "AWS") {
      fwrite($fp, "          mkdir -p \$RESULTSDIR/genomes\n");
    }
    fwrite($fp, "          \$put_cmd \"./$2\" \$RESULTSDIR/genomes/\n");
  }
  fwrite($fp, "      fi\n");
  fwrite($fp, "   fi\n");
  fwrite($fp, "}\n");
}


function write_do_prep_bam($fp,$compute_target) {
  global $toolsinfo_h;
  fwrite($fp, "do_prep_bam () {\n");  
  fwrite($fp, "   SAMTOOLS_DIR=".$toolsinfo_h['samtools']['path']."\n");
  fwrite($fp, "   SAMTOOLS_EXE=".$toolsinfo_h['samtools']['exe']."\n");
  fwrite($fp, "   outpfx1=`basename \$1 .bam`\n");
  fwrite($fp, "   outpfx2=`basename \$outpfx1 .orig`\n");
  fwrite($fp, "   chk=`\$SAMTOOLS_DIR/\$SAMTOOLS_EXE view -H \$1 | grep -c 'SO:coordinate'`\n");
  fwrite($fp, "   if [[ \$chk -eq 0 ]] ; then\n");
  fwrite($fp, "      echo \"Sorting bam \$1 ...\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "      \$SAMTOOLS_DIR/\$SAMTOOLS_EXE sort  \$1  \$outpfx1.sort\n");
  fwrite($fp, "      ln -s ./\$outpfx1.sort.bam  ./\$outpfx2.fixed.bam\n");
  fwrite($fp, "      echo \"   ...now creating index...\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "      \$SAMTOOLS_DIR/\$SAMTOOLS_EXE index \$outpfx2.fixed.bam\n");
  // In the current run, *.fixed.bam is unknown, so make a link to the known name
  fwrite($fp, "      echo \"   ...done.\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "      ln -s ./\$outpfx2.fixed.bam     ./\$outpfx2.bam\n");
  fwrite($fp, "      ln -s ./\$outpfx2.fixed.bam.bai ./\$outpfx2.bam.bai\n");
  if($_POST['save_gen']==1) {
    if ($compute_target != "AWS") {
      fwrite($fp, "      mkdir -p \$RESULTSDIR/genomes\n");
    }
    fwrite($fp, "      \$put_cmd ./\$outpfx2.fixed.bam     \$RESULTSDIR/genomes/\n");
    fwrite($fp, "      \$put_cmd ./\$outpfx2.fixed.bam.bai \$RESULTSDIR/genomes/\n");
  }
  fwrite($fp, "   else\n");
  fwrite($fp, "      ln -s \"./\$1\"  ./\$outpfx2.fixed.bam\n");
  fwrite($fp, "      echo \"Creating bam index for \$1 ...\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "      \$SAMTOOLS_DIR/\$SAMTOOLS_EXE index \$outpfx2.fixed.bam\n");
  fwrite($fp, "      echo \"   ...done.\" >> \$RUNDIR/status/LOG\n");
  fwrite($fp, "      ln -s ./\$outpfx2.fixed.bam     ./\$outpfx2.bam\n");
  fwrite($fp, "      ln -s ./\$outpfx2.fixed.bam.bai ./\$outpfx2.bam.bai\n");
  if($_POST['save_gen']==1) {
    if ($compute_target != "AWS") {
      fwrite($fp, "      mkdir -p \$RESULTSDIR/genomes\n");
    }
    fwrite($fp, "      \$put_cmd ./\$outpfx2.fixed.bam.bai \$RESULTSDIR/genomes/\$outpfx2.bam.bai\n");
  }
  fwrite($fp, "   fi\n");
  fwrite($fp, "}\n");
  
}
?>
