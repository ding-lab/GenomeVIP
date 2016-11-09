.. _GeneralUsage:

.. role:: btn

#############
General usage
#############

The flowchart along the lefthand side can be used as a guide for the typical order for configuring a computation. Although the pages can be visited in any order, the dynamic content updates depending on the developing configuration. The display text and alerts will provide assistance.

1. Select :btn:`Accounts`

   - For Amazon EC2/Cloud (i.e., AWS), generate a new SessionID (Option 3) or enter a previous SessionID (Option 1).

     - Note: the SessionID provides user account type functionality, allowing multiple users to use the same server instance. SessionsIDs persists until the server is terminated or rebooted and enables you to submit additional computations to a running runtime instance.

   - For local clusters, provide your login credentials to your local cluster.


2. Select Genomes

   - Load names and locations of bam files, reference genomes, and index files (e.g. by uploading a file(s) from your computer, by pointing to a remote file, or by obtaining a remote directory listing):

     - For AWS:

       - EBS volumes: select the volumes you wish to use and the corresponding lists of files (and/or upload files). Files should be given as their full path on that particular volume. Click :btn:`Apply lists` when done.

       - Path to file:  enter a valid S3 path to a list file (e.g. ``s3://bucket/path/to/listfile.txt``) and click :btn:`Retrieve file`

       - File upload: :btn:`Browse...` to a file on your computer and then click :btn:`Upload File`

       - 1000 Genomes files: select one of the pre-formed list based on AWS's 1000 Genomes mirror and then click :btn:`Load list`

     - For local clusters:

   - Select samples and a reference.

     - Double-click on the bam name to transfer it to the "Selected bams" box. A search box with live update is available.
     - In the "Selected box" box, arrange the bams in order according to the desired study:

       - somatic: matched pairs (in the order of *tumor followed by normal*)

       - trio: triples (in the order of *father, mother, child*)

     - Note: missing index files will be generated automatically at run time. If necessary, bam files will be sorted as well. You may opt to save a copy of such generated files to the storage location where the results of the computaions are sent.



3. Execution Profile

   This series of tabs allows users to configure computations at a high level across multiple tools or at a low level across individual tools. The collection of selected tools and corresponding parameters comprise the *execution profile*. There are three classes of tabs: 1) Quick Setup, 2) Individual tools, and 3) Post-discovery tools.


   - **Quick Setup**:

     The four main tasks available here are optional and independent of one another. At any time you may also optionally visit the other tabs for further configuring such as de/activating any tools appropriately and fine-tuning parameters.

     1. **Start configuring a new execution profile:** Select a pre-defined **Run mode** (germline, somatic, *de novo*/family trio) and/or **Parameter set** (these can be tuned further in each tool tab). Then in Step 3, click :btn:`Apply Profile` to propagate settings to the other tabs.

     2. **Upload execution profile** from uploaded file: Click :btn:`Browse...`, selecting a file from your computer using the dialog box that appears, and then clicking :btn:`Upload File`. Then in Step 3, click :btn:`Apply Profile` to propagate settings to the other tabs.

             **TIP**: Execution profiles may be re-used across different computational configurations to ensure consistency and reproducibility, an approach that may be helpful in analyzing batches of sample sets in piecewise fashion.

     3. **Select genomic regions**: Select one option. For the user-defined list option, you can type or copy-paste directly into the textbox or supply a local file (click :btn:`Browse...`, select local file from the dialog box that appears, click :btn:`Upload File`). The format of the list must be either (a) ``<chr> <start> <stop>`` triples (one per line),  or (b) a comma-separated list such as ``1-4,X,6:1000,5:1000-2000,22``. Prepend the chromosome numbers with ``chr`` if indicated by the reference genome. Click :btn:`Apply Profile` to propagate settings to the other tabs.

     4. :btn:`Reset`: Reinitialize all computational options to their default (possibly empty) values. **CAUTION**: This operation also clears the visible account information (however, sessionIDs are preserved but must be re-entered).



   - **Individual Tools**:

     A selection of tools from among those most often relied upon. GenomeVIP associates these tools with three common study types in the following way:

        ==============  ===================================================
        Germline        VarScan, GATK, BreakDancer, Pindel, Genome STRiP
        Somatic         VarScan, MuTect, Strelka, BreakDancer, Pindel
        *De novo*/Trio  VarScan, BreakDancer, Pindel
        ==============  ===================================================

     Documentation on these tools can be obtained by following the links to the tools' home page in :ref:`Info`.

   - **Post-discovery Tools**:

     Options to filter and annotate raw variants are provided and are applied in the order displayed.

     - Filtering

       - Identify/Remove dbSNP variants: Database options include the dbSNP database (provided on the GenomeVIP runtime image) or a user-supplied VCF file, accessible via public FTP/HTTP/HTTPS or user's/public AWS S3 location.

       - Identify/Remove false positives: This approach is based on the bam-readcount tool and a series of heuristics for identify variant calls of lower quality and is implemented via the VarScan tool. The parameter values shown are considered by some to be generally appropriate.

         **Note**: This option is independent of the false-positives annotation provided by the panel-of-normals option available with MuTect somatic variant discovery.

     - Annotation

       - The Variant Effect Predictor (VEP) software with human genome reference has been installed on the GenomeVIP runtime image. A user-provided VCF file, accessible via public FTP/HTTP/HTTPS or user's/public AWS S3 location, may alternatively be supplied.


