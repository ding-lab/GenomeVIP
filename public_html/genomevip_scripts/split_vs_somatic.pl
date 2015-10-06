#!/usr/bin/perl -w
# ---------------------
# @name GenomeVIP utility to split VarScan somatic calls
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# @version
#
# @syntax  split_vs_somatic.pl  source.vcf 
# ---------------------
use strict;
use warnings;

my $infile = $ARGV[0];
open (IN, "<", $infile) || die "Error: cannot open input file $infile";

my $base = `basename $infile .vcf`;
chomp($base);
open (OUT1, ">", "$base.Germline.vcf") || die "Error: cannot open output file for writing";
open (OUT2, ">", "$base.Somatic.vcf" ) || die "Error: cannot open output file for writing";
open (OUT3, ">", "$base.LOH.vcf"     ) || die "Error: cannot open output file for writing";
open (OUT5, ">", "$base.other.vcf"   ) || die "Error: cannot open output file for writing";

while (<IN>) {
    if (/^#/) {
	print OUT1 $_;
	print OUT2 $_;
	print OUT3 $_;
	print OUT5 $_;
    } else {
	# Certainly not the most elegant, but it will do.
	my @a = split /\t/;
	if ($a[7] =~ /^SS=0/ ||  $a[7] =~ /;SS=0$/ ||  $a[7] =~ /;SS=0;/) { print OUT5 $_; next; }
	if ($a[7] =~ /^SS=5/ ||  $a[7] =~ /;SS=5$/ ||  $a[7] =~ /;SS=5;/) { print OUT5 $_; next; }
	if ($a[7] =~ /^SS=1/ ||  $a[7] =~ /;SS=1$/ ||  $a[7] =~ /;SS=1;/) { print OUT1 $_; next; }
	if ($a[7] =~ /^SS=2/ ||  $a[7] =~ /;SS=2$/ ||  $a[7] =~ /;SS=2;/) { print OUT2 $_; next; }
	if ($a[7] =~ /^SS=3/ ||  $a[7] =~ /;SS=3$/ ||  $a[7] =~ /;SS=3;/) { print OUT3 $_; next; }
    }
}

close (OUT5);
close (OUT3);
close (OUT2);
close (OUT1);
close (IN);

