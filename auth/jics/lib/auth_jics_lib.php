<?php
/**
 * auth_jics_lib.php - LDAP functions to suppor JICS Authentication plugin
 *
 * AZ 2012-03-05 Original /lib/ldaplib.php file cloned and adapted
 * to avoid a dependency on the usual ldaplib.php code.
 */ 
/**
 * ldaplib.php - LDAP functions & data library
 *
 * Library file of miscellaneous general-purpose LDAP functions and
 * data structures, useful for both ldap authentication (or ldap based
 * authentication like CAS) and enrolment plugins.
 *
 * @author     I�aki Arenaza
 * @package    core
 * @subpackage lib
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @copyright  2010 onwards I�aki Arenaza
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// AZ 2012-05-04
// TO DO: WHY IS FOLLOWING LINE FAILING ?
// WE WANT TO USE $CFG . How do we know it is available?
//require_once '../../../config.php';
require_once "{$CFG->libdir}/adminlib.php";
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot. '/local/jics/jics_lib.php'); // for the dbg function

// rootDSE is defined as the root of the directory data tree on a directory server.
if (!defined('ROOTDSE')) {
    define ('ROOTDSE', '');
}
function init_plugin_params() {
	//echo "<br />Inside init_plugin_params in auth_jics_lib.php";
	// Following table used to create initial required or default values for settings of the auth/jics plugin.
	// This code executes from config.html which is loaded by admin/auth_config.php
	$auth_plugin_params = array(
		//2014-02-24
		array("name"=>"field_map_suspended","value"=>"msDS-UserAccountDisabled"),
		array("name"=>"field_updatelocal_suspended","value"=>"onlogin"),
		array("name"=>"field_updateremote_suspended","value"=>"0"),
		array("name"=>"field_lock_suspended","value"=>"unlockedifempty"),
	
		// To turn on debug tracing throughout the auth/jics plugin
		array("name"=>"debug_trace","value"=>"0"),
		// Initialize the lastmodtime value
		array("name"=>"lastmodtime","value"=>"0"),
		// To prevent direct logins to Moodle outside portlet (for remotely hosted Moodle without SSL)
		array("name"=>"nodirectlogins","value"=>"0"),
		// Default wait time between cron executions of this plugin = 1 day
		array("name"=>"cron","value"=>"86400"), 
		// cell to store last cron execution. Not exposed to admin settings UI.
		array("name"=>"lastcron","value"=>"0"),
		// Suggest they create and use a "read only" account if hosted offsite
		array("name"=>"bind_dn","value"=>"CN=HighPrivs,OU=PortalUsers,CN=Portal,O=Jenzabar,C=US"), 
		array("name"=>"ldapencoding","value"=>"utf-8"),
		array("name"=>"contexts","value"=>"OU=PortalUsers,CN=Portal,O=Jenzabar,C=US"),
		array("name"=>"user_type","value"=>"adlds"),
		array("name"=>"user_attribute","value"=>"cn"),
		array("name"=>"memberattribute","value"=>"member"),
		array("name"=>"version","value"=>"3"),
		array("name"=>"passtype","value"=>"sha1"),
		// mapped fields
		array("name"=>"field_map_firstname","value"=>"givenName"),
		array("name"=>"field_updatelocal_firstname","value"=>"onlogin"),
		array("name"=>"field_updateremote_firstname","value"=>"0"),
		array("name"=>"field_lock_firstname","value"=>"unlockedifempty"),
		array("name"=>"field_map_lastname","value"=>"sn"),
		array("name"=>"field_lock_city","value"=>"unlockedifempty"),
		array("name"=>"field_lock_country","value"=>"unlockedifempty"),
		array("name"=>"field_updatelocal_lastname","value"=>"onlogin"),
		array("name"=>"field_updateremote_lastname","value"=>"0"),
		array("name"=>"field_lock_lastname","value"=>"unlockedifempty"),
		array("name"=>"field_map_email","value"=>"jenzabar-ICSNET-EmailAddress"),
		array("name"=>"field_updatelocal_email","value"=>"onlogin"),
		array("name"=>"field_updateremote_email","value"=>"0"),
		array("name"=>"field_lock_email","value"=>"unlockedifempty"),
		array("name"=>"field_map_city","value"=>"l"),
		array("name"=>"field_updatelocal_city","value"=>"onlogin"),
		array("name"=>"field_updateremote_city","value"=>"0"),
		array("name"=>"field_map_country","value"=>"c"),
		array("name"=>"field_updatelocal_country","value"=>"onlogin"),
		array("name"=>"field_updateremote_country","value"=>"0"),
		array("name"=>"field_map_idnumber","value"=>"jenzabar-ICSNET-GUID"),
		array("name"=>"field_updatelocal_idnumber","value"=>"oncreation"),
		array("name"=>"field_updateremote_idnumber","value"=>"0"),
		array("name"=>"field_lock_idnumber","value"=>"locked")
		) ;
	foreach ($auth_plugin_params as $param) {
		if (get_config('auth/jics',$param["name"]) === FALSE) {
			//echo "<br />",__FILE__,":",__LINE__,": name=",$param["name"]," & value=",$param["value"],"<br />";
			set_config($param["name"], $param["value"], 'auth/jics');
		}
	}
}

/**
 * AZ 2012-03-05 Only Microsoft Active Directory is supported by this plugin.
 * Returns predefined user types
 *
 * @return array of predefined user types
 */
