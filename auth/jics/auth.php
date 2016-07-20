<?php
/**
 * @author Alan Zaitchik
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: JICS Authentication
 *
 * Authentication using JICS AD LDS and Active Directory
 *
 * AZ 2012-03-04  File revised.
 * Much of the code in this plugin was borrowed from the LDAP authentication plugin of Martin Dougiamas.
 * Specialized code for JICS and LTI-OAuth based authentication was added to implement the loginpage_hook
 * functionality, and additional specialized code was added to the bulk upload ('user syncronization') functionality.
 * Note that /lib/ldaplib.php has been cloned as /auth/jics/lib/auth_jics_lib.php, so as
 * to avoid a dependency on the current ldaplib.php code.
 * Much code could have been stripped out as irrelevant but was generally not
 * due to a lack of time. These routines, e.g. NTLMSSO, updates of the remote LDAP data, password caching/expiring, etc.,
 * are not used by the JICS authentication plugin and should be pruned out
 * when time allows!!
 */ 

/**
 * @author Martin Dougiamas
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: LDAP Authentication
 *
 * Authentication using LDAP (Lightweight Directory Access Protocol).
 *
 * 2006-08-28  File created.
 */ 
//require_once '../../config.php';
global $CFG;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// See http://support.microsoft.com/kb/305144 to interprete these values.
if (!defined('AUTH_AD_ACCOUNTDISABLE')) {
    define('AUTH_AD_ACCOUNTDISABLE', 0x0002);
}
if (!defined('AUTH_AD_NORMAL_ACCOUNT')) {
    define('AUTH_AD_NORMAL_ACCOUNT', 0x0200);
}
require_once($CFG->libdir.'/authlib.php');
require_once($CFG->libdir.'/ldaplib.php');
require_once($CFG->dirroot. '/auth/jics/lib/auth_jics_lib.php'); // adaptation of /lib/ldaplib.php

require_once($CFG->dirroot. '/local/jics/jics_lib.php'); // for the dbg function

/**
 * JICS authentication plugin.
 */
class auth_plugin_jics extends auth_plugin_base {

    /**
     * Constructor with initialisation.
     */
	function auth_plugin_jics() {

		$this->authtype = 'jics';
		$this->roleauth = 'auth_jics';
		$this->errorlogtag = '[AUTH JICS] ';
		$this->init_plugin($this->authtype);
    }

    function init_plugin($authtype) {
	    $this->pluginconfig = 'auth/'.$authtype;
        $this->config = get_config($this->pluginconfig);
        if (empty($this->config->ldapencoding)) {
            $this->config->ldapencoding = 'utf-8';
        }
        if (empty($this->config->user_type)) {
            $this->config->user_type = 'ad';
        }

        $ldap_usertypes = jics_supported_usertypes();
        $this->config->user_type_name = $ldap_usertypes[$this->config->user_type];
        unset($ldap_usertypes);
			
		// See auth/jics/lib/auth_jics_lib.php for function.
        $default = jics_getdefaults();

        // Use defaults if values not given
        foreach ($default as $key => $value) {
            // watch out - 0, false are correct values too
            if (!isset($this->config->{$key}) or $this->config->{$key} == '') {
                $this->config->{$key} = $value[$this->config->user_type];
            }
        }
        // Hack prefix to objectclass
        if (empty($this->config->objectclass)) {
            // Can't send empty filter
            $this->config->objectclass = '(objectClass=*)';
        } 
		else if (stripos($this->config->objectclass, 'objectClass=') === 0) {
            // Value is 'objectClass=some-string-here', so just add ()
            // around the value (filter _must_ have them).
            $this->config->objectclass = '('.$this->config->objectclass.')';
        }
        else if (strpos($this->config->objectclass, '(') !== 0) {
            // Value is 'some-string-not-starting-with-left-parentheses',
            // which is assumed to be the objectClass matching value.
            // So build a valid filter with it.
            $this->config->objectclass = '(objectClass='.$this->config->objectclass.')';
        }                                       
        else {
            //echo "<br />",__FILE__,":",__LINE__,": objectclass filter:" ,$this->config->objectclass ;
        }                                               
        
		
    }
	/**
	 * AZ 2012-04-17 Following adapted from parallel function in enrollib.php
	 * except that we do not read the version.php file, instead we use the setting.
	 * Note: if cron setting is -1 then return false unconditionally.
     * @return bool
    */
	 
    public function is_cron_required() {
        global $CFG;
		if ($this->config->cron <= 0) { // -1 also, for backwards compatibility.
			//dbg($this->pluginconfig,__FILE__,__LINE__,"is_cron_required returning FALSE because cron setting is <= 0.");
			return false;
		}
		/* old semantics for value of 0. To not wait at all just use value of 1 (=1 second). This is more Moodle-standard.
		else if ($this->config->lastcron == 0) {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"is_cron_required returning TRUE because lastcron setting is 0.");
			return true;
		}
		*/
		else {
			// need to calculate lapsed interval
			if ($this->config->lastcron + $this->config->cron < time()) {
				//dbg($this->pluginconfig,__FILE__,__LINE__,"is_cron_required returning TRUE. lastexecuted={$this->config->lastcron} and config->cron={$this->config->cron}");			
				return true;
			} else {
				//dbg($this->pluginconfig,__FILE__,__LINE__,"is_cron_required returning FALSE. lastexecuted={$this->config->lastcron} and config->cron={$this->config->cron}");					
				return false;
			}
		}
    }
	/* AZ 2013-01-10
	*  This will be the target of the CLI sync_users.php separately scheduled as a cron job.
	*  No need to test the wait interval since it has its own schedule!
	*  Write a file to leave a trace.
	*/
    public function cli_cron() {   
		global $CFG;
		try{
			$filepath = $CFG->dataroot.'/auth_cron.log';
			$fhandle=fopen($filepath,'a') ;
			$data = 'The JICS authentication plugin wrote this record at ' . date("Y-m-d H:i:s"). PHP_EOL;
			fwrite($fhandle, $data);
			fclose($fhandle);
		}
		catch (Exception $exception) {
			// continue;
		}
		// should we execute at all?
		$uploadmode = $this->config->upload_mode ; // Quick or Full or None
		if (strtolower($uploadmode) == 'none') {
			mtrace(html_br()."User upload mode was set to: {$uploadmode}. Skipping user upload.".html_br());
			return 0;
		}
		mtrace(html_br()."Starting AUTH_JICS user synchronization at " . userdate(time()) . html_br());
		
		// read settings and call sync_users()
		$ldap_filter = make_ldap_filter($this->config->upload_lastmodfilter, 
			$this->config->upload_groupfilter) ;
		//$uploadmode = $this->config->upload_mode ; // Quick or Full or None
		mtrace(html_br()."Using ldap filter: {$ldap_filter}".html_br());
		mtrace(html_br()."Using upload mode: {$uploadmode}".html_br());
		$retval = $this->sync_users (1000, (strtolower($uploadmode) == 'full'), $ldap_filter) ;
		mtrace(html_br()."Completed AUTH_JICS user synchronization at " . userdate(time()) . html_br());
		
		// save lastcron setting
		set_config('lastcron', time(), $this->pluginconfig);
		
		return $retval ; //0=success, 1=error
    } // end of function
	
	// called by Moodle cron 
    public function cron() {   
		// should we execute at all?
		$uploadmode = $this->config->upload_mode ; // Quick or Full or None
		if (strtolower($uploadmode) == 'none') {
			mtrace(html_br()."User upload mode was set to: {$uploadmode}. Skipping user upload.".html_br());
			return 0;
		}
		if (!$this->is_cron_required()) {
			mtrace(html_br()."No cron execution of user upload is required at this time. Skipping user upload.".html_br());
			return 0 ; //0=success, 1=error
		}
		mtrace(html_br()."Starting AUTH_JICS user synchronization at " . userdate(time()) . html_br());
		
		// read settings and call sync_users()
		$ldap_filter = make_ldap_filter($this->config->upload_lastmodfilter, 
			$this->config->upload_groupfilter) ;
		//$uploadmode = $this->config->upload_mode ; // Quick or Full or None
		mtrace(html_br()."Using ldap filter: {$ldap_filter}".html_br());
		mtrace(html_br()."Using upload mode: {$uploadmode}".html_br());
		$retval = $this->sync_users (1000, (strtolower($uploadmode) == 'full'), $ldap_filter) ;
		mtrace(html_br()."Completed AUTH_JICS user synchronization at " . userdate(time()) . html_br());
		
		// save lastcron setting
		set_config('lastcron', time(), $this->pluginconfig);
		
		return $retval ; //0=success, 1=error
    } // end of function

