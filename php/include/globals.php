<?php
/* This file will contain golbal variables function definitions, etc. for use by all DET handling code */

/* Global variables */

$LOG_PATH="../php/logs";


/* Functions */

function nowstr()
{
	/* return the current date and time in a string format suitable for a timestamp */
	return date("Y-m-d H:i:s");				
}

function writelog($logstr, $level)
{
	global $LOGFILE;
	global $APPNAME;
	global $LOGLEVEL;

	#	echo "Writelog $LOGLEVEL, $logstr, $level";

	/* Log levels

	0 = None
	1 = Normal - Errors & important information
	2 = Debug Information
	*/

	#file_put_contents($LOGFILE, "LOGLEVEL $LOGLEVEL level $level \n",FILE_APPEND);

	# Only write to the log if $level is <= the current $LOGLEVEL
	if ($level > $LOGLEVEL) 
		return;

	if (!$APPNAME) {
		$APPNAME="UNKNOWN";
	}

	$now=nowstr();
	file_put_contents($LOGFILE, $now." ".$APPNAME." ".$logstr."\n", FILE_APPEND);
}

function redcap_checkurl()
{
	if ($_POST["redcap_url"]) {
		writelog("Request received from: ".$_POST["redcap_url"],1);
		return(true);
	}
	writelog("REDCap URL not found in request data",1);
	return(false);
}
		

function logPostVars()
{
	/* Cycle through the POST data to log the individual variables */

	$post_vars="";

	if ($_POST)
	{
		$kv = array();
		foreach ($_POST as $k => $v)
		{
		    	if (is_array($v)):
		        	$temp = array();

					foreach ($v as $v2)
					{
						$temp[] = $v2;
					}

					$kv[] = "$k=" . join("|", $temp);
			else:
				$kv[] = "$k=$v";
			endif;
		}
		$post_vars = join("&", $kv);
	}

	if ($post_vars)
	{
		writelog("POST: ".$post_vars,2);
		return true;
	}
	else
	{
		writelog("POST: No variables",1);
		return false;
	}
}

function logGetVars()
{
	/* Cycle through the GET data to log the individual variables */

	$get_vars="";

	if ($_GET)
	{
		$kv = array();
		foreach ($_GET as $k => $v)
		{
		    	if (is_array($v)):
		        	$temp = array();

					foreach ($v as $v2)
					{
						$temp[] = $v2;
					}

					$kv[] = "$k=" . join("|", $temp);
			else:
				$kv[] = "$k=$v";
			endif;
		}
		$get_vars = join("&", $kv);
	}

	if ($get_vars)
	{
		writelog("GET: ".$get_vars,2);
		return true;
	}
	else
	{
		writelog("GET: No variables",1);
		return false;
	}
}


function validateUrl($url)
{
	# Search for the given URL in the list of approved URLs
	# we won't need this if we're using the database instead of a file
	# see below

	require 'allowed_urls.php';
	if (in_array($url,$ALLOWED_URLS))
	{	
		writelog("Source URL validated - ".$url,1);
		return true;
	}
	else
	{
		writelog("Invalid source URL - ".$url,1);
		return false;
	}
}

function getApiToken($pid)
{
	# Get a valid API token for the project
	# we won't need this if we're using the database instead of a file
	# see below

	require 'project_tokens.php';

	if (array_key_exists($pid,$PROJECT_TOKENS))
	{
		writelog("API token found for project ".$pid,2);		
		return $PROJECT_TOKENS[$pid];
	}
	else
	{
		writelog("API token not found for project ".$pid,1);
		return false;
	}
}

function dbconnect($host, $user, $pwd, $name)
{
	/* connect to the databse */
	$db = mysqli_connect($host,$user,$pwd,$name);
	if (mysqli_connect_errno($db))
	{
		writelog("DB connection failed ".mysqli_connect_error(),1);
		return(0);
	}
	else {
		writelog("DB connection successful",2);
		return $db;
	}
}

