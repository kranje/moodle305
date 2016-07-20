<?php
// sync.php
// Handle all sync functions by invoking relevant script(s) of plugins
// Called as $wantsurl by /login/index from /jics/index.php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/jics/jics_lib.php');
require_once($CFG->libdir . '/dml/moodle_database.php');
require_once($CFG->libdir .'/enrollib.php');
require_once($CFG->dirroot.'/enrol/jics/lib.php');
global $SESSION ;

try{
	// auth jics sync
	if (strtolower($_SESSION["custom_ldap_sync_mode"]) != 'none') {
		$right_now_display = date("Y-m-d H:i:s",time());
		echo html_br(),"User data upload started at ", $right_now_display ;	
		require_once($CFG->dirroot.'/auth/jics/auth.php');		
		$authplugin = get_auth_plugin('jics');

        if (!method_exists($authplugin, 'do_sync')) {
			error_log("[LOCAL_JICS] Error loading auth/jics plugin to run User Synchronization");
			die("auth/jics plugin or do_sync method not found");
		}
			
		$retval = $authplugin->do_sync();
		$right_now_display = date("Y-m-d H:i:s",time());
		//Might need to check $script_tz = date_default_timezone_get(); to see if it is not set or  is GMT
		//and if so explain that times displayed are GMT ????

		if ($retval == 1) {
			// failure
			echo html_br(),"Error during User data upload at ", $right_now_display ;
			echo html_br(),"Aborting data transfer. Exiting.";
			exit(1); // error
		}
		else {
			echo html_br(),"User data upload completed at ", $right_now_display ;
		}
	}	
	else {
		echo html_br(),"User data upload not requested. ";	
	}
	// If we are here then proceed with enrollment sync
	if (strtolower($_SESSION["custom_xdb_sync_mode"]) != 'none') {
		$right_now_display = date("Y-m-d H:i:s",time());
		echo html_br(),html_br(),"Enrollment data upload started at ", $right_now_display ;	
		if ($_SESSION["custom_xdb_sync_startdate"] != '') {
			$startdate = $_SESSION["custom_xdb_sync_startdate"];
			//echo html_br(),"Filter: Enrollments in courses with startdate >= ", $startdate ;	
		}
		else {
			$startdate = 0;
		}	
		
		echo html_br(),"Filter: Enrollments in courses with startdate >= ", $startdate ;

		

		$enrols = enrol_get_plugins(true);

		$jics_enroll_plugin = $enrols['jics'];
		
		//*****************************************************
		/*if($startdate == 0){
			$time = new DateTime('now');
			$newtime = $time->modify('-1 year')->format('Y-m-d');
			$startdate = $newtime;

			mtrace(html_br(). "STARTDATE: " . $startdate);
		}*/
		mtrace(html_br(). "STARTDATE: " . $startdate);
		//*****************************************************

		$retval = $jics_enroll_plugin->sync_enrolments($startdate);

		$right_now_display = date("Y-m-d H:i:s",time());
		if ($retval == 1) {
			// failure
			echo html_br(),"Error during Enrollment data upload at ", $right_now_display ;
			echo html_br(),"Aborting data transfer. Exiting.";
			exit;
		}
		else {
			echo html_br(),"Enrollment data upload completed at ", $right_now_display ;
		}
	}
	else {
		echo html_br(),"Enrollment data upload not requested. ";	
	}

	echo html_br(),html_br(),html_br(),html_br(),"Synchronization completed.",html_br();			
}
catch (Exception $e) {
	echo html_br(),html_br(),"Error performing synchronization:",html_br(),$e;
}
?>
<script type="text/javascript">
function close_window() {
	window.close();
}
</script>
<br />
<hr />
<br />
<input type="button" onclick="javascript:close_window();" value="Close Window" />