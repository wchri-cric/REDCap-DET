<?php
	require 'globals.php';
	$LOGFILE = $LOG_PATH.'/det.log';
	$APPNAME = "DET_TEST";

	if (!logPostVars())
		return;	

	if (!validateUrl($_POST["redcap_url"]))
		return;

	$api_token = getApiToken($_POST["project_id"]);
	if ($api_token = false)
		return;

	
?>
	