function jics_supported_usertypes() {
    $types = array();
    //$types['edir'] = 'Novell Edirectory';
    //$types['rfc2307'] = 'posixAccount (rfc2307)';
    //$types['rfc2307bis'] = 'posixAccount (rfc2307bis)';
    //$types['samba'] = 'sambaSamAccount (v.3.0.7)';
	$types['adlds'] = 'JICS AD LDS only';
    $types['ad'] = 'MS ActiveDirectory';
	$types['openldap'] = 'Open LDAP';
	//$types['default'] = 'JICS AD LDS only';
    return $types;
}

/**
 * Initializes needed variables for ldap-module
 *
 * Uses names defined in ldap_supported_usertypes.
 * $default is first defined as:
 * $default['pseudoname'] = array(
 *                      'typename1' => 'value',
 *                      'typename2' => 'value'
 *                      ....
 *                      );
 *
 * @return array of default values
 */
function jics_getdefaults() {
    // All the values have to be written in lowercase, even if the
    // standard LDAP attributes are mixed-case
    $default['objectclass'] = array(
                        //'edir' => 'user',
                        //'rfc2307' => 'posixaccount',
                        //'rfc2307bis' => 'posixaccount',
                        //'samba' => 'sambasamaccount',
						'adlds' => 'jenzabar-ICSNET-PortalUser',
                        'ad' => 'user', //??
						'openldap' => 'user', //??
                        'default' => '*'
                        );
    $default['user_attribute'] = array(
                        //'edir' => 'cn',
                        //'rfc2307' => 'uid',
                        //'rfc2307bis' => 'uid',
                        //'samba' => 'uid',
						'adlds' => 'cn',
                        'ad' => 'cn', //??
						'openldap' => 'cn', //??
                        'default' => 'cn'
                        );
    $default['memberattribute'] = array(
                        //'edir' => 'member',
                        //'rfc2307' => 'member',
                        //'rfc2307bis' => 'member',
                        //'samba' => 'member',
                        'adlds' => 'cn', //??
						'ad' => 'member', //??
						'openldap' => 'member',	//??
                        'default' => 'member'
                        );
    $default['memberattribute_isdn'] = array(
                        //'edir' => '1',
                        //'rfc2307' => '0',
                        //'rfc2307bis' => '1',
                        //'samba' => '0', // is this right?
                        'adlds' => '1',
						'ad' => '1', //??
						'openldap' => '1', //??
                        'default' => '0'
                        );
    $default['expireattr'] = array (
                        //'edir' => 'passwordexpirationtime',
                        //'rfc2307' => 'shadowexpire',
                        //'rfc2307bis' => 'shadowexpire',
                        //'samba' => '', // No support yet
                        'adlds' => 'pwdlastset', //??
						'ad' => 'pwdlastset', //??
                        'openldap' => 'pwdlastset', //??
                        'default' => ''
                        );
    return $default;
}

/**
 * Checks if user belongs to specific group(s) or is in a subtree.
 *
 * Returns true if user belongs to a group in grupdns string OR if the
 * DN of the user is in a subtree of the DN provided as "group"
 *
 * @param mixed $ldapconnection A valid LDAP connection.
 * @param string $userid LDAP user id (dn/cn/uid/...) to test membership for.
 * @param array $group_dns arrary of group dn
 * @param string $member_attrib the name of the membership attribute.
 * @return boolean
 *
 */
