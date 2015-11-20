#!/usr/bin/perl -w
#--------------------------------------
# @name GenomeVIP Pindel filters
# @author Beifang Niu
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# 
# @version 0.3 (rjm): generalize filter hierarchy filename handling and simply code structure
# @version 0.2 (rjm): added coverage, germline, trio, minimal pool filtering; revised approach; added failed pass. Adjusted filename and parameters names; allow for commented lines
# @version 0.1 (bn):  original somatic filter, written for (tumor,normal) column order
#--------------------------------------
use strict;
use warnings;

use Cwd;
use Carp;
use FileHandle;
use IO::File;
use Getopt::Long;
use POSIX qw( WIFEXITED );
use File::Temp qw/ tempfile /;


my $zero=0.01;

my $thisdir;
$thisdir=`dirname $ARGV[0]`;
chomp $thisdir;

# get paras from config file
my %paras; 
map { chomp; if ( !/^[#;]/ &&  /=/) { @_ = split /=/; $_[1] =~ s/ //g; my $v = $_[1]; $_[0] =~ s/ //g; $paras{ (split /\./, $_[0])[-1] } = $v } } (<>);
#map { print; print "\t"; print $paras{$_}; print "\n" } keys %paras;
if ( ! exists($paras{'mode'})) { die "Could not detect a filtering mode !!!\n"; }
# file exist ? 
unless ( -e $paras{'variants_file'} ) { die "input indels not exist !!! \n"; }


my $var_file        = $paras{'variants_file'};

# Filters for coverages, vaf, and balanced reads
my %filter1_prefix;
my %filter2_prefix;
$filter1_prefix{'pass'} = "";
$filter1_prefix{'fail'} = "";
$filter2_prefix{'pass'} = "";
$filter2_prefix{'fail'} = "";
if ($paras{'apply_filter'} eq "true") { 
    $filter1_prefix{'pass'} = "CvgVafStrand_pass";
    $filter1_prefix{'fail'} = "CvgVafStrand_fail";
    $filter2_prefix{'pass'} = "Homopolymer_pass";
    $filter2_prefix{'fail'} = "Homopolymer_fail";
}
my $filter1_pass_fh;
my $filter1_fail_fh;

# Conversion to VCF and homopolymer filtering
my $input_fh;
my $filter2_fh_pass;
my $filter2_fh_fail;



# Optional filter, part 1
if ($paras{'apply_filter'} eq "true"  &&  $paras{'mode'} ne "pooled") {
    $input_fh        = IO::File->new( "$var_file"                               ) or die "Could not open $var_file for reading $! ";
    $filter1_pass_fh = IO::File->new( "$var_file.$filter1_prefix{'pass'}",  ">" ) or die "Could not create $var_file.$filter1_prefix{'pass'} for writing $!";
    $filter1_fail_fh = IO::File->new( "$var_file.$filter1_prefix{'fail'}",  ">" ) or die "Could not create $var_file.$filter1_prefix{'fail'} for writing $!";


    if ($paras{'mode'} eq "germline") {   # germline
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;
	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  ) {
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if( ($t[34] + $t[36] + $t[34] + $t[36])/($t[32] + $t[33] + $t[34] + $t[36] + $t[34] + $t[36] ) <  $paras{'min_var_allele_freq'} ){
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[34] == 0 ||  $t[36] == 0 ) {
		    $filter1_fail_fh->print($_."\n");
		    next;
		}
	    }
	    $filter1_pass_fh->print($_."\n");
	}
    }

    if ($paras{'mode'} eq "somatic") {   # somatic
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;
	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  || ($t[39] + $t[41] + $t[43] < $paras{'min_coverages'}) ||  ($t[40] + $t[41] + $t[43] <  $paras{'min_coverages'})) {
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if( ($t[34] + $t[36] + $t[34] + $t[36])/($t[32] + $t[33] + $t[34] + $t[36] + $t[34] + $t[36] ) <  $paras{'min_var_allele_freq'} ||  ($t[41] + $t[43] + $t[41] + $t[43])/($t[39] + $t[40] + $t[41] + $t[43] + $t[41] + $t[43]) > $zero) {
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[34] == 0 ||  $t[36] == 0 || $t[41] > 0 || $t[43] > 0 ) {
		    $filter1_fail_fh->print($_."\n");
		    next;
		}
	    }
	    if($paras{'remove_complex_indels'} =~ /true/) {  
		if ( $t[1] eq "I" || $t[1] eq "D") {
		    if ( $t[1] eq "I" || ($t[1] eq "D" && $t[4] == 0) ) {
			print "Indel filter: passed\n";
			$filter1_pass_fh->print($_."\n");
		    } else {
			$filter1_fail_fh->print($_."\n");
		    }
		    next;
		}
	    }
	    $filter1_pass_fh->print($_."\n");
	}
    }
    


    if ($paras{'mode'} eq "trio") {   # trio
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;
	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  || ($t[39] + $t[41] + $t[43] < $paras{'min_coverages'}) ||  ($t[40] + $t[41] + $t[43] <  $paras{'min_coverages'}) ||   ($t[46] + $t[48] + $t[50] < $paras{'min_coverages'}) ||  ($t[47] + $t[48] + $t[50] <  $paras{'min_coverages'}) ) {
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if( ($t[48] + $t[50] + $t[48] + $t[50])/($t[46] + $t[47] + $t[48] + $t[50] + $t[48] + $t[50]) < $paras{'child_var_allele_freq'}    ) { 
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if( ($t[34] + $t[36]) + ($t[41] + $t[43]) >  $paras{'parents_max_num_supporting_reads'}  ) {
		$filter1_fail_fh->print($_."\n");
		next;
	    }
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[48] == 0 ||  $t[50] == 0 ) {
		    $filter1_fail_fh->print($_."\n");
		    next;
		}
	    }
	    $filter1_pass_fh->print($_."\n");
	}
    }

    $filter1_fail_fh->close;
    $filter1_pass_fh->close; 
    $input_fh->close; 
}