4. Submit

   - Computing/storage resources and additional information

     - For AWS:

       - **Select a compute resource**: This can be a new cluster, or, if you have previously instantiated a cluster under the current GenomeVIP SessionID, a running cluster. Re-using an existing resource may have certain cost efficiencies.

       - **Select a "bucket" for storing results**: Buckets are uniquely named directories or folders in AWS's S3 resource. Select an existing bucket, or create a new one by clicking :btn:`Create a new bucket` (the list will update automatically).

         Note: buckets can also be viewed/crated in your S3 Console (see Shortcuts under :ref:`QStart`).

       - **Additional information**:

         - Supply the full paths of any files required by the configuration.
         - Optionally provide a comment that will appear in the generated execution profile.

     - For local clusters:

       - **Select a compute resource**: No user selection is provided here; the resource is actually specified through the fields under :btn:`Accounts`.

       - **Additional information**:

         - Supply the full paths of any files required by the configuration.
         - Optionally provide a comment that will appear in the generated execution profile.
         - Provide the name of a working directory (it will be created, if necessary) into which GenomeVIP will copy the generated execution profile and master job script. This directory is assumed to be relative to your home directory unless overridden by specifying a full path.
         - Select submit action: Choose whether to execute (default is 'yes') the job script in the working directory. (Here, "power users" may wish just to transmit the script for inspection or modification by hand, after which time it can be run as a standard shell script.)


   - Pre-submission checks (available at any time during the configuration process):

     - Click :btn:`Preview` to display the current execution profile, or retrieve it as a file by clicking :btn:`Download`.

     - Click :btn:`Validate` to have GenomeVIP perform basic checks and flag certain misconfigurations.

     - Click :btn:`Submit` to perform the submit action specified above.

     - Finally, clicking :btn:`Reset` sets all options to their default (possibly empty) values. (This is the same behavior described above for Execution Profile > Quick Setup > Reset).


5. Results

   - For AWS:

     - Navigate your web browser to your Amazon S3 Console (see Shortcuts/Tips under :ref:`QStart`)

     - In the list of buckets on the lefthand side, click on the bucket you specified when submitting the job. After the page updates, click on the folder corresponding to the jobID assigned to the computation.

     - The "results" folder contains downloadable files containing variant calls according to sample sets. Inter

     - The "status" folder displays sentinel files indicating which tasks/computations did not go to completion as expected. These filenames can be used to create additional jobs to provide the missing results.


   - For local clusters:

     - Log in to your cluster account.

     - Change to the working directory specified at the time of job submission.

     - The "results" directory contains a summary of variant calls obtained.


6. Options

   This tab provides access to some additional features for working with AWS:

   - AMI specification: The GenomeVIP runtime images are expected to be revised to include updates to the underlying operating system as well as to include minor tool bugfixes and feature enhancements. Here, users may can enter an alternative AMI ID in the specified format for instantiations rather than the default ID as programmed into the GenomeVIP server files.

   - Settings: By default, GenomeVIP employs secure transfers (HTTPS) to/from S3 as implemented by the S3 Tools package and requests S3 server-side encryption, both of which options may be disabled. The default settings are recommended even when working with public data.

   - Cluster management: Running EC2 clusters associated with the current GenomeVIP SessionsID are listed, each having the option to be terminated from GenomeVIP interface instead of from the EC2 Dashboard.


