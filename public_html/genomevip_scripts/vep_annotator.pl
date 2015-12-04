#!/usr/bin/perl -w 
#----------------------------------
# @name GenomeVIP VEP annotator script
# @version
# @author R. Jay Mashl <rmashl@genome.wustl.edu>
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

# get paras from config file
my (%paras);
map {chomp;  if(!/^[#;]/ && /=/) { @_ = split /=/; $_[1] =~ s/ //g; my $v = $_[1]; print $v."\n";  $_[0] =~ s/ //g; $paras{ (split /\./, $_[0])[-1] } = $v } } (<>);
 map { print; print "\t"; print $paras{$_}; print "\n" } keys %paras;

my $cmd="";

# split off original header
my (undef, $tmp_orig_calls)  = tempfile();
$cmd="/bin/grep -v ^# $paras{'vcf'} > $tmp_orig_calls";
   system($cmd);

# run vep
my (undef, $tmp_vep_out) = tempfile();
$cmd = "perl $paras{'vep_cmd'} --database --offline --cache --dir $paras{'cachedir'} --assembly $paras{'assembly'} --fork 4 --format vcf --vcf -i $tmp_orig_calls -o $tmp_vep_out --force_overwrite  --fasta $paras{'reffasta'}";
   system($cmd);

# re-merge headers and move
my (undef, $tmp_merge) = tempfile();
$cmd = "grep ^##fileformat $tmp_vep_out > $tmp_merge";
   system($cmd);
$cmd = "grep ^# $paras{'vcf'} | grep -v ^##fileformat | grep -v ^#CHROM >> $tmp_merge";
   system($cmd);
$cmd = "grep -v ^##fileformat $tmp_vep_out >> $tmp_merge";
   system($cmd);
$cmd = "cat $tmp_merge > $paras{'output'}";
   system($cmd);

#Save other output
my @suffix=("_summary.html", "_warnings.txt");
foreach (@suffix) {
    my $file = $tmp_vep_out.$_;   if ( -e $file ) { $cmd = "cat $file > $paras{'output'}".$_; system($cmd); }
}
# clean up
$cmd = "rm -f $tmp_orig_calls $tmp_vep_out"."*"." ".$tmp_merge;
system($cmd);

1;

