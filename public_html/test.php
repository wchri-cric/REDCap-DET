<?php
	require 'globals.php';

	$LOGFILE = $LOG_PATH.'/det.log';
	$LOGLEVEL= 3;						/* Log all debug output */
	$APPNAME = "test";			/* Will be used to identify the application db record */

	if (!logGetVars())
		return;	
?>
