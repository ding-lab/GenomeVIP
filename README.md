# GenomeVIP

GenomeVIP is a web-based platform for performing variant discovery on Amazon's Web Service (AWS) cloud or on local high-performance computing clusters.



## Versions

* 0.1 (ac89e21) | initial release (beta) 


## Getting Started

With version 0.1, users need to install the GenomeVIP code on their own web server and configure it for their locally installed genomics tools. Preconfigured GenomeVIP images for Amazon are forthcoming.



## User Guide

### Accounts

Select  either AWS or Local Cluster

	If AWS

		Provide your AWS login credentials

		A session ID will be generated for future logins

	If Local Cluster

		Provide your login credentials to your local cluster

### Select Genomes	

Select either an EBS volume (only with AWS accounts), user defined, or 1000 Genomes

Click “Load list” to load the data

Choose the .bam files to use

Choose a reference genome

Select if you want a copy of all sorted .bam and .bai created to storage directory

### Configure Tools

	Execution Profiles

		New

	Select a Run mode (germline, somatic, de novo/ family trio)

	Select Paramaters (these can be tuned further in each tool)

Existing

	Upload from file

	Select Genomic Regions

		No selection

		Standard chromosome1-22, X, Y

		Standard chromosomes plus contigs MT, GI, GL, NC

		User defined either manual entry or file upload

	Click the “Apply Profile” button to apply above entries

	Customization

		For each tool switched on by the run mode selected, fine tune any parameters. If necessary, turn off any tools switched on or turn on any tools not automatically selected.
	
### Submit

	If AWS

		Select an AWS computing resource instance

		Select a storage bucket to put results

	If Local Cluster

		Select a local computing resource

	Choose a working directory to run jobs

	Select submit action (create script vs. execute script)

	Specify any additional comments (optional)

	Double Check

		Click the “Preview” button to check execution profile

		Click the “Validate” button to preview and check user settings

	Click “Submit”

### Results

	Check out the results by navigating to the location displayed (for both AWS and Local Cluster)

	Enjoy!

### Options

	Additional options and accounts management may be configured for AWS
