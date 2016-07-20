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
 * Strings for component 'auth_jics', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   auth_jics
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// not sure where this came from in older code 
$string['auth_jicstitle'] = 'JICS';
$string['auth_jics_general_settings'] = 'General settings';
$string['auth_jics_nodirectlogins_key']='Disable direct logins to Moodle';
$string['auth_jics_nodirectlogins']='Check to disable direct logins to Moodle and thus require users to access their Moodle courses through the MyCourses portlet.';
// 2013-09-17 Support for modifying usernames
$string['auth_jics_lockusername_key']='Lock username field.';
$string['auth_jics_lockusername']='If set to false the username field will be updated if JICS has different username for this user\'s student id.';

$string['auth_jics_additionalRole_txt']='Additional base role required';
$string['auth_jics_debug_trace_key']='Debug mode';
$string['auth_jics_debug_trace']='Check to enable debug messages to screen during plugin execution.';
$string['auth_jics_ad_create_req'] = 'Cannot create the new account in Active Directory. Make sure you meet all the requirements for this to work (LDAPS connection, bind user with adequate rights, etc.)';
$string['auth_jics_attrcreators'] = 'List of groups or contexts whose members are allowed to create attributes. Separate multiple groups with \';\'. Usually something like \'cn=teachers,ou=staff,o=myorg\'';
$string['auth_jics_attrcreators_key'] = 'Attribute creators';
$string['auth_jics_auth_user_create_key'] = 'Create users externally';
$string['auth_jics_bind_dn'] = 'You may use any AD LDS account that has "readall" capability for PortalUsers. Specify full DN like \'cn=ReadAllUser,ou=PortalUsers,cn=Portal,o=Jenzabar,c=US\'.';
$string['auth_jics_bind_dn_key'] = 'Distinguished name';
$string['auth_jics_bind_pw'] = 'Password for AD LDS bind user account.';
$string['auth_jics_bind_pw_key'] = 'Password';
$string['auth_jics_bind_settings'] = 'AD LDS Bind User account settings';
$string['auth_jics_contexts'] = 'Context where users are located in AD LDS. Default: OU=PortalUsers,CN=Portal,O=Jenzabar,C=US';
$string['auth_jics_contexts_key'] = 'JICS AD LDS contexts';
$string['auth_jics_create_context'] = 'If you enable user creation with email confirmation, specify the context where users are created. This context should be different from other users to prevent security issues. You don\'t need to add this context to ldap_context-variable, Moodle will search for users from this context automatically.<br /><b>Note!</b> You have to modify the method user_create() in file auth/ldap/auth.php to make user creation work';
$string['auth_jics_create_context_key'] = 'Context for new users';
$string['auth_jics_create_error'] = 'Error creating user in LDAP.';
$string['auth_jics_creators'] = 'List of groups or contexts whose members are allowed to create new courses. Separate multiple groups with \';\'. Usually something like \'cn=teachers,ou=staff,o=myorg\'';
$string['auth_jics_creators_key'] = 'Creators';
$string['auth_jicsdescription'] = 'This method provides authentication against the JICS LDAP service (AD LDS or Active Directory/Open LDAP).
                                  LTI/OAuth-based authentication as well as direct LDAP authentication are supported, along with User bulk upload and update.';
