<?php
/************************************************************************************************

	breathe_in.php

	Process the DET call from the Breathe Consent survey

	If data parameters are correct (a separate parental consent is required) then write the
	required data into the Breathe Parental consent project. This will result in a survey 
	invitation being sent to the parent.

*************************************************************************************************/

	require 'globals.php';
	require 'det_database.php';			/* set up DB connection details */

	$LOGFILE = $LOG_PATH.'/det.log';
	$LOGLEVEL= 1;						/* Log all debug output */
	$APPNAME = "breathe_test";			/* Will be used to identify the application db record */

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

	/* Check that the DET was fired by the correct form */

	if ($det_record["instrument"] != $_POST["instrument"])
	{
		writelog("Instrument name not verified",0);
		exit();
	}

	writelog("DET record returned = ".print_r($det_record,true),3);

	
	/* Retrieve the instrument data from REDCap and validate:
		process was initiated by a child			[parent_youth]	= 2
		an that the child is in the right age group	[age_group]		= 1
	*/

	$redcap_data = redcap_get_records(	$det_record['url']."api/",			# url
										$det_record['token'],				# token
										array($_POST["record"]),			# record
										'',									# events
										array($_POST["instrument"])			# form
									);

	if (!$redcap_data)
	{
		writelog("Unable to retrieve instrument data from REDCap.",1);
		exit();
	}

	writelog("parent_youth = ".$redcap_data[0]["parent_youth"]." age_group = ".$redcap_data[0]["age_group"],2);

	if ($redcap_data[0]["parent_youth"] != "2" or $redcap_data[0]["age_group"] != "1")
	{
		writelog("Participant category and/or age group do not match criteria.",1);
		exit();
	}

	/*	If we've got this far then we have a valid trigger and the age group matches.
		We can now insert the required data into the target form.
	*/

	$target_rec['record_id']		= $redcap_data[0]["record_id"];
	$target_rec['p_email']			= $redcap_data[0]["p_email"];
	$target_rec['p_name']			= $redcap_data[0]["prntname"];
	$target_rec['y_name']			= $redcap_data[0]["yfnametxt"];
	$target_rec['details_complete']	= '2';

	writelog("Output record = ".print_r($target_rec,true),2);

	if (!redcap_put_records(	$det_record['url']."api/",
								$det_record['target_token'],
								$target_rec,
								0
							)) 
	{
		writelog("Unable to insert target record.",1);
		exit();
	}

	writelog("Finished",0);
	exit();
?>
