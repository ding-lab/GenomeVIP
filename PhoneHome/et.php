<?php
/* Calls home with usage info
	author: Adam D Scott
	first created: 2015*10*09
	borrowed flow from: http://davidwalsh.name/curl-post
*/
$endline = '<br>';
$endl = "\n";
$home = "localhost:8888/portal/PhoneHome/phoneHomeOperator.php";

$client = "localhost:8888/portal/PhoneHome/test.html";
#echo "et".$endline;
if ( isset( $_POST ) ) {
	$fields = array();
	foreach ( $_POST as $element => $value ) { #collect usage info
		#if $value is array, then handle differently
		$fields[$element] = urlencode( $value );
	}
	$address = "noProxy_";
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
?>
