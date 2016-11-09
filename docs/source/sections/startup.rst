.. role:: btn
.. role:: btnb
.. _QStart:

###########
Quick Start
###########

Using Amazon-hosted GenomeVIP server
------------------------------------

**SHORTCUTS / TIPS**

- The **AWS Management Console** lists Shortcuts and Recently Viewed Services, Quick Starts, and AWS Services. It can be reached in two ways:

    a) Once logged in to your AWS account, select the menu item Services > Console Home, if that option appears; or

    b) click on the orange box icon in the upper left corner of the page.

- The **EC2 Dashboard** can be reached in two ways:

    a) Once logged in to your AWS account, select the menu item Services > EC2, if that option appears; or

    b) click on the orange box icon in the upper left corner of the page, and then in section AWS Services below, in section "Compute", select EC2.

- The **list of running instances** can be reached from your EC2 Dashboard in two ways:

    a) in the left-hand panel, select the menu item Instances > Instances; or

    b) in the Resources section at the top of the page, click the link "Running Instances".

- The **S3 Console** can be reached in two ways:

    a) Once logged in to your AWS account, select the menu item Services > S3, if that option appears; or

    b) click on the orange box icon in the upper left corner of the page, and then in section AWS Services below, in section "Storage & Content Delivery", select S3.


**PROCEDURE**

A. Selecting server image

   #. Log in to your AWS account and navigate to the EC2 Dashboard.

   #. In the left-hand panel, in the Images menu, click on "AMIs".

   #. Locate the filter/search field and enter ``genomevip``. Then in the adjacent menu to the left, select "Public images". The table below will update automatically.

   #. Under "AMI Name", locate the GenomeVIP server images, and select one (the most recent version is recommended) by checking the corresponding checkbox in the left column. *Security tip: verify that the image has Owner 785242596344.* When ready to proceed to the image configuration step, click on :btnb:`Launch` near the top of the page.


B. Configuring the server

   #. Select an instance type (minimum recommendation: 1 or more vCPUs with 4 GiB memory, e.g. ``m1.medium``).

   #. Click on item "3. Configure Instance" near the top of the page, and then set the following:

      =========================  ======================= =====================================================================================
      OPTION                     VALUE                   COMMENT
      =========================  ======================= =====================================================================================
      Number of instances:       1
      Network:                   EC2 Classic             Virtual private cloud (VPC) should also work
      Availability Zone:         No preference           **IMPORTANT:** This generally much match those of any/all EBS data volumes requested to use
      IAM role:                  None                    Choice may vary depending on how your AWS account has been configured for your use
      =========================  ======================= =====================================================================================


   #. Click on item "6. Configure Security Group" near the top of the page. (Items 4 and 5 are not necessary.) The suggested security group information is likely adequate. On the left-hand side farther down the page, click the button :btn:`Add Rule`, and then in the dropdown menu that appears, select :btn:`HTTPS` and leave the port range set at "443", which GenomeVIP expects. Your server instance is currently usable by anyone; if more security is desired, modify the source IP address setting.

   #. Click on item "7. Review" at the top of the page, and if the displayed information is in order, click on :btnb:`Launch` in the bottom right corner of the page.

   #. Selecting key pair. The dialog pop-up box allows you to use an existing, or create a new, key pair. Selecting "Process without a key pair" is sufficient when instantiating a GenomeVIP server simply for running analyses. (Developers will want to use a key pair instead.) Check the acknowledgment box, and then click on :btnb:`Launch Instances`.

   #. Launch Status. Review the information displayed, and then to view instances on your EC2 Dashboard, click on :btnb:`View Instances` in the bottom right corner of the page.



C. Accessing the user interface

   #. Navigate your browser to the list of running instances, locate the running instance of interest, and check the corresponding checkbox in the left column. The panel at the bottom of the web page now displays information about the instance.

   #. In that lower panel, the "Description" tab should already be active (if not, click on it to make it active). Note the entry Public IP (hereafter referred to as <publicIP>), which you will need to access the GenomeVIP interface.

   #. Point your web browser to ``https://<publicIP>/~genomevip`` to reach the GenomeVIP home page.

          Note: you may receive a warning notification (Your connection is not secure, <publicIP> uses an invalid security certificate, Error code: SEC_ERROR_UNKNOWN_USER, Can't verify the identity of the website, etc.) because we have used a self-signed certificate. You will need to add an exception in order to proceed further. More information can be obtained from, e.g. `Mozilla support`_.

.. _Mozilla support: https://support.mozilla.org/en-US/kb/troubleshoot-SEC_ERROR_UNKNOWN_ISSUER

D. Configuring computations: see :ref:`GeneralUsage`

E. Shutting down the server

      All EC2 instances in "running" mode contribute to the charges billed to your account. After you submit your analysis, you may wish to change this mode (accessible from the "list of instances" page, under menu item :btn:`Actions` > Instances State):

      a) **Stop**:  put the instance into sleep/hibernate mode
      b) **Start**:  put the instance into running mode at a new address (see Step C above)
      c) **Terminate**:  end the instance entirely


Using locally hosted GenomeVIP server
-------------------------------------

This section applies to installations on physical machines as well as on virtualization software products (e.g. VirtualBox).


**PROCEDURE**

A. Navigate your web browser to the appropriate site or address (e.g. *\https://192.168.57.1/~genomevip*).

     The actual address may vary depending on how the underlying web server running GenomeVIP was configured and, accordingly, where GenomeVIP was installed. Check with your administrator, if needed.

B. Configuring computations: see :ref:`GeneralUsage`.

C. Shutting down the server

     This step is likely needed only in virtual machine deployments; refer to your virtualization software documentation.

