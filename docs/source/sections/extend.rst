.. role:: btn

###################
Extending GenomeVIP
###################

GenomeVIP's server and runtime environments can be customized, updated, or extended. Some examples of how developers might extend its capabilities are given below.

- Updates to tools or to the operating system not requiring user interface changes in the
  server environment can be readily carried out. On AWS, a new runtime image would be generated
  whose AMI ID may be furnished to the server as an alternative image via :btn:`Options`.

- Tool updates (or new tools) requiring user interface modification: a full description is too
  large to fit in the margin, but suffice it to say the process involves steps such as
  installing the tool in the runtime environment, updating path information in server
  environment (see files ``configsys/tools.info.*``), and modifying the various HTML, PHP, and
  text files underlying the user interface content and functionality. Insight into this process
  may be able to be gleaned from the GenomeVIP repository's commit 08e22db entitled "added gatk
  module". Finally, on AWS, new images of both environments would then be generated.

- Custom/New execution profiles may be installed into the server environment and added to the
  list of options available to users. See the profile files ``*.prof`` inside directories
  ``configsys/profiles/`` and ``configsys/run_modes/``). On AWS, a new server image would need
  to be generated.

