<?php
//  Support seemless login from JICS to Moodle
require_once "../../config.php";
require_once($CFG->dirroot . '/local/jics/jics_lib.php');
require_once($CFG->libdir. '/moodlelib.php');


// The custom_target_op parameter determines where we should go.
// Currently we recognize only two basic targets: entering a Moodle course context and 
// executing one of the "data sync" scripts.
// Note that it doesn't matter if some irrelevant form parameters are set to "" since the target is not
// going to do anything with those parameters. For example, course/view.php doesn't care that the form
// field ldap_sync_filter is empty!
// Of course we make sure that parameters required for Basic LTI and OAuth are passed.
$ldap_sync_filter = "";
$ldap_sync_mode = "";
$xdb_sync_mode = "";
$xdb_sync_startdate = ""; // for Moodle 1.9 must be 0 or null.
$ldap_sync_lastmodified = "";
if (optional_param("custom_target_op",null,PARAM_RAW_TRIMMED) != null && 
	($target_op = optional_param("custom_target_op",null,PARAM_RAW_TRIMMED)) == "course" ) {
	// If course section passed, translate jics course section name to moodle course id and prepare to send user to course.
	// Note that the method called will provide id=1 in case the translation fails, so we will send user to Moodle home page.
	if (optional_param("custom_course_section_name",null,PARAM_RAW_TRIMMED) != null && 
		($section_name = optional_param("custom_course_section_name",null,PARAM_RAW_TRIMMED)) != "" ) {
		$_SESSION["wantsurl"] = ($wantsurl = $CFG->wwwroot.'/course/view.php?id=' . shortname_to_id ($section_name) ) ;
	}
}
else if ($target_op == "sync") {
	if (optional_param("custom_ldap_sync_filter",null,PARAM_RAW_TRIMMED) != null) { 
		$ldap_sync_filter = optional_param("custom_ldap_sync_filter",null,PARAM_RAW_TRIMMED) ; 
	}
	if (optional_param("custom_ldap_sync_mode",null,PARAM_RAW_TRIMMED) != null) { 
		$ldap_sync_mode = optional_param("custom_ldap_sync_mode",null,PARAM_RAW_TRIMMED) ; 
	}
	if (optional_param("custom_xdb_sync_mode",null,PARAM_RAW_TRIMMED) != null) {
		$xdb_sync_mode = optional_param("custom_xdb_sync_mode",null,PARAM_RAW_TRIMMED) ; 
	}
	if (optional_param("custom_xdb_sync_startdate",null,PARAM_RAW_TRIMMED) != null) {
		$xdb_sync_startdate = optional_param("custom_xdb_sync_startdate",null,PARAM_RAW_TRIMMED) ; 
	}
	if (optional_param("custom_ldap_sync_lastmodified",null,PARAM_RAW_TRIMMED) != null) {
		$ldap_sync_lastmodified = optional_param("custom_ldap_sync_lastmodified",null,PARAM_RAW_TRIMMED) ;
	}
	$_SESSION["wantsurl"] = ( $wantsurl = $CFG->wwwroot.'/local/jics/sync.php' ) ;
}
else {
	mtrace(html_br() . "Error: Unrecognized target operation ." . $target_op);
	die();
}
 
?>
<html>
<head>
<SCRIPT LANAGUAGE=JAVASCRIPT>
function autoSubmit(){
	document.sso_form.submit();
}
</SCRIPT>
</head>

