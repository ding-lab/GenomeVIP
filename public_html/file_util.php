<?php
// --------------------------------------
// @name GenomeVIP file utilities
// @version
// @author R. Jay Mashl <rmashl@genome.wustl.edu>
// --------------------------------------

function verify_rel_homedir( &$p ) {
  if ( ! preg_match('#^[/~]#', $p) ) {
    $p = "~/" . $p; 
  }
}
?>
