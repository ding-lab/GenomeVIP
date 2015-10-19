<?php
/* Calls home with usage info
	author: Adam D Scott
	first created: 2015*10*09
	borrowed flow from: http://davidwalsh.name/curl-post
*/
#ini_set('display_errors',1);
#error_reporting(E_ALL & ~E_DEPRECATED);
#print "start\n";
include "fileconfig.php";
function callHome() {
	/* Collect only tools (and version) used, whether AWS or local, and ip. */
	$endline = '<br>';
	$endl = "\n";
	#global $home;
	#$home = "localhost:8888/portal/PhoneHome/phoneHomeOperator.php";

	#$client = "localhost:8888/Portals/GenomeVIP/PhoneHome/test.html";
	#echo "et".$endline;
#print "check POST\n";
	if ( isset( $_POST ) ) {
#print "good POST\n";
		$fields = array();
		foreach ( $_POST as $element => $value ) { #collect usage info
			#if $value is array, then handle differently
			if ( ( strcmp( $element , 'vs_cmd' ) && isset( $_POST['vs_cmd'] ) ) ||
				( strcmp( $element , 'vs_version' ) && isset( $_POST['vs_version'] ) ) ||
				( strcmp( $element , 'strlk_cmd' ) && isset( $_POST['strlk_cmd'] ) ) ||
				( strcmp( $element , 'bd_cmd' ) && isset( $_POST['bd_cmd'] ) ) ||
				( strcmp( $element , 'pin_cmd' ) && isset( $_POST['pin_cmd'] ) ) ||
				( strcmp( $element , 'gs_cmd' ) && isset( $_POST['gs_cmd'] ) ) ||
				( strcmp( $element , 'strlk_version' ) && isset( $_POST['strlk_version'] ) ) ||
				( strcmp( $element , 'bd_version' ) && isset( $_POST['bd_version'] ) ) ||
				( strcmp( $element , 'pin_version' ) && isset( $_POST['pin_version'] ) ) ||
				( strcmp( $element , 'version_gs' ) && isset( $_POST['version_gs'] ) ) ||
				( strcmp( $element , 'compute_target' ) ) ||
				( strcmp( $element , 'bam_count' ) ) ) {
				$fields[$element] = urlencode( $value );
			}
#print $element." => ".$value.$endl;
		}

		$address .= "noProxy_";
		if ( $_SERVER['HTTP_X_FORWARDED_FOR'] != Null ) { #if proxy
			$address = $_SERVER['HTTP_X_FORWARDED_FOR']."_";
		}
		$address .= $_SERVER['REMOTE_ADDR']."_";
		$address .= $_SERVER['REQUEST_URI'];
		$fields['from'] = urlencode( $address );
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

	#this doesn't redirect
	#header( 'Location: localhost:8888/portal/PhoneHome/test.html');
	#header( 'Location: '.$client );
}
?>
