#!/bin/bash
# ---------------------
# @name GenomeVIP utility script for setting filter field in VCF files
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# @version
#
# @syntax set_vcf_status.sh   input.vcf  desired_filter_label
# ---------------------
file="$1"
export label="$2"

#CHROM	POS	ID	REF	ALT	QUAL	FILTER	INFO	FORMAT	Father	Mother	Child
perl -i -n -e 'if(/^#/){print}else{@a=split/\t/;$a[6]=$ENV{'label'};print join("\t",@a);}' $file