	// Called from /local/jics/sync.php (which is target $wantsurl for /login/index.php)
	// Session variables were set up in /local/jics/sync.php based on 
	// parameters in the HTTP Request from JICS!
	public function do_sync() {
		mtrace(html_br()."Starting AUTH JICS user synchronization at " . userdate(time()) . html_br());
		// If these parameters were not setup we use the persistent settings
		if (!empty($_SESSION["custom_ldap_sync_lastmodified"])) {
			$upload_lastmodefilter = $_SESSION["custom_ldap_sync_lastmodified"] ;
		}
		else {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"Inside do_sync with missing SESSION variable for custom_ldap_sync_lastmodified");
			$upload_lastmodefilter = $this->config->upload_lastmodfilter ;
		}
		if (!empty($_SESSION["custom_ldap_sync_filter"])) {
			$upload_groupfilter = $_SESSION["custom_ldap_sync_filter"] ;
		}
		else {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"Inside do_sync with missing SESSION variable for custom_ldap_sync_filter");
			$upload_groupfilter = $this->config->upload_groupfilter ;
		}
		$ldap_filter = make_ldap_filter($upload_lastmodefilter, $upload_groupfilter) ;
		mtrace(html_br()."Using ldap filter: {$ldap_filter}".html_br());

		if (!empty($_SESSION["custom_ldap_sync_mode"])) {
			$uploadmode = $_SESSION["custom_ldap_sync_mode"] ;
		}
		else {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"Inside do_sync with missing SESSION variable for custom_ldap_sync_mode");
			$uploadmode = $this->config->upload_mode ;
		}
		mtrace(html_br()."Using upload mode: {$uploadmode}".html_br());
		$retval = $this->sync_users (1000, (strtolower($uploadmode) == 'full'), $ldap_filter) ;
		mtrace(html_br()."Completed AUTH JICS user synchronization at " . userdate(time()) . html_br());
		return $retval ; //0=success, 1=error
	}
	
	function try_manual_login($username, $password) {
		global $CFG, $DB, $USER;
		// skip if password is not to be used
		if ($this->config->nodirectlogins || $password === 'changeme') {
			return false;
		}
		else if (!$user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) {
			return false;
		}
		else if (!validate_internal_user_password($user, $password)) {
			return false;
		}
		else {
			dbg($this->pluginconfig,__FILE__,__LINE__,"");
			return true;
		}
		
	}
	
    /**
	 * 12/27/2010 
	 * IMPORTANT CHANGE: We support option to disable direct login, e.g.
	 * when Moodle is hosted off-campus by 3rd party, without LDAPS for AD LDS. 
	 * The option now implemented in config_plugins table (AZ 2012-03-05)
	 *
 	 * 04/01/2011 
	 * IMPORTANT CHANGE: We try Active Directory in case the first bind to ADAM fails.
	 * THIS IS FOR JICS SITES USING EXTERNAL AUTHENTICATION
	 *
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     *
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
		
		global $CFG;
		global $site;
		if  ($this->config->nodirectlogins) {
			// user was not authenticated by special BLTI OAuth before we got here, so block user from logging in
			print_header("$site->fullname", $site->fullname, $navigation, '', '', true, '<div class="langmenu">'.$langmenu.'</div>');
			print_heading('Login failure.');
			print_simple_box('<h3>Please close this window and access your Moodle courses through the MyCourses portlet</h3>', 'center', '70%');
			print_footer();
			die;
		}
        if (! function_exists('ldap_bind')) {
            print_error('auth_ldapnotinstalled','auth');
            return false;
        }

        if (!$username or !$password) {    // Don't allow blank usernames or passwords
            return false;
        }
		
		//dbg($this->pluginconfig,__FILE__,__LINE__,"Inside user_login with username/password {$username} / {$password} ");
		
		//RIGHT HERE:
        //$textlib = textlib_get_instance();
        $extusername = core_text::convert(stripslashes($username), 'utf-8', $this->config->ldapencoding);
        $extpassword = core_text::convert(stripslashes($password), 'utf-8', $this->config->ldapencoding);
		// 2012-10-19 (AZ) If we are not using AD LDS ONLY then we must have configured the AD URL and Contexts settings (also for OpenLdap, despite the name)
 		if ($this->config->user_type != 'adlds') {
			if( empty($this->config->host_url_AD)) {
				// error. Missing URL
				dbg($this->pluginconfig,__FILE__,__LINE__,"Error. LDAP URL is not configured.");
				error_log("Error. LDAP URL is not configured.");
				return false;
			}
			else if( empty($this->config->ldapcontexts) ) {
				// error. Missing URL
				dbg($this->pluginconfig,__FILE__,__LINE__,"Error. LDAP Context must be specified when using Active Directory or OpenLDAP.");
				error_log("Error. LDAP Context must be specified when using Active Directory or OpenLDAP.");
				return false;
			}
			$use_ExtAuth = true;	// same for AD and OpenLDAP so just one switch
		}
		else {
			$use_ExtAuth = false;
		}
		//dbg($this->pluginconfig,__FILE__,__LINE__,"Will try to authenticate {$username} using {$this->config->user_type} at {$this->config->host_url}");
		
		// 2012-10-19 (AZ)
		// We will still need to get user information from AD LDS whether or not we're using AD, so first open
		// connection to AD LDS and check that the user information is there, using the privileged bind user account. 
		// If we fail we exit without bothering to authenticate user in AD since user is absent.
		$ldapconnection = $this->ldap_connect(); 
		if (!$ldapconnection) {
			// we were not able to connect to ADAM at all! 
			$this->ldap_close();
			error_log("Could not connect to AD LDS. ");
			// 2012-10-19 (AZ)
			// We used to die at this point, but instead continue IF preventpassindb is false for this plugin!
			if  ($this->config->preventpassindb) {
				dbg($this->pluginconfig,__FILE__,__LINE__,"Cannot connect to AD LDS. No cached passwords enabled so will NOT try manual login."); 
				print_error('auth_jics_noconnect','auth_jics','',$this->config->host_url);
				$retval = false; //would return false if we ever got there ;-)
			}
			else {
				// cached passwords enabled so give it a try...
				dbg($this->pluginconfig,__FILE__,__LINE__,"Cannot connect to AD LDS, but will try manual login using cached password."); 
				$retval = $this->try_manual_login($username, $password) ;
			}
		}
		else {
			// make sure user exists in ADAM. Every jics-authenticating user must exist in ADAM, even if we use AD!
			$ldap_user_dn = $this->jics_find_userdn($ldapconnection, $extusername);
			// if ldap_user_dn is empty, user does not exist
			// We reject this user and do not check for a local manual login option based on a cached password since user is gone frome JICS!!
			if (!$ldap_user_dn) {
				dbg($this->pluginconfig,__FILE__,__LINE__,"No record for {$extusername} in AD LDS."); 
				error_log("No record for [$extusername} in AD LDS.");
				$this->ldap_close();
				// We do not try manual auth with cached password since AD LDS is available and user is not in it!!
				$retval = false ; // return false;
			}
			// User record exists so it pays to try to authenticate user...
			// Note: Connection to AD LDS is still open.
			// Enhance performance by testing for username=='administrator' or username='HighPrivs'. In these cases try AD LDS bind ONLY no matter what.
			// No point trying AD/OpenLDAP for these accounts and then trying AD LDS after failure!
			else if (!$use_ExtAuth || strtolower($extusername) == 'administrator' || strtolower($extusername) == 'highprivs') {
				// Try AD LDS, close connection, return result		
				$ldap_login = @ldap_bind($ldapconnection, $ldap_user_dn, $extpassword);
				$this->ldap_close();
				if ($ldap_login) {
					dbg($this->pluginconfig,__FILE__,__LINE__,"ldap_bind in AD LDS returned TRUE for user_dn {$ldap_user_dn} ");
					$retval = true; //return true;
				}
				else {				
					// we failed to bind in AD LDS and are not using AD or OpenLDAP. Exit in shame.
					dbg($this->pluginconfig,__FILE__,__LINE__,"ldap_bind in AD LDS failed for user_dn {$ldap_user_dn}. Not configured to authenticate with AD or OpenLDAP."); 
					// we do NOT try a cached password in this case. the user must authenticate correctly in AD LDS since it IS available !
					$retval = false ; // return false;
				}		
			}
			// remaining cases: use AD/OpenLDAP. We may also try AD LDS and even Manual authentication as last ditch tries if AD/OpenLDAP fails
			else {
				// let's try AD/OpenLDAP
				if (TRUE===($retval=$this->jics_bind_AD($extusername,$extpassword))) {
					$this->ldap_close(); // we never closed it if !$use_ExtAuth
					$dbg_msg = ($retval)? "User {$extusername} was authenticated" : "User {$extusername} was NOT authenticated" ;
					dbg($this->pluginconfig,__FILE__,__LINE__,$dbg_msg); 
				}
				else {
					// Last ditch effort for a JICS-only account not in AD (other than administrator and HighPrivs which we handled above as special case)
					$ldap_login = @ldap_bind($ldapconnection, $ldap_user_dn, $extpassword);
					$this->ldap_close();
					if ($ldap_login) {
						dbg($this->pluginconfig,__FILE__,__LINE__,"ldap_bind in AD LDS returned TRUE for user_dn {$ldap_user_dn} after failing in AD/OpenLDAP");
						$retval = true; //return true;
					}
					// we failed to bind in AD and in AD LDS
					// was it a failure to connect?
					else if ($retval==-1 && !$this->config->preventpassindb) {
							// let user try manual authentication with cached password
							dbg($this->pluginconfig,__FILE__,__LINE__,"Cannot authenticate in AD or AD LDS, but will try manual login using cached password."); 
							$retval = $this->try_manual_login($username, $password) ;
					}
					else {	
						dbg($this->pluginconfig,__FILE__,__LINE__,"ldap_bind in AD LDS failed for user_dn {$ldap_user_dn} after failing in AD/OpenLDAP."); 
						$retval = false ; // return false;
					}
	
				}		
			}
		}
		
		// 2014-03-19 Add if $retval == TRUE...
		if ($retval) {
			// AZ 2013-08-12
			dbg($this->pluginconfig,__FILE__,__LINE__,"Will update mapped user fields.");
			// Update local records as appropriate
			$all_keys = array_keys(get_object_vars($this->config));
            $updatekeys = array();
            foreach ($all_keys as $key) {
                if (preg_match('/^field_updatelocal_(.+)$/',$key, $match)) {
                    // if we have a field to update it from
                    // and it must be updated 'onlogin' we
                    // update it here
                    if ( !empty($this->config->{'field_map_'.$match[1]})
                         and $this->config->{$match[0]} === 'onlogin') {
                        array_push($updatekeys, $match[1]); // the actual key name
                    }
                }
            }
            unset($all_keys); unset($key);
			try{
				if (!$this->update_user_record($username, $updatekeys)) {
					dbg($this->pluginconfig,__FILE__,__LINE__,"Could not update user record."); flush();
				}
				unset($updatekeys) ;
			}
			catch (Exception $exception){
				// non-existent user cannot be updated, so ignore error.
				return $retval;
			}
			dbg($this->pluginconfig,__FILE__,__LINE__,"Updated user record");
		}
		return $retval ;
	}
	function is_synchronised_with_external(){
		return true;
	}
    /**
     * reads userinformation from ldap and return it in array()
     *
     * Read user information from external database and returns it as array().
     * Function should return all information available. If you are saving
     * this information to moodle user-table you should honor syncronization flags
     *
     * @param string $username username (with system magic quotes)
     *
     * @return mixed array with no magic quotes or false on error
     */
    function get_userinfo($username) {
		//dbg($this->pluginconfig,__FILE__,__LINE__,"get_userinfo called for user {$username} ") ;

		//RIGHT HERE
        //$textlib = textlib_get_instance();
        $extusername = core_text::convert(stripslashes($username), 'utf-8', $this->config->ldapencoding);

        $ldapconnection = $this->ldap_connect();
        $attrmap = $this->ldap_attributes();

        $result = array();
        $search_attribs = array();

        foreach ($attrmap as $key=>$values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            foreach ($values as $value) {
                if (!in_array($value, $search_attribs)) {
                    array_push($search_attribs, $value);
                }
            }
        }

        $user_dn = $this->jics_find_userdn($ldapconnection, $extusername);
        if (!$user_info_result = ldap_read($ldapconnection, $user_dn, $this->config->objectclass, $search_attribs)) {
		//if (!$user_info_result = ldap_read($ldapconnection, $user_dn, $ldap_filter , $search_attribs)) {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"Skipping user {$username}. Disabled account in AD LDS?");
			//return false;
            return false; // error!
        }
        $user_entry = $this->ldap_get_entries($ldapconnection, $user_info_result);
        if (empty($user_entry)) {
			//dbg($this->pluginconfig,__FILE__,__LINE__,"");
            return false; // entry not found
        }

        foreach ($attrmap as $key=>$values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            $ldapval = NULL;
            foreach ($values as $value) {
                if ((core_text::strtolower($value) == 'dn') || (core_text::strtolower($value) == 'distinguishedname')) {
                    $result[$key] = $user_dn;
                }
                if (!array_key_exists($value, $user_entry[0])) {
					// special case: do not wend if key is country, city, or suspended since we handle that differently below!!
					if ($key  != 'country' && $key != 'city' && $key != 'suspended') {
						dbg($this->pluginconfig,__FILE__,__LINE__,"No AD LDS value for attribute {$value}. Will continue... " );
						continue; // wrong data mapping!
					}
                }

                if ($key == "idnumber") {
	                $newval = strtoupper(binaryGUID_to_textGUID($user_entry[0][$value][0] ));
              	}
				// Special cases: City and Country
				else if ($key == 'city' && ( !isset($user_entry[0][$value]) || $user_entry[0][$value][0] == null) ) {
					$newval = ( ($default_city = get_config(null, "defaultcity")) != null) ? $default_city : 'Not Available' ;
					//dbg($this->pluginconfig,__FILE__,__LINE__,"Updating City attribute value to {$newval}");
				}
				else if ($key == 'country' && ( !isset($user_entry[0][$value]) || $user_entry[0][$value][0] == null) ) {
					$newval = ( ($default_country = get_config(null, "country")) != null) ? $default_country : 'US' ;
					//dbg($this->pluginconfig,__FILE__,__LINE__,"Updating Country attribute value to {$newval}" );
				}
				// 2014-02-24 Special case: suspend Moodle account if user login disabled in JICS (if suspendifdisabled is set) but do NOT reset otherwise!
				else if ($key == 'suspended' ) { //&& isset( $user_entry[0][$value] ) && $user_entry[0][$value][0] != null ){
						if ($this->config->suspendifdisabled != true) {
							dbg($this->pluginconfig,__FILE__,__LINE__,"Suspend If Disabled feature not turned on." );
							continue; // ignore the suspended attribute. Leave user setting as it was...
						}
					// update our mapped field
					// suspend account if need be
					dbg($this->pluginconfig,__FILE__,__LINE__,"LDAP value for disable account {$username} is {$suspendval }");
					dbg($this->pluginconfig,__FILE__,__LINE__,"Raw LDAP value for disable account {$username} is " . $user_entry[0][$value][0]);
					$newval = $this->disabled_to_suspended($user_entry[0][$value]);
				}
            	else {
	                if (is_array($user_entry[0][$value])) {
	                   	$newval = core_text::convert($user_entry[0][$value][0], $this->config->ldapencoding, 'utf-8');
	                } else {
	                    $newval = core_text::convert($user_entry[0][$value], $this->config->ldapencoding, 'utf-8');
	                }
         		}
                if (!empty($newval)) { // favour ldap entries that are set
                    $ldapval = $newval;
                }
            }
            if (!is_null($ldapval)) {
                $result[$key] = $ldapval;
            }
        }
        $this->ldap_close();
        return $result;
    }
    /**
     * Translates the JICS "msDS-userAccountDisabled" value in AD LDS into the appropriate Moodle suspended value
     * @param mixed $disabled (null or array whose 0th element is empty string, 'TRUE', or 'FALSE')
     * @return boolean true or false
     */
    function disabled_to_suspended($disabled) {
		if ( !is_array($disabled) || empty($disabled[0]) || strtolower($disabled[0]) != 'true') {
			dbg($this->pluginconfig,__FILE__,__LINE__,"function disabled_to_suspended returning false " );
			return false;
		}
		else {
			dbg($this->pluginconfig,__FILE__,__LINE__,"function disabled_to_suspended returning true " );
			return true;
		}
	}
	
    /**
     * reads userinformation from ldap and return it in an object
     *
     * @param string $username username (with system magic quotes)
     * @return mixed object or false on error
     */
    function get_userinfo_asobj($username) {
        $user_array = $this->get_userinfo($username);
        if ($user_array == false) {
            return false; //error or not found
        }
        $user_array = truncate_userinfo($user_array);
        $user = new object();
        foreach ($user_array as $key=>$value) {
            $user->{$key} = $value;
        }
        return $user;
    }

    /**
     * returns all usernames from external database
     *
     * get_userlist returns all usernames from external database
     *
     * @return array
     */
    function get_userlist() {
        return $this->ldap_get_userlist("({$this->config->user_attribute}=*)");
    }

    /**
     * checks if user exists in Adam
     *
     * @param string $username (with system magic quotes)
     */
    function user_exists($username) {
		
		//RIGHT HERE
        //$textlib = textlib_get_instance();
        $extusername = core_text::convert(stripslashes($username), 'utf-8', $this->config->ldapencoding);

        //returns true if given username exist on ldap
        $users = $this->ldap_get_userlist("({$this->config->user_attribute}=".$this->filter_addslashes($extusername).")");
        return count($users);
    }


    /**
     * syncronizes user fron JICS AD LDS to moodle user table
     * Sync is now using username attribute.
     *
     * Syncing users can removes or suspends users that don't exists anymore in JICS AD LDS.
     * Sync creates new users and can updates mapped fields (e.g. email, names, etc.)
     *
     * @param int $bulk_insert_records will insert $bulkinsert_records per insert statement
     *                         valid only with $unsafe. increase to a couple thousand for
     *                         blinding fast inserts -- but test it: you may hit mysqld's
     *                         max_allowed_packet limit.
     * @param bool $do_updates will compare existing users with their JICS AD LDS records, and 
	 * updates Moodle records if needed. This takes time as it generates a new ldap query per useer.
	 * @param string $ldap_filter uses the filter in querying AD LDS.
     */
    function sync_users ($bulk_insert_records = 1000, $do_updates = true, $ldap_filter = null) {

        global $CFG, $DB;
		
		// suspend max execution time?
		$orig_max_execution_time = reset_max_execution_time($this->pluginconfig,0) ; // see /local/jics/jics_lib.php
		
		//RIGHT HERE:
        //$textlib = textlib_get_instance();
        $dbman = $DB->get_manager();

    /// Define table user to be created
        $table = new xmldb_table ('tmp_extuser');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mnethostid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('username', XMLDB_INDEX_UNIQUE, array('mnethostid', 'username'));
        print_string('creatingtemptable', 'auth_jics', 'tmp_extuser');	flush();
        $dbman->create_temp_table($table);


		echo html_br(),"Please be patient. The upload process may take time.",html_br(); flush();

        print_string('connectingldap', 'auth_jics');flush();
        $ldapconnection = $this->ldap_connect();

        if (!$ldapconnection) {
            $this->ldap_close();
            print get_string('auth_jics_noconnect','auth_jics',$this->config->host_url); flush();
			reset_max_execution_time($this->pluginconfig,$orig_max_execution_time) ; 
            return (1) ; // 0=success; 1=failure
        }

        ////
        //// get user's list from ldap to sql in a scalable fashion
        ////
        // prepare some data we'll need
		$filter = ($ldap_filter == null) ? 
			'(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')' :
			$ldap_filter ;
			
		echo "QUERY FILTER!: " . $filter;
		
        $contexts = explode(";",$this->config->contexts);

        if (!empty($this->config->create_context)) {
              array_push($contexts, $this->config->create_context);
        }


        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                //use ldap_search to find first user from subtree
                $ldap_result = ldap_search($ldapconnection, $context,
                    $filter,
                    array($this->config->user_attribute));
            } else {
                //search only in this context
                $ldap_result = ldap_list($ldapconnection, $context,
                    $filter,
                    array($this->config->user_attribute));
            }

			if(!$ldap_result) {
				continue;
            }
			
			$dot_counter = 0;
			$newline = false;
            if ($entry = @ldap_first_entry($ldapconnection, $ldap_result)) {
                do {
					if ($dot_counter++ >= 80) {
						$newline = true;
						$dot_counter = 0;
					}
					else {
						$newline = false;
					}
                    $value = ldap_get_values_len($ldapconnection, $entry, $this->config->user_attribute);
                    $value = core_text::convert($value[0], $this->config->ldapencoding, 'utf-8');
                    $this->ldap_bulk_insert($value, $newline);
                } while ($entry = ldap_next_entry($ldapconnection, $entry));
            }
            unset($ldap_result); // free mem
        }
        // If the temp table is empty, it probably means that something went wrong, exit
        // so as to avoid mass deletion of users if that option was selected. 
		// Still, we return success since the filter might be responsible for this situation.
        $count = $DB->count_records_sql('SELECT COUNT(username) AS count, 1 FROM {tmp_extuser}');
        if ($count < 1) {
			if (strpos($ldap_filter,'whenChanged')===FALSE) {
				// there is probably something wrong
				print_string('didntgetusersfromldap', 'auth_jics');
				reset_max_execution_time($this->pluginconfig,$orig_max_execution_time) ; 
				return (0) ; // 0=success; 1=failure
			}
			else {
				// if we are using the lastmodtime as a filter this might be ok. continue.
				print_string('didntgetusersfromldapOK', 'auth_jics');
				flush();
				// continue...
			}
        } else {
			echo "\n<br />";
            print_string('gotcountrecordsfromldap', 'auth_jics', $count);
        }
		flush();
