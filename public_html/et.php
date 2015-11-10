<?php
/* Calls home with usage info
	original author: Adam D Scott
	first created: 2015*10*09
	borrowed flow from: http://davidwalsh.name/curl-post
	Production debugging: R. Jay Mashl
*/
#ini_set('display_errors',1);
#error_reporting(E_ALL & ~E_DEPRECATED);
#print "start\n";
include realpath(dirname(__FILE__)."/"."fileconfig.php");

function callHome( $how ) {
	/* Collect only tools (and version) used, whether AWS or local, and ip. */
	$endline = '<br>';
	$endl = "\n";
	#echo "et".$endline;
	#print "check POST\n";
	if ( isset( $_POST ) ) {
		#print "good POST\n";
		$fieldString = collectUsage();

		$phonedResult = null;
		if ( strcmp( $how , 'mail' ) == 0 ) {
			$phonedResult = mailHome( $fieldString );
		} else {
			$phonedResult = curlHome( $fieldString );
		}
	}
	#echo "end transmission".$endline;
	return $phonedResult;
}


function mailHome( $fieldString ) {
        global $homemail, $homesubject, $homeheaders; // from fileconfig.php
	return mail( $homemail , $homesubject , $fieldString , $homeheaders );
}

function curlHome( $fieldString ) {
        global $homecurl; // from fileconfig.php
	$curl = curl_init();
	curl_setopt( $curl , CURLOPT_URL , $homecurl );
	curl_setopt( $curl , CURLOPT_POST , count( explode('&',$fieldString)) );
	curl_setopt( $curl , CURLOPT_POSTFIELDS , $fieldString );
	$result = curl_exec( $curl );
	curl_close( $curl );
	return $result;
}

function collectUsage() {
        global $tool;
	$fields_string = ""; #returned
	$fields = array();
	$fields['caller'] = urlencode($tool);
	foreach ( $_POST as $element => $value ) { #collect usage info
		#if $value is array, then handle differently
		if ( ( strcmp( $element , 'vs_cmd' ) == 0 && isset( $_POST['vs_cmd'] ) ) ||
			( strcmp( $element , 'vs_version' ) == 0  && isset( $_POST['vs_version'] ) ) ||
			( strcmp( $element , 'strlk_cmd' ) == 0  && isset( $_POST['strlk_cmd'] ) ) ||
			( strcmp( $element , 'bd_cmd' ) == 0  && isset( $_POST['bd_cmd'] ) ) ||
			( strcmp( $element , 'pin_cmd' ) == 0  && isset( $_POST['pin_cmd'] ) ) ||
			( strcmp( $element , 'gs_cmd' ) == 0  && isset( $_POST['gs_cmd'] ) ) ||
			( strcmp( $element , 'strlk_version' ) == 0  && isset( $_POST['strlk_version'] ) ) ||
			( strcmp( $element , 'bd_version' ) == 0  && isset( $_POST['bd_version'] ) ) ||
			( strcmp( $element , 'pin_version' ) == 0  && isset( $_POST['pin_version'] ) ) ||
			( strcmp( $element , 'version_gs' ) == 0  && isset( $_POST['version_gs'] ) ) ||
			( strcmp( $element , 'compute_target' ) == 0  ) ||
			( strcmp( $element , 'bam_count' ) == 0  ) ) {
			$fields[$element] = urlencode( $value );
		}
		#print $element." => ".$value.$endl;
	}

	$address = "noProxy_";
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { #if proxy
		$address = $_SERVER['HTTP_X_FORWARDED_FOR']."_";
	}
	$address .= $_SERVER['REMOTE_ADDR']."_";
	$address .= $_SERVER['REQUEST_URI'];
	$fields['from'] = urlencode( $address );
	$tmp_arr = array();
	foreach( $fields as $key => $value ) { #construct message to send
	    array_push( $tmp_arr, $key.'='.$value );
	}
	$fields_string = implode('&', $tmp_arr );

	#echo $fields_string.'<br>';
	#echo $message.$endline;
	#echo "dialing".$endline;

	return $fields_string;
}

?>
