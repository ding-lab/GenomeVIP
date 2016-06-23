#!/usr/bin/env perl 
# ---------------------
# @name GenomeVIP utility script for VarScan trios
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# @version
#
# Input: varscan trio/de novo pass vcf
# ---------------------
use strict;
use warnings;

use Cwd;
use Carp;
use FileHandle;
use IO::File;
use Getopt::Long;
use POSIX qw( WIFEXITED );
use File::Temp qw/ tempfile /;

my @err_msg=("Error opening input file", "Error opening an output file","Error closing an output file", "Error closing input file");

# get paras from config file 
my (%paras);
map { chomp;  if(!/^[#;]/ && /=/) { @_ = split /=/; $_[1] =~ s/ //g; my $v = $_[1]; print $v."\n";  $_[0] =~ s/ //g; $paras{ (split /\./, $_[0])[-1] } = $v } } (<>);
# map { print; print "\t"; print $paras{$_}; print "\n" } keys %paras; 


open (IN,     "<", $paras{'variants_file'}   ) || die "$err_msg[0] $paras{'variants_file'}";
open (PASS,   ">", $paras{'passfile'}  ) || die $err_msg[1];
open (FAIL,   ">", $paras{'failfile'}  ) || die $err_msg[1];

#CHROM	POS	ID	REF	ALT	QUAL	FILTER	INFO	FORMAT	Father	Mother	Child
#00	0000	.	T	C	.	PASS	ADP=177;STATUS=2	GT:GQ:SDP:DP:RD:AD:FREQ:PVAL:RBQ:ABQ:RDF:RDR:ADF:ADR	1/1:224:66:39:0:39:100%:3.6742E-23:0:19:0:0:20:19	1/1:255:177:168:0:168:100%:1.6424E-100:0:24:0:0:66:102	0/1:255:331:326:161:165:50.61%:1.0886E-62:30:25:62:99:71:94

while( <IN> ) {
    chomp;
    if (! /^#/) {
	my ($var_support_dad, $var_support_mom) = (0,0);
	my @a = split /\t/;
	my @fmt = split /\:/,$a[8];

	for(my $idx=0; $idx < scalar(@fmt); $idx++) {
	    if( $fmt[$idx] eq "AD") {
		my @dad = split /\:/,$a[9];
		$var_support_dad = $dad[$idx];
		my @mom = split /\:/,$a[10];
		$var_support_mom = $mom[$idx];
		last;
	    }
	}
	if($var_support_dad + $var_support_mom <= $paras{'parents_max_supporting_reads'}) {
	    print PASS $_."\n";
	} else {
	    $a[6] = "parent_count";
	    print FAIL join("\t", @a)."\n";
	}	    
    } else {
	if(/^#CHROM/) {
	    my $s = "##FILTER=<ID=parent_count,Description=\"Exceeds allowed number (".$paras{'parents_max_supporting_reads'}.") of combined parental var-supporting reads\">";
	    print PASS $s."\n";
	    print FAIL $s."\n";
	}
	print PASS $_."\n";
	print FAIL $_."\n";
    }
}
close (FAIL)  || die $err_msg[2];
close (PASS)  || die $err_msg[2];
close (IN)    || die $err_msg[3];

1;