/* AZ 2012-03-05 Adapted following case for our plugin even though
** we would never (?) want to use this function since our LDAP read
** is likely to exclude certain user groups who should remain
** in Moodle. 
*/
/// User removal
        // Find JICS users in DB that aren't in ldap -- to be removed!
        // this is still not as scalable (but how often do we mass delete?)

		// AZ 2012-04-17 Had to make following change but not sure why! Seems to be wrong.
        if ($this->config->removeuser != AUTH_REMOVEUSER_KEEP) {
		//if ($this->config->removeuser !== AUTH_REMOVEUSER_KEEP) {
            $sql = 'SELECT u.*
                      FROM {user} u
                      LEFT JOIN {tmp_extuser} e ON (u.username = e.username AND u.mnethostid = e.mnethostid)
                     WHERE u.auth = ?
                           AND u.deleted = 0
                           AND e.username IS NULL';
            $remove_users = $DB->get_records_sql($sql, array($this->authtype));

            if (!empty($remove_users)) {
                print_string('userentriestoremove', 'auth_jics', count($remove_users)); flush();

                foreach ($remove_users as $user) {
                    if ($this->config->removeuser == AUTH_REMOVEUSER_FULLDELETE) {
                        if (delete_user($user)) {
                            echo "\t"; print_string('auth_dbdeleteuser', 'auth_jics', array('name'=>$user->username, 'id'=>$user->id)); echo html_br(); flush();
                        } else {
                            echo "\t"; print_string('auth_dbdeleteusererror', 'auth_jics', $user->username); echo html_br(); flush();
                        }
                    } else if ($this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
                        $updateuser = new stdClass();
                        $updateuser->id = $user->id;
                        $updateuser->auth = 'nologin';
                        $DB->update_record('user', $updateuser);
                        echo "\t"; print_string('auth_dbsuspenduser', 'auth_jics', array('name'=>$user->username, 'id'=>$user->id)); echo html_br(); flush();
                    }
                }
            } else {
                print_string('nouserentriestoremove', 'auth_jics'); flush();
            }
            unset($remove_users); // free mem!
        }
		flush();