$string['auth_jics_expiration_desc'] = 'Select No to disable expired password checking or LDAP to read passwordexpiration time directly from LDAP';
$string['auth_jics_expiration_key'] = 'Expiration';
$string['auth_jics_expiration_warning_desc'] = 'Number of days before password expiration warning is issued.';
$string['auth_jics_expiration_warning_key'] = 'Expiration warning';
$string['auth_jics_expireattr_desc'] = 'Optional: overrides ldap-attribute that stores password expiration time';
$string['auth_jics_expireattr_key'] = 'Expiration attribute';
$string['auth_ldapextrafields'] = '<p>These fields are managed by JICS but are uploaded into Moodle and automatically updated to reflect current values in JICS.</p>';
$string['auth_jics_graceattr_desc'] = 'Optional: Overrides  gracelogin attribute';
$string['auth_jics_gracelogin_key'] = 'Grace login attribute';
$string['auth_jics_gracelogins_desc'] = 'Enable LDAP gracelogin support. After password has expired user can login until gracelogin count is 0. Enabling this setting displays grace login message if password is expired.';
$string['auth_jics_gracelogins_key'] = 'Grace logins';
$string['auth_jics_groupecreators'] = 'List of groups or contexts whose members are allowed to create groups. Separate multiple groups with \';\'. Usually something like \'cn=teachers,ou=staff,o=myorg\'';
$string['auth_jics_groupecreators_key'] = 'Group creators';
$string['auth_jics_host_url'] = 'Specify AD LDS host in URL-form like \'ldap://ldap.myorg.com/\' or \'ldaps://ldap.myorg.com/\' Separate multipleservers with \';\' to get failover support. If port is non-standard please specify it.';
$string['auth_jics_host_url_key'] = 'AD LDS URL';
$string['auth_jics_changepasswordurl_key'] = 'Password-change URL';
$string['auth_jics_ldap_encoding'] = 'Specify encoding used by LDAP server, usually utf-8. (Value must be supported by AD LDS and any external authentication in use. Note: MS AD v2 uses default platform encoding such as cp1252, cp1250, etc.)';
$string['auth_jics_ldap_encoding_key'] = 'LDAP encoding';
$string['auth_jics_login_settings'] = 'Login settings';
$string['auth_jics_memberattribute'] = 'Active Directory/OpenLDAP member attribute, for example \'cn\', \'samAccount\', \'uid\', or \'member\''; 
$string['auth_jics_memberattribute_key'] = 'AD/OpenLDAP Member attribute';
$string['auth_jics_memberattribute_isdn'] = 'Optional: Overrides handling of member attribute values, either 0 or 1';
$string['auth_jics_memberattribute_isdn_key'] = 'Member attribute uses dn';
$string['auth_jics_noconnect'] = 'LDAP-module cannot connect to server: {$a}';
$string['auth_jics_noconnect_all'] = 'LDAP-module cannot connect to any servers: {$a}';
$string['auth_jics_noextension'] = '<em>The PHP LDAP module does not seem to be present. Please ensure it is installed and enabled if you want to use this authentication plugin.</em>';
$string['auth_jics_no_mbstring'] = 'You need the mbstring extension to create users in Active Directory.';
$string['auth_ldapnotinstalled'] = 'Cannot use LDAP authentication. The PHP LDAP module is not installed.';
$string['auth_jics_objectclass'] = 'Optional: Overrides objectClass used to name/search users on ldap_user_type. Usually you dont need to chage this.';
$string['auth_jics_objectclass_key'] = 'Object class';
$string['auth_jics_opt_deref'] = 'Determines how aliases are handled during search. Select one of the following values: "No" (LDAP_DEREF_NEVER) or "Yes" (LDAP_DEREF_ALWAYS)';
$string['auth_jics_opt_deref_key'] = 'Dereference aliases';
$string['auth_jics_passtype'] = 'Specify the format of new or changed passwords in LDAP server.';
$string['auth_jics_passtype_key'] = 'Password format';
$string['auth_jics_passwdexpire_settings'] = 'LDAP password expiration settings.';
$string['auth_jics_preventpassindb'] = 'Select yes to prevent direct manual login to Moodle using cached password when JICS and Active Directory unavailable.';
$string['auth_jics_preventpassindb_key'] = 'Do not cache passwords.';
$string['auth_jics_search_sub'] = 'Search users from subcontexts.';
$string['auth_jics_search_sub_key'] = 'Search subcontexts';
$string['auth_jics_server_settings'] = 'LDAP server settings';

$string['auth_jics_suspendifdisabled_key'] = 'Suspend user if disabled in JICS';
$string['auth_jics_suspendifdisabled'] = 'Suspend Moodle user account if user login is disabled in JICS AD LDS';

$string['auth_dbsuspenduser'] = 'Suspended user {$a->name} id {$a->id}';
$string['auth_dbsuspendusererror'] = 'Error suspending user {$a}';
$string['auth_jics_unsupportedusertype'] = 'auth: ldap user_create() does not support selected usertype: {$a}';
$string['auth_jics_update_userinfo'] = 'Update user information (firstname, lastname, address..) from LDAP to Moodle.  Specify "Data mapping" settings as you need.';
$string['auth_jics_upload_settings']='Bulk Upload options';
$string['auth_jics_upload_mode_key']='User Upload Mode';
$string['auth_jics_upload_mode']='Quick (create users), Full (update all accounts, or None (skip User processing)';
$string['auth_jics_upload_mode_quick']='quick';
$string['auth_jics_upload_mode_full']='full';
$string['auth_jics_upload_mode_none']='none';

$string['auth_jics_upload_groupfilter_key']='User groups filter';
$string['auth_jics_upload_groupfilter']='Select User groups to process in subsequent bulk uploads/updates.';
$string['auth_jics_upload_groupfilter_stufac']='Students,Faculty';
$string['auth_jics_upload_groupfilter_stu']='Students only';
$string['auth_jics_upload_groupfilter_fac']='Instructors only';
$string['auth_jics_upload_groupfilter_staff']='Staff only';
$string['auth_jics_upload_groupfilter_all']='All JICS Users';

