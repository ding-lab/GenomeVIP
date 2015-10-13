<?php
/* Receiver collecting phone home messages & writes them to unique files (on S3?)
	author: Adam D Scott
	first created: 2015*10*08
*/
echo "hello".'<br>';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	echo "its post".'<br>';
	$transmission = json_encode( $_POST );
	echo "conversation: ".'<br>'.$transmission.'<br>';
	$proxy = "noProxy";
	if ( $_SERVER['HTTP_X_FORWARDED_FOR'] != Null ) {
		$proxy = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	$ip = $_SERVER['REMOTE_ADDR'];
	echo $proxy." ".$ip.'<br>';
	$times = split( " " , microtime() );
	$now = array_sum( $times );
	$log = implode( "." , array( "received" , $proxy , $ip , $now ) ).".log";
	$yadi = fopen( $log , "w" );
	fwrite( $yadi , $transmission."\n" );
	fclose( $yadi );
}
echo "goodbye".'<br>';
?>