/// Revive suspended users
        if (!empty($this->config->removeuser) and $this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
            $sql = "SELECT u.id, u.username
                      FROM {user} u
                      JOIN {tmp_extuser} e ON (u.username = e.username AND u.mnethostid = e.mnethostid)
                     WHERE u.auth = 'nologin' AND u.deleted = 0";
            $revive_users = $DB->get_records_sql($sql);

            if (!empty($revive_users)) {
                print_string('userentriestorevive', 'auth_jics', count($revive_users)); flush();

                foreach ($revive_users as $user) {
                    $updateuser = new stdClass();
                    $updateuser->id = $user->id;
                    $updateuser->auth = $this->authtype;
                    $DB->update_record('user', $updateuser);
                    echo "\t"; print_string('auth_dbreviveduser', 'auth_jics', array('name'=>$user->username, 'id'=>$user->id)); echo html_br(); flush();
                }
            } else {
                print_string('nouserentriestorevive', 'auth_jics');
            }

            unset($revive_users);
        }
		flush();

/// User Updates - time-consuming (optional)
        if ($do_updates) {
            // narrow down what fields we need to update
            $all_keys = array_keys(get_object_vars($this->config));
            $updatekeys = array();
            foreach ($all_keys as $key) {
                if (preg_match('/^field_updatelocal_(.+)$/',$key, $match)) {
                    // if we have a field to update it from
                    // and it must be updated 'onlogin' we
                    // update it here
                    if ( !empty($this->config->{'field_map_'.$match[1]})
                         and $this->config->{$match[0]} === 'onlogin') {
                        array_push($updatekeys, $match[1]); // the actual key name
                    }
                }
            }
            unset($all_keys); unset($key);

        } 
        else {
			print_string('noupdatestobedone', 'auth_jics');
			echo html_br();
        }
		flush();
        // if no fields need to be updated (none mapped for local update) then skip it.
		if ($do_updates and !empty($updatekeys)) { // run updates only if relevant
            $users = $DB->get_records_sql('SELECT u.username, u.id
                                             FROM {user} u
                                            WHERE u.deleted = 0 AND u.auth = ? AND u.mnethostid = ?',
                                          array($this->authtype, $CFG->mnet_localhost_id));
            if (!empty($users)) {
				echo html_br();
                print_string('userentriestoupdate', 'auth_jics', count($users)); flush();

                $sitecontext = get_context_instance(CONTEXT_SYSTEM);
                if (!empty($this->config->creators) and !empty($this->config->memberattribute)
                  and $roles = get_archetype_roles('coursecreator')) {
                    $creatorrole = array_shift($roles);      // We can only use one, let's use the first one
                } else {
                    $creatorrole = false;
                }

                $transaction = $DB->start_delegated_transaction();
                $xcount = 0;
                $maxxcount = 100;

                foreach ($users as $user) {
                    echo html_br(),"\t"; print_string('auth_dbupdatinguser', 'auth_jics', array('name'=>$user->username, 'id'=>$user->id));
                    if (!$this->update_user_record($user->username, $updatekeys)) {
                        echo ' - '.get_string('skipped'); flush();
                    }
                    $xcount++;

                    // Update course creators if needed
                    if ($creatorrole !== false) {
                        if ($this->iscreator($user->username)) {
                            role_assign($creatorrole->id, $user->id, $sitecontext->id, $this->roleauth);
                        } else {
                            role_unassign($creatorrole->id, $user->id, $sitecontext->id, $this->roleauth);
                        }
                    }
                }
                $transaction->allow_commit();
                unset($users); // free mem
            }
		}else if ( $do_updates){
        // Display message only if Full Updates requested
			echo html_br();
            print_string('noupdatestobedone', 'auth_jics'); 
        }
		echo html_br();
		flush();