function jics_isgroupmember($ldapconnection, $userid, $group_dns, $member_attrib) {
    if (empty($ldapconnection) || empty($userid) || empty($group_dns) || empty($member_attrib)) {
        return false;
    }

    $result = false;
    foreach ($group_dns as $group) {
        $group = trim($group);
        if (empty($group)) {
            continue;
        }

        // Check cheaply if the user's DN sits in a subtree of the
        // "group" DN provided. Granted, this isn't a proper LDAP
        // group, but it's a popular usage.
        if (stripos(strrev(strtolower($userid)), strrev(strtolower($group))) === 0) {
            $result = true;
            break;
        }

        $search = ldap_read($ldapconnection, $group,
                            '('.$member_attrib.'='.jics_filter_addslashes($userid).')',
                            array($member_attrib));

        if (!empty($search) && ldap_count_entries($ldapconnection, $search)) {
            $info = jics_get_entries_moodle($ldapconnection, $search);
            if (count($info) > 0 ) {
                // User is member of group
                $result = true;
                break;
            }
        }
    }

    return $result;
}
/**
 * Search specified contexts for username and return the user dn like:
 * cn=username,ou=suborg,o=org
 *
 * @param mixed $ldapconnection a valid LDAP connection.
 * @param mixed $username username (external LDAP encoding, no db slashes).
 * @param array $contexts contexts to look for the user.
 * @param string $objectclass objectlass of the user (in LDAP filter syntax).
 * @param string $search_attrib the attribute use to look for the user.
 * @param boolean $search_sub whether to search subcontexts or not.
 * @return mixed the user dn (external LDAP encoding, no db slashes) or false
 *
 */
function jics_find_userdn($ldapconnection, $username, $contexts, $objectclass, $search_attrib, $search_sub) {
    if (empty($ldapconnection) || empty($username) || empty($contexts) || empty($objectclass) || empty($search_attrib)) {
        return false;
    }

    // Default return value
    $ldap_user_dn = false;

    // Get all contexts and look for first matching user
    foreach ($contexts as $context) {
        $context = trim($context);
        if (empty($context)) {
            continue;
        }

        if ($search_sub) {
            $ldap_result = ldap_search($ldapconnection, $context,
                                       '(&'.$objectclass.'('.$search_attrib.'='.jics_filter_addslashes($username).'))',
                                       array($search_attrib));
        } else {
            $ldap_result = ldap_list($ldapconnection, $context,
                                     '(&'.$objectclass.'('.$search_attrib.'='.jics_filter_addslashes($username).'))',
                                     array($search_attrib));
        }

        $entry = ldap_first_entry($ldapconnection, $ldap_result);
        if ($entry) {
            $ldap_user_dn = ldap_get_dn($ldapconnection, $entry);
            break;
        }
    }

    return $ldap_user_dn;
}

/**
 * Returns values like ldap_get_entries but is binary compatible and
 * returns all attributes as array.
 *
 * @param mixed $ldapconnection A valid LDAP connection
 * @param mixed $searchresult A search result from ldap_search, ldap_list, etc.
 * @return array ldap-entries with lower-cased attributes as indexes
 */
function jics_get_entries_moodle($ldapconnection, $searchresult) {
    if (empty($ldapconnection) || empty($searchresult)) {
        return array();
    }

    $i = 0;
    $result = array();
    $entry = ldap_first_entry($ldapconnection, $searchresult);
    if (!$entry) {
        return array();
    }
    do {
        $attributes = array_change_key_case(ldap_get_attributes($ldapconnection, $entry), CASE_LOWER);
        for ($j = 0; $j < $attributes['count']; $j++) {
            $values = ldap_get_values_len($ldapconnection, $entry, $attributes[$j]);
            if (is_array($values)) {
                $result[$i][$attributes[$j]] = $values;
            } else {
                $result[$i][$attributes[$j]] = array($values);
            }
        }
        $i++;
    } while ($entry = ldap_next_entry($ldapconnection, $entry));

    return ($result);
}

/**
 * Quote control characters in texts used in LDAP filters - see RFC 4515/2254
 *
 * @param string filter string to quote
 * @return string the filter string quoted
 */
function jics_filter_addslashes($text) {
    $text = str_replace('\\', '\\5c', $text);
    $text = str_replace(array('*',    '(',    ')',    "\0"),
                        array('\\2a', '\\28', '\\29', '\\00'), $text);
    return $text;
}

