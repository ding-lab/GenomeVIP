#!/bin/bash
# ---------------------
# @name GenomeVIP utility script to catenate BreakDancer ITX calls
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# @version 
#
# @syntax gather_itx.sh  itx_dir  filelist result
# ---------------------
export ITX_DIR="$1"
export LIST="$2"
export OUT="$3"

cd $ITX_DIR

while read file ; do
  cat "$file"
done < ./$LIST  > ./tmp


grep -E '^#'                ./tmp | sort -u                                                  >  ./$OUT
grep -E '^(chr)?[1-9]'      ./tmp | sort -k1,1g -k4,4 -k2,2g                                 >> ./$OUT
grep -E '^(chr)?X'          ./tmp | sort -k4,4 -k2,2g                                        >> ./$OUT
grep -E '^(chr)?Y'          ./tmp | sort -k4,4 -k2,2g                                        >> ./$OUT
grep -E '^((chr)?M(T)?|MT)' ./tmp | sort -k4,4 -k2,2g                                        >> ./$OUT
grep -E '^(chr)?[A-WZ]'     ./tmp | grep -E    '^((chr)?M(T)?|MT)' | sort -k4,4 -k2,2g       >> ./$OUT
grep -E '^(chr)?[A-WZ]'     ./tmp | grep -E -v '^((chr)?M(T)?|MT)' | sort -k1,1 -k4,4 -k2,2g >> ./$OUT

rm -f ./tmp