/// User Additions
        // note: we do not care about deleted accounts anymore
		echo html_br(),"Please continue to be patient. "; 
		echo html_br(),"Now calculating number of user records that need to be inserted.";
		echo html_br(),"This may take some time..."; 
		flush();
		$sql = 'SELECT e.id, e.username
					FROM {tmp_extuser} e
					LEFT JOIN {user} u ON (e.username = u.username AND e.mnethostid = u.mnethostid)
					WHERE u.id IS NULL';
        $add_users = $DB->get_records_sql($sql);

        if (!empty($add_users)) {
            print_string('userentriestoadd', 'auth_jics', count($add_users));
			flush();
            $sitecontext = get_context_instance(CONTEXT_SYSTEM);
            if (!empty($this->config->creators) and !empty($this->config->memberattribute)
              and $roles = get_archetype_roles('coursecreator')) {
                $creatorrole = array_shift($roles);      // We can only use one, let's use the first one
            } else {
                $creatorrole = false;
            }
			//dbg($this->pluginconfig,__FILE__,__LINE__,"creatorrole is set to {$creatorrole}") ;
            $transaction = $DB->start_delegated_transaction();
            foreach ($add_users as $user) {
                $user = $this->get_userinfo_asobj($user->username);
                //dbg($this->pluginconfig,__FILE__,__LINE__,"Dump of user from db:<br />\n");
                //print_r($user) ;
                //dbg($this->pluginconfig,__FILE__,__LINE__,"<br />\n");
				dbg($this->pluginconfig,__FILE__,__LINE__,"Processing user with username {$user->username}") ;
                // add a few attributes
				$user->timecreated  = time();
                $user->timemodified   = time();
                $user->confirmed  = 1;
                $user->auth       = $this->authtype;
                $user->mnethostid = get_config(null,'mnet_localhost_id');
				$user->deleted  = 0;
				
                // get_userinfo_asobj() might have replaced $user->username with the value
                // from JICS (which can be mixed-case). Make sure it's lowercase.
                $user->username = trim(core_text::strtolower($user->username));
                if (empty($user->lang)) {
                    $user->lang = $CFG->lang;
                }

				// Following now throws exception on error so no need to test result.
				// Note that following the new approach (using $DB->insert_record) we no longer check that the idnumber is not
				// already in use by a different username, as we still do if a user logs in and tries to authenticate
				// before the user record has been cretaed through bulk upload (sync). This test is probably silly anyway
				// given the guaranteed uniqueness of the JICS GUID.
                $id = $DB->insert_record('user', $user);
				echo "\t",html_br(); print_string('auth_dbinsertuser', 'auth_db', array('name'=>$user->username, 'id'=>$id)); flush();

                // Add course creators if needed
                if ($creatorrole !== false and $this->iscreator($user->username)) {
                    role_assign($creatorrole->id, $id, $sitecontext->id, $this->roleauth);
                }                  

            }
            $transaction->allow_commit();
            unset($add_users); // free mem
        } else {
            print_string('nouserstobeadded', 'auth_jics'); 
        }
        
		flush();
		$dbman->drop_table($table);
        $this->ldap_close();
		reset_max_execution_time($this->pluginconfig,$orig_max_execution_time) ;
		
		// 2012-10-31 (AZ) We now update the lastmodtime value no matter what. If the admin is changing the groups
		// in uploads/updates it is the Admin's responsibility to turn off the "modified since last upload" switch!
		
		
		//if (strpos($ldap_filter,'whenChanged') === FALSE) {
			$new_lastmodtime=time() ;
			dbg($this->pluginconfig,__FILE__,__LINE__,"Updating lastmodtime to {$new_lastmodtime} " ) ;
			// update lastmodtime
			set_config('lastmodtime',$new_lastmodtime,'auth/jics');
		//}
 		
        return 0; // 0=success; 1=error
    }

    /**
     * Update a local user record from an external source.
     * This is a lighter version of the one in moodlelib -- won't do
     * expensive ops such as enrolment.
     *
     * If you don't pass $updatekeys, there is a performance hit and
     * values removed from LDAP won't be removed from moodle.
     *
     * @param string $username username (with system magic quotes)
     */
    function update_user_record($username, $updatekeys = false) {
        global $CFG;
		global $DB;

        //just in case check text case
        $username = trim(core_text::strtolower($username));

// Get the current user record
        $user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id));
        if (empty($user)) { // trouble
            error_log($this->errorlogtag.get_string('auth_dbusernotexist', 'auth_jics', '', $username));
            print_error('auth_dbusernotexist', 'auth_jics', '', $username);
            die;
        }

        // Protect the userid from being overwritten
        $userid = $user->id;

        if ($newinfo = $this->get_userinfo($username)) {
            $newinfo = truncate_userinfo($newinfo);

            if (empty($updatekeys)) { // all keys? this does not support removing values
                $updatekeys = array_keys($newinfo);
            }

            foreach ($updatekeys as $key) {
                if (isset($newinfo[$key])) {
                    $value = $newinfo[$key];
                } else {
                    $value = '';
                }
				//2014-02-24
				// If LDAP disable account field is null or empty or FALSE then make sure Moodle suspend is false
				// ... but ONLY IF suspendifdisabled is set to true. 
                if (!empty($this->config->{'field_updatelocal_' . $key})) {
                    if ($user->{$key} != $value) { // only update if it's changed
						
						// 2014-02-24 Added test for new mapped field suspended
						if ($key == 'suspended' && $this->config->suspendifdisabled == true ) {
							$msg = "key is {$key} and value is {$value} for user {$username}"; 
							dbg($this->pluginconfig,__FILE__,__LINE__,"{$msg}") ;
							$DB->set_field('user', $key, $value, array('id'=>$userid));
						}

						// special hack to protect overwriting city, country, and email with null value
						else if ( ( ($key != 'email' && $key != 'city' && $key != 'country') || $value != '') ) {
								$msg = "key is {$key} and value is {$value} for user {$username}"; 
								dbg($this->pluginconfig,__FILE__,__LINE__,"{$msg}") ;
								$DB->set_field('user', $key, $value, array('id'=>$userid));
						}
                    }
                }
            }
        } else {
            return false;
        }
        return $DB->get_record('user', array('id'=>$userid, 'deleted'=>0));
    }

    // Using new version AZ 2012-03-05   
    /**
     * Bulk insert in SQL's temp table
     */
    function ldap_bulk_insert($username, $newline=false) {
        global $DB, $CFG;

        $username = core_text::strtolower($username); // usernames are __always__ lowercase.
		//dbg($this->pluginconfig,__FILE__,__LINE__,"Inside ldap_bulk_insert for {$username}") ;
		try{
			$DB->insert_record_raw('tmp_extuser', array('username'=>$username,
                    'mnethostid'=>$CFG->mnet_localhost_id), false, true);
		}
		catch (Exception $e) {
			mtrace("Error in ldap_bulk_insert for username {$username}" ) ;
			error_log("[AUTH_JICS] Error in ldap_bulk_insert for username {$username} ");
			exit(1); // blow up with error condition
		}
        if ($newline) { echo "\n"; }
		echo '.'; flush();
    }

    /**
	 * AZ 2012-03-27
	 * Leave this in but we will not use it since we will not base Creator capability on AD LDS information.
	 * The memberattribute will always be null.
	 *
     * Returns true if user should be coursecreator.
     *
     * @param mixed $username    username (without system magic quotes)
     * @return boolean result
     */
    function iscreator($username) {
        if (empty($this->config->creators) or empty($this->config->memberattribute)) {
            return null;
        }
		
		//RIGHT HERE
        //$textlib = textlib_get_instance();
        $extusername = core_text::convert($username, 'utf-8', $this->config->ldapencoding);

        $ldapconnection = $this->ldap_connect();

        if ($this->config->memberattribute_isdn) {
            if(!($userid = $this->jics_find_userdn($ldapconnection, $extusername))) {
                return false;
            }
        } else {
            $userid = $extusername;
        }

        $group_dns = explode(';', $this->config->creators);
        $creator = jics_isgroupmember($ldapconnection, $userid, $group_dns, $this->config->memberattribute);

        $this->ldap_close();

        return $creator;
    }
 

 
    /**
     * connects to ldap server
     *
     * Tries connect to specified ldap servers.
     * Returns connection result or error.
     *
     * @return connection result or dies
     */
    function ldap_connect($binddn='',$bindpwd='') {
		global $CFG;
        // Cache ldap connections (they are expensive to set up
        // and can drain the TCP/IP ressources on the server if we 
        // are syncing a lot of users (as we try to open a new connection
        // to get the user details). This is the least invasive way
        // to reuse existing connections without greater code surgery.
        if(!empty($this->ldapconnection)) {
            $this->ldapconns++;
            return $this->ldapconnection;
        }
        //Select bind password, With empty values use
        //ldap_bind_* variables or anonymous bind if ldap_bind_* are empty
        if ($binddn == '' and $bindpwd == '') {
            if (!empty($this->config->bind_dn)) {
               $binddn = $this->config->bind_dn;
            }
            if (!empty($this->config->bind_pw)) {
               $bindpwd = $this->config->bind_pw;
            }
        }
		
		// use new function in ldaplib.php
		// Note: it expects possible ";" delimited list of fully specified ldap sources 
		$debuginfo = '';	
		// ugly hack alert:
		// although internally we use the user_type 'adlds' throughout, when we actually connect to AD LDS using
		// the ldap_connect_moodle function we need to switch it back to 'ad'
		$adlds_usertype = 'ad' ; // needed value for next connect!!
		if($ldapconnection = ldap_connect_moodle($this->config->host_url, $this->config->ldap_version,
                                //$this->config->user_type, $this->config->bind_dn,
								$adlds_usertype, $this->config->bind_dn,
                                $this->config->bind_pw, $this->config->opt_deref,
                                $debuginfo)) {
            $this->ldapconns = 1;
            $this->ldapconnection = $ldapconnection;
            return $ldapconnection;
        }
		// AZ 2012-09-24 Throw no error. Just return FALSE.
		if (is_dbg($this->pluginconfig)) {
			echo "<br />",__FILE__,":",__LINE__,": Did not return normally from call to ldap_connect_moodle.";
			echo "<br />",__FILE__,":",__LINE__,": url: {$this->config->host_url}";
			echo "<br />",__FILE__,":",__LINE__,": user_type: {$this->config->user_type}";
			echo "<br />",__FILE__,":",__LINE__,": adlds_usertype: {$adlds_usertype}";
			echo "<br />",__FILE__,":",__LINE__,": bind_dn: {$this->config->bind_dn}";
			echo "<br />",__FILE__,":",__LINE__,": bind_pw: {$this->config->bind_pw} <br />";
		}
		return false;
    }


    /**
     * disconnects from a ldap server
     *
     */
    function ldap_close() {
        $this->ldapconns--;
        if($this->ldapconns == 0) {
            @ldap_close($this->ldapconnection);
            unset($this->ldapconnection);
        }
    }

    /**
     * retuns user attribute mappings between moodle and ldap
     *
     * @return array
     */

    function ldap_attributes () {
        $moodleattributes = array();
		// 2014-03-18 
		if (!in_array('suspended', $this->userfields)) {
			array_push($this->userfields,'suspended');
		}

        foreach ($this->userfields as $field) {
            if (!empty($this->config->{"field_map_$field"})) {
                $moodleattributes[$field] = $this->config->{"field_map_$field"};
                if (preg_match('/,/',$moodleattributes[$field])) {
                    $moodleattributes[$field] = explode(',', $moodleattributes[$field]); // split ?
                }
            }
        }
        $moodleattributes['username'] = $this->config->user_attribute;
        return $moodleattributes;
    }

    /**
     * Returns all usernames from LDAP
     *
     * @param $filter An LDAP search filter to select desired users
     * @return array of LDAP user names converted to UTF-8
     */

    function ldap_get_userlist($filter="*") {
    /// returns all users from ldap servers
        $fresult = array();

        $ldapconnection = $this->ldap_connect();

        if ($filter=="*") {
           $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->objectclass.')';
        }

        $contexts = explode(";",$this->config->contexts);

        if (!empty($this->config->create_context)) {
              array_push($contexts, $this->config->create_context);
        }

        foreach ($contexts as $context) {

            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                //use ldap_search to find first user from subtree
                $ldap_result = ldap_search($ldapconnection, $context,$filter,array($this->config->user_attribute));
            }
            else {
                //search only in this context
                $ldap_result = ldap_list($ldapconnection, $context,
                                         $filter,
                                         array($this->config->user_attribute));
            }
            if(!$ldap_result) {
                continue;
            }
            $users = $this->ldap_get_entries($ldapconnection, $ldap_result);

            //add found users to list
			//RIGHT HERE
			//$textlib = textlib_get_instance();
            for ($i=0;$i<count($users);$i++) {
				$extuser = core_text::convert($users[$i][$this->config->user_attribute][0],
                                             $this->config->ldapencoding, 'utf-8');
                array_push($fresult, $extuser);
            }
        }
		$this->ldap_close();
        return $fresult;
    }

    /**
     * return entries from ldap
     *
     * Returns values like ldap_get_entries but is
     * binary compatible and return all attributes as array
     *
     * @return array ldap-entries
     */

    function ldap_get_entries($conn, $searchresult) {
    //Returns values like ldap_get_entries but is
    //binary compatible
        $i=0;
        $fresult=array();
        $entry = ldap_first_entry($conn, $searchresult);
        do {
            $attributes = @ldap_get_attributes($conn, $entry);
            for ($j=0; $j<$attributes['count']; $j++) {
                $values = ldap_get_values_len($conn, $entry,$attributes[$j]);
                if (is_array($values)) {
                $fresult[$i][$attributes[$j]] = $values;
                }
                else {
                    $fresult[$i][$attributes[$j]] = array($values);
                }
            }
            $i++;
        }
        while ($entry = @ldap_next_entry($conn, $entry));
        //were done
        return ($fresult);
    }

    function prevent_local_passwords() {
		//echo "<br />",__FILE__,":",__LINE__,": this->config->preventpassindb=", $this->config->preventpassindb ;
		//echo "<br />",__FILE__,":",__LINE__,": !empty(this->config->preventpassindb)=", !empty($this->config->preventpassindb) ;
        return !empty($this->config->preventpassindb);
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }
   /**
     * Will get called before the login page is shown.
	 * This is how we implement SSO from JICS using LTI-OAuth !
     *
     */
    function loginpage_hook() {
        global $CFG, $SESSION;
        global $user;
		// Let Basic LTI-OAuth try to authenticate the user.
		   require_once('lib/auth_jics_lib.php');
		// If BLTI_OAuth rejects the SSO request then we'll try
		// another enabled auth plugin or fall through to the login screen.
		// Note: BLTI_OAuth will either create global $user object or not.
		// Our caller will test it. No return status is expected from OAuth 
		// or passed back to our caller.
		dbg($this->pluginconfig,__FILE__,__LINE__,"Calling jics_moodle_blti_auth for OAuth authentication.");
		try{ 
			jics_moodle_blti_auth('jics'); // now in /auth/jics/lib/auth_jics_lib.php
			if ( (is_dbg('local_jics') || is_dbg($this->pluginconfig)) && $user != null) { 
				$msg = "loginpage_hook(): OAuth authenticated username: {$user->username} with email: {$user->email}";
				dbg(null,__FILE__,__LINE__,$msg);
			}
			if (is_dbg('local_jics') && $user != null && $user->username != '' && $user->username != 'admin' && $user->username != 'administrator' ) {
				die("Forcing exit for non admin account since local_jics debug flag is set." ) ;
			}
			
			// AZ (2013-08-12)
			// Update local records as appropriate
			$all_keys = array_keys(get_object_vars($this->config));
			$updatekeys = array();
			foreach ($all_keys as $key) {
				if (preg_match('/^field_updatelocal_(.+)$/',$key, $match)) {
					// if we have a field to update it from
					// and it must be updated 'onlogin' we
					// update it here
					if ( !empty($this->config->{'field_map_'.$match[1]})
						 and $this->config->{$match[0]} === 'onlogin') {
						array_push($updatekeys, $match[1]); // the actual key name
					}
				}
			}
			unset($all_keys); unset($key);
			
			if (!$this->update_user_record($user->username, $updatekeys)) {
				dbg($this->pluginconfig,__FILE__,__LINE__,"Could not update user record."); flush();
			}
			unset($updatekeys) ;
			dbg($this->pluginconfig,__FILE__,__LINE__,"Updated user record");
		}
		
		catch (Exception $e) {
			$msg = "loginpage_hook(): OAuth did not authenticate user";
			dbg($this->pluginconfig,__FILE__,__LINE__,$msg);
			if (is_dbg('local_jics') ) {
				die("OAuth exception. Forcing exit since local_jics debug flag is set." ) ;
			}
		}

		return;
    }

    /**
     * Sync roles for this user
     *
     * @param $user object user object (without system magic quotes)
     */
    function sync_roles($user) {
        $iscreator = $this->iscreator($user->username);
        if ($iscreator === null) {
            return; //nothing to sync - creators not configured
        }

        if ($roles = get_roles_with_capability('moodle/legacy:coursecreator', CAP_ALLOW)) {
            $creatorrole = array_shift($roles);      // We can only use one, let's use the first one
            $systemcontext = get_context_instance(CONTEXT_SYSTEM);

            if ($iscreator) { // Following calls will not create duplicates
                role_assign($creatorrole->id, $user->id, 0, $systemcontext->id, 0, 0, 0, 'ldap');
            } else {
                //unassign only if previously assigned by this plugin!
                role_unassign($creatorrole->id, $user->id, 0, $systemcontext->id, 'ldap');
            }
        }
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
	
        include 'config.html';
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {
        // set to defaults if undefined
		
		// 2014-04-17 Support suspending user if disabled in AD LDS
		// NOTE: service contract required for cansuspendifdisabled
		if (isset($config->cansuspendifdisabled) &&  
			$config->cansuspendifdisabled == '1' ) {
				$DEFAULT_SUSPENDIFDISABLED = '1';	
		}
		else {
				$DEFAULT_SUSPENDIFDISABLED = '0';	
		}
		
		if (!isset($config->suspendifdisabled))
            { $config->suspendifdisabled= $DEFAULT_SUSPENDIFDISABLED; }		

		// 2013-09-17 Support username updating
		if (!isset($config->canunlockusername))
            { $config->canunlockusername= '0'; }
		if (!isset($config->lockusername))
            { $config->lockusername= '1'; }		

			// 2011-04-01 Active Directory support
		if (!isset($config->host_url_AD))
            { $config->host_url_AD= ''; }
		if (!isset($config->host_dn_AD))
            { $config->host_dn_AD= ''; }
		// bulk upload settings
		if (!isset($config->cron))
            { $config->cron = '86400'; } // default of 1 day
		if (!isset($config->lastcron))
            { $config->lastcron = '0'; } // default of never executed
		if (!isset($config->upload_mode))
            { $config->upload_mode= 'quick'; }
		if (!isset($config->debug_trace))
            { $config->debug_trace = '0'; }
        if (!isset($config->nodirectlogins))
            {$config->nodirectlogins = '0'; }
		if (!isset($config->upload_lastmodfilter))
            { $config->upload_lastmodfilter = 'false'; }
		if (!isset($config->upload_groupfilter))
            { $config->upload_groupfilter = 'Students,Faculty'; }
		if (!isset($config->upload_suspendmaxtime))
            { $config->upload_suspendmaxtime = 'false'; }
        if (!isset($config->host_url))
            { $config->host_url = ''; }
	    if (empty($config->ldapencoding))
            { $config->ldapencoding = 'utf-8'; }
        if (!isset($config->contexts))
            { $config->contexts = 'OU=PortalUsers,CN=Portal,O=Jenzabar,C=US'; }
			
        if (!isset($config->ldapcontexts))
            { $config->ldapcontexts = 'cn=users,dc=HOSTNAME,dc=DOMAIN,dc=edu' ; }

        if (!isset($config->user_type))
            { $config->user_type = 'ad'; }
        if (!isset($config->user_attribute))
            { $config->user_attribute = 'cn'; }
        if (!isset($config->search_sub))
            { $config->search_sub = 'false'; }
        if (!isset($config->opt_deref))
            { $config->opt_deref = LDAP_DEREF_NEVER; }
        if (!isset($config->preventpassindb))
            { $config->preventpassindb = '0'; } //save last password
        if (!isset($config->bind_dn))
            {$config->bind_dn = 'cn=HighPrivs,ou=PortalUsers,cn=Portal,o=Jenzabar,c=US'; }
        if (!isset($config->bind_pw))
            {$config->bind_pw = ''; }
        if (!isset($config->ldap_version))
            {$config->ldap_version = '3'; }
        if (!isset($config->objectclass))
            {$config->objectclass = 'jenzabar-ICSNET-PortalUser' ; }
        if (!isset($config->memberattribute))
            {$config->memberattribute = 'member'; }
        if (!isset($config->memberattribute_isdn))
            {$config->memberattribute_isdn = ''; }
        if (!isset($config->passtype))
           {$config->passtype = 'sha1'; }
        if (!isset($config->removeuser))
            {$config->removeuser = '0'; }
			
		// 2013-09-17 Support username modification
		set_config('lockusername', trim($config->lockusername),$this->pluginconfig);
		// 2014-02-24 Support suspending user if disabled in AD LDS
		set_config('suspendifdisabled', trim($config->suspendifdisabled),$this->pluginconfig);
		
		set_config('additionalRole', trim($config->additionalRole),$this->pluginconfig);
		
		set_config('host_url_AD', trim($config->host_url_AD), $this->pluginconfig);
        set_config('host_dn_AD', trim($config->host_dn_AD), $this->pluginconfig);
		set_config('host_url', trim($config->host_url), $this->pluginconfig);
        set_config('ldapencoding', trim($config->ldapencoding), $this->pluginconfig);
        set_config('host_url', trim($config->host_url), $this->pluginconfig);
        set_config('contexts', trim($config->contexts), $this->pluginconfig);
		set_config('ldapcontexts', trim($config->ldapcontexts), $this->pluginconfig);
		
        set_config('user_type', trim($config->user_type), $this->pluginconfig);
        set_config('user_attribute', trim($config->user_attribute), $this->pluginconfig);
        set_config('search_sub', trim($config->search_sub), $this->pluginconfig);
        set_config('opt_deref', trim($config->opt_deref), $this->pluginconfig);
        set_config('preventpassindb', trim($config->preventpassindb), $this->pluginconfig);
        set_config('bind_dn', trim($config->bind_dn), $this->pluginconfig);
        set_config('bind_pw', trim($config->bind_pw), $this->pluginconfig);
        set_config('ldap_version', trim($config->ldap_version), $this->pluginconfig);
        set_config('objectclass', trim(trim($config->objectclass)), $this->pluginconfig);
        set_config('memberattribute', trim($config->memberattribute), $this->pluginconfig);
        set_config('memberattribute_isdn', trim($config->memberattribute_isdn), $this->pluginconfig);
        set_config('passtype', trim($config->passtype), $this->pluginconfig);
        set_config('removeuser', trim($config->removeuser), $this->pluginconfig);
		set_config('upload_suspendmaxtime', trim($config->upload_suspendmaxtime), $this->pluginconfig);
		set_config('upload_mode', trim($config->upload_mode), $this->pluginconfig);
		set_config('debug_trace', trim($config->debug_trace), $this->pluginconfig);
        set_config('nodirectlogins', trim($config->nodirectlogins), $this->pluginconfig);
		set_config('cron', trim($config->cron), $this->pluginconfig);
		set_config('upload_lastmodfilter', trim($config->upload_lastmodfilter), $this->pluginconfig);
		set_config('upload_groupfilter', trim($config->upload_groupfilter), $this->pluginconfig);

        return true;
    }

    /**
     * Quote control characters in texts used in ldap filters - see RFC 4515/2254
     *
     * @param string
     */
    function filter_addslashes($text) {
        $text = str_replace('\\', '\\5c', $text);
        $text = str_replace(array('*',    '(',    ')',    "\0"),
                            array('\\2a', '\\28', '\\29', '\\00'), $text);
        return $text;
    }

    /**
     * The order of the special characters in these arrays _IS IMPORTANT_.
     * Make sure '\\5C' (and '\\') are the first elements of the arrays.
     * Otherwise we'll double replace '\' with '\5C' which is Bad(tm)
     */ 
    var $LDAP_DN_QUOTED_SPECIAL_CHARS = array('\\5c','\\20','\\22','\\23','\\2b','\\2c','\\3b','\\3c','\\3d','\\3e','\\00');
    var $LDAP_DN_SPECIAL_CHARS        = array('\\',  ' ',   '"',   '#',   '+',   ',',   ';',   '<',   '=',   '>',   "\0");

    /**
     * Quote control characters in distinguished names used in ldap - See RFC 4514/2253
     *
     * @param string
     * @return string
     */
    function ldap_addslashes($text) {
        $text = str_replace ($this->LDAP_DN_SPECIAL_CHARS,
                             $this->LDAP_DN_QUOTED_SPECIAL_CHARS,
                             $text);
        return $text;
    }

	////////////////////////////////////////////////////
	//	Added to support Active Directory as 
	//	JICS External Authentication source
	////////////////////////////////////////////////////
	/**
	 * connect to AD as an alternate LDAP server 
	 *
	 * This is much simplified since we do not
	 * connect to AD except when individual user
	 * is logging in to Moodle directly. If there is
	 * a problem the subsequent bind for that user will
	 * fail. No need to worry about synchronization
	 * case so there is no anoynymous bind and
	 * no bind with a bind dn.
	 */
    function jics_connect_AD($server) {
		global $CFG;
		// Use an existing connection
		if(!empty($this->ldapconnection_AD)) {
			dbg($this->pluginconfig,__FILE__,__LINE__," Count of connection was {$this->ldapconns_AD} ");
            $this->ldapconns_AD++;
            return $this->ldapconnection_AD;
        }
		$connresult = false;
		//Following is now passed as a parameter
		//$server = $this->config->host_url_AD;
		
		// if configuration did not specify any method, make ldap the default
		// need to prepend method so we can handle case where port was specified without method
		// using normal code below
		if (strpos(strtolower($server),'ldap://') === false  && 
			strpos(strtolower($server),'ldaps://') === false ) {
			$server = "ldap://" . $server ;
		}
		if (strpos(strtolower($server),'ldaps://') === false ) {
			$ldap_method = "ldap://";
			// set default port
			$ldap_port = '389' ;
		}
		else {
			$ldap_method = "ldaps://";
			// set default port			
			$ldap_port = '636' ;
		}
		// Respect request for a specific port and set up host in same test
		if ( ($port_delimiter = strpos($server,':',strlen($ldap_method))) !== false) {
			$ldap_host = substr($server,0,$port_delimiter);
			$ldap_port = substr($server,$port_delimiter+1);
		}
		else {
			$ldap_host = $server;
		}
		dbg($this->pluginconfig,__FILE__,__LINE__," ldap_host for AD = {$ldap_host}");
		dbg($this->pluginconfig,__FILE__,__LINE__," ldap_port for AD = {$ldap_port}");
		
		$connresult = ldap_connect($ldap_host,$ldap_port);
		ldap_set_option($connresult, LDAP_OPT_REFERRALS, 0);
		
		$this->ldapconns_AD = 1;  
		$this->ldapconnection_AD = $connresult;
		return $connresult;
		
	}		
	/**
	* Bind to Active Directory as a user.
	* Returns true (1) to authenticate, -1 if AD unavailable, false (0) if user failed to authenticate for some other reason (e.g. bad credentials)
	* Opens and closes connection to AD, as needed.
	*/
	function jics_bind_AD($username,$password){
		// There could be more than one AD server configured in semicolon-delimited string $this->config->host_url_AD
		// There may also be multiple contexts in which user is found, in $this->config->ldapcontexts.
		// For each configured server we check each configured context. 
		// If we make any successful bind we declare victory.
		$retval= 0 ; // assume general failure
		dbg($this->pluginconfig,__FILE__,__LINE__,": Value for host_url_AD is {$this->config->host_url_AD}");
		$AD_urls = explode(';', $this->config->host_url_AD);
		foreach ($AD_urls as $server) {
			$server = trim($server);
			if (empty($server)) {
				continue;
			}
			dbg($this->pluginconfig,__FILE__,__LINE__,": Trying server {$server}");
			$ldap_user_dn = $this->jics_find_userdn_AD(null, $username) ;
			//possibly got back multiple user DN strings, semicolon delimtied, one per searchable context
			$user_dn= explode(';',$ldap_user_dn) ;
			// do each context
			foreach ($user_dn as $userdn) { 
				if (empty($userdn)) {
					continue;
				}
				// Not sure this will help with ldap_bind on all LDAP servers...
				// And what timeout value (in seconds) should we use?
				// Need to think this through...
				//ldap_set_option($connresult, LDAP_OPT_NETWORK_TIMEOUT, 30);
				
				// Note that following ALWAYS returns a resource in LDAP 2.2 and later
				$ldapconnection_AD = $this->jics_connect_AD($server) ;
				dbg($this->pluginconfig,__FILE__,__LINE__,": Trying bind on server {$server} using {$userdn}");
				try{
					$ldap_login = @ldap_bind($ldapconnection_AD, $userdn, $password);
					$retval = ( ($binderrno=ldap_errno($ldapconnection_AD)) == -1)? -1 : $ldap_login ;
					dbg($this->pluginconfig,__FILE__,__LINE__,": jics_bind_AD set to return {$retval} .");
				}
				catch (Exception $exception) { 
					$retval = 0 ;
					dbg($this->pluginconfig,__FILE__,__LINE__,": Exception binding on server {$server} using {$userdn}.");
					error_log("[AUTH_JICS] Error binding on server {$server} using {$userdn}");
					dbg($this->pluginconfig,__FILE__,__LINE__,"Exception caught. Will continue..."); 
				}
				dbg($this->pluginconfig,__FILE__,__LINE__,"Closing LDAP connection");
				$this->jics_close_AD();
				if ($ldap_login) {
					dbg($this->pluginconfig,__FILE__,__LINE__,": ldap_bind in AD returned TRUE for user_dn  {$ldap_user_dn}"); 
					if (is_noredirect($this->pluginconfig)) {
						die("Asked to die at ".__FILE__.':'.__LINE__) ;
					}
					return $retval;
				}
				else {
					dbg($this->pluginconfig,__FILE__,__LINE__,": ldap_bind in AD returned FALSE for user_dn  {$ldap_user_dn}");
				}
				// else try next URL ...
			}
			// We failed on all contexts for this server. Try another server if any.
			dbg($this->pluginconfig,__FILE__,__LINE__,"retval = {$retval}");
			if (is_noredirect($this->pluginconfig)) {
				die("Asked to die at ".__FILE__.':'.__LINE__) ;
			}
			//$this->jics_close_AD();
		}
		// User could not bind, possibly not found at all
		dbg($this->pluginconfig,__FILE__,__LINE__,": ldap_bind in AD returning  {$retval} for user_dn  {$ldap_user_dn}"); 
		if (is_noredirect($this->pluginconfig)) {
			die("Asked to die at ".__FILE__.':'.__LINE__) ;
		}
		return $retval;
	}
	/**
     * disconnects from an AD server
     */
    function jics_close_AD() {
        $this->ldapconns_AD--;
        if($this->ldapconns_AD == 0) {
            @ldap_close($this->ldapconnection_AD);
            unset($this->ldapconnection_AD);
        }
    }
	/**
	 * return dn of username for bind in AD
	 *
	 * We do not need to search any contexts because the configuration with AD 
	 * requires that the users be found on a simple bind like
	 * "john.due@my.school.edu" which where the host dn has been configured already
	 * during integration and saved.
	 * The $ldapconnection parameter is just in case we want to change this someday
	 * and support lookups by context per server. It is passed as a NULL at present.
	 * 2012-10-30 (AZ) Added support for multiple contexts and for OpenLDAP syntax
	 */
	function jics_find_userdn_AD ($ldapconnection,$extusername) {
		global $CFG;
		dbg($this->pluginconfig,__FILE__,__LINE__,": Entered jics_find_userdn_AD");
		dbg($this->pluginconfig,__FILE__,__LINE__,": extusername is ".$extusername) ; 
		dbg($this->pluginconfig,__FILE__,__LINE__,": LDAP Contexts is ".($this->config->ldapcontexts)) ;
		dbg($this->pluginconfig,__FILE__,__LINE__,": Member attribute is {$this->config->memberattribute}");
		$user_dn = "";
		
		//explode the $this->config->ldapcontexts string into pieces
		$ldap_contexts= explode(';',$this->config->ldapcontexts) ;
		//distribute the filter into each piece and create new string to return
		foreach ($ldap_contexts as $context) {
			dbg($this->pluginconfig,__FILE__,__LINE__,"this context = {$context}");
		    if (empty($context)) {
				dbg($this->pluginconfig,__FILE__,__LINE__,"Empty context found in ldapcontexts string");
                continue;
            }
			dbg($this->pluginconfig,__FILE__,__LINE__,": Adding context {$context}");
			if (strpos($context,'@') === false)
			{	
				dbg($this->pluginconfig,__FILE__,__LINE__,": Using FQN syntax ") ;
				$user_dn .= sprintf(";%s=%s,%s", $this->config->memberattribute,$extusername,$context);
			}
			else 
			{	
				dbg($this->pluginconfig,__FILE__,__LINE__,": Using AD @dc syntax ") ;
				$user_dn .= sprintf(";%s%s",$extusername,$context) ;
			}
			dbg($this->pluginconfig,__FILE__,__LINE__,": Added a term to user_dn. It is now {$user_dn}");
		}
		// old code for AD and for single-context version
		//return ($extusername . "@" . $this->config->host_dn_AD) ;
		//return sprintf("%s=%s,%s", $this->config->memberattribute,$extusername,$this->config->ldapcontexts);
		
		//skip over initial semicoloon
		dbg($this->pluginconfig,__FILE__,__LINE__,": jics_find_userdn_AD returning ". substr($user_dn,1));
        return (substr($user_dn,1));
	}

    /**
     * retuns dn of username
     *
     * Search specified contexts for username and return user dn
     * like: cn=username,ou=suborg,o=org
     *
     * @param mixed $ldapconnection  $ldapconnection result
     * @param mixed $username username (external encoding no slashes)
     *
     */
    function jics_find_userdn ($ldapconnection, $extusername) {

        //default return value
        $ldap_user_dn = FALSE;

        //get all contexts and look for first matching user
        $ldap_contexts = explode(";",$this->config->contexts);

        if (!empty($this->config->create_context)) {
          array_push($ldap_contexts, $this->config->create_context);
        }

        foreach ($ldap_contexts as $context) {

            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            if ($this->config->search_sub) {
                //use ldap_search to find first user from subtree
                $ldap_result = ldap_search($ldapconnection, $context, "(".$this->config->user_attribute."=".$this->filter_addslashes($extusername).")",array($this->config->user_attribute));

            }
            else {
                //search only in this context
                $ldap_result = ldap_list($ldapconnection, $context, "(".$this->config->user_attribute."=".$this->filter_addslashes($extusername).")",array($this->config->user_attribute));
            }

            $entry = ldap_first_entry($ldapconnection,$ldap_result);

            if ($entry) {
                $ldap_user_dn = ldap_get_dn($ldapconnection, $entry);
                break ;
            }
        }

        return $ldap_user_dn;
    }

}


?>
