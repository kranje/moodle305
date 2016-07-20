<?PHP // $Id: enrol_database.php,v 1.5.2.1 2007/11/21 11:04:52 thepurpleblob Exp $ 
      // enrol_database.php - created with Moodle 1.7 beta + (2006101003)

// Following changed for JICS-Moodle integration
$string['enrolname'] = 'JICS';
$string['pluginname'] = 'JICS Enrollment Plugin';
$string['pluginname_desc'] = 'JICS-Moodle integration uses the ICS_NET database as its enrollment data source, for user access to courses, 
						  for batch upload of enrollment information, and also for automatic course creation.';
$string['enrol_jics_autocreation_settings'] = 'Auto-creation of new courses';
$string['enrollment_service'] = 'JICS enrollment web service';
$string['enrollment_roles'] = 'Moodle enrollment roles.';						  
$string['enrollment_service_desc'] = 'Path to JICS web service for enrollment data, starting with \'Portlets/\'.';
$string['enrollment_roles_desc'] = 'Moodle roles used as mappings for JICS roles by enrollment plugin.';
$string['settingsheaderdb'] = 'JICS plugin settings';
$string['settingsheaderlocal']='Local (Moodle) settings';
$string['settingsheaderremote']='Remote (JICS) settings';
$string['dbhost'] = 'JICS URL';
$string['dbhost_desc'] = 'Enter full JICS web address, e.g. http://www.myschool.edu/ics';
$string['enrol_autocreate_desc'] = 'Courses can be created automatically if there are enrollments in a JICS course that doesn\'t yet exist in Moodle.';
$string['enrol_autocreate']= 'Autocreate new course shells';
$string['enrol_autocreate_onlogin_desc'] = 'Courses can be created automatically if the instructor logs into Moodle.';
$string['enrol_autocreate_onlogin_if_deepcopy_desc'] = '(Ignored if Deep Copy enabled!) Course shell is created automatically when the instructor logs into Moodle from JICS.';
$string['enrol_autocreate_onlogin']= 'Autocreate new course shells on instructor login';
$string['autocreation_settings'] = 'Autocreation Settings';
$string['category'] = 'The default category for auto-created courses.';
//$string['create_subcategories'] = 'Categories for autocreated courses';
//$string['create_subcategories_desc'] = 'Check to create autocreated courses in appropriately named categories. The category will be created automatically if it does not yet exist.';

$string['subcategoryparent'] = 'Create/Use new categories for autocreated courses';
$string['subcategoryparent_desc'] = 'You can create new courses in the default category you selected above, in categories under that default category, or in top level categories.';