function get_token($db)
{
	global 	$TOKEN, $APPNAME;

	/*	Connects to the specified database and attempts to retrieve a token based on
		the URL and PID in the _POST data.
	
		Returns	1 	if URL/PID exist in the table and is enabled
				0	if URL/PID cannot be found or is not enabled

		Sets $TOKEN the value of the token, which may be null, if the record is
		found. (The script may not need a token, in which case we are just
		validating that the project is approved for this script.)
	*/

	$query="select * from project_tokens where det_name='$APPNAME' and url='".$_POST["redcap_url"]."' and pid='".$_POST["project_id"]."'";
	writelog("get_token query: ".$query,2);
	$result = mysqli_query($db,$query);
	if (!$result)
		return(0);
	
	$row=mysqli_fetch_assoc($result);
	if ($row) {
		writelog("get_token: Configuration data retrieved",2);
		writelog("get_token: Configuration data: ".print_r($row,true),3);
	}
	else
	{
		writelog("get_token: No configuration record retrieved for ".$_POST["redcap_url"],1);
		return(0);
	}

	if ($row['enabled']=0) {
		writelog("get_token: Configuration record is not enabled.",1);
		return(0);
	}

	return($row);
}

function redcap_get_metadata($url, $token, $fields, $forms)
{
	#Retrieves metadata from REDCap

	$request = array(
		'token'   => $token,
		'content' => 'metadata',
		'format'  => 'json',
		'type'    => 'flat',
		'fields'  => $fields,
		'forms'   => $forms
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
		writelog("redcap_get_metadata(): Curl request not successful ". $errstr,1);
		curl_close($ch);
		return(false);
	}
	curl_close($ch);
	writelog("Retrieved metadata from REDCap",2);
	writelog("redcap_get_metadata(): curl output ".$output,2);
	$out_array=json_decode($output,true);
	writelog("redcap_get_metadata() decoded output: ".print_r($out_array,true),2);
	return($out_array);
}

function redcap_get_records($url, $token, $records, $events, $forms)
{
	#Retrieves data from REDCap

	$request = array(
		'token'   => $token,
		'content' => 'record',
		'format'  => 'json',
		'type'    => 'flat',
		'records' => $records,
		'events'  => $events,
		'forms'   => $forms
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
		writelog("redcap_get_records(): Curl request not successful ". $errstr,1);
		curl_close($ch);
		return(false);
	}
	curl_close($ch);
	writelog("Retrieved data from REDCap",2);
	writelog("redcap_get_records(): curl output ".$output,2);
	$out_array=json_decode($output,true);
	writelog("redcap_get_records() decoded output: ".print_r($out_array,true),2);
	return($out_array);
}

function redcap_put_records($url, $token, $record, $overwriteflag )
{
	# Write data into REDCap project. Return the number of records written.

	if ($overwriteflag)	{
		$overwrite = 'overwrite';	}
	else	{
		$overwrite = 'normal';	}

	writelog("redcap_put_records() parameters - URL: ". $url . " Token:" . $token . " Overwrite flag: " . $overwriteflag,2);
	writelog("redcap_put_records() decoded output: ".print_r($record,true),2);

	$data = json_encode( array( $record ) );

	$fields = array(
		'token' 		 	=> $token,
		'content' 			=> 'record',
		'format'  			=> 'json',
		'type'    			=> 'flat',
		'overwriteBehavior' => $overwrite,
		'data'    			=> $data,
		'returnContent'		=> 'count',
    	'returnFormat'		=> 'json'
	);

	writelog("redcap_put_records() CURL data: ".print_r($fields,true),2);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
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
		writelog("redcap_put_records(): Curl request not successful ". $errstr. " ". $output, 1);
		curl_close($ch);
	}

	curl_close($ch);
	
	$out_array=json_decode($output,true);

	if (!isset($out_array["count"]))
	{
		writelog("redcap_put_records() failed to write output data:".print_r($output,true),1);
		return(false);
	}

	writelog("redcap_put_records() success. Returning:".$out_array["count"],2);
	return((int)$out_array["count"]);
}

function redcap_split_options($str,$prefix)
{
	# splits a REDCap option list into an array.
	# The array index will be based on the coded value. Optionally the prefix parameter can be used to
	# create indexex that match the export column neme (checkboxes).

	$arr=explode("|",$str);
	foreach ($arr as $str)
	{
		$commapos=strpos($str,",");
		$index=strtolower(trim(substr($str,0,$commapos)));
		$value=trim(substr($str,$commapos+1));
		$list[$prefix.$index]=$value;
		writelog("OptionList: pos ".$commapos." index ".$index." value ".$value,2);
	}

	writelog("OptionList: ".print_r($list,true),2);
	return($list);
}
?>
