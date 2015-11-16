# GenomeVIP

GenomeVIP is a web application platform for performing variant discovery on Amazon's Web Service (AWS) cloud or on local high-performance computing clusters.


## Versions

* v1.0 (3a1e935)
* v0.1 (ac89e21) | initial release 


## Getting Started

Below are potential installation scenarios for GenomeVIP. 

AWS prerequisites: Amazon Web Services account; input genomics data stored in EBS (or S3, such as the public 1000 Genomes Project). 

* 1. AWS cloud only: a pre-configured GenomeVIP server image is available in AWS public image repository. Computations created through this image will instantiate a public GenomeVIP runtime image with preinstalled genomics and supporting tools.

* 2. Local with AWS option: the GenomeVIP code is installed on a web server in the user's local network and configured to use locally installed genomics and supporting tools; computations can be sent to a local job manager (LSF support provided currently). This server may also be used as an alternative to the public server image mentioned in (1) above; AWS computations proceed as in (1) above.


A step-by-step user guide is in development and can be found in the document HowTo_GenomeVIP.docx



## User Guide

### Setting up GenomeVIP in Amazon:

	Login to your AWS account
	
	Choose EC2 from the AWS Console
	
	Select Images (AMIs)
		
		From the dropdown public AMIs search GenomeVIP
		
	Choose a server & runtime images
	
		Launch the server (most recent version is recommended) from the Actions menu
		
		Select Launch
			
		Choose an instance type (m1.medium recommended)
		
		Click "Next: Configure Instance Details"
		
		Select number of instances (at least 1)
		
		Select the network type, EC2 Classic
		
			NOTE: The Availability Zone should match the instance zone (and the Volumes Availability Zone should match)
			
		Skip the Storage & Tag tabs
		
		Go to tab 6, Configure Security Group
		
			Add a rule for HTTPS
		
		Review selections, and launch
		
		Choose the desired keypair (you can proceed without a key pair)
		
	Go to your dashboard (orange box)
	
		Select EC2 to check its running as many instances as you chose above and *get the public IP*
		
	To get to the GenomeVIP homepage, the site is https://publicip/~genomevip (where publicip is the public IP of a running instance, the site is case sensititve).

### From GenomeVIP homepage:

#### Accounts

	Select  either AWS or Local Cluster

	If AWS

		Provide your AWS login credentials

		A session ID will be generated for future logins

	If Local Cluster

		Provide your login credentials to your local cluster

#### Select Genomes	

	Select either an EBS volume (only with AWS accounts), user defined, or 1000 Genomes

	Click “Load list” to load the data

	Choose the .bam files to use

	Choose a reference genome

	Select if you want a copy of all sorted .bam and .bai created to storage directory

#### Configure Tools

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
	
#### Submit

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

#### Results

	Check out the results by navigating to the location displayed (for both AWS and Local Cluster)

	Enjoy!

#### Options

	Additional options and accounts management may be configured for AWS