<body>
<?php
//foreach ($_POST as $k=> $v) {
//	echo "<br />",$k,"->",$v;
//}
?>
<form action="<?php echo $CFG->wwwroot ?>/login/index.php" method="post" name="sso_form" id="sso_form">
<input type="hidden" name="username" value=""  />
<input type="hidden" name="password" value=""  />
<input type="hidden" name="wantsurl" value = "<?php echo $wantsurl ?>"/>
<!-- We have hard coded the parameters we might need to pass, in keeping with the Basic LTI 1.0 specification. -->
<!-- Future changes to that specification will requite changes to this parameter list. -->
<input type="hidden" name="basiclti_submit" value="<?php echo optional_param("basiclti_submit",null,PARAM_RAW_TRIMMED)  ?>"/>
<input type="hidden" name="context_id" value="<?php echo optional_param("context_id",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="context_label" value="<?php echo optional_param("context_label",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="context_title" value="<?php echo optional_param("context_title",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="context_type" value="<?php echo optional_param("context_type",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="custom_ldap_sync_filter" value="<?php echo $ldap_sync_filter ?>"/>
<input type="hidden" name="custom_ldap_sync_mode" value="<?php echo $ldap_sync_mode ?>"/>
<input type="hidden" name="custom_ldap_sync_lastmodified" value="<?php echo $ldap_sync_lastmodified ?>"/>
<input type="hidden" name="custom_xdb_sync_mode" value="<?php echo $xdb_sync_mode ?>"/>
<input type="hidden" name="custom_xdb_sync_startdate" value="<?php echo $xdb_sync_startdate ?>"/>
<input type="hidden" name="custom_target_op" value="<?php echo $target_op ?>"/>
<input type="hidden" name="custom_course_section_name" value="<?php  echo optional_param("custom_course_section_name",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="custom_lis_person_address_country" value="<?php echo optional_param("custom_lis_person_address_country",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="custom_lis_person_address_city" value="<?php echo optional_param("custom_lis_person_address_city",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="launch_presentation_document_target" value="<?php echo optional_param("launch_presentation_document_target",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="launch_presentation_height" value="<?php echo optional_param("launch_presentation_height",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="launch_presentation_locale" value="<?php echo optional_param("launch_presentation_locale",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="launch_presentation_return_url" value="<?php echo optional_param("launch_presentation_return_url",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="launch_presentation_width" value="<?php echo optional_param("launch_presentation_width",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_course_section_sourcedid" value="<?php echo optional_param("lis_course_section_sourcedid",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_person_sourcedid" value="<?php echo optional_param("lis_person_sourcedid",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_person_name_given" value="<?php echo optional_param("lis_person_name_given",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_person_name_family" value="<?php echo optional_param("lis_person_name_family",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_person_name_full" value="<?php echo optional_param("lis_person_name_full",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lis_person_contact_email_primary" value="<?php echo optional_param("lis_person_contact_email_primary",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="lti_message_type" value="<?php echo optional_param("lti_message_type",null,PARAM_RAW_TRIMMED)  ?>"/>
<input type="hidden" name="lti_version" value="<?php echo optional_param("lti_version",null,PARAM_RAW_TRIMMED)  ?>"/>
<input type="hidden" name="oauth_callback" value="<?php echo optional_param("oauth_callback",null,PARAM_RAW_TRIMMED)  ?>"/>
<input type="hidden" name="oauth_consumer_key" value="<?php echo optional_param("oauth_consumer_key",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="oauth_nonce" value="<?php echo optional_param("oauth_nonce",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="oauth_signature_method" value="<?php echo optional_param("oauth_signature_method",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="oauth_timestamp" value="<?php echo optional_param("oauth_timestamp",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="oauth_version" value="<?php echo optional_param("oauth_version",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="oauth_signature" value="<?php echo optional_param("oauth_signature",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="resource_link_description" value="<?php echo optional_param("resource_link_description",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="resource_link_id" value="<?php echo optional_param("resource_link_id",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="resource_link_title" value="<?php echo optional_param("resource_link_title",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="roles" value="<?php echo optional_param("roles",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="tool_consumer_instance_contact_email" value="<?php echo optional_param("tool_consumer_instance_contact_email",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="tool_consumer_instance_description" value="<?php echo optional_param("tool_consumer_instance_description",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="tool_consumer_instance_guid" value="<?php echo optional_param("tool_consumer_instance_guid",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="tool_consumer_instance_name" value="<?php echo optional_param("tool_consumer_instance_name",null,PARAM_RAW_TRIMMED) ?>"/>
<input type="hidden" name="user_id" value="<?php echo optional_param("user_id",null,PARAM_RAW_TRIMMED) ?>"/>
</form>
<SCRIPT type="text/javascript">
	autoSubmit();
</SCRIPT>
</body>
</html>