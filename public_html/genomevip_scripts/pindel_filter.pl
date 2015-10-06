#!/usr/bin/perl -w
#--------------------------------------
# @name GenomeVIP Pindel filters
# @author Beifang Niu
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
# 
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
my $filter_pass     = $paras{'variants_file'}.".filter1_pass";
my $filter_pass_fh;
my $filter_fail     = $paras{'variants_file'}.".filter1_fail";
my $filter_fail_fh;

# Conversion to VCF and homopolymer filtering
my $filter_output_fn_vcf  = $paras{'variants_file'}.".filter1_pass.filter2_all.vcf";
my $input_fh;
my $filter_output_fn_vcf2 = $paras{'variants_file'}.".filter1_pass.filter2_pass.vcf";
my $filter_output_fh;
my $filter_output_fn_vcf2_fail = $paras{'variants_file'}.".filter1_pass.filter2_fail.vcf";
my $filter_output_fh_fail;


# Optional filter
if ($paras{'apply_filter'} eq "true"  &&  $paras{'mode'} ne "pooled") {
    $input_fh       = IO::File->new( $paras{'variants_file'}    ) or die " could not open $paras{'variants_file'} for reading $! ";
    $filter_pass_fh = IO::File->new( $filter_pass ,         ">" ) or die "Could not create $filter_pass for writing $!";
    $filter_fail_fh = IO::File->new( $filter_fail ,         ">" ) or die "Could not create $filter_fail for writing $!";


    if ($paras{'mode'} eq "germline") {   # germline
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;

	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  ) {
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    
	    if( ($t[34] + $t[36] + $t[34] + $t[36])/($t[32] + $t[33] + $t[34] + $t[36] + $t[34] + $t[36] ) <  $paras{'min_var_allele_freq'} ){
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[34] == 0 ||  $t[36] == 0 ) {
		    $filter_fail_fh->print($_."\n");
		    next;
		}
	    }
	    
	    $filter_pass_fh->print($_."\n");
	}
    }

    if ($paras{'mode'} eq "somatic") {   # somatic
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;
	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  || ($t[39] + $t[41] + $t[43] < $paras{'min_coverages'}) ||  ($t[40] + $t[41] + $t[43] <  $paras{'min_coverages'})) {
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    
	    if( ($t[34] + $t[36] + $t[34] + $t[36])/($t[32] + $t[33] + $t[34] + $t[36] + $t[34] + $t[36] ) <  $paras{'min_var_allele_freq'} ||  ($t[41] + $t[43] + $t[41] + $t[43])/($t[39] + $t[40] + $t[41] + $t[43] + $t[41] + $t[43]) > $zero) {
		$filter_fail_fh->print($_."\n");
		next;
	    }
	    
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[34] == 0 ||  $t[36] == 0 || $t[41] > 0 || $t[43] > 0 ) {
		    $filter_fail_fh->print($_."\n");
		    next;
		}
	    }
	    
	    if($paras{'remove_complex_indels'} =~ /true/) {  
		
		if ( $t[1] eq "I" || $t[1] eq "D") {

		    if ( $t[1] eq "I" || ($t[1] eq "D" && $t[4] == 0) ) {
			print "Indel filter: passed\n";
			$filter_pass_fh->print($_."\n");
		    } else {
			$filter_fail_fh->print($_."\n");
		    }
		    next;
		}

	    }



	    $filter_pass_fh->print($_."\n");
	}
    }
    


    if ($paras{'mode'} eq "trio") {   # trio
	while (<$input_fh>) {
	    chomp; 
	    my @t = split /\s+/;
	    if(  ($t[32] + $t[34] + $t[36] <  $paras{'min_coverages'}) || ($t[33] + $t[34] + $t[36] <  $paras{'min_coverages'})  || ($t[39] + $t[41] + $t[43] < $paras{'min_coverages'}) ||  ($t[40] + $t[41] + $t[43] <  $paras{'min_coverages'}) ||   ($t[46] + $t[48] + $t[50] < $paras{'min_coverages'}) ||  ($t[47] + $t[48] + $t[50] <  $paras{'min_coverages'}) ) {
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    
	    if( ($t[48] + $t[50] + $t[48] + $t[50])/($t[46] + $t[47] + $t[48] + $t[50] + $t[48] + $t[50]) < $paras{'child_var_allele_freq'}    ) { 
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    if( ($t[34] + $t[36]) + ($t[41] + $t[43]) >  $paras{'parents_max_num_supporting_reads'}  ) {
		$filter_fail_fh->print($_."\n");
		next;
	    }

	    
	    if($paras{'require_balanced_reads'} =~ /true/) {  
		if ( $t[48] == 0 ||  $t[50] == 0 ) {
		    $filter_fail_fh->print($_."\n");
		    next;
		}

	    }
	    
	    $filter_pass_fh->print($_."\n");
	}
    }



    $filter_fail_fh->close;
    $filter_pass_fh->close; 
    $input_fh->close; 
}




# run pindel2vcf
my $pindel2vcf_command = "";
my $ref_base = `basename $paras{'REF'}`;
chomp  $ref_base;
my $result;

if ($paras{'apply_filter'} eq "true") {

    if ($paras{'mode'} eq "pooled") {
	$pindel2vcf_command = "$paras{'pindel2vcf'} -r $paras{'REF'} -R $ref_base -p $var_file      -d $paras{'date'} -v $filter_output_fn_vcf  -he $paras{'heterozyg_min_var_allele_freq'} -ho $paras{'homozyg_min_var_allele_freq'} 2> $thisdir/log.pindel2vcf";
#    print $pindel2vcf_command."\n";
	$result = system( $pindel2vcf_command );
	$result = system(`rm -f $filter_pass`);
    } else {
	$pindel2vcf_command = "$paras{'pindel2vcf'} -r $paras{'REF'} -R $ref_base -p $filter_pass   -d $paras{'date'} -v $filter_output_fn_vcf  -he $paras{'heterozyg_min_var_allele_freq'} -ho $paras{'homozyg_min_var_allele_freq'} 2> $thisdir/log.pindel2vcf";
#    print $pindel2vcf_command."\n";
	$result = system( $pindel2vcf_command );
	$result = system(`rm -f $filter_pass`);
    }

    
    # finish filtering
    if ($paras{'mode'} eq "somatic" || $paras{'mode'} eq "germline" || $paras{'mode'} eq "pooled" || $paras{'mode'} eq "trio") {
	$input_fh              = IO::File->new( $filter_output_fn_vcf            ) or die "Could not open $filter_output_fn_vcf for reading $!";
	$filter_output_fh      = IO::File->new( $filter_output_fn_vcf2,      ">" ) or die "Could not create $filter_output_fn_vcf2 for writing $!";
	$filter_output_fh_fail = IO::File->new( $filter_output_fn_vcf2_fail, ">" ) or die "Could not create $filter_output_fn_vcf2_fail for writing $!";

	
	while ( <$input_fh> ) {
#	print;
	    if ( /^#/ ) { $filter_output_fh->print($_); next };
	    my @a= split /\t/; 
	    my @b = split/\;/, $a[7]; 
	    for ( my $i=0; $i<scalar(@b); $i++) { 
		if ( $b[$i]=~/^HOMLEN/ ) { 
		    my @c = split/=/, $b[$i]; 
		    if ( $c[1] <= $paras{'max_num_homopolymer_repeat_units'} ) { 
			$filter_output_fh->print($_); 
		    }  else {
			$filter_output_fh_fail->print($_); 
		    }
		    last;
		} 
	    }
	}
	$filter_output_fh_fail->close;
	$filter_output_fh->close;
	$input_fh->close;
	$result = system(`rm -f $filter_output_fn_vcf`);
	
    }


    else { 
	$result = system(`cat $filter_output_fn_vcf > $filter_output_fn_vcf2`);
	$result = system(`rm -f $filter_output_fn_vcf`);
    }





} else { 

    $pindel2vcf_command = "$paras{'pindel2vcf'} -r $paras{'REF'} -R $ref_base -p $paras{'variants_file'}  -d $paras{'date'} -v $filter_output_fn_vcf2  -he $paras{'heterozyg_min_var_allele_freq'} -ho $paras{'homozyg_min_var_allele_freq'} 2> $thisdir/log.pindel2vcf";
#    print $pindel2vcf_command."\n";
    $result = system( $pindel2vcf_command );
    
}



1;

