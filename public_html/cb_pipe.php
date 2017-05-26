<?php
/************************************************************************************************

	cb_pipe.php

	Process a DET call and write checkbox data into a text variable

*************************************************************************************************/

	require 'globals.php';
	require 'det_database.php';			/* set up DB connection details */

	$LOGFILE = $LOG_PATH.'/det.log';
	$LOGLEVEL= 2;						/* Only log important info */
	$APPNAME = "cb_pipe";				/* Will be used to identify the application db record */


	/*	Check that the POST data contains a REDCap URL. If this fails then
		the POST has not originated from a REDCap system and can be discarded.
	*/

	if (!redcap_checkurl())
		exit();

	if (!logPostVars())
		return;	

	/*	Connect to the database and get the configuration record. If this fails then
		we are not configured to handle this request.

		If this is successful then $TOKEN will contain the API token.
	*/

	$db=dbconnect($DBHOST, $DBUSER, $DBPSWD, $DBNAME);
	if (!$db) exit();

	$det_record=(get_token($db));	
	mysqli_close($db);
	if (!$det_record) exit();

	/* Retrieve the instrument metadata from REDCap */

	$redcap_metadata = redcap_get_metadata(	$det_record['url']."api/",			# url
											$det_record['token'],				# token
											'',									# fields
											array($_POST["instrument"])			# form
									);

	if (!$redcap_metadata)
	{
		writelog("Unable to retrieve instrument metadata from REDCap.",1);
		exit();
	}

	/* Retrieve the instrument data from REDCap */

	$redcap_data = redcap_get_records(	$det_record['url']."api/",			# url
										$det_record['token'],				# token
										array($_POST["record"]),			# record
										array($_POST["redcap_event_name"]),	# event
										array($_POST["instrument"])			# form
									);

	if (!$redcap_data)
	{
		writelog("Unable to retrieve instrument data from REDCap.",1);
		exit();
	}

	/* Create an array to hold the output data */

	$target_rec['record_id']			= $redcap_data[0]["record_id"];
	$target_rec['redcap_event_name']	= $redcap_data[0]["redcap_event_name"];

	writelog("Output record = ".print_r($target_rec,true),2);

	/* Loop through the metadata for the form. If the variable type is checkbox and the field annotation
		contains @CBPIPE then process the checkboc field.
	*/

	$write_output=false;

	foreach ($redcap_metadata as $variable)
	{
		writelog("Processing variable metadata ".print_r($variable,true),2);
		
		if ($variable["field_type"] == "checkbox" and strpos($variable["field_annotation"],"@CBPIPE") !== false)
		{
			$write_output=true;

			writelog("Variable ".$variable["field_name"]." is a checkbox that requires piping",2);
			$cbvars=redcap_split_options($variable["select_choices_or_calculations"],$variable["field_name"]."___");

			writelog("REDCap Data = ".print_r($redcap_data,true),2);

			$outputFieldName=$variable["field_name"]."_cbp";
			$target_rec[$outputFieldName] = "-";

			foreach($cbvars as $varnam => $value)
			{
				writelog("Processing ".$varnam,2);

				writelog("REDCap Data: ".$varnam." = ".$redcap_data[0][$varnam],2);

				if ($redcap_data[0][$varnam]=="1")
				{
					writelog("Data for ".$varnam." is ".$redcap_data[0][$varnam]." so append ".$value." to target_rec[".$outputFieldName."]",2);
					if ($target_rec[$outputFieldName] != "-")
						$target_rec[$outputFieldName]=$target_rec[$outputFieldName].", ".$value;
					else
						$target_rec[$outputFieldName]=$value;

					writelog("Output data ".print_r($target_rec,true),2);
				}
			}
		}
		else
			writelog("Variable ".$variable["field_name"]." is not a checkbox that requires piping",2);
	}
	
	if ($write_output)
	{
		$ret=redcap_put_records(	$det_record['url']."api/",
									$det_record['token'],
									$target_rec,
									0
								);
		writelog("redcap_put_records() returned:".$ret,2);

		if (!$ret)
		{
			writelog("Unable to insert target record.",1);
			exit();
		}
	}

	writelog("Finished. Records written:".$ret,1);
	exit();
?>
