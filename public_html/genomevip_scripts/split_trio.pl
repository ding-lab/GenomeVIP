#!/usr/bin/perl -w 
# ---------------------
# @name GenomeVIP utility script for VarScan trios
# @author R. Jay Mashl<rmashl@genome.wustl.edu>
# @version
#
# @syntax split_trio.pl  input.vcf
# ---------------------
use strict;
use warnings;

my @err_msg=("Error opening input file", "Error opening an output file","Error closing an output file", "Error closing input file");

my $infile=$ARGV[0];
if (not defined $infile) { die "Error: input filename was not specified" }
if ($infile !~ /vcf$/) { die "Error: input filename does not end in .vcf" }


open (IN,   "<", $infile               ) || die "$err_msg[0] $infile";

$infile =~ s/vcf$//; 
open (NTR,      ">", $infile."untransm.vcf"    ) || die $err_msg[1];
open (TR,       ">", $infile."transm.vcf"      ) || die $err_msg[1];
open (MIE,      ">", $infile."mie.vcf"         ) || die $err_msg[1];
open (DN_STR10, ">", $infile."denovo_str10.vcf") || die $err_msg[1];
open (DN_PASS,  ">", $infile."denovo_pass.vcf" ) || die $err_msg[1];
open (DN_OTHER, ">", $infile."denovo_other.vcf") || die $err_msg[1];
open (OT,       ">", $infile."other.vcf"       ) || die $err_msg[1];

while ( <IN> ) {
    if (/^#/) {
	print NTR $_; print TR $_; print MIE $_; print DN_STR10 $_; print DN_PASS $_; print DN_OTHER $_; print OT $_;
    } else {
  	if (/STATUS=1/) { print NTR $_ }
	elsif (/STATUS=2/) { print TR $_ }
	elsif (/STATUS=3/) { 
	    if (/str10/) { print DN_STR10 $_ }
	    elsif (/PASS/) { print DN_PASS $_ }
	    else { print DN_OTHER $_ }
	}
	elsif (/STATUS=4/) { print MIE $_ }
	else { print OT $_ }
    }
}
close (OT)       || die $err_msg[2];
close (DN_OTHER) || die $err_msg[2];
close (DN_PASS)  || die $err_msg[2];
close (DN_STR10) || die $err_msg[2];
close (MIE)      || die $err_msg[2];
close (TR)       || die $err_msg[2];
close (NTR)      || die $err_msg[2];
close (IN)       || die $err_msg[3];

1;

