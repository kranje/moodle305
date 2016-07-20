<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page
	require($CFG->dirroot.'/local/jics/version.php');
    $settings = new admin_settingpage('local_jics', 'JICS Integration Plugins v'. $plugin->release);
    $ADMIN->add('localplugins', $settings);
	$settings->add(new admin_setting_heading('local_jics_settings', '', get_string('local_jicsdescription', 'local_jics')));	
	$settings->add(new admin_setting_configtext('local_jics/jics_url', get_string('jics_url', 'local_jics'), get_string('jics_url_desc', 'local_jics'),''));	
	$settings->add(new admin_setting_configcheckbox('local_jics/debug_trace', get_string('debug_trace', 'local_jics'), get_string('debug_trace_desc', 'local_jics'), '0','1','0'));
	$settings->add(new admin_setting_configtext('local_jics/oauth_key', get_string('oauth_key', 'local_jics'), get_string('oauth_key_desc', 'local_jics'),''));
	$settings->add(new admin_setting_configtext('local_jics/oauth_secret', get_string('oauth_secret', 'local_jics'), get_string('oauth_secret_desc', 'local_jics'),''));
	$settings->add(new admin_setting_configtext('local_jics/oauth_timeout', get_string('oauth_timeout', 'local_jics'), get_string('oauth_timeout_desc', 'local_jics'),600, PARAM_INT));
	$settings->add(new admin_setting_configcheckbox('local_jics/course0_redirect', get_string('course0_redirect', 'local_jics'), get_string('course0_redirect_desc', 'local_jics'), '0','1','0'));

}

require_once('jics_lib.php');
if (!setup_administrator()) {
	mtrace("Error. setup_administrator could not create or set capabilities of JICS administrator account.");
}
?>
