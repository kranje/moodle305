<?php
/**
 * JICS Enrollment plugin.
 *
 * This plugin handles enrollments for the JICS-Moodle integration.
 *
 * @package    enrol
 * @subpackage jics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Database enrolment plugin implementation.
 * @author  Petr Skoda - based on code by Martin Dougiamas, Martin Langhoff and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_jics_plugin extends enrol_plugin {
    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance 
     * @return bool
     */
    public function instance_deleteable($instance) {
        if (!enrol_is_enabled('jics')) {
            return true;
        }
        if (!get_config('local_jics','jics_url') or !$this->get_config('dbtype') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            return true;
        }
        return false;
    }
	
	/* AZ 2013-01-10
	*  This cron method is called from generla moodle cron logic, not the separately scheduled enrol/jics/cli/sync.php
	*  Check wait interval.
	*/
    public function cron() {
		global $CFG;
		$lastcron = $this->get_config('lastcron');
		$versionfile = "$CFG->dirroot/enrol/jics/version.php";
        $plugin = new stdClass();
        include($versionfile);
		if (empty($plugin->cron) || ($plugin->cron < 0 )) {
			// skip sync
			mtrace("Skipping JICS enrollment sync.");
			return 0;
		}
		// Following code will never execute if is_cron_required() in enrollib.php functions correctly.
		// But I will keep it here as extra safeguard, and also to cover case where we want to call cron() directly
		// for some reason.
		else if ($lastcron + $plugin->cron >= time()) {
            mtrace("JICS Enrollment plugin wait interval {$plugin->cron} not yet exceeded.");
			return 0;
		}
		else {
			//mtrace(html_br()."Starting ENROL_JICS user synchronization at " . userdate(time()) . html_br());
			mtrace("Starting ENROL_JICS user synchronization at " . userdate(time()) ." GMT");
			$uploadmode = $this->get_config('upload_mode') ; // Add/Drop or None
			if (strtolower($uploadmode)=='none') {
				mtrace("Enrollment upload mode set to None. Will skip enrollment synchronization.");
				return 0;
			}
			$datefilter = $this->get_config('start_date') ? $this->get_config('start_date') : 0 ;
			mtrace("\ndatefilter setting is {$datefilter}");


			$retval = $this->sync_enrolments( $datefilter) ;
			mtrace("\nCompleted ENROL_JICS user synchronization at " . userdate(time()) . " GMT\n");

			return $retval ; //0=success, 1=error
		}
    } // end of function
	
	
	/* AZ 2013-01-10
	*  This will be the target of the CLI sync.php separately scheduled as a cron job.
	*  No need to test the wait interval since it has its own schedule!
	*  Write a file to leave a trace.
	*/
 
    public function cli_cron() {

		global $CFG;
		/* for customers not logging the execution of scheduled cron jobs the following may be used
		 * to track the execution of cron executions of the JICS enrollment synchronization code.
		*/
		/* Uncomment this out if you want to use it... */
		try{
			$filepath = $CFG->dataroot.'/enrol_cron.log';
			$fhandle=fopen($filepath,'a') ;
			$data = 'The JICS enrollment plugin wrote this record at ' . date("Y-m-d H:i:s"). PHP_EOL;
			fwrite($fhandle, $data);
			fclose($fhandle);
		}
		catch (Exception $exception) {
			error_log("[ENROL_JICS] Exception " . $exception->getMessage() . " trying to open or write " . $filepath . " Will continue anyway.");
			continue;
		}
		

		mtrace("\nCompleted ENROL_JICS user synchronization at " . userdate(time()) . " GMT\n");
		$uploadmode = $this->get_config('upload_mode') ; // Add/Drop or None
		if (strtolower($uploadmode)=='none') {
			mtrace("\nEnrollment upload mode set to None. Will skip enrollment synchronization.\n");
			return 0;
		}
		$datefilter = $this->get_config('start_date') ? $this->get_config('start_date') : 0 ;
		mtrace("\ndatefilter setting is {$datefilter}");
		// AZ 2014-01-09 Add try... catch block
		try {
			$retval = $this->sync_enrolments( $datefilter) ;
			mtrace(html_br()."Completed ENROL_JICS user synchronization at " . userdate(time()) . " GMT\n");
		}
		catch (Exception $e){
			mtrace(html_br()."\nDBG: Failure in ENROL_JICS cli_cron() at " . userdate(time()) . " GMT\n");
			mtrace(html_br()."\nDBG: Returning 1\n");
			return 1 ;
		}
		return $retval ; //0=success, 1=error
    } // end of function

	/**
     * Forces synchronisation of user enrolments with external database,
     * does not create new courses.
     *
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $CFG, $DB;
		require_once ("{$CFG->dirroot}/enrol/jics/enrol_jics_lib.php");	// for jics_enrollment_webservice class
		//dbg("enrol_jics_",__FILE__,__LINE__ ," Entering sync_user_enrolments for User = {$user->id}. ");

		if ( strtolower($user->auth) != 'jics') { 
			return 1;
		}

        if (!get_config('local_jics','jics_url') ) {
			error_log('[ENROL_JICS] You must first enable the Local JICS plugin before enabling the JICS Enrollment plugin.');
			return 1 ;
		}
        if (!get_config('local_jics','jics_url') or !$this->get_config('dbtype') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
			return 1;
        }
        $table            = "TEMPORARY HACK" ; //$this->get_config('remoteenroltable');
        $coursefield      = strtolower($this->get_config('remotecoursefield'));
        $userfield        = strtolower($this->get_config('remoteuserfield'));
        $rolefield        = strtolower($this->get_config('remoterolefield'));

        $localrolefield   = $this->get_config('localrolefield');
        $localuserfield   = $this->get_config('localuserfield');
        $localcoursefield = $this->get_config('localcoursefield');

        $unenrolaction    = $this->get_config('unenrolaction');
        $defaultrole      = $this->get_config('defaultrole');

        $ignorehidden     = $this->get_config('ignorehiddencourses');

        if (!is_object($user) or !property_exists($user, 'id')) {
            throw new coding_exception('Invalid $user parameter in sync_user_enrolments()');
        }

        if (!property_exists($user, $localuserfield)) {
            debugging('Invalid $user parameter in sync_user_enrolments(), missing '.$localuserfield);
            $user = $DB->get_record('user', array('id'=>$user->id));
        }
		// If the user does not have an idnumber then the user's enrollments should not be retrieved from JICS !
		if ($user->$localuserfield == null) {
			error_log("[ENROL_JICS]User idnumber for user {$user->id} is null. This user does not have JICS-based enrollments.");
			return;
		}
		// create an jics_enrollment_webservice object to receive data
		$enrollment_service = new jics_enrollment_webservice();
		if (!$enrollment_service->init()) {
			// abort
			error_log('[ENROL_JICS] Could not initialize remote enrollment web service.');
			return;	
		}

		// get recordset $mapped_roles_rs of relevant role objects
		$mapped_roles_rs = $this->get_mapped_roles();

		if ($mapped_roles_rs == null) {
			error_log('[ENROL_JICS] No roles are mapped. Empty enrollment_roles configuration setting.');
			return;	
		}

        $roles = array();
        foreach ($mapped_roles_rs as $role) {
            $roles[$role->$localrolefield] = $role->id;
        }
		$mapped_roles_rs->close();

		// 2013-01-17 Now doing single pass for role '*' !!!
		//foreach ($roles as $localrolefieldvalue=>$current_roleid) {		

			$enrols = array();
			$instances = array();

			// instead of querying external database we get an XML stream and process each records as an associative array
			
			// get enrollments for ANY role the user has
			//$xmldoc=$enrollment_service->get_enrollments(0,$user->$localuserfield,$localrolefieldvalue);
			$xmldoc=$enrollment_service->get_enrollments(0,$user->$localuserfield,'*');

			// if status is 0 do quick exit!
			if ($xmldoc == null || $xmldoc->status == 0) {
				error_log('[ENROL_JICS] Error status from remote enrollment web service.');
				return;
			}
			// in new scheme there are no other roles to try so just return bereft of enrollments altogether
			else if ($xmldoc->count == 0) {
				error_log("[ENROL_JICS] NO enrollment returned by remote enrollment web service for user.");
				return;
			}
			foreach ($xmldoc->enrollment as $one_record) {
				$one_record = array_change_key_case((array)$one_record, CASE_LOWER);
                $one_record = $this->db_decode($one_record);	
				if (empty($one_record[$coursefield])) {
                    // missing course info
					error_log("[ENROL_JICS] Missing coursefield in XML record. Will try next record.");
                    continue; //try next record
                }
				if (!$course = $DB->get_record('course', array($localcoursefield=>$one_record[$coursefield]), 'id,visible')) {
					// Note use of "editingteacher" in next line. This is the string returned from JICS no matter what the Moodle shortname is.
					if ( $one_record[$rolefield] != 'editingteacher') {
						error_log( "Course {$one_record->coursecodeyrtermsection} does not exist, but user is not an instructor. Skipping this course.\n",'notifysuccess');
						continue;
					}
					else if (!$this->get_config('enrol_autocreate_onlogin')) { // autocreation on login not allowed
						//error_log( "Course {$one_record->coursecodeyrtermsection} does not exist, but course autocreation on login is disabled. Skipping this course.\n",'notifysuccess');
						continue;
					}
					else if ($this->get_config('allowdeepcopy') && $this->get_config('deepcopy')) { // deepcopy option allowed and turned on so autocreate on login disallowed
						//error_log( "Course {$one_record->coursecodeyrtermsection} does not exist, but Deep Copy is turned on. Skipping this course.\n",'notifysuccess');
						continue;
					}
					else { // try creating the course
						//dbg("enrol_jics",__FILE__,__LINE__ ,"Trying to create course ". $one_record["coursecodeyrtermsection"] );
						// get course data for this course
						$crsdoc=$enrollment_service->get_courses('0', '*', '*', $one_record["coursecodeyrtermsection"]) ;
						//dbg("enrol_jics",__FILE__,__LINE__ ,": Returned from call to get_courses");
						foreach ($crsdoc->enrollment as $one_crs_record) { // should really only be 1
							//dbg("enrol_jics",__FILE__,__LINE__ ,"Processing course {$one_crs_record->coursecodeyrtermsection}");
							$tval_div = array_key_exists('div',$one_crs_record->description) ;
							// Following needed because portal only courses end up with "<div>" in description field!
							if (!array_key_exists('div',$one_crs_record->description)) {
								$one_description = $one_crs_record->description ; 
							}
							else {
								$one_description = $one_crs_record->description->div ; 				
							}
						}
						//dbg("enrol_jics",__FILE__,__LINE__ ,"");
						$course = new StdClass;
						$course->{$localcoursefield} = "{$one_crs_record->coursecodeyrtermsection}";
						$course->fullname  = $this->make_fullname($one_crs_record->coursecodeyrtermsection,
							$one_crs_record->title ,
							$one_crs_record->coursecode); 
						$course->shortname = $this->make_shortname($one_crs_record->coursecodeyrtermsection, 
							$one_crs_record->title ,
							$one_crs_record->coursecode); 
						$course->summary = "{$one_description}";
						$course->startdate  = $this->make_startdate($one_crs_record->startdate) ;
						$course->enddate  = $this->make_enddate($one_crs_record->enddate) ;

						$template = false;
						if ($template_crs=$this->get_config('templatecourse')) {
							//dbg("enrol_jics",__FILE__,__LINE__ ,"Looking for template course with shortname {$template_crs} ");
							if ($template = $DB->get_record('course', array("shortname"=>"{$template_crs}"))) {
								//dbg("enrol_jics",__FILE__,__LINE__ ,"Template course {$template_crs} found for course autocreation.");
								$templateid = $template->id; //save id for deep copy
							}
						}

						$transaction = $DB->start_delegated_transaction();	
						//dbg("enrol_jics",__FILE__,__LINE__ ,"");
						if (!($newcourseid = $this->create_course($course)
						 and $course = $DB->get_record('course', array('id'=>$newcourseid), '*', MUST_EXIST)  )) {
							error_log( "Creating course {$one_crs_record->coursecodeyrtermsection} failed");
							// no choice... abort!
							//dbg("enrol_jics",__FILE__,__LINE__ ,"Trying to rollback failed course autocreation of {$one_crs_record->coursecodeyrtermsection}");
							try { 
								$transaction->rollback(new Exception("Could not autocreate course {$one_crs_record->coursecodeyrtermsection}."));
							}
							catch (Exception $e) {
								error_log( "Error rolling back failed course creation of {$one_crs_record->coursecodeyrtermsection} ");
							}
							if ( get_config('enrol_jics','email_on_error') == true && 
									($send_to = get_config('', 'supportemail' )) != null  ) {
								// send email to special send_to account
								$subject = "[ENROL_JICS] Error autocreating course {$one_record->coursecodeyrtermsection} on instructor login." ;
								$message = "JICS enrollment plugin cron execution failed to autocreate course {$one_record->coursecodeyrtermsection} on instructor login." ;
								$stat = mail($to, $subject, $message);
							}							
							exit(1);	// better to exit than continue?
						}
						else { 
							//dbg("enrol_jics",__FILE__,__LINE__ ,"Created course {$one_crs_record->coursecodeyrtermsection}"); 
							error_log( "Created course {$one_crs_record->coursecodeyrtermsection} on instructor login.");
						}
						$transaction->allow_commit();
						//dbg("enrol_jics",__FILE__,__LINE__ ,"Commit of course creation of {$one_crs_record->coursecodeyrtermsection} succeeded.");
					}
				}
				else {
					//dbg("enrol_jics",__FILE__,__LINE__ ,"Course {$one_record->coursecodeyrtermsection} already exists.") ;
				}
				if (!$course->visible and $ignorehidden) {
					//dbg("enrol_jics",__FILE__,__LINE__ ,"Course {$one_record->coursecodeyrtermsection} is hidden and ignorehidden set to true.") ;
					continue; // try next record
				}
                if (empty($one_record[$rolefield]) or !isset($roles[$one_record[$rolefield]])) {
                    if (!$defaultrole) {
						error_log("[ENROL_JICS] No default role. Will try next record.");
                        continue;
                    }
					//Use default role
                    $roleid = $defaultrole;
                } 
				else {
                    $roleid = $roles[$one_record[$rolefield]];
                }
				if (empty($enrols[$course->id])) {
					$enrols[$course->id] = array();
				}
				
                $enrols[$course->id][] = $roleid;
				mtrace(html_br() . __FILE__ . ":" . __LINE__);
				if ($instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'jics'), '*', IGNORE_MULTIPLE)) {
				mtrace(html_br() . __FILE__ . ":" . __LINE__);
					$instances[$course->id] = $instance;
				}
				else {
				mtrace(html_br() . __FILE__ . ":" . __LINE__);
					$enrolid = $this->add_instance($course);
					$instances[$course->id] = $DB->get_record('enrol', array('id'=>$enrolid));
				}
			}	
			mtrace(html_br() . __FILE__ . ":" . __LINE__);
			foreach ($enrols as $courseid => $roles) {
				if (!isset($instances[$courseid])) {
					continue;
				}
				$instance = $instances[$courseid];
				// NB: instance->id in following is id of mdl_enrol row
				if ($e = $DB->get_record('user_enrolments', array('userid'=>$user->id, 'enrolid'=>$instance->id))) {
					//dbg("enrol_jics",__FILE__,__LINE__ ," Found existing user_enrolment record for user {$user->id} and mdl_enrol row {$instance->id}");
					// reenable enrolment when previously disable enrolment refreshed
					if ($e->status == ENROL_USER_SUSPENDED) {
						// az 2013-01-11 Handle suspended and removed roles!
						//dbg("enrol_jics",__FILE__,__LINE__ ," User was suspended in course. Calling enrol_user and update_user_enrol");
						$roleid = reset($roles);
						//dbg("enrol_jics",__FILE__,__LINE__ ," roleid, instance parameters passed to enrol_user are {$roleid} , {$instance->id}");
						$this->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
						//dbg("enrol_jics",__FILE__,__LINE__ ," Calling update_user_enrol for user {$user->id} passing instance parameter {$instance}");
						$this->update_user_enrol($instance, $user->id, ENROL_USER_ACTIVE);
					}
				} 
				else {	// this is what caused trouble before...
					//dbg("enrol_jics",__FILE__,__LINE__ ," There was no existing user_enrolment for user {$user->id} and mdl_enrol row {$instance->id} so we must create one");
					$roleid = reset($roles);
					//dbg("enrol_jics",__FILE__,__LINE__ ," roleid,instance->id of instance parameters passed to enrol_user are {$roleid} , {$instance->id}"); 
					$this->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
				}

				// AZ 2016-02-15
				//fif (!$context = get_context_instance(CONTEXT_COURSE, $instance->courseid)) {
				if (!$context = context_course::instance($instance->courseid)) {
					//weird case
					//dbg("enrol_jics",__FILE__,__LINE__ ," Weird case. instance->courseid={$instance->courseid}");
					continue;
				}
				
				$current = $DB->get_records('role_assignments', array('contextid'=>$context->id, 'userid'=>$user->id, 'component'=>'enrol_jics', 'itemid'=>$instance->id), '', 'id, roleid');

				$existing = array();
				foreach ($current as $r) {
					if (in_array($r->roleid, $roles)) {
						//dbg("enrol_jics",__FILE__,__LINE__ ," in_array TRUE: user has role {$r->roleid} in this context");
						$existing[$r->roleid] = $r->roleid;
						//dbg("enrol_jics",__FILE__,__LINE__ ," Added role to so-called existing array");
					} 
					else {
						//dbg("enrol_jics",__FILE__,__LINE__ ," in_array FALSE: user does not already have role {$r->roleid} in this context");
						//dbg("enrol_jics",__FILE__,__LINE__ ," calling role_unassign passing role {$r->roleid}, user {$user->id}, context {$context->id}, item {$instance->id}");
						role_unassign($r->roleid, $user->id, $context->id, 'enrol_jics', $instance->id);
					}
				}
				foreach ($roles as $rid) {
					//dbg("enrol_jics",__FILE__,__LINE__ ," processing roles array for current XML record to assign roles. rid={$rid}");
					if (!isset($existing[$rid])) {
						//dbg("enrol_jics",__FILE__,__LINE__ ," role {$rid} not set in so-called existing array");
						role_assign($rid, $user->id, $context->id, 'enrol_jics', $instance->id);
					} 
					// else { dbg("enrol_jics",__FILE__,__LINE__ ," role {$rid} was set in so-called existing array. Do nothing here."); }
				}
			}
			// unenrol as necessary
			//dbg("enrol_jics",__FILE__,__LINE__ ," About to do unenrollment based on current data not found in XML stream");
			$sql = "SELECT e.*, c.visible AS cvisible, ue.status AS ustatus
					  FROM {enrol} e
					  JOIN {user_enrolments} ue ON ue.enrolid = e.id
					  JOIN {course} c ON c.id = e.courseid
					 WHERE ue.userid = :userid AND e.enrol = 'jics'";
			$rs = $DB->get_recordset_sql($sql, array('userid'=>$user->id));
			//dbg("enrol_jics",__FILE__,__LINE__ ," user: {$user->id} sql: {$sql}");
			foreach ($rs as $instance) {
				if (!$instance->cvisible and $ignorehidden) {
					//dbg("enrol_jics",__FILE__,__LINE__ ," course not visible and ignorehidden true");
					continue;
				}
				// AZ 2016-02-15
				//if (!$context = get_context_instance(CONTEXT_COURSE, $instance->courseid)) {
				if (!$context = context_course::instance($instance->courseid)) {
					//weird case
					//dbg("enrol_jics",__FILE__,__LINE__ ," Weird case");
					continue;
				}

				if (!empty($enrols[$instance->courseid])) {
					// we want this user enrolled
					//dbg("enrol_jics",__FILE__,__LINE__ ," enrols array contains course {$instance->courseid}. Continue loop...");
					continue;
				}
				// At this point we must remove an existing enrollment of this user and
				// deal with enrolments removed from external table
				if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
					// unenrol
					//dbg("enrol_jics",__FILE__,__LINE__ ," Option 1. unenrol_user for user {$user->id} and instance->courseid {$instance->courseid}");
					$this->unenrol_user($instance, $user->id);

				} 
				else if ($unenrolaction == ENROL_EXT_REMOVED_KEEP) {
					//dbg("enrol_jics",__FILE__,__LINE__ ," Option 2. Do nothing.");
					// keep - only adding enrolments

				} 
				else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND or $unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
					// disable
					if ($instance->ustatus != ENROL_USER_SUSPENDED) { 
						//dbg("enrol_jics",__FILE__,__LINE__ ," Option 3. Disable enrollment but leave role.");
						//dbg("enrol_jics",__FILE__,__LINE__ ," Calling update_user_enrol passing mdl_enrol instance {$instance->id}, user {$user->id}");						
						$this->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
					}
					if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
						//dbg("enrol_jics",__FILE__,__LINE__ ,"Option 4: calling role_unassign_all passing contextid {$instance->id}, userid {$user->id} and itemid {$instance->id}");	
						role_unassign_all(array('contextid'=>$context->id, 'userid'=>$user->id, 'component'=>'enrol_jics', 'itemid'=>$instance->id));
					}
				}
				//else {
					//dbg("enrol_jics",__FILE__,__LINE__ ," Case else");
				//}
			}
			$rs->close();
		//}	// end for each role
		//dbg("enrol_jics",__FILE__,__LINE__ ," Exiting sync_user_enrolments.");
	}	// end function
	
	/* 
     * Forces synchronisation of all enrolments with external database.
     *
	 * @param bool $startdate:	ignore courses starting before this date. 0 = process all courses
     * @return int 0 means success, 1 failure
	 *
 	 * AZ 2012-04-02 Following is adapted from Moodle 1.9 version and from Inaki's Moodle 2.2 version
	 * of an LDAP-based plugin, even though we do not use LDAP for the enrollment data. It was just solid
	 * code that was convenient to use as a point of departure. The additional functionality in this plugin
	 * includes a "starting term date" with which to filter enrollment data, as well as the ability to 
	 * dynamically associate categories with autocreated courses. The use of templates is also somewhat more
	 * extensive. Also added some additional course creation options...
	 * NOTE: if 'none' was configured as the upload_mode for this plugin we do not ever get here!
	 * Cron will not call us (see above), and a push from JICS will return from /local/jics/sync.php before calling us.
	 * NOTE: we are more generous in generating debug traces to STDOUT since users will not see this.
	 * That is not the case with sync_user_enrolments() above.	 
     */
    public function sync_enrolments($startdate = 0) {
		global $CFG, $DB;
		require_once ('enrol_jics_lib.php');	// for jics_enrollment_webservice class
        if (!get_config('local_jics','jics_url') or !$this->get_config('dbtype') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            mtrace(html_br().'User enrolment synchronisation skipped.');
            return 0;
        }

        mtrace(html_br().'Starting user enrolment synchronisation...');
 
		// create an jics_enrollment_webservice object to receive data
		$enrollment_service = new jics_enrollment_webservice();
		if (!$enrollment_service->init()) {
			// abort
			dbg("enrol_jics",__FILE__,__LINE__ ,": Error initializing jics_enrollment_webservice object.") ;
			error_log('[ENROL_JICS] Could not initialize remote enrollment web service.');
			return 1;	
		}
        // we may need a lot of memory here
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        // second step is to sync instances and users
        $table            = "TEMPORARY HACK" ; // $this->get_config('remoteenroltable');
        $coursefield      = strtolower($this->get_config('remotecoursefield'));
        $userfield        = strtolower($this->get_config('remoteuserfield'));
        $rolefield        = strtolower($this->get_config('remoterolefield'));

        $localrolefield   = $this->get_config('localrolefield');
        $localuserfield   = $this->get_config('localuserfield');
        $localcoursefield = $this->get_config('localcoursefield');

        $unenrolaction    = $this->get_config('unenrolaction');
        $defaultrole      = $this->get_config('defaultrole');
		
		$ignorehidden 	  = $this->get_config('ignorehiddencourses');
		
		// get recordset $mapped_roles_rs of relevant role objects
		$mapped_roles_rs = $this->get_mapped_roles();
		if ($mapped_roles_rs == null) {
			error_log('[ENROL_JICS] No roles are mapped. Empty enrollment_roles configuration setting.');
			return 1;	
		}
        $roles = array();
        foreach ($mapped_roles_rs as $role) {
            $roles[$role->$localrolefield] = $role->id;
        }
		$mapped_roles_rs->close();
		
		foreach ($roles as $localrolefieldvalue=>$current_roleid) {		
			mtrace(html_br().html_br()."****** Processing enrollments for {$localrolefieldvalue} role ******".html_br());
			$xmldoc=$enrollment_service->get_courses($startdate,'*',$localrolefieldvalue,'*');
			dbg("enrol_jics",__FILE__,__LINE__ ,": startdate={$startdate}") ;
			dbg("enrol_jics",__FILE__,__LINE__ ,": localrolefieldvalue={$localrolefieldvalue}") ;
			
			// if status is 0 do quick exit!
			if ($xmldoc->status == 0) {
				error_log('[ENROL_JICS] Error status from remote enrollment web service.');
				return 1; // 0 = success; 1 = failure
			}
			// if count is 0 do quick exit!
			else if ($xmldoc->count == 0) {
				// try the next role...
				mtrace(html_br().'JICS returned no courses to process...'.html_br()); 
				error_log('[ENROL_JICS] NO courses returned by remote enrollment web service for role {$mappedrole}.');
				continue; //continue;
			}
			else {		
				mtrace(html_br()."JICS returned {$xmldoc->count} courses to process...");

				// Otherwise continue...
				$extcourses = array();	// stack of CoursecodeYrtermsection values, needed later for pruning moodle
				foreach ($xmldoc->enrollment as $one_record) {
					mtrace(html_br(). "Processing course {$one_record->coursecodeyrtermsection}");
					$tval_div = array_key_exists('div',$one_record->description) ;
					array_push($extcourses, $one_record->coursecodeyrtermsection);
					// Following needed because portal only courses end up with "<div>" in description field!
					if (!array_key_exists('div',$one_record->description)) {
						$one_description = $one_record->description ; 
					}
					else {
						$one_description = $one_record->description->div ; 				
					}
					// AZ 2016-02-15 Cleaned out mtrace statements, some causing errors
					// must we create this course?
					$course = $DB->get_record('course', array("{$localcoursefield}"=>"{$one_record->coursecodeyrtermsection}")) ;

					if (!$course) {
						//*************************
						// mtrace(html_br(). "DO NOT HAVE THE COURSE RECORD - NEED TO CREATE IT");
						//*************************

						dbg("enrol_jics",__FILE__,__LINE__ ,": Course {$one_record->coursecodeyrtermsection} does not yet exist."); flush();
						if (!$this->get_config('enrol_autocreate')) { // autocreation not allowed
							mtrace(html_br()."Course {$one_record->coursecodeyrtermsection} does not exist, but course autocreation is disabled. Skipping this course.");
							error_log( "Course {$one_record->coursecodeyrtermsection} does not exist, but course autocreation is disabled. Skipping this course.\n",'notifysuccess');
							continue; // next XML record
						}
						// ok, now then let's create it!
						// prepare any course properties we actually have
						$course = new StdClass;
						$course->{$localcoursefield} = "{$one_record->coursecodeyrtermsection}";
						$course->fullname  = $this->make_fullname($one_record->coursecodeyrtermsection,
							$one_record->title ,
							$one_record->coursecode); 
						$course->shortname = $this->make_shortname($one_record->coursecodeyrtermsection, 
							$one_record->title ,
							$one_record->coursecode); 
						$course->summary = "{$one_description}";
						$course->startdate  = $this->make_startdate($one_record->startdate) ;
						$course->enddate  = $this->make_enddate($one_record->enddate) ;
						
						$transaction = $DB->start_delegated_transaction();
						
						//*************************
						mtrace(html_br(). "DELEGATE TRANSACTION STARTED");
						//*************************
						

						if (!($newcourseid = $this->create_course($course)
						 and $course = $DB->get_record('course', array('id'=>$newcourseid), '*', MUST_EXIST)  )) {
							// AZ 2014-01-09 Added next line
							mtrace(html_br()."DBG: " . __FILE__. ":" . __LINE__ . " FAILURE Creating course {$one_record->coursecodeyrtermsection}"); 
							error_log( "Creating course {$one_record->coursecodeyrtermsection} failed");
							 // no choice... abort!

							try { 
								$transaction->rollback(new Exception("Could not autocreate course"));
								
								//*************************
								mtrace(html_br(). "TRY THE TRANSACTION");
								//*************************
							}
							catch (Exception $e) { 
								mtrace(html_br() . __FILE__. ":" . __LINE__ . " Rollback after failure creating course {$one_record->coursecodeyrtermsection}"); 
							}
							if ( get_config('enrol_jics','email_on_error') == true && 
									($send_to = get_config('', 'supportemail' )) != null  ) {
								// send email to special send_to account
								$subject = "[ENROL_JICS] Error autocreating course {$one_record->coursecodeyrtermsection}" ;
								$message = "JICS enrollment plugin cron execution failed to autocreate course {$one_record->coursecodeyrtermsection}" ;
								$stat = mail($to, $subject, $message);
							}
							// AZ 2014-01-09 Replaced exit with return in next lines
							//exit(1);
							return (1);
						}
						else { 
							mtrace(html_br()."Created course {$one_record->coursecodeyrtermsection}"); 
						}
						$transaction->allow_commit();
					}


					//*************************
					//mtrace(html_br(). "THE COURSE EXISTS!!");
					//*************************

					// Enroll the student in the proper role if need be
					// and prune missing enrollments per new external data
					
					// get a list of the JICS student IDs that are enrolled in the course in this role in JICS
					$xmldoc_users=$enrollment_service->get_users($startdate,
						$one_record->coursecodeyrtermsection,
						$localrolefieldvalue);
					// if status is 0 do quick exit!
					if ($xmldoc == null || $xmldoc_users->status == 0) {
						error_log('[ENROL_JICS] Error status from remote enrollment web service:get_users.');
						dbg("enrol_jics",__FILE__,__LINE__ ,": Error calling get_users for {$one_record->coursecodeyrtermsection} in the role {$localrolefieldvalue} "); flush();
						exit(1); // 0=success, 1=error
					}
					mtrace(html_br()."DBG: " . __FILE__. ":" . __LINE__ );
					// AZ 2013-01-27 Allow return of all courses that EVER had an enrollment but which 
					// might now have only 'D' status. UDF returns courses even if no enrollments are active.

					$extenrolments = array();// stack of userid values, needed later for pruning moodle
					dbg("enrol_jics",__FILE__,__LINE__ ,": JICS returned {$xmldoc_users->count} users for {$one_record->coursecodeyrtermsection} in the role {$localrolefieldvalue} "); flush();
					// AZ 2013-01-27
					// Since we now allows service to return courses with all 'D' enrollments, there may well be no enrollments sent, i.e. no <enrollment> elements
					foreach ($xmldoc_users->enrollment as $one_user_record) {
						dbg("enrol_jics",__FILE__,__LINE__ , ": Pushing user id {$one_user_record->userid} onto stack for course {$one_record->coursecodeyrtermsection} in the role {$localrolefieldvalue} ");
						array_push($extenrolments, "{$one_user_record->userid}");
					}
					mtrace(html_br()."DBG: " . __FILE__. ":" . __LINE__ );
					// AZ 2013-01-27 
					// $extenrolments array may well be empty!
					// Now we have all enrolled users in the array.
					// Prune old JICS enrolments
					$sql= "SELECT u.id as userid, u.username, ue.status,ra.contextid, ra.itemid as instanceid
						  FROM {user} u
						  JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.component = 'enrol_jics' AND ra.roleid = :roleid)
						  JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = ra.itemid)
						  JOIN {enrol} e ON (e.id = ue.enrolid)
						  WHERE u.deleted = 0 AND e.courseid = :courseid ";
					dbg("enrol_jics",__FILE__,__LINE__ , ": Testing need to prune enrollments in course	{$course->id} for role {$current_roleid}");
					$params = array('roleid'=>$current_roleid, 'courseid'=>$course->id);
					// AZ 2016-02-15
					//$context = get_context_instance(CONTEXT_COURSE, $course->id);
					$context = context_course::instance($course->id) ;
					mtrace(html_br()."DBG: " . __FILE__. ":" . __LINE__ );
					dbg("enrol_jics",__FILE__,__LINE__ , "");
					mtrace(html_br()."DBG: " . __FILE__. ":" . __LINE__ );
					if (!empty($extenrolments)) {
						dbg("enrol_jics",__FILE__,__LINE__ ," NON-Empty extenrolments array");
						list($ldapml, $params2) = $DB->get_in_or_equal($extenrolments, SQL_PARAMS_NAMED, 'm', false);
						$sql .= "AND u.idnumber $ldapml";
						$params = array_merge($params, $params2);
						unset($params2);
					}
					else {
						dbg("enrol_jics",__FILE__,__LINE__ ," Empty extenrolments array");
						$shortname = format_string($course->shortname, true, array('context' => $context));
						printf( get_string('emptyenrolment', 'enrol_jics') , 
							$current_roleid,$course->shortname) ;	
					}
				
					$todelete = $DB->get_records_sql($sql, $params);

					if (!empty($todelete)) {
						mtrace(html_br(). "There are " .count($todelete). " users to unenroll...");
						$transaction = $DB->start_delegated_transaction();
						foreach ($todelete as $row) {
							$instance = $DB->get_record('enrol', array('id'=>$row->instanceid));
							switch ($this->get_config('unenrolaction')) {
							case ENROL_EXT_REMOVED_UNENROL:
								mtrace(html_br());	// improve display
								dbg("enrol_jics",__FILE__,__LINE__ ,"Option 1. Calling unenrol_user() for user  {$row->userid} in course {$course->shortname}");
								$this->unenrol_user($instance, $row->userid);
								printf( get_string('extremovedunenrol', 'enrol_jics') , 
									$row->username,$course->shortname,$course->id) ;
								break;
							case ENROL_EXT_REMOVED_KEEP:
								// Keep - only adding enrolments
								break;
							case ENROL_EXT_REMOVED_SUSPEND:
								if ($row->status != ENROL_USER_SUSPENDED) {
									$DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
									dbg("enrol_jics",__FILE__,__LINE__ ,"Option 3. Suspending user_enrolments row for user {$row->userid} in course {$course->shortname}");
									printf( get_string('extremovedsuspend', 'enrol_jics') , 
										$row->username,$course->shortname,$course->id) ;
								}
								break;
							case ENROL_EXT_REMOVED_SUSPENDNOROLES:
								if ($row->status != ENROL_USER_SUSPENDED) {
									$DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
								}
								dbg("enrol_jics",__FILE__,__LINE__ ,"Option 4. Calling role_unassign_all() for user  {$row->userid} in course {$course->shortname}");
								role_unassign_all(array('contextid'=>$row->contextid, 'userid'=>$row->userid, 'component'=>'enrol_jics', 'itemid'=>$instance->id));
								printf( get_string('extremovedsuspendnoroles', 'enrol_jics') , 
									$row->username,$course->shortname,$course->id) ;
								break;
							}
						}
						$transaction->allow_commit();
					}
					else {
						mtrace(html_br()."There were no users to unenroll...");
					}
				
					// Add necessary enrol instance if not present yet;
					$sql = "SELECT c.id, c.visible, e.id as enrolid
							  FROM {course} c
							  JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'jics')
							 WHERE c.id = :courseid";
					$params = array('courseid'=>"{$course->id}");
					if (!($course_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
						dbg("enrol_jics",__FILE__,__LINE__ ," Need to create new course enrol instance");
						$course_instance = new stdClass();
						$course_instance->id = $course->id;
						$course_instance->visible = $course->visible;
						$course_instance->enrolid = $this->add_instance($course_instance);
					}
					if (!$instance = $DB->get_record('enrol', array('id'=>$course_instance->enrolid))) {
						continue; // Weird; skip this one.
					}
					if ($ignorehidden && !$course_instance->visible) {
						dbg("enrol_jics",__FILE__,__LINE__ ,"Ignorehidden set to TRUE amd course is not visible");
						continue;
					}
					$transaction = $DB->start_delegated_transaction();
					foreach ($extenrolments as $extenrolmentuser) {
						$sql = 'SELECT id,username,1 FROM {user} WHERE idnumber = ? AND deleted = 0';
						$member = $DB->get_record_sql($sql, array("idnumber"=>"{$extenrolmentuser}"));
						if(empty($member) || empty($member->id)){
							if(empty($member)) { dbg("enrol_jics",__FILE__,__LINE__ ,'empty(member) case');}
							if (empty($member->id)) { dbg("enrol_jics",__FILE__,__LINE__ ,'empty(member->id) case');}
							printf( get_string('couldnotfinduser', 'enrol_jics') , $extenrolmentuser) ;
							continue;
						}
						else {
							dbg("enrol_jics",__FILE__,__LINE__ ,"member->username={$member->username}");
						}

						$sql= "SELECT ue.status
								 FROM {user_enrolments} ue
								 JOIN {enrol} e ON (e.id = ue.enrolid)
								 JOIN {role_assignments} ra ON (ra.itemid = e.id AND ra.component = 'enrol_jics')
								WHERE e.courseid = :courseid AND ue.userid = :userid";
						$params = array('courseid'=>$course->id, 'userid'=>$member->id);
						dbg("enrol_jics",__FILE__,__LINE__ ,"");
						//print_r($params);
						dbg("enrol_jics",__FILE__,__LINE__ ," sql= {$sql}");
						$userenrolment = $DB->get_record_sql($sql, $params);
						dbg("enrol_jics",__FILE__,__LINE__ ,"userenrolment->status= {$userenrolment->status}");
						
						if(empty($userenrolment)) {
							dbg("enrol_jics",__FILE__,__LINE__ ,	"Will enroll user {$member->username} (moodle id {$member->id}) in role {$current_roleid} in course {$course->shortname} (moodle id {$course->id})");
							mtrace(html_br()."Enroling user {$member->id}/{$member->username} in role {$current_roleid}/{$localrolefieldvalue}");
							//mtrace('\n<br />');	// improve display							
							$this->enrol_user($instance, $member->id, $current_roleid);

							// Inaki wrote: Make sure we set the enrolment status to active. If the user wasn't
							// previously enrolled to the course, enrol_user() sets it. But if we
							// configured the plugin to suspend the user enrolments _AND_ remove
							// the role assignments on external unenrol, then enrol_user() doesn't
							// set it back to active on external re-enrolment. So set it
							// unconditionnally to cover both cases.
							// Let's just do what he recommended! He's smart.
							$DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
							mtrace(html_br());
							printf( get_string('xenroluser', 'enrol_jics') ,
									$member->username,$member->id,$course->shortname,$course->id) ;
						}
						else if ($userenrolment->status == ENROL_USER_SUSPENDED) {
							mtrace(html_br()."Reviving suspended enrollment. "); 
							$this->enrol_user($instance, $member->id, $current_roleid);
							dbg("enrol_jics",__FILE__,__LINE__ ," Executed enrol_user() to restore role.");
							// Reenable enrolment that was previously disabled. Enrolment refreshed
							$DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
							mtrace(html_br());
							printf( get_string('enroluserenable', 'enrol_jics') , 
								$member->username,$member->id,$course->shortname,$course->id) ;	
							dbg("enrol_jics",__FILE__,__LINE__ ,"Reactivated enrollment.");
						}
					}
                    $transaction->allow_commit();
				}
			}
		}
		dbg("enrol_jics",__FILE__,__LINE__ ,"Returning SUCCESS (0) from sync_enrolments");
        return 0;
	}					

    // some useful functions taken from the database enrollment plugin
    protected function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    protected function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }
	// Return array of role objects which correspond to role shortnames 
	// configured in enrollments_roles setting for this plugin.
	protected function get_mapped_roles(){
		global $DB;
		$mappedroles_str = $this->get_config('enrollment_roles');
		if (empty($mappedroles_str)) {return null;}
		$mappedroles_arr = explode(',',$mappedroles_str);
		// if following throws exception then we really should die
		return ($DB->get_recordset_list('role',$this->get_config('localrolefield'), $mappedroles_arr));
	}

	protected function  make_fullname($crs_idnumber,$crs_title,$crs_code){
		// Typical inputs:
		// 		$crs_idnumber: BUS211_2009_SP_01
		//		$crs_title: Business 211: Introduction to Small Business Management
		// 		$crs_code: BUS 211
		// Return: Business 211 (BUS 211-01, SP 2009)
		global $CFG;
		$fname = null;
		// try a customized function?
		$customfile = "$CFG->dirroot/local/jics/custom_lib.php";
		if (file_exists($customfile)) {
			include_once ($customfile);
			$customfunction = "custom_make_fullname" ;
			if (function_exists($customfunction)) {
				$fname = custom_make_fullname($crs_idnumber,$crs_title,$crs_code) ;
			}
		}
		// if custom method did not exist or if it declined to return a fullname we'll do the work here
		if ($fname == null) {
			$idnumber_arr=explode("_",$crs_idnumber);
			$ccode = $idnumber_arr[0];
			$term =  $idnumber_arr[1];
			$sec =   $idnumber_arr[2];
			$fname = "{$crs_title} ({$ccode}-{$sec}, {$term}) ";
		}
		return $fname;
	}
	protected function  make_shortname($crs_idnumber, $crs_title, $crs_code){
		// Typical inputs:
		// 		$crs_idnumber: 	BUS211_2009_SP_01
		//		$crs_title:		Business 211: Introduction to Small Business Management
		// Return: 		BUS 211-01, SP 2009
		// OR Return: 	BUS211_2009_SP_01
		// OR Return: 	Business 211: Introduction to Small Business Management
		global $CFG;
		$sname = null;
		// try a customized function?
		$customfile = "$CFG->dirroot/local/jics/custom_lib.php";
		if (file_exists($customfile)) {
			include_once ($customfile);
			$customfunction = "custom_make_shortname" ;
			if (function_exists($customfunction)) {
				$sname = custom_make_shortname($crs_idnumber,$crs_title, $crs_code) ;
			}
		}
		// if custom method did not exist or if it declined to return a shortname we'll do the work here
		if ($sname == null) {
			$idnumber_arr=explode("_",$crs_idnumber);
			$ccode = $idnumber_arr[0];
			$term =  $idnumber_arr[1];
			$sec =   $idnumber_arr[2];
			/*
			// pick one of these:
			$sname = "{$ccode}-{$sec}, {$term} ";	// return BUS 211-01, SP 2009
			$sname = $crs_idnumber ;				// return BUS211_2009_SP_01
			$sname = $crs_title ;					// return Business 211: Introduction to Small Business Management
			*/
			$sname = "{$ccode}-{$sec}, {$term} ";	// return BUS 211-01, SP 2009
		}
		return $sname;
	}
	// Convert the SQL date like "2012-12-19 00:00:00" to the Unix timestamp
    protected function make_startdate($sdate)  {
		// to do: check that we were passed a kosher value
		return (strtotime(date($sdate)));
	}
	protected function make_enddate($sdate)  {
		// to do: check that we were passed a kosher value
		return (strtotime(date($sdate)));
	}
    /**
    *  Will extract the term from the course idnumber value
    *  sent as coursecode_term_section from JICS.
	*  But we allow customer to change category name based on idnumber or just term.
    */                                         
    function get_categoryname_from_courseidnumber($idnumber){
		//PHY-101_2012SU_A
		if ( ($cname = custom_make_categoryname_from_idnumber($idnumber)) != null )
		{
			// use customer's preferred naming convention
			return $cname ;
		}
		else {
			// must do it ourselves!
			// locate first underscore
			if (FALSE == ($first_underscore_loc = strpos($idnumber,"_"))) {
				return null;
			}
			// locate last underscore
			else if (FALSE == ($last_underscore_loc = strrpos($idnumber,"_"))){
				return null;
			}            
			else {
				// extract whatever is between the two locations
				$term_name_length = ($last_underscore_loc-1 ) - $first_underscore_loc ;
				$tname =  substr($idnumber,$first_underscore_loc+1,$term_name_length) ;
		        dbg("enrol_jics",__FILE__,__LINE__ ,"term_name= {$tname}");
				// allow customer one last chance to massage term name
				return (( ($cname = custom_make_categoryname_from_term($tname))!= null) ? $cname : $tname ); 
			}
		}
	}
    function import_course($importfrom, $importto) {
		global $CFG, $DB;
		dbg("enrol_jics",__FILE__,__LINE__ ,"");		
		require_once($CFG->dirroot . '/course/externallib.php');
		dbg("enrol_jics",__FILE__,__LINE__ ,"");		
		$deletecontent=0;
		$options = array() ;
		/*
		$options = array(
			"activities" => 1,
			"blocks" => 1,
			"filters" => 1 );
		*/
		
		dbg("enrol_jics",__FILE__,__LINE__ ,"is_array(options)=" . (is_array($options))?"True":"False");

		$USER = get_admin();
		dbg("enrol_jics",__FILE__,__LINE__ ,"importfrom/importto={$importfrom}/{$importto}");
		
		try {
			core_course_external::import_course($importfrom,
				$importto,
				$deletecontent,
				$options);
		} 
		catch (exception $e) {
			error_log ("[ENROL_JICS] Error importing from template course {$importfrom} to {$importto} " . html_br() . $e->getMessage() .html_br()) ;
			dbg("enrol_jics",__FILE__,__LINE__ , "****" . $e->getMessage(). "****");
			// Some debugging information to see what went wrong
			//if (is_dbg("enrol_jics")) { var_dump($e); }
			return false;
		}
		dbg("enrol_jics",__FILE__,__LINE__ ," Exiting Import Course.");		
		return true;
		
	}
	/* EXPLANATION OF THE NEXT FEW FUNCTIONS and create_course()
	** Will return the number of course sections needed for this course.
	** If format is weeks then use JICS start and end dates.
	** Else get the template_course and examine its entries in mdl_course_sections.
	** If there is no Template course then that means the customer wants all new course properties
	** to come from the Course defaults. Only a few attributes will come from JICS:
	** 		idnumber, shortname, fullname, summary
	*/
	function getNumSectionsFromJicsDates($template, $startdate,$enddate){
		global $CFG, $DB ;
		$numsections =  ceil(  ($enddate - $startdate) / (60 * 60 * 24 * 7) );
		dbg("enrol_jics",__FILE__,__LINE__ ,"getNumSectionsFromJicsDates returning ".  $numsections . " sections."	);
		return $numsections;
	}
	function getNumSectionsFromTemplate($templateid){
		global $CFG, $DB ;
		dbg("enrol_jics",__FILE__,__LINE__ ,"entered getNumSectionsFromTemplate with templateid {$templateid}");
		$result = $DB->get_records_sql("select count(cs.id) as numsections from mdl_course_sections cs  where cs.course=".$templateid);		
		foreach ($result as $row) {
			$numsections = (int)$row->numsections;
		}
		dbg("enrol_jics",__FILE__,__LINE__ ,"getNumSectionsFromTemplate returning ".  $numsections . " sections."	);
		return $numsections;
	}
	/*
	** Will return a templatecourse object baeed on Moodle-wide defaults
	*/
	function getTemplateFromDefaults(){
		global $CFG, $DB ;
		dbg("enrol_jics",__FILE__,__LINE__ ,"entered getTemplateFromDefaults");
		$courseconfig = get_config('moodlecourse');
		$template = new stdClass();
		$template->summary        = '';
		$template->summaryformat  = FORMAT_HTML;
		$template->format         = $courseconfig->format;
		$template->numsections    = $courseconfig->numsections; // see comments above
		$template->hiddensections = $courseconfig->hiddensections;
		$template->newsitems      = $courseconfig->newsitems;
		$template->showgrades     = $courseconfig->showgrades;
		$template->showreports    = $courseconfig->showreports;
		$template->maxbytes       = $courseconfig->maxbytes;
		$template->groupmode      = $courseconfig->groupmode;
		$template->groupmodeforce = $courseconfig->groupmodeforce;
		$template->visible        = $courseconfig->visible;
		$template->lang           = $courseconfig->lang;
		$template->groupmodeforce = $courseconfig->groupmodeforce;
		dbg("enrol_jics",__FILE__,__LINE__ ,"getTemplateFromDefaults returning template with ".  $template->numsections . " sections."	);
		return $template ;
	}
			
	/**
     * Will create the moodle course from the template or from defaults as needed
     * @param array $course_ext -- external course object and some properties to use
     * @return mixed false on error, id for the newly created course otherwise.
     */
    function create_course($course, $skip_fix_course_sortorder=false) {
		dbg("enrol_jics",__FILE__,__LINE__ ," Entered create_course");
        global $CFG, $DB, $PAGE;
        require_once("$CFG->dirroot/course/lib.php");
		// new for Moodle 3.0
		require_once $CFG->dirroot.'/group/lib.php';      
		// Override defaults with template course
        $template = false;
        if ($template_crs=$this->get_config('templatecourse')) {
			dbg("enrol_jics",__FILE__,__LINE__ ,"Looking for template course with shortname {$template_crs} ");
            if ($template = $DB->get_record('course', array("shortname"=>"{$template_crs}"))) {
				dbg("enrol_jics",__FILE__,__LINE__ ,"Template course {$template_crs} found for course autocreation.");
				$templateid = $template->id; //save id for deep copy
            }
        }
        if (!$template) {
			// This shoudl not happen but carry on the best we can after notifying user
			dbg("enrol_jics",__FILE__,__LINE__ ,"No template course available for course autocreation. Using defaults.");
			$template = $this->getTemplateFromDefaults( );
		}
		else if ($template->format == 'weeks' ) {
			dbg("enrol_jics",__FILE__,__LINE__ ,"Using template course for course autocreation. Format=WEEKS.");		
			$course->numsections  = $this->getNumSectionsFromJicsDates($template, $course->startdate,$course->enddate);
		}
		else {
			dbg("enrol_jics",__FILE__,__LINE__ ,"Using template course for course autocreation. Format != WEEKS.");				
			$course->numsections  = $this->getNumSectionsFromTemplate($templateid);
		}
        unset($template->id); // So we are clear to reinsert the record
        unset($template->fullname);
        unset($template->shortname);
        unset($template->idnumber);

		// Get default category for autocreated courses. We may need to reset it if using new subcategoryparent config options.
		$defaultcategory = $this->get_config('defaultcategory');
		$toplevelcategoryparent = 0 ; // top level categories have a parent category value of 0
		// Special categories based on the term of the course to be created.

		dbg("enrol_jics",__FILE__,__LINE__ ,"get_config(subcategoryparent)=".$this->get_config('subcategoryparent') );
		if ($this->get_config('subcategoryparent') != 'default') {  // The constants are defined in /enrol/jics/settings.php

            // what is the term?
            $course_category  = $this->get_categoryname_from_courseidnumber($course->idnumber) ;
            if ($course_category == null) {
                // die now
				//*************************
				mtrace(html_br(). "Error. COURSE CATEGORY IS NULL");
				//*************************
                error_log("[ENROL_JICS] Error attempting to get category name for new course {$course->idnumber}");
                dbg("enrol_jics",__FILE__,__LINE__ ,"Error attempting to get category name for new course {$course->idnumber}.");
                return 1 ;
            }
            else {
                // The category name has been generated and customized one way or the other.
                // Does this category already exist?
                if ($category_rec=$DB->get_record('course_categories', array('name'=>$course_category))) {
                    // use the id of the existing category
                    $course->category = $category_rec->id ;
                }    
                else {
                    // create it
                    $newcat = new stdClass();
                    $newcat->name = $course_category;
                    $newcat->description = "Courses during the {$course_category} term" ;
                    $newcat->visible = 1;

					//THIS IS NEW, I ADDED THIS:
					//$newcat->parent = $this->get_config('subcategoryparent'); //<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
					$newcat->parent = $defaultcategory;


					
					
					// The constants are defined in /enrol/jics/settings.php
					if ($this->get_config('subcategoryparent') == 'subdefault') {
						$newcat->parent = $defaultcategory->id; // create as child of default category
					}
					else if ($this->get_config('subcategoryparent') == 'toplevel') {
						$newcat->parent = $toplevelcategoryparent->id;	// create as top level
					}
					else {
						error_log("[ENROL_JICS] Error attempting to process subcategory for course creation of new course {$course->idnumber}");
						dbg("enrol_jics",__FILE__,__LINE__ ,"Error attempting to process subcategory for course creation of new course{$course->idnumber}.");
						return 1 ;
					}
					$newcat->parent = 0; //$course_category->id; // 0; // $course_category;
					dbg("enrol_jics",__FILE__,__LINE__ ,"Creating subdirectory with name {$newcat->name} under parent category with id {$newcat->parent}");
                    $catid = $DB->insert_record('course_categories', $newcat);
                    if (FALSE === $catid) {
						mtrace(html_br()."Error trying to create new category {$course_category}");
                        error_log("[ENROL_JICS] Could not create new category {$course_category}") ;
                        dbg("enrol_jics",__FILE__,__LINE__ ,"Could not create new category {$course_category}") ;
                        return 1;
                    }
                    else {
						mtrace(html_br()."Created new category {$course_category}");
						dbg("enrol_jics",__FILE__,__LINE__ ,"Created new category {$course_category}") ;
                        $course->category = $catid ;    
                    }
                }    
            }      
        }
		else {
			// use the default category, not subcategories. This category is guaranteed to exist.
            if ($category_rec=$DB->get_record('course_categories', array('id'=>$defaultcategory))) {
                $course->category = $category_rec->id ;
			}
			else {
				// should never happen!
                // die now
                error_log("[ENROL_JICS] Error attempting to get category record for default category {$defaultcategory}");
                dbg("enrol_jics",__FILE__,__LINE__ ,"Error attempting to get category record for default category {$defaultcategory}.");
                return 1 ;
			}
		}

        // Override with template data only where we are missing data
		foreach ($template as $key=>$val) {
			if (empty($course->$key)) { 
				$course->$key = $val; 
			}
		}
		try {
			$newcourse = create_course($course);
		}
		catch (Exception $e) {
			error_log("[ENROL_JICS] FAILURE: Error creating course {$course->idnumber}");
			mtrace(html_br()."[ENROL_JICS] FAILURE: Error creating course {$course->idnumber}");
			return false;
		}
		if ( ($new_sec0_summary = $this->get_config('section0text')) != '' ) {
			if (!($sec0 = $DB->get_record('course_sections',array("course"=>"{$newcourse->id}","section"=>0)))) {	
				// section 0 does not exist for this course, so create a new one
				$section = NULL;
				$section->course = $newcourse->id;   // Create a default section.
				$section->summary= $new_sec0_summary ;
				$section->section = 0;
				$section->id = $DB->insert_record("course_sections", $section);
			}
			else {
				// section 0 exists for this course
				$sec0id = $sec0->id ; // get section id
				$sec0summ = $sec0->summary; // get existing summary 
				// if no summary, update it
				if ($sec0summ ==  null) {
					// update the record for this course section
					try {
						$DB->set_field("course_sections","summary",$new_sec0_summary,array("id"=>$sec0id)) ;
					}
					catch (Exception $e) {
						error_log("[ENROL_JICS] Error updating summary for section 0 of course {$course->shortname}");
						return(1);
					}
				}
			}
		}
		
		// Add section N text if asked to do so in each section.
		if ( ($new_secN_summary = $this->get_config('sectionNtext')) != '' ) {
			// AZ 2012-05-03. TO DO: What if we want to "deep copy" the template's sectionN into the new course?
			for ($i=1;$i<=$course->numsections;$i++) {
				if (!($secN = $DB->get_record('course_sections',array("course"=>"{$newcourse->id}","section"=>$i)))) {	
					$section = NULL;
					$section->course = $newcourse->id;   // Create a default section.
					$section->summary= $new_secN_summary ;
					$section->section = $i;
					$section->id = $DB->insert_record("course_sections", $section);
				}
			}
		}

		// if deepcopy is turned on do it here.
		if ($this->get_config('deepcopy'))  {
			dbg("enrol_jics",__FILE__,__LINE__ ,"Deep Copy turned on.");		
			if (!$this->import_course($templateid,$newcourse->id)) {
				dbg("enrol_jics",__FILE__,__LINE__ ,"Error attempting deep copy course import from template course {$templateid} into auto-created course {$course->shortname}.");
				// error copying content from template course
				error_log("[ENROL_JICS] Error. Could not copy blocks and activities from template course {$templateid} into auto-created course {$course->shortname}");
				return false ;
			}
			dbg("enrol_jics",__FILE__,__LINE__ ,"Deep copy successful for course {$course->shortname} .");			
		}
		dbg("enrol_jics",__FILE__,__LINE__ ,"Created new course with newcourse->id={$newcourse->id}");
        return $newcourse->id;
    }
}