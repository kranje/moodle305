<?php

//require_once "../../../config.php";
//require_once($CFG->dirroot . '/local/jics/jics_lib.php');
require_once($CFG->libdir. '/moodlelib.php');
require_once($CFG->libdir. '/dml/moodle_database.php');
define ('JICS_ADMIN_ID','427DC8E3-E4EE-4178-9A66-33447620E61E') ;

function html_br(){
	if (isset($_SERVER['REMOTE_ADDR'])) {
		return "\n<br />";
	}
	else {
		return "\n";
	}
}
function is_dbg($plugin) {
	
	if (($plugin != null) && get_config($plugin,'debug_trace')== true){
		return true;
	}
	else {
		return false;
	}
}
function is_noredirect($plugin){
	if ($plugin != null && get_config($plugin,'debug_noredirect')== true){
		return true;
	}
	else {
		return false;
	}
}
function dbg($plugin,$file,$line,$msg='Execution Trace'){
	// the caller can pass its plugin name or a null
	//echo html_br(),"DBG: ",$file,":",$line,": dbg called with plugin= ", $plugin ;
	if ($plugin == null || get_config($plugin,'debug_trace')== true){
		echo html_br(),"DBG: ",$file,":",$line,": ",$msg ;
	}
	else { 
		return ; 
	}
}
// ignore bad name of this function. the 1st parameter is the coursecode-term-section string, not the shortname!
function shortname_to_id ($crs_term_section_name){

    global $DB;
    // handle switch from JICS course-section name to Moodle course id
    // we will need to make this translation to support "deep" seamless login from JICS
    $course_id = 1 ; // Moodle home page in case lookup fails.
	$crs=$DB->get_record('course', array("idnumber"=>"{$crs_term_section_name}"));
	// 2013-10-02: bug fix for case where JICS enrollment exists but course does not yet exist in Moodle
	if (!$crs || get_config('local_jics','course0_redirect') == 1) { 
		return ($course_id)  ; 
	}
	else {
		return ($crs->id); 
	}
}
 /*
 *	If upload_suspendmaxtime configuration setting is set for this plugin
 *  then set it to desired value.
 *  @param	int	$plugin	Plugin whose setting we must check
 *  @param	int	$maxtime New value to set. Default is 0 (suspend max time limit).
 *  Return	int	Original value. 
 * 	Note that even if we make no change we always return the original value.
 */
function reset_max_execution_time($plugin, $newval=0) {
	$origval = ini_get('max_execution_time');
	if ($plugin != null && get_config($plugin,'upload_suspendmaxtime') != false && $newval != $origval ){ 
		set_time_limit($newval) ;
	}		
	return $origval;
}
function setup_administrator(){
	global $DB ;
	// who are current admins?
	$admins_id_string = get_config(NULL,'siteadmins') ;
	$admins_id_arr = explode(',',$admins_id_string) ;
	// does JICS Administrator account exist?
	$admin_rec=$DB->get_record('user', array("idnumber"=>JICS_ADMIN_ID));
	if (!$admin_rec) {
		// no, must create the account
		// user does not yet exist in Moodle
		$user = new object();
		$user->id = 0;     // User does not exist
		$user->auth='jics'; // could hard code it but ...
		$user->password = '';
		$user->idnumber = JICS_ADMIN_ID ;
		// set temporary email that will update on next bulk upload
		$user->email = 'administrator' . random_string(5).'@jics.edu' ;
		$user->username= 'administrator';
		$user->confirmed = 1;
		$user->lastip = getremoteaddr();
		$user->timemodified = time();
		$user->mnethostid = get_config(NULL,'mnet_localhost_id');
		try {
			// Following throws exception if there is an error!
			$id = $DB->insert_record('user', $user);
			$admins_id_string .= ",{$id}" ;
			return (set_config("siteadmins","{$admins_id_string}")) ;
		}
		catch (Exception $e) {
			error_log("[LOCAL_JICS] Error trying to create jics administrator account: " . $e->getMessage() );
			return false;
		}
	}
	else {
		// yes, it exists
		// is it already a sitadmin?
		if ($admin_rec->id) {
			return true;
		}
		else {
			$admins_id_string .= ",{$admin_rec->id}" ;
			return (set_config("siteadmins","{$admins_id_string}")) ;
		}
	}
}
?>