$string['course_fullname'] = 'The name of the field where the course fullname is stored.';
$string['course_id'] = 'The name of the field where the course ID is stored. The values of this field are used to match those in the \"enrol_db_l_coursefield\" field in Moodle\'s course table.';
$string['course_shortname'] = 'The name of the field where the course shortname is stored.';
$string['course_table'] = 'Then name of the table where we expect to find the course details in (short name, fullname, ID, etc.)';
$string['dbtype'] = 'JICS';
$string['upload_suspendmaxtime']='Suspend PHP max execution time limit';
$string['upload_suspendmaxtime_desc']='Check to remove the limit on maximum time allowed a PHP script to execute. This may be necessary for very large data uploads. The original value is restored when the plugin completes execution.';
$string['debug_trace']='Debug mode';
$string['debug_trace_desc']='Check to enable debug messages to screen during plugin execution';
$string['email_on_error'] = 'Email on error';
$string['email_on_error_desc'] = 'Send email to Support account if error on course autocreation during bulk upload.';
$string['start_date']='Start date filter';
$string['start_date_desc']='Process enrollments for courses starting AFTER this date only. Leave blank to process all terms. You must enter the date in the format YYYY-MM-DD';
//$string['upload_cronwaitinterval_key']='Cron execution wait interval.';
//$string['upload_cronwaitinterval']='Seconds to wait between Moodlecron executions of Enrollment uploads. Use 0 to wait forever, 1 to not wait at all.';
$string['upload_mode']='Select Add/Drop for normal data transfers.';
$string['upload_mode_desc']='Selecting Add/Drop will affect ONLY those courses retrieved from JICS in the current upload and ONLY those enrollments which are based on JICS. Selecting None will skip execution of this plugin through cron but not through the MyCourses portlet.';
$string['template_patternmatch'] = 'Pattern match template course shortname';
$string['template_patternmatch_desc'] = 'If enabled, selection of the template course will examine the coursecode elements and year-term code from the most specific to the most general, looking for a matching template course shortname. (Note: All template course shortnames must start with the shortname entered above!)';
//$string['defaultcourseroleid'] = 'The role that will be assigned by default if no other role is specified.';
$string['defaultrole'] = 'Default role.';
$string['defaultrole_desc'] = 'The role that will be assigned by default if no other role is specified.';
$string['disableunenrol'] = 'If set to yes users previously enrolled by the external database plugin will not be unenrolled by the same plugin regardless of the database contents.';
$string['general_options'] = 'General Options';
//$string['host'] = 'JICS server hostname.';
$string['ignorehiddencourses'] = 'Disable enrollment in courses set to \'Unavailable\'';
$string['ignorehiddencourses_desc'] = 'If set to yes users will not be enroled on courses that are set to be unavailable to students.';
$string['localcoursefield'] = 'The name of the field in the course table that we are using to match entries in the remote database (eg idnumber).';
$string['localrolefield'] = 'The name of the field in the roles table that we are using to match entries in the remote database (eg shortname).';
$string['localuserfield'] = 'The name of the field in the user table that we are using to match entries in the remote database (eg idnumber).';
$string['local_fields_mapping'] = 'Moodle (local) database fields';
//$string['name'] = 'The specific database to use.';
//$string['pass'] = 'Password to access the server.';
$string['remote_fields_mapping'] = 'Enrolment (remote) database fields.';
$string['remotecoursefield'] = 'Remote course id field.';
$string['remoterolefield'] = 'Remote role id field';
$string['remoteuserfield'] = 'Remote user id field.';
$string['remotecoursefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the course table.';
$string['remoterolefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the roles table.';
$string['remoteuserfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the user table.';
$string['server_settings'] = 'External Database Server Settings';
$string['student_coursefield'] = 'The name of the field in the student enrolment table that we expect to find the course ID in.';
$string['student_l_userfield'] = 'The name of the field in the local user table that we use to match the user to a remote record for students (eg idnumber).';
$string['student_r_userfield'] = 'The name of the field in the remote student enrolment table that we expect to find the user ID in.';
$string['student_table'] = 'The name of the table where student enrolments are stored.';
$string['teacher_coursefield'] = 'The name of the field in the teacher enrolment table that we expect to find the course ID in.';
$string['teacher_l_userfield'] = 'The name of the field in the local user table that we use to match the user to a remote record for teachers (eg idnumber).';
$string['teacher_r_userfield'] = 'The name of the field in the remote teacher enrolment table that we expect to find the user ID in.';
$string['teacher_table'] = 'The name of the table where teacher enrolments are stored.';
$string['settingsheadernewcourses']= 'Course Autocreation Settings';
$string['defaultcategory']= 'Default course category';
$string['defaultcategory_desc']= 'Select the category for course autocrreation. Note that this choice is used with the following setting.';
$string['templatecourse'] = 'Template course shortname';
$string['templatecourse_desc'] = 'Optional: auto-created courses will copy some settings from a template course. Enter here the shortname of the template course.';
$string['deepcopy']='Deep copy';
$string['deepcopy_desc']='Copy blocks and activities from template course into all new autocreated courses.';
$string['section0text'] = 'Initial Section 0 text.';
$string['section0text_desc'] = 'This text will be displayed in the top most section of autocreated courses until edited by the instructor.';
$string['sectionNtext'] = 'Initial text for all weeks or units.';
$string['sectionNtext_desc'] = 'This text will be displayed in each week or unit of autocreated courses until edited by the instructor.';
$string['extremovedsuspend'] =  "Disabled enrolment for user %s in course %s (id %d)";
$string['extremovedsuspendnoroles'] =  "Disabled enrolment and removed roles for user %s in course %s (id %d)";
$string['extremovedunenrol'] =  "Unenrol user %s from course %s (id %d)";
$string['xenroluser'] =  "Enroling user %s (id %s) into course %s (id %d) .";
$string['couldnotfinduser'] = "Could not find Moodle user with idnumber %s -- Skipping this user ...";
$string['enroluserenable'] =  "Reenabled enrolment for user %s (id %d) in course %s (id %d)";
$string['emptyenrolment'] = "Empty enrolment for role %s in course %s\n";

//$string['type'] = 'Database server type.';
//$string['user'] = 'Username to access the server.';

?>
