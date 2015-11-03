<?php
/* Calls home with usage info
	author: Adam D Scott
	first created: 2015*10*09
	borrowed flow from: http://davidwalsh.name/curl-post
*/
#ini_set('display_errors',1);
#error_reporting(E_ALL & ~E_DEPRECATED);
#print "start\n";

function callHome() {
  # (rjm) make local instead
  include realpath(dirname(__FILE__)."/"."fileconfig.php");
	/* Collect only tools (and version) used, whether AWS or local, and ip. */
	$endline = '<br>';
	$endl = "\n";
	#echo "et".$endline;
	#print "check POST\n";
	if ( isset( $_POST ) ) {
		#print "good POST\n";
		$fields = array();
		foreach ( $_POST as $element => $value ) { #collect usage info
			#if $value is array, then handle differently
		        # (rjm) strcmp, if used, should equate to zero
			if ( ( strcmp( $element , 'vs_cmd' )==0 && isset( $_POST['vs_cmd'] ) ) ||
				( strcmp( $element , 'vs_version' )==0 && isset( $_POST['vs_version'] ) ) ||
				( strcmp( $element , 'strlk_cmd' )==0 && isset( $_POST['strlk_cmd'] ) ) ||
				( strcmp( $element , 'bd_cmd' )==0 && isset( $_POST['bd_cmd'] ) ) ||
				( strcmp( $element , 'pin_cmd' )==0 && isset( $_POST['pin_cmd'] ) ) ||
				( strcmp( $element , 'gs_cmd' )==0 && isset( $_POST['gs_cmd'] ) ) ||
				( strcmp( $element , 'strlk_version' )==0 && isset( $_POST['strlk_version'] ) ) ||
				( strcmp( $element , 'bd_version' )==0 && isset( $_POST['bd_version'] ) ) ||
				( strcmp( $element , 'pin_version' )==0 && isset( $_POST['pin_version'] ) ) ||
				( strcmp( $element , 'version_gs' )==0 && isset( $_POST['version_gs'] ) ) ||
				( strcmp( $element , 'compute_target' )==0 ) ||
				( strcmp( $element , 'bam_count' )==0 ) ) {
				$fields[$element] = urlencode( $value );
			}
			#print $element." => ".$value.$endl;
		}
		$fields['caller'] = urlencode( $tool );

		$address = "noProxy_";
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { #if proxy
			$address = $_SERVER['HTTP_X_FORWARDED_FOR']."_";
		}
		$address .= $_SERVER['REMOTE_ADDR'];
		$fields['from'] = urlencode( $address );
		$fields_string = "";
		foreach( $fields as $key => $value ) { #construct message to send
			$fields_string .= $key.'='.$value.'&';
		}
		rtrim( $fields_string , '&' );

		#echo $fields_string.'<br>';
		#echo $message.$endline;
		#echo "dialing".$endline;

		$curl = curl_init();
		curl_setopt( $curl , CURLOPT_URL , $home );
		curl_setopt( $curl , CURLOPT_POST , count($fields) );
		curl_setopt( $curl , CURLOPT_POSTFIELDS , $fields_string );
		$result = curl_exec( $curl );
		curl_close( $curl );
		#echo "curl result: ".$result.$endline;
	}
	#echo "end transmission".$endline;
}
?>