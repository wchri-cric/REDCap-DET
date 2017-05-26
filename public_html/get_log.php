<?php
/******************************************************

	Script to log GET variables to a file.

********************************************************/

	require 'globals.php';

	$logfile = $LOG_PATH.'/det.log';	/* define an output file for logging */

	$now=nowstr();
	$get_vars="";

	/* Cycle through the GET data to retrieve the individual variables and concatonate them into a string */

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
		file_put_contents($logfile, $now." GET: ".$get_vars."\n", FILE_APPEND);
	}
	else
	{
		file_put_contents($logfile, $now." GET: No variables\n", FILE_APPEND);
	}
?>
	