$string['auth_jics_cron_key']='Cron execution wait interval.';
$string['auth_jics_cron']='Seconds to wait between Moodle Cron executions of User uploads. Use 0 to wait forever, 1 to not wait at all. 86400 = 24 hours.';
$string['auth_jics_upload_lastmodfilter_key']='Last Modified Filter';
//$string['auth_jics_upload_lastmodfilter']='Select Yes to have Quick Upload ignore users not modified since the last update that did NOT use this filter. Select No if you want to upload users in a group not previously selected for upload.';
$string['auth_jics_upload_lastmodfilter']='Be sure to select No if you are changing the User Groups filter to include additional groups.';
$string['auth_jics_upload_suspendmaxtime_key']='Suspend max execution time limit';
$string['auth_jics_upload_suspendmaxtime']='Select Yes to temporarily suspend the max execution time limit during User bulk upload. Suggested for large bulk uploads.';

$string['auth_jics_user_attribute'] = 'Optional: Overrides the attribute used to name/search users. Usually \'cn\'.';
$string['auth_jics_user_attribute_key'] = 'User attribute';
$string['auth_jics_user_exists'] = 'LDAP username already exists.';
$string['auth_jics_user_settings'] = 'User settings';
$string['auth_jics_user_type'] = 'If using JICS External Authentication, select type: MS Active Directory or Open LDAP. Default: JICS AD LDS only.';
$string['auth_jics_user_type_key'] = 'Authentication type';
$string['auth_jics_usertypeundefined'] = 'config.user_type not defined or function ldap_expirationtime2unix does not support selected type!';
$string['auth_jics_usertypeundefined2'] = 'config.user_type not defined or function ldap_unixi2expirationtime does not support selected type!';
$string['auth_jics_ldap_version'] = 'The version of the LDAP protocol your server is using. (Value must be supported by AD LDS and any external authentication in use.)';
$string['auth_jics_ldap_version_key'] = 'LDAP Version';
// used to be retrieved from db plugin but now moving them into this plugin's language file.
$string['auth_dbdeleteuser'] = 'Deleted user {$a->name} id {$a->id}';
$string['auth_dbdeleteusererror'] = 'Error deleting user {$a}';
$string['auth_dbreviveduser'] = 'Revived user {$a->name} id {$a->id}';
$string['auth_dbrevivedusererror'] = 'Error reviving user {$a}';
$string['auth_dbupdatinguser'] = 'Updating user {$a->name} id {$a->id}';
$string['auth_dbusernotexist'] = 'Cannot update non-existent user: {$a}';
// many of following not needed but some are, so need to review them when we get a chance.
$string['auth_ntlmsso'] = 'NTLM SSO';
$string['auth_ntlmsso_enabled'] = 'Set to yes to attempt Single Sign On with the NTLM domain. <strong>Note:</strong> this requires additional setup on the webserver to work, see <a href="http://docs.moodle.org/en/NTLM_authentication">http://docs.moodle.org/en/NTLM_authentication</a>';
$string['auth_ntlmsso_enabled_key'] = 'Enable';
$string['auth_ntlmsso_ie_fastpath'] = 'Set to yes to enable the NTLM SSO fast path (bypasses certain steps and only works if the client\'s browser is MS Internet Explorer).';
$string['auth_ntlmsso_ie_fastpath_key'] = 'MS IE fast path?';
$string['auth_ntlmsso_subnet'] = 'If set, it will only attempt SSO with clients in this subnet. Format: xxx.xxx.xxx.xxx/bitmask. Separate multiple subnets with \',\' (comma).';
$string['auth_ntlmsso_subnet_key'] = 'Subnet';
$string['auth_ntlmsso_type_key'] = 'Authentication type';
$string['auth_ntlmsso_type'] = 'The authentication method configured in the web server to authenticate the users (if in doubt, choose NTLM)';
$string['connectingldap'] = "Connecting to LDAP server...\n";
$string['creatingtemptable'] = "Creating temporary table {\$a}\n";
$string['didntfindexpiretime'] = 'password_expire() didn\'t find expiration time.';
$string['didntgetusersfromldap'] = "Did not get any users from LDAP -- check for error conditions -- exiting\n";
$string['didntgetusersfromldapOK'] = "Did not get any users from LDAP.\n";
$string['gotcountrecordsfromldap'] = "Got {\$a} records from LDAP\n";
$string['morethanoneuser'] = 'Strange! More than one user record found in ldap. Only using the first one.';
$string['needbcmath'] = 'You need the BCMath extension to use grace logins with Active Directory';
$string['needmbstring'] = 'You need the mbstring extension to change passwords in Active Directory';
$string['nodnforusername'] = 'Error in user_update_password(). No DN for: {$a->username}';
$string['noemail'] = 'Tried to send you an email but failed!';
$string['notcalledfromserver'] = 'Should not be called from the web server!';
$string['noupdatestobedone'] = "No updates to be done\n";
$string['nouserentriestoremove'] = "No user entries to be removed\n";
$string['nouserentriestorevive'] = "No user entries to be revived\n";
$string['nouserstobeadded'] = "No users to be added\n";
$string['ntlmsso_attempting'] = 'Attempting Single Sign On via NTLM...';
$string['ntlmsso_failed'] = 'Auto-login failed, try the normal login page...';
$string['ntlmsso_isdisabled'] = 'NTLM SSO is disabled.';
$string['ntlmsso_unknowntype'] = 'Unknown ntlmsso type!';
$string['pluginname'] = 'JICS Authentication';
$string['pluginnotenabled'] = 'Plugin not enabled!';
$string['renamingnotallowed'] = 'User renaming not allowed in LDAP';
$string['rootdseerror'] = 'Error querying rootDSE for Active Directory';
$string['updateremfail'] = 'Error updating LDAP record. Error code: {$a->errno}; Error string: {$a->errstring}<br/>Key ({$a->key}) - old moodle value: \'{$a->ouvalue}\' new value: \'{$a->nuvalue}\'';
$string['updateremfailamb'] = 'Failed to update LDAP with ambiguous field {$a->key}; old moodle value: \'{$a->ouvalue}\', new value: \'{$a->nuvalue}\'';
$string['updatepasserror'] = 'Error in user_update_password(). Error code: {$a->errno}; Error string: {$a->errstring}';
$string['updatepasserrorexpire'] = 'Error in user_update_password() when reading password expiration time. Error code: {$a->errno}; Error string: {$a->errstring}';
$string['updatepasserrorexpiregrace'] = 'Error in user_update_password() when modifying expirationtime and/or gracelogins. Error code: {$a->errno}; Error string: {$a->errstring}';
$string['updateusernotfound'] = 'Could not find user while updating externally. Details follow: search base: \'{$a->userdn}\'; search filter: \'(objectClass=*)\'; search attributes: {$a->attribs}';
$string['user_activatenotsupportusertype'] = 'auth: ldap user_activate() does not support selected usertype: {$a}';
$string['user_disablenotsupportusertype'] = 'auth: ldap user_disable() does not support selected usertype: {$a}';
$string['userentriestoadd'] = "User entries to be added: {\$a}\n";
$string['userentriestoremove'] = "User entries to be removed: {\$a}\n";
$string['userentriestorevive'] = "User entries to be revived: {\$a}\n";
$string['userentriestoupdate'] = "User entries to be updated: {\$a}\n";
$string['usernotfound'] = 'User not found in LDAP';
$string['useracctctrlerror'] = 'Error getting userAccountControl for {$a}';
$string['auth_updatelocal_expl']= '<p>Update Local: Suggested setting is \'On every login\' except for Idnumber field.</p>';
$string['auth_fieldlock_expl']='<p>Lock Value: Suggested setting is \'Locked\' since manual user edits inside Moodle are overwritten by next Update operation anyway.</p>';
$string['auth_updateremote_expl']= '<p>Update Remote: You must leave as \'Never\' as there is no mechanism for updating AD LDS based on manual edits inside Moodle.</p>';
// Active Directory strings

$string['auth_jics_host_url_key_AD'] = 'AD/OpenLDAP URL or IP';
$string['auth_jics_host_url_AD'] = '(Only if using AD/OpenLDAP) Enter URL or IP address of Active Directory/OpenLDAP if JICS is using external authentication.';
//$string['auth_jics_host_dn_key_AD'] = 'AD user DN suffix';
//$string['auth_jics_host_dn_AD'] = 'Enter AD user DN suffix for Active Directory authentication, e.g. AD.school.edu in john.doe@AD.school.edu';
$string['auth_jics_server_AD_settings'] = 'JICS External Authentication.';
//$string['auth_jics_AD_key'] = 'Enable Active Directory?';
//$string['auth_jics_AD'] = 'JICS users authenticated externally by Active Directory';
$string['auth_jics_ldapcontexts_key'] = 'AD/OpenLDAP Contexts' ;
$string['auth_jics_ldapcontexts'] = '(Only if using AD/OpenLDAP) Context where users are located, e.g. cn=users,dc=HOSTNAME,dc=DOMAIN,dc=edu. Note: If you are using MS Active Directory you can specify the domain string from the user fully qualified name, e.g. \'@mydc.com\'. Be sure to include the \'@\' sign.';