# run pindel2vcf
my $pindel2vcf_command = "";
my $ref_base = `basename $paras{'REF'}`;
chomp  $ref_base;
my $result;
my $var_src;
my $var_dest;

if ($paras{'apply_filter'} eq "true" && $paras{'mode'} ne "pooled") { # only case for filter1
    $var_src  = "$var_file.$filter1_prefix{'pass'}";
    $var_dest = "$var_file.$filter1_prefix{'pass'}.vcf";
} else {
    $var_src  = "$var_file";
    $var_dest = "$var_file.vcf";
}
$pindel2vcf_command = "$paras{'pindel2vcf'} -r $paras{'REF'} -R $ref_base -p $var_src -d $paras{'date'} -v $var_dest -he $paras{'heterozyg_min_var_allele_freq'} -ho $paras{'homozyg_min_var_allele_freq'} 2> $thisdir/log.pindel2vcf";
# print $pindel2vcf_command."\n";
$result = system( $pindel2vcf_command );

    
# Optional filter, part 2; here pooled is ok
if ($paras{'apply_filter'} eq "true") {
    
    if ($paras{'mode'} eq "pooled") {
	$input_fh        = IO::File->new( "$var_file.vcf"                             ) or die "Could not open $var_file.vcf for reading $!";
	$filter2_fh_pass = IO::File->new( "$var_file.$filter2_prefix{'pass'}.vcf", ">") or die "Could not create $var_file.$filter2_prefix{'pass'}.vcf for writing $!";
	$filter2_fh_fail = IO::File->new( "$var_file.$filter2_prefix{'fail'}.vcf", ">") or die "Could not create $var_file.$filter2_prefix{'fail'}.vcf for writing $!";
    } else {
	$input_fh        = IO::File->new( "$var_file.$filter1_prefix{'pass'}.vcf"                             ) or die "Could not open $var_file.$filter1_prefix{'pass'}.vcf for reading $!";
	$filter2_fh_pass = IO::File->new( "$var_file.$filter1_prefix{'pass'}.$filter2_prefix{'pass'}.vcf", ">") or die "Could not create $var_file.$filter1_prefix{'pass'}.$filter2_prefix{'pass'}.vcf for writing $!";
	$filter2_fh_fail = IO::File->new( "$var_file.$filter1_prefix{'pass'}.$filter2_prefix{'fail'}.vcf", ">") or die "Could not create $var_file.$filter1_prefix{'pass'}.$filter2_prefix{'fail'}.vcf for writing $!";
    }
    
    while ( <$input_fh> ) {
#	print;
	if ( /^#/ ) { $filter2_fh_pass->print($_); next };
	my @a= split /\t/; 
	my @b = split/\;/, $a[7]; 
	for ( my $i=0; $i<scalar(@b); $i++) { 
	    if ( $b[$i]=~/^HOMLEN/ ) { 
		my @c = split/=/, $b[$i]; 
		if ( $c[1] <= $paras{'max_num_homopolymer_repeat_units'} ) { 
		    $filter2_fh_pass->print($_); 
		}  else {
		    $filter2_fh_fail->print($_); 
		}
		last;
	    } 
	}
    }
    $filter2_fh_fail->close;
    $filter2_fh_pass->close;
    $input_fh->close;
    
}

1;
