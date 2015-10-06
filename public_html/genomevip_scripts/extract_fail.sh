#!/bin/bash
# ---------------------
# @name GenomeVIP utility script to extract failed calls
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# @version
#
# @syntax extract_fail.sh  orig.vcf  pass.vcf  fail.vcf
# ---------------------
export myorig="$1"
export mypass="$2"
export myfail="$3"

grep '^#' "$mypass"             > "$myfail"
comm -2 -3 "$myorig" "$mypass" >> "$myfail"