if(!defined('LDAP_DN_SPECIAL_CHARS')) {
    define('LDAP_DN_SPECIAL_CHARS', 0);
}
if(!defined('LDAP_DN_SPECIAL_CHARS_QUOTED_NUM')) {
    define('LDAP_DN_SPECIAL_CHARS_QUOTED_NUM', 1);
}
if(!defined('LDAP_DN_SPECIAL_CHARS_QUOTED_ALPHA')) {
    define('LDAP_DN_SPECIAL_CHARS_QUOTED_ALPHA', 2);
}

/**
 * The order of the special characters in these arrays _IS IMPORTANT_.
 * Make sure '\\5C' (and '\\') are the first elements of the arrays.
 * Otherwise we'll double replace '\' with '\5C' which is Bad(tm)
 */
function jics_get_dn_special_chars() {
    return array (
        LDAP_DN_SPECIAL_CHARS              => array('\\',  ' ',   '"',   '#',   '+',   ',',   ';',   '<',   '=',   '>',   "\0"),
        LDAP_DN_SPECIAL_CHARS_QUOTED_NUM   => array('\\5c','\\20','\\22','\\23','\\2b','\\2c','\\3b','\\3c','\\3d','\\3e','\\00'),
        LDAP_DN_SPECIAL_CHARS_QUOTED_ALPHA => array('\\\\','\\ ', '\\"', '\\#', '\\+', '\\,', '\\;', '\\<', '\\>', '\\=', '\\00'),
        );
}

/**
 * Quote control characters in distinguished names used in LDAP - See RFC 4514/2253
 *
 * @param string The text to quote
 * @return string The text quoted
 */
function jics_addslashes($text) {
    $special_dn_chars = jics_get_dn_special_chars();

    $text = str_replace ($special_dn_chars[LDAP_DN_SPECIAL_CHARS],
                         $special_dn_chars[LDAP_DN_SPECIAL_CHARS_QUOTED_NUM],
                         $text);
    return $text;
}

/**
 * Unquote control characters in distinguished names used in LDAP - See RFC 4514/2253
 *
 * @param string The text quoted
 * @return string The text unquoted
 */
function jics_stripslashes($text) {
    $special_dn_chars = jics_get_dn_special_chars();

    // First unquote the simply backslashed special characters. If we
    // do it the other way, we remove too many slashes.
    $text = str_replace($special_dn_chars[LDAP_DN_SPECIAL_CHARS_QUOTED_ALPHA],
                        $special_dn_chars[LDAP_DN_SPECIAL_CHARS],
                        $text);

    // Next unquote the 'numerically' quoted characters. We don't use
    // LDAP_DN_SPECIAL_CHARS_QUOTED_NUM because the standard allows us
    // to quote any character with this encoding, not just the special
    // ones.
    $text = preg_replace('/\\\([0-9A-Fa-f]{2})/e', "chr(hexdec('\\1'))", $text);

    return $text;
}
function use_lastmodfilter($apply_lastmod) {
	if ($apply_lastmod == "1") return true ;
	else if ($apply_lastmod == "0") return false;
	else {
		// not yet implemented option of passing a specific time value to use
		error_log("[AUTH_JICS] This option {$apply_lastmod} is not yet supported. Reset upload_lastmodfilter to 0 or 1");
		die('Error. This option is not yet supported. Reset upload_lastmodfilter to 0 or 1');
	}
}
function make_ldap_filter($apply_lastmod_filter,$groupstr){
	$usinglastmodfilter = use_lastmodfilter($apply_lastmod_filter) ;
	global $DB;
	$groups_arr =  array(
		"Users" => "(memberOf=cn=Users,OU=Groups,CN=Portal,o=Jenzabar,c=US)",
		"Staff" => "(memberOf=cn=Staff,OU=Groups,CN=Portal,o=Jenzabar,c=US)",
		"Students" => "(memberOf=cn=Students,OU=Groups,CN=Portal,o=Jenzabar,c=US)",
		"Faculty" => "(memberOf=cn=Faculty,OU=Groups,CN=Portal,o=Jenzabar,c=US)"
		);
	if (!$usinglastmodfilter && $groupstr == "") { return "*"; }
	if ($groupstr != "" ) {
		$groupname_arr= explode(",",$groupstr);
		$filter_arr = array();
		$filter_str = "";
		foreach ($groupname_arr as $one_group_name) {
			$filter_arr[] = $groups_arr[$one_group_name];
		}
		for ($i=0; $i<count($filter_arr); $i++) {
			if ($i==0) { $filter_str = $filter_arr[$i]; }
			else {	$filter_str = '(|' . $filter_arr[$i] . $filter_str . ')';}
		}
	}
	// Unconditionally exclude users with disabled logins in JICS
	// The suspendifdisabled apparatus is only to toggle suspension for users that 
	// already exist in Moodle.
	//if (get_config('auth/jics','suspendifdisabled') == TRUE) {
	
	$filter_str = '(&' . $filter_str . '(!(msDS-UserAccountDisabled=TRUE)))' ;
	
	//}
	
	// 7/1/2015 : CUTHE
	//This should check the config file and see if there is a custom role defined
	//if there is a custom role we want to REQUIRE that role be present.
	//This will let schools be more selective when to upload users
	//They will need to add a base role of users when they want them to go over to Moodle.
	
	echo "GET CONFIG for AdditionalRole: " . get_config('auth/jics','additionalRole');
	
	if (get_config('auth/jics', "additionalRole" ) != null )
	{
		$filter_str = '(&'.$filter_str. '(&(memberOf=cn='.get_config('auth/jics','additionalRole').',OU=Groups,CN=Portal,o=Jenzabar,c=US)))';
	}

	// dbg("auth/jics",__FILE__,__LINE__, "Modified filter_str to be {$filter_str}");
	// if $apply_lastmod_filter is 0 no point adding it to filter
	// if $apply_lastmod_filter is 1 use last full update time
	// if $apply_lastmod_filter is >0 use it as time value
  
	if (!$usinglastmodfilter) {
		// ignore altogether -- quick exit
		return $filter_str ;
	}
	else {
		// use lastmod value
		$lastmod = get_config('auth/jics', "lastmodtime");
		if ($lastmod == 0) {
			// ignore 0 value
			return $filter_str ;		
		}
	}
	$lastmodified_gmt = gmdate('YmdHis.0\Z',$lastmod);		
	//$filter_str = "(&(!(whenChanged<=" . $lastmodified_gmt . "))(" . $filter_str . "))" ;
	$filter_str = "(&(!(whenChanged<=" . $lastmodified_gmt . "))" . $filter_str . ")" ;
	return $filter_str;
 }

