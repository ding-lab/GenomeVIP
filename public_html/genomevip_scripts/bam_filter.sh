#!/bin/bash
# ---------------------
# @name GenomeVIP utility script to filter BreakDancer ITX calls
# @author R. Jay Mashl <rmashl@genome.wustl.edu>c
# @version
#
# @syntax bam_filter.sh  mode   bamfilelist   variant_file  
#
# @comment outputs to variant_file.filter_{pass,fail}
# @comment Note: We assume full paths in bamfilelist and variant_file
# ---------------------
MODE="$1"
BAMLIST="$2"
VARFILE="$3"

if [ "$MODE" = "somatic" ] ; then
    FAIL_STR1=`awk '{if(NR==2){print $0}}' $BAMLIST `
    grep    '^#' $VARFILE | tee $VARFILE.somfilter_pass > $VARFILE.somfilter_fail
    grep -v '^#' $VARFILE | tee >(grep -v $FAIL_STR1 >> $VARFILE.somfilter_pass) | grep  $FAIL_STR1 >> $VARFILE.somfilter_fail
    rm -f $VARFILE
    
elif [ "$MODE" = "trio" ] ; then
    FAIL_STR1=`awk '{if(NR==1){print $0}}' $BAMLIST `
    FAIL_STR2=`awk '{if(NR==2){print $0}}' $BAMLIST `
    dn=`dirname $VARFILE`
    printf '%s\n'  $FAIL_STR1 $FAIL_STR2 >  $dn.patterns
    grep    '^#' $VARFILE | tee $VARFILE.triofilter_pass > $VARFILE.triofilter_fail
    grep -v '^#' $VARFILE | tee >(grep -v $FAIL_STR1 | grep -v $FAIL_STR2 >> $VARFILE.triofilter_pass) | grep -f $dn.patterns  >> $VARFILE.triofilter_fail
    rm -f $VARFILE

else 
    echo "\nERROR in bam_filter.sh: " $MODE " is not a valid mode."

fi

