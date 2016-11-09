.. _Build:

##################
Building GenomeVIP
##################

GenomeVIP is divided conceptually into server and runtime environments, the manifestations of
which depend on several factors, including the expected user base (i.e., casual vs. power
users) and method for access (centralized server, personal virtual machine, etc.). Our general
recommendation is to install the genomics software systematically into a user-accessible
location before building the server, as the server environment contains configuration files (in
`INI`_ format) that will be modified to point to the locations of these genomics tools.

|

**Runtime Environment (General Instructions)**

- If the runtime build involves installing an operating system (on hardware) or instantiating a base operating system (on AWS, etc.), you should generally install all package updates.

- Select a location for installing software, such as /usr/local, a home directory, common workgroup disk, etc.

- Download and install named software on to your target machine. (Links to software home pages are provided in :ref:`Info`.) For example, our Amazon runtime images have keypairs like

  .. code-block:: none

     [samtools]
     path=/usr/local/bin/samtools/1.2/bin
     version=1.2
     exe=samtools

  **CAUTION**: Some software carries mimimum version requirements of Java. For example, GATK-3.5 does not support JRE 7u51, but we find it does support 7u80.

- Modify the paths in GenomeVIP's ``configsys/tools.info.*`` files to agree with your installed software.

- On AWS: Create an image (e.g. AWS > EC2 Dashboard > Actions > Image > Create Image) from this instance and note the machine image ID (AMI). This AMI ID can be pre-programmed into the server environment as the default runtime image (see file ``versions.php``). Additionally, if operating system up runtime image.

|

**Server Environment (General Instructions)**

- Install an operating system (on hardware) or instantiate a base operating system (on AWS, etc.)

  **TIP**: the GenomeVIP server has been installed and run successfuly under Ubuntu on Amazon EC2, VirtualBox, and OpenStack platforms.

- Install all package updates; then install a web server (e.g. Apache), PHP, and mod-php package families

- Configure the web server:

  - Use HTTPS only

  - Disable insecure SSL protocols

  - Add/Enable quality SSL ciphers

  - Allow the directory serving GenomeVIP to run PHP scripts

- Install GenomeVIP:

  - Download the application to the serving directory

    .. code-block:: none

       git clone https://github.com/ding-lab/GenomeVIP.git

- Post-installation

  - Create an image (e.g. AWS > EC2 Dashboard > Actions > Image > Create Image) to preserve the installation for future use.


.. _INI: http://en.wikipedia.org/wiki/INI_file

