<?php
/************************************************************************************************

	breathe_out.php

	Process the DET call from the Breathe Parental Consent survey

	If data parameters are correct (parent has consented) then write the required data into
	the Breathe Consent Process project. This will result in a survey invitation being sent to
	the participant.

*************************************************************************************************/

	require 'globals.php';
	require 'det_database.php';			/* set up DB connection details */

	$LOGFILE = $LOG_PATH.'/det.log';
	$LOGLEVEL= 2;						/* Log all debug output */
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

	## rewrite from here...

	/* Retrieve the instrument data from REDCap and validate	*/

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

	writelog("yn_consent = ".$redcap_data[0]["yn_consent"],2);

	/* If the parent has not consented then exit.	*/

	if ($redcap_data[0]["yn_consent"] != "1")
	{
		writelog("Consent has not been given. yn_consent=".$redcap_data[0]["yn_consent"],1);
		exit();
	}

	/* Check that the target record exists...	*/

	$temp_data = redcap_get_records(	$det_record['url']."api/",					# url
										$det_record['target_token'],				# token
										array($_POST["record"]),					# record
										'',											# events
										array($_POST["participant_registration"])	# form
									);

	if (!$redcap_data)
	{
		writelog("Participant record does not exist in target project. Record=".$_POST["record"],1);
		exit();
	}

	/*	If we've got this far then we have a valid trigger, the parent has consented and
		the participant exists in the target project.
		We can now insert the required data back into the target project form.
	*/

	$target_rec['record_id']		= $_POST["record"];
	$target_rec['p_consent']		= "1";
	$target_rec['child_survey___1']	= "1";
	$target_rec['separate_parental_consent_complete']	= '2';

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