// useful function to convert binary idnumber returned from ADAM to a text version we can store in MySQL
function binaryGUID_to_textGUID($bin) {
          $hex_guid = bin2hex($bin);
          $hex_guid_to_guid_str = '';
          for($k = 1; $k <= 4; ++$k) {
              $hex_guid_to_guid_str .= substr($hex_guid, 8 - 2 * $k, 2);
          }
          $hex_guid_to_guid_str .= '-';
          for($k = 1; $k <= 2; ++$k) {
              $hex_guid_to_guid_str .= substr($hex_guid, 12 - 2 * $k, 2);
          }
          $hex_guid_to_guid_str .= '-';
          for($k = 1; $k <= 2; ++$k) {
              $hex_guid_to_guid_str .= substr($hex_guid, 16 - 2 * $k, 2);
          }
          $hex_guid_to_guid_str .= '-' . substr($hex_guid, 16, 4);
          $hex_guid_to_guid_str .= '-' . substr($hex_guid, 20);
          return strtoupper($hex_guid_to_guid_str);
}
 // This is the heart of the OAuth-based authentication, called from the loginpage_hook in auth/jics/auth.php. 
 // We authenticate the user by comparing the signature we were passed against the signature we generate ourselves. 
 // We also process various parameters that can be passed in the login request handled by this plugin, setting up the 
 // $user object appropriately so the code in auth.php can perform updates to the user record as needed.
 // Important note: this code also executes when launching enrollment sync without auth sync, but we put it here 
 // (and set up all the SESSION variables since even on that path we must authenticate the Administrator's SSO from the portlet!
 function jics_moodle_blti_auth($authname) {
		
	 	// No need to return any value to caller. Either we set up $user and $frm or we do not.
	 	// Caller will test these globals to decide what to do next.
		// However we do unset $_POST on failure to make sure that index.php will not see the BLTI/OAuth 
		// posted parameters and will rather use the login form or (possibly) another auth plugin.
		global $CFG, $DB;
		require_once($CFG->dirroot. '/local/jics/jics_lib.php'); // for the dbg function
		global $user;
		global $frm;
		global $SESSION;
		
		// Were we called from BLTI consumer?
		if (NULL == optional_param("oauth_consumer_key",NULL,PARAM_RAW_TRIMMED)) { 
			return ; 
		}

		// If we are still here then we are handling BLTI seamless login.
		require_once "{$CFG->dirroot}/local/jics/blti/OAuth.php";
		require_once "{$CFG->dirroot}/local/jics/blti/datastore.php";
		require_once "{$CFG->libdir}/dml/moodle_database.php";
		
		//require_once "{$CFG->libdir}/setup.lib"; // for invalid_parameter_exception class
		try {
			// Look up the secret for this consumer
			$consumer_key = trim(optional_param("oauth_consumer_key",NULL,PARAM_RAW_TRIMMED));
			if ($consumer_key == NULL) { return ; } // not configured yet. Try some other auth plugin.
			// Compare the stored Key with what we were passed. 
			// If they are not identical there is a misconfiguration.
			$stored_key = get_config('local_jics', "oauth_key");
			if ($consumer_key != $stored_key)
			{
				// throw exception
				throw new invalid_parameter_exception("Module: auth/jics. Method: jics_moodle_blti_auth. Error: Was passed key {$consumer_key} but found stored key {$stored_key}.");
			}
			$consumer_secret = get_config('local_jics', "oauth_secret");
			$consumer_timeout = get_config('local_jics', "oauth_timeout");
       	    if ($consumer_secret == null || $consumer_timeout == null) {
				// this is also a misconfiguration error!
				//dbg("auth/jics",__FILE__,__LINE__, " : Either the oauth_consumer_secret or oauth_consumer_timeout was not retrieved for the key {$consumer_key}");
				unset ($_POST);
				throw new invalid_parameter_exception("Module: auth/jics. Method: jics_moodle_blti_auth. Error: Either the oauth_consumer_secret or oauth_consumer_timeout was not retrieved for the key {$consumer_key}.");
		    }

		    // Add the consumer-secret pair to the datastore
		    $store = new NtsOAuthDataStore();
		    // create the consumer object in the datastore
		    $store->add_consumer($consumer_key, $consumer_secret);
		    $server = new OAuthServer($store,$consumer_timeout);
		    
			if (optional_param("oauth_signature_method",NULL,PARAM_RAW_TRIMMED) != 'HMAC-SHA1' ) {
			    unset ($_POST);
			    return ;
		    }
		    
		    $method = new OAuthSignatureMethod_HMAC_SHA1();
		    $server->add_signature_method($method);
			// remove any POST parameters with no values. This is required by BLTI.
			foreach ($_POST as $k=>$v) {
				dbg("local_jics",__FILE__,__LINE__,"{$k} => {$v} ");
				if ($v == null || trim($v)=='') {
					unset ($_POST[$k]);
				}
			}
			// Must pass original target URL since that is how the request was signed by the portlet.
			$target_url = make_oauth_target();
			dbg("local_jics",__FILE__,__LINE__,"target_url= {$target_url}");	
		    $request = OAuthRequest::from_request("POST", $target_url );
		    if ($request == null) {
				unset ($_POST);
				return ;
		    }
		    $base = $request->get_signature_base_string();
			dbg("local_jics",__FILE__,__LINE__,"base={$base}");
			$server->verify_request($request);
			
		    // While coralling passed values also construct array to use later to update the user record.
			// Upcase the idnumber now that we are done with OAuth. This will make it match what's in ICS_NET 
			// and make it easier for humans to compare visually!
		    $mapped_user_fields = array( 
				"firstname"  => trim(optional_param("lis_person_name_given",NULL,PARAM_RAW_TRIMMED)),
				"lastname" => trim(optional_param("lis_person_name_family",NULL,PARAM_RAW_TRIMMED)),
				"idnumber" => trim(strtoupper(required_param("user_id",PARAM_RAW_TRIMMED)))
			);
			// Email, City and Country are not guaranteed to have values in JICS, 
			// especially when JICS is unning without an ERP. So if we were passed values
			// use them and update the current values. If not let the current values remain.
			// If customer sets fields unlocked or unlocked-if-empty then users can edit fields in Moodle.
			if (($email=trim(optional_param("lis_person_contact_email_primary",NULL,PARAM_RAW_TRIMMED)))!=null) {
				$mapped_user_fields["email"] = $email ;
			}
			
			if ( ($config_city=get_config('auth/jics', "field_map_city")) != null && 
				(($city_param=trim(optional_param("custom_lis_person_address_city",NULL,PARAM_RAW_TRIMMED)))!=null) ){			
				// temporary fix for 7.5.2 LearningTools portlet bug
				if (strpos($city_param,'Jenzabar.CRM.Deserializers') !== false) { 
					$city_param = ( ($default_city = get_config(null, "defaultcity")) != null) ? 
						$default_city : 'Not Available' ; 
					dbg("auth/jics",__FILE__,__LINE__,"City value forced to {$city_param}");
				}
				$mapped_user_fields["city"] = $city_param ;
				//dbg("auth/jics",__FILE__,__LINE__,"CITY MAPPED config_city={$config_city} .... city_param={$city_param}");
			}

			
			if ( ($config_country=get_config('auth/jics', "field_map_country")) != null && 
				(($country_param=trim(optional_param("custom_lis_person_address_country",NULL,PARAM_RAW_TRIMMED)))!=null) ) {
				// temporary fix for 7.5.2 LearningTools portlet bug
				// AZ 2012-12-17: ERP might send full country name rather than 2 letter code!! Must fix with hack until better solution found.
				if (strlen($country_param) > 2 || strpos($country_param,'Jenzabar.CRM.Deserializers') !== false) { 
					$country_param = ( ($default_country = get_config(null, "country")) != null) ? 
						$default_country : 'US' ;
					dbg("auth/jics",__FILE__,__LINE__,"Country value forced to {$country_param}");
				}
				$mapped_user_fields["country"] = $country_param ;	
				//dbg("auth/jics",__FILE__,__LINE__,"COUNTRY MAPPED config_country={$config_country} .... city_param={$country_param}");				
			}			
			//2014-03-19
			// It is really simple! If the user followed a link here from JICS then clearly
			// the user is NOT disabled in JICS !!! No need to read the msDS-UserAccountDisabled attribute.
			if (get_config('auth/jics', "suspendifdisabled" ) ) {
				$mapped_user_fields["suspended"] = false;
			}
			//dbg("auth/jics",__FILE__,__LINE__,"mapped_user_fields[suspended] =". $mapped_user_fields["suspended"]);
		    
			// If this user does not yet have an account in the moodle db we must create one.
		    // Remember: we will be mapping the BLTI user_id value to the Moodle idnumber field
		    // and the BLTI lis_person_sourcedid value to the username. The field names are misleading. Sorry.
		    // Neither should be in use for a different person.
			
			// NOTE: (2012-10-18) The following assumed that the username is passed in lis_person_sourcedid, which is no longer true with the 
			// 7.5.3 LearningTools portlet. OK, we use legacy LT code for this integration, but when that changes the following will break!!
		    if (false == ($user = get_complete_user_data('username', strtolower(trim(required_param("lis_person_sourcedid",PARAM_RAW_TRIMMED))))) ) {
				// This username is not yet in use. Check the idnumber.
				if ($found_user=get_complete_user_data('idnumber', trim(required_param("user_id",PARAM_RAW_TRIMMED)))) {
					// The idnumber is in use!! Something is wrong.
					error_log("[AUTH_JICS]. Error creating new user ".
						trim(required_param("lis_person_sourcedid",PARAM_RAW_TRIMMED)) .
						" with idnumber " .
						trim(required_param("user_id",PARAM_RAW_TRIMMED)) . 
						" A different username already associated with this idnumber: " .
						$found_user->username );
					unset ($_POST);
					return ;
				}
				
				// user does not yet exist in Moodle
				$user = new object();
			    $user->id = 0;     // User does not exist
				$user->auth=$authname; // could hard code it but ...
	   			$user->password = '';
				$user->email = trim(optional_param("lis_person_contact_email_primary",NULL,PARAM_RAW_TRIMMED));
				$user->username=strtolower(trim(required_param("lis_person_sourcedid",PARAM_RAW_TRIMMED)));
				$user->confirmed = 1;
	   			$user->lastip = getremoteaddr();
	    		$user->timemodified = time();
	   			$user->mnethostid = get_config(NULL,'mnet_localhost_id');
	   			if (isset($_SERVER['REMOTE_ADDR'])) {
	   				$user->sesskey  = random_string(10);
	   				$user->sessionIP = md5(getremoteaddr());   // Store the current IP in the session
				}
				// Following throws exception if there is an error!
				
				$id = $DB->insert_record('user', $user);
		    } 	// end user does not exist

		    // User either was just created or already existed.
		    // Either way we need to add in/update the mapped values.
			dbg("auth/jics",__FILE__,__LINE__,"setting fields for user {$user->username}");
		    foreach ($mapped_user_fields as $key=>$val) {
				// we must remember to update the user record fields using Moodle column names
				// dbg("auth/jics",__FILE__,__LINE__,"key / val = {$key} / {$val} ");
				$DB->set_field('user', $key, $val, array('username'=>$user->username));
		    }
		    // update global object
		    $user=get_complete_user_data('username', $user->username);
		}
		catch (OAuthException $e) {
			dbg("local_jics",__FILE__,__LINE__,"Error. OAuth Exception: " . $e->getMessage());
			dbg("auth/jics",__FILE__,__LINE__,"Error. OAuth Exception: " . $e->getMessage());
			if (get_config("local_jics",'debug_trace')) {
				dbg("local_jics",__FILE__,__LINE__,"Forcing Exit.");
				exit;
			}
			error_log($e->getMessage());
			unset ($_POST);
			return;
		}
		catch (Exception $e) {
			dbg("local_jics",__FILE__,__LINE__,"Error. Exception: " . $e->getMessage());
			dbg("auth/jics",__FILE__,__LINE__,"Error. Exception: " . $e->getMessage());
			error_log($e->getMessage());
			unset ($_POST);
			return;
		}
		if (get_config("local_jics",'debug_trace')) {
			dbg("local_jics",__FILE__,__LINE__,"Debug set in Local JICS plugin: Forcing Exit.");
			exit;
		}
		$frm = new object();
		$frm->username = $user->username;
		$_SESSION["wantsurl"] = trim(required_param("wantsurl",PARAM_RAW_TRIMMED)); // for our own code
		$SESSION->wantsurl = trim(required_param("wantsurl",PARAM_RAW_TRIMMED));	// for Moodle code

		// Make sure the special POST parameters for the administrative functions are also placed in SESSION context so
		// they will be available to the sync routines. The latter should UNSET them when completed. But in case there is a
		// premature exit from the sync routines -- there are many error exit paths-- we add the "else" conditions here for a possible re-sync with default, missing 
		// parameters (in the same browser session). 
		if (null!=(optional_param("custom_ldap_sync_filter",null,PARAM_RAW_TRIMMED))) { $_SESSION["custom_ldap_sync_filter"] = trim(optional_param("custom_ldap_sync_filter",null,PARAM_RAW_TRIMMED)) ; }
		else { unset($_SESSION["custom_ldap_sync_filter"]) ;}
		if (null!=(optional_param("custom_ldap_sync_mode",null,PARAM_RAW_TRIMMED))) { $_SESSION["custom_ldap_sync_mode"] = trim(optional_param("custom_ldap_sync_mode",null,PARAM_RAW_TRIMMED)) ; }
		else { unset($_SESSION["custom_ldap_sync_mode"]) ;}
		if (null!=(optional_param("custom_xdb_sync_mode",null,PARAM_RAW_TRIMMED))) { $_SESSION["custom_xdb_sync_mode"] = trim(optional_param("custom_xdb_sync_mode",null,PARAM_RAW_TRIMMED)) ; }
		else { unset($_SESSION["custom_xdb_sync_mode"]) ;}
		if (null!=(optional_param("custom_xdb_sync_startdate",null,PARAM_RAW_TRIMMED))) { $_SESSION["custom_xdb_sync_startdate"] = trim(optional_param("custom_xdb_sync_startdate",null,PARAM_RAW_TRIMMED)) ; }
		else { unset($_SESSION["custom_xdb_sync_startdate"]) ;}
		if (null!=(optional_param("custom_ldap_sync_lastmodified",null,PARAM_RAW_TRIMMED))) { $_SESSION["custom_ldap_sync_lastmodified"] = trim(optional_param("custom_ldap_sync_lastmodified",null,PARAM_RAW_TRIMMED)) ; }
		else { unset($_SESSION["custom_ldap_sync_lastmodified"]) ;}
		
		return;
	} // end function
 /*
 *	Special method to construct the target url that must be built into the 
 *	OAuth request for signing. This url must be the same as the one used by
 *	JICS when it signed the OAuth request, namely "/local/jics/index.php". 
 *	It will NOT be the current Request URL which is /login/index.php.
 */
 function make_oauth_target(){
    $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
              ? 'http'
              : 'https';
    $orig_url = str_replace("login/index.php", "local/jics/index.php",$_SERVER['REQUEST_URI']);
    // the port number should be included in the $_SERVER['HTTP_HOST'] string
    return ($scheme . '://' . $_SERVER['HTTP_HOST'] . $orig_url );
 }

 ?>