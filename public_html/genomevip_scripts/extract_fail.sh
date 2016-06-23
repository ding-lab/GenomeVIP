#!/usr/bin/env perl 
# ---------------------
# @name GenomeVIP utility script to extract failed calls
# @author R. Jay Mashl <rmashl@genome.wustl.edu>

# @version 0.2: change scripting language to reduce i/o
# @version 0.1: original
#
# @syntax extract_fail.sh  orig.vcf  pass.vcf  fail.vcf
# ---------------------
# previous version
#(grep '^#' $mypass  &&  grep -v -f $mypass  $myorig) > $myfail

use warnings;
use strict;

my ($myorig, $mypass, $myfail) = @ARGV;
my %pass;

# read filter lines
open(IN, "< $mypass");
  while(<IN>) { $pass{$_}=1; }
close(IN);
# process
open(OUT, "> $myfail");
  open(IN, "< $myorig");
    while(<IN>) {
	if(/^#/ || !exists($pass{$_})){ print OUT $_; }
    }
  close(IN);
close(OUT);
