<?php

	require 'globals.php';
	$LOGFILE = $LOG_PATH.'/det.log';
	$APPNAME = "RCREDIRECT";
	$LOGLEVEL= 2;						/* Log all debug output */

	function redcap_get_bookmark($url,$authkey)
	{
		#Retrieves data from REDCap

		$request = array(
			'authkey'   => $authkey,
			'format'  	=> 'json'
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request, '', '&'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

		$output = curl_exec($ch);

		if (!$output || curl_errno($ch) !== CURLE_OK)
		{
			$errstr = 'cURL error (' . curl_errno($ch) . '): ' . curl_error($ch);
			print "RCRedirect: $errstr <br>";
			writelog("redcap_get_bookmark(): ".$errstr,1);
			curl_close($ch);
			return(false);
		}
		curl_close($ch);
		writelog("Retrieved data from REDCap",2);
		writelog("redcap_get_bookmark(): curl output ".$output,2);
		$out_array=json_decode($output,true);
		writelog("redcap_get_bookmark() decoded output: ".print_r($out_array,true),2);
		return($out_array);
	}

	if (!logGetVars())
	{
		print("RCRedirect: No GET variables");
		exit();
	}

	if (!logPostVars())
	{
		print("RCRedirect: No POST variables");
		exit();
	}

	$redcap_data=redcap_get_bookmark($_GET["target"],$_POST["authkey"]);

	if (!$redcap_data)
	{
		writelog("Unable to retrieve instrument data from REDCap.",1);
		print("RCRedirect: Unable to retrieve instrument data from REDCap");
		exit();
	}

	/* get the callback URL excluding the project ID */

	$pos = strrpos($redcap_data["callback_url"], '/');
	if (!$pos)
	{
		writelog("No callback_url found.",2);
		print("RCRedirect: No callback_url found.");
		exit();		
	}

	if ($_GET["record"])
	{
		$callback_url = substr($redcap_data["callback_url"],0,$pos+1)
			."DataEntry/record_home.php?pid=".$_GET["target_pid"]."&id=".$_GET["record"];
	}
	else
	{
		$callback_url = substr($redcap_data["callback_url"],0,$pos+1)
			."DataEntry/record_home.php?pid=".$_GET["target_pid"];
	}

	/* Check to make sure callback is to a UofA system */

	if (!strrpos($callback_url, "ualberta.ca"))
	{
		writelog("Callback URL is not valid ualberta.ca.",2);
		print("RCRedirect: Callback URL is not valid ualberta.ca.");
		exit();	
	}

	writelog("Redirecting to Callback URL ".$callback_url,2);

	header("Location: ".$callback_url);
	exit();
?>
	
