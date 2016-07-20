<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JICS enrolment plugin settings and presets.
 *
 * @package    enrol
 * @subpackage jics
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Following need not be editable in this plugin!
set_config('dbtype', 'JICS', 'enrol_jics');
// Do not support yet as a customizable feature
set_config('template_patternmatch',0,'enrol_jics');

if ($ADMIN->fulltree) {
	// initialize the settings to their default values

	// Service timeout governs enrollment HTTP request/response service calls (different from OAuth timeout).
	// This setting is NOT exposed in the plugin UI.
	$current_value=get_config('enrol_jics','enrollment_service_timeout');
	if (!$current_value) {
		set_config('enrollment_service_timeout', 120, 'enrol_jics');
	}
	else {
		set_config('enrollment_service_timeout', $current_value, 'enrol_jics');
	}	
	
	// service endpoint
	$current_value=get_config('enrol_jics','enrollment_service');
	if (!$current_value) {
		set_config('enrollment_service', 'Portlets/CUS/Portlet.MyCourses/WSEnrollment.aspx', 'enrol_jics');
	}
	else {
		set_config('enrollment_service', $current_value, 'enrol_jics');
	}	
	// moodle roles to use for JICS roles, respectively
	$current_value=get_config('enrol_jics','enrollment_roles');
	if (!$current_value) {
		set_config('enrollment_roles', 'editingteacher,student', 'enrol_jics');
	}
	else {
		set_config('enrollment_roles', $current_value, 'enrol_jics');
	}

	// suspend PHP max execution time limit?
	$current_value=get_config('enrol_jics','upload_suspendmaxtime');
	if (!$current_value) {
		set_config('upload_suspendmaxtime', 0, 'enrol_jics');
	}
	else {
		set_config('upload_suspendmaxtime', $current_value, 'enrol_jics');
	}
	
	// turn on debug traces inside this plugin
	$current_value=get_config('enrol_jics','upload_mode');
	if (!$current_value) {
		set_config('upload_mode', 'Add/Drop', 'enrol_jics');
	}
	else {
		set_config('upload_mode', $current_value, 'enrol_jics');
	}
	// send email if error on course autocreation
	$current_value=get_config('enrol_jics','email_on_error');
	if (!$current_value) {
		set_config('email_on_error', 0, 'enrol_jics');
	}
	else {
		set_config('email_on_error', $current_value, 'enrol_jics');
	}
		
	// startdate for enrollments to process
	$current_value=get_config('enrol_jics','start_date');
	if (!$current_value) {
		set_config('start_date', '', 'enrol_jics');
	}
	else {
		set_config('start_date', $current_value, 'enrol_jics');
	}
	
	// turn on debug traces inside this plugin
	$current_value=get_config('enrol_jics','debug_trace');
	if (!$current_value) {
		set_config('debug_trace', 0, 'enrol_jics');
	}
	else {
		set_config('debug_trace', $current_value, 'enrol_jics');
	}
	// local course field	
	$current_value=get_config('enrol_jics','localcoursefield');
	if (!$current_value) {
		set_config('localcoursefield', 'idnumber', 'enrol_jics');
	}
	else {
		set_config('localcoursefield', $current_value, 'enrol_jics');
	}
	// local user field
	$current_value=get_config('enrol_jics','localuserfield');
	if (!$current_value) {
		set_config('localuserfield', 'idnumber', 'enrol_jics');
	}
	else {
		set_config('localuserfield', $current_value, 'enrol_jics');
	}
	// local role field
	$current_value=get_config('enrol_jics','localrolefield');
	if (!$current_value) {
		set_config('localrolefield', 'shortname', 'enrol_jics');
	}
	else {
		set_config('localrolefield', $current_value, 'enrol_jics');
	}
	// remote course field	
	$current_value=get_config('enrol_jics','remotecoursefield');
	if (!$current_value) {
		set_config('remotecoursefield', 'CoursecodeYrTermSection', 'enrol_jics');
	}
	else {
		set_config('remotecoursefield', $current_value, 'enrol_jics');
	}
	// remote user field
	$current_value=get_config('enrol_jics','remoteuserfield');
	if (!$current_value) {
		set_config('remoteuserfield', 'Userid', 'enrol_jics');
	}
	else {
		set_config('remoteuserfield', $current_value, 'enrol_jics');
	}
	// remote role field
	$current_value=get_config('enrol_jics','remoterolefield');
	if (!$current_value) {
		set_config('remoterolefield', 'Role', 'enrol_jics');
	}
	else {
		set_config('remoterolefield', $current_value, 'enrol_jics');
	}	

	// shortname of template_course
	$current_value=get_config('enrol_jics','templatecourse');
	if (!$current_value) {
		set_config('templatecourse', 'template_course', 'enrol_jics');
	}
	else {
		set_config('templatecourse', $current_value, 'enrol_jics');
	}
	// Deep copy?
	// Default is to hide option altogether
	$current_value=get_config('enrol_jics','allowdeepcopy');
	if (!$current_value) {
		set_config('allowdeepcopy', 0, 'enrol_jics');
	}
	else {
		set_config('allowdeepcopy', $current_value, 'enrol_jics');
	}
	// Default is to disable, even when allowed
	$current_value=get_config('enrol_jics','deepcopy');
	if (!$current_value) {
		set_config('deepcopy', 0, 'enrol_jics');
	}
	else {
		set_config('deepcopy', $current_value, 'enrol_jics');
	}
	
	// turn on course autocreation?
	$current_value=get_config('enrol_jics','enrol_autocreate');
	if ($current_value == null) {
		set_config('enrol_autocreate', 1, 'enrol_jics');
	}
	else {
		set_config('enrol_autocreate', $current_value, 'enrol_jics');
	}
	// turn on course autocreation on login?
	$current_value=get_config('enrol_jics','enrol_autocreate_onlogin');
	if (!$current_value) {
		set_config('enrol_autocreate_onlogin', 0, 'enrol_jics');
	}
	else {
		set_config('enrol_autocreate_onlogin', $current_value, 'enrol_jics');
	}
	/* future enhancement...
	// turn on template matching search?
	$current_value=get_config('enrol_jics','template_patternmatch');
	if (!$current_value) {
		set_config('template_patternmatch', 0, 'enrol_jics');
	}
	else {
		set_config('template_patternmatch', $current_value, 'enrol_jics');
	}
	*/
	// text to add to section 0 of autocreated course
	$current_value=get_config('enrol_jics','section0text');
	if (!$current_value) {
		set_config('section0text', '', 'enrol_jics');
	}
	else {
		set_config('section0text', $current_value, 'enrol_jics');
	}
	// text to add to section 0 of autocreated course
	$current_value=get_config('enrol_jics','sectionNtext');
	if (!$current_value) {
		set_config('sectionNtext', '', 'enrol_jics');
	}
	else {
		set_config('sectionNtext', $current_value, 'enrol_jics');
	}
	
    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_jics_settings', '', get_string('pluginname_desc', 'enrol_jics')));

    $settings->add(new admin_setting_heading('enrol_jics_exdbheader', get_string('settingsheaderdb', 'enrol_jics'), ''));
	$settings->add(new admin_setting_configtext('enrol_jics/enrollment_service', get_string('enrollment_service', 'enrol_jics'), get_string('enrollment_service_desc','enrol_jics'),'Portlets/CUS/Portlet.MyCourses/WSEnrollment.aspx'));
	$settings->add(new admin_setting_configtext('enrol_jics/enrollment_roles', get_string('enrollment_roles', 'enrol_jics'), get_string('enrollment_roles_desc', 'enrol_jics'), 'editingteacher,student'));	
    $settings->add(new admin_setting_configcheckbox('enrol_jics/upload_suspendmaxtime', get_string('upload_suspendmaxtime', 'enrol_jics'), get_string('upload_suspendmaxtime_desc', 'enrol_jics'), 0));
	$options = array('Add/Drop'=>'Add/Drop', 'None'=>'None');
    $settings->add(new admin_setting_configselect('enrol_jics/upload_mode', get_string('upload_mode', 'enrol_jics'),get_string('upload_mode_desc', 'enrol_jics') , 'Add/Drop', $options));

	$settings->add(new admin_setting_configtext('enrol_jics/start_date', get_string('start_date', 'enrol_jics'), get_string('start_date_desc', 'enrol_jics'),''));
	
	$settings->add(new admin_setting_configcheckbox('enrol_jics/debug_trace', get_string('debug_trace', 'enrol_jics'), get_string('debug_trace_desc', 'enrol_jics'), '0','1','0'));
	$settings->add(new admin_setting_configcheckbox('enrol_jics/email_on_error', get_string('email_on_error', 'enrol_jics'), get_string('email_on_error_desc', 'enrol_jics'), 0));
    $settings->add(new admin_setting_heading('enrol_jics_localheader', get_string('settingsheaderlocal', 'enrol_jics'), ''));
    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_jics/localcoursefield', get_string('localcoursefield', 'enrol_jics'), '', 'idnumber', $options));
    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_jics/localuserfield', get_string('localuserfield', 'enrol_jics'), '', 'idnumber', $options));
    $options = array('id'=>'id', 'shortname'=>'shortname', 'fullname'=>'fullname');
    $settings->add(new admin_setting_configselect('enrol_jics/localrolefield', get_string('localrolefield', 'enrol_jics'), '', 'shortname', $options));
    $settings->add(new admin_setting_heading('enrol_jics/remoteheader', get_string('settingsheaderremote', 'enrol_jics'), ''));
    $settings->add(new admin_setting_configtext('enrol_jics/remotecoursefield', get_string('remotecoursefield', 'enrol_jics'), get_string('remotecoursefield_desc', 'enrol_jics'), 'CoursecodeYrTermSection'));
    $settings->add(new admin_setting_configtext('enrol_jics/remoteuserfield', get_string('remoteuserfield', 'enrol_jics'), get_string('remoteuserfield_desc', 'enrol_jics'), 'Userid'));
    $settings->add(new admin_setting_configtext('enrol_jics/remoterolefield', get_string('remoterolefield', 'enrol_jics'), get_string('remoterolefield_desc', 'enrol_jics'), 'Role'));

	// AZ 2012-03-26 WHEN DOES THE FOLLOWING EXECUTE??
	// Do we need to offer only the roles we are mapping? Rewrite get_default_enrol_roles? Cannot- it's in accesslib.php.
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_jics/defaultrole', get_string('defaultrole', 'enrol_jics'), get_string('defaultrole_desc', 'enrol_jics'), $student->id, $options));
    }

    $settings->add(new admin_setting_configcheckbox('enrol_jics/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_jics'), get_string('ignorehiddencourses_desc', 'enrol_jics'), 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_jics/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));



    $settings->add(new admin_setting_heading('enrol_jics_newcoursesheader', get_string('settingsheadernewcourses', 'enrol_jics'), ''));
	$settings->add(new admin_setting_configcheckbox('enrol_jics/enrol_autocreate', get_string('enrol_autocreate', 'enrol_jics'), get_string('enrol_autocreate_desc', 'enrol_jics'), 1));
	
	
    $settings->add(new admin_setting_configtext('enrol_jics/templatecourse', get_string('templatecourse', 'enrol_jics'), get_string('templatecourse_desc', 'enrol_jics'), 'template_course'));
	// Hide if option is not enabled
	if (get_config('enrol_jics','allowdeepcopy')){
		$settings->add(new admin_setting_configcheckbox('enrol_jics/deepcopy', get_string('deepcopy', 'enrol_jics'), get_string('deepcopy_desc', 'enrol_jics'), 0));
	}

	// AZ 2014-05-22
	if (get_config('enrol_jics','allowdeepcopy')){
		$settings->add(new admin_setting_configcheckbox('enrol_jics/enrol_autocreate_onlogin', get_string('enrol_autocreate_onlogin', 'enrol_jics'), get_string('enrol_autocreate_onlogin_if_deepcopy_desc', 'enrol_jics'), 0));
	}
	else {
		$settings->add(new admin_setting_configcheckbox('enrol_jics/enrol_autocreate_onlogin', get_string('enrol_autocreate_onlogin', 'enrol_jics'), get_string('enrol_autocreate_onlogin_desc', 'enrol_jics'), 0));	
	}

    if (!during_initial_install()) {
        require_once($CFG->dirroot.'/course/lib.php');
        $options = array();



		//Please use coursecat::make_categories_list() and coursecat::get_parents()
        //$parentlist = array();
        //make_categories_list($options, $parentlist);

		$parentlist = coursecat::get_parents();
        coursecat::make_categories_list($options, $parentlist);



        $settings->add(new admin_setting_configselect('enrol_jics/defaultcategory', get_string('defaultcategory', 'enrol_jics'), get_string('defaultcategory_desc', 'enrol_jics'), 1, $options));
        unset($parentlist);

		$options = array('default'=>'Create courses in Default Category',
			'subdefault'=>'Create courses in new subcategory of Default Category',
			'toplevel'=>'Create courses in new top level category (ignore Default Category)');
		$settings->add(new admin_setting_configselect('enrol_jics/subcategoryparent', get_string('subcategoryparent', 'enrol_jics'),get_string('subcategoryparent_desc', 'enrol_jics') , 'subdefault', $options));

    }
	// Future enhancement...
	// $settings->add(new admin_setting_configcheckbox('enrol_jics/template_patternmatch', get_string('template_patternmatch', 'enrol_jics'), get_string('template_patternmatch_desc', 'enrol_jics'), 0));
    $settings->add(new admin_setting_configtext('enrol_jics/section0text', get_string('section0text', 'enrol_jics'), get_string('section0text_desc', 'enrol_jics'),''));

require_once($CFG->libdir.'/editor/tinymce/lib.php');
	$settings->add(new admin_setting_confightmleditor('enrol_jics/sectionNtext', get_string('sectionNtext', 'enrol_jics'),get_string('sectionNtext_desc', 'enrol_jics'),''));

}
