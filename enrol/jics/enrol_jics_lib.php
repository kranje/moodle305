<?php
/*
To eliminate need for ODBC connection to JICS database use webservice.
This class and methods serve both setup_enrolments as well as sync_enrolments
and thus allows for various arguments to guide the call to the parameterized web service.
*/

define ('MOODLE_VERSION_nnx','Moodle22x') ;
class jics_enrollment_webservice {
	var $service_source;
	var $service_endpoint;
	var $selectedlms;
	var $selectedlmsversion;
	var $service_timeout;
	/*
	* Set up the connector to the JICS web service
	*/
	function init() {
    	global $CFG;
		require_once "{$CFG->dirroot}/local/jics/jics_lib.php";
        // use the setting from the local_jics plugin ! no need to duplicate it here.
        $this->service_source = trim(get_config('local_jics','jics_url')) ;
    	//dbg('enrol_jics',__FILE__,__LINE__,"service_source is {$this->service_source}") ;
    	$this->service_endpoint = trim(get_config('enrol_jics','enrollment_service')) ;
    	//dbg('enrol_jics',__FILE__,__LINE__,"service_endpoint is {$this->service_endpoint }");
    	// identify ourselves as the SelectedLMS. This is our OAuth key by which we are known to the external system.
    	$this->selectedlms = $CFG->wwwroot . '/local/jics/index.php' ;
		//dbg('enrol_jics',__FILE__,__LINE__,"selectedlms is {$this->selectedlms}") ;
    	$this->selectedlmsversion = MOODLE_VERSION_nnx ;
		//dbg('enrol_jics',__FILE__,__LINE__,"selectedlmsversion is {$this->selectedlmsversion}") ;
		// get timeout from config_plugins table
		$this->service_timeout = ( ($t_out= get_config('enrol_jics','enrollment_service_timeout')) == null ) ? 120 : $t_out ;
		//dbg('enrol_jics',__FILE__,__LINE__,"enrollment_service_timeout is {$this->service_timeout}") ;
		if (empty($this->service_source) || empty($this->service_endpoint) ) { return false; }
    	else {return true ;} 
	}
	/*
	* Functions to invoke JICs web service that will
	* return XML document containing the enrollment data requested.
	*/
	// get all enrollment data for (specified) user(s) (the id parameter)
	function get_enrollments($lmod=0, $id='*', $role='*') {
		global $CFG;
		require_once "{$CFG->dirroot}/local/jics/jics_lib.php";
		// what last modified filter should we use?
	    if ($lmod != 0) {
			// not yet supported
			//throw new Exception ("LMOD parameter not yet supported by JICS enrollment plugin");
		}
		
		// calculate timeout value for this request as now plus 5 minutes
		$to = time() + 5*60 ;
		$qs = "selectedlms={$this->selectedlms}&selectedlmsversion={$this->selectedlmsversion }&id={$id}&timeout={$to}&lastmodtime={$lmod}&role={$role}&get=enrollments";

		// AZ 2012-03-28
		// TO DO: 
		// Use OAuth Body signing to send and receive these messages!!
		
		// Use OAuth to sign the parameters according to OAuth rules, for this service provider.
		// We get back a full query string that includes all parameters that went into the signing, 
		// as well as the signature itself (as "oauth_signature")
		$signed_qstring=$this->jics_moodle_enroll_oauth($this->service_source,$this->service_endpoint, $qs);
	
		// construct the effective URL to use
		$service = "{$this->service_source}/{$this->service_endpoint}?{$signed_qstring}" ;
		$timeout = $this->service_timeout ;
		dbg('enrol_jics',__FILE__,__LINE__,"Service URL= {$service}") ;
		try{
			$ctx=stream_context_create(array('http'=> array( 'timeout' => $timeout ) ));
			$buff=htmlspecialchars_decode(urldecode(file_get_contents($service,false,$ctx)));
			//$buff=htmlspecialchars_decode(urldecode(file_get_contents($service)));

			$buff_out=str_replace("<","| ",$buff);
			$buff_out=str_replace(">","| ",$buff_out);
			//dbg('enrol_jics',__FILE__,__LINE__,"START BUFF-->") ;
			//dbg('enrol_jics',' ',' ',"{$buff_out}") ;
			//dbg('enrol_jics',__FILE__,__LINE__,"<--END BUFF") ;
			// parse as XML
			$xml = simplexml_load_string($buff);
			return $xml ; 

		}
		catch (Exception $e) {
			dbg('enrol_jics',__FILE__,__LINE__,"Error trying to retrieve enrollment data from JICS.") ;
			return null;
		}
 	}

 	// get all users is a given course (the id parameter=CoursecodeYrTermSection) in a given role.
	function get_users($lmod=0, $id='*', $role='*') {
		global $CFG;	
		// what last modified filter should we use?
	    if ($lmod != 0) {
			// not yet supported
			//throw new Exception ("LMOD parameter not yet supported by JICS enrollment plugin");
		}
		// calculate timeout value for this request as now plus 5 minutes
		$to = time() + 5*60 ;
		$qs = "selectedlms={$this->selectedlms}&selectedlmsversion={$this->selectedlmsversion}&id={$id}&timeout={$to}&lastmodtime={$lmod}&role={$role}&get=users";

		// Use OAuth to sign the parameters according to OAuth rules, for this service provider.
		// We get back a full query string that includes all parameters that went into the signing, 
		// as well as the signature itself (as "oauth_signature")
		$signed_qstring=$this->jics_moodle_enroll_oauth($this->service_source,$this->service_endpoint, $qs);
	
		// construct the effective URL to use
		$service = "{$this->service_source}/{$this->service_endpoint}?{$signed_qstring}" ;
		dbg('enrol_jics',__FILE__,__LINE__,"Service URL= {$service}") ;

		
		dbg('enrol_jics',__FILE__,__LINE__,"Service URL= {$service}") ;
			
		try {
			$timeout = $this->service_timeout ;
			$ctx=stream_context_create(array('http'=> array('timeout' => $timeout  ) ));
			$buff=htmlspecialchars_decode(urldecode(file_get_contents($service,false,$ctx)));
			//$buff=htmlspecialchars_decode(urldecode(file_get_contents($service)));

			$buff_out=str_replace("<","| ",$buff);
			$buff_out=str_replace(">","| ",$buff_out);

			dbg('enrol_jics',__FILE__,__LINE__,"START BUFF-->") ;
			dbg('enrol_jics',' ',' ',"{$buff_out}") ;
			dbg('enrol_jics',__FILE__,__LINE__,"<--END BUFF") ;

			// parse as XML
			$xml = simplexml_load_string($buff);
		}
		catch (Exception $e) {
			dbg('enrol_jics',__FILE__,__LINE__,"Exception is ". $e->getMessage());
			error_log('[ENROL_JICS] Error status from remote enrollment web service:' . $e->getMessage() );
			$xml = null;
		}		
		return $xml ;
 	}
 	// get all courses with some users enrolled in a (given) role.
 	// can specify user to see if specific user is in any course in (specific) role or can wildcard id parameter to get all courses
 	function get_courses($earliest='0', $id='*', $role='*', $crs='*') {
		global $CFG;	
		//dbg('enrol_jics',__FILE__,__LINE__," inside get_courses. Recived crs parameter {$crs}");
		// calculate timeout value for this request as now plus 5 minutes
		$to = time() + 5*60 ;
		$qs = "selectedlms={$this->selectedlms}&selectedlmsversion={$this->selectedlmsversion}&id={$id}&		timeout={$to}&lastmodtime={$earliest}&role={$role}&get=courses&crs={$crs}";

		// Use OAuth to sign the parameters according to OAuth rules, for this service provider.
		// We get back a full query string that includes all parameters that went into the signing, 
		// as well as the signature itself (as "oauth_signature")
		$signed_qstring=$this->jics_moodle_enroll_oauth($this->service_source,$this->service_endpoint, $qs);
	
		// construct the effective URL to use
		$service = "{$this->service_source}/{$this->service_endpoint}?{$signed_qstring}" ;
		$timeout = $this->service_timeout ;
		dbg('enrol_jics',__FILE__,__LINE__,"Service URL= {$service}") ;
		try {		
			$ctx=stream_context_create(array('http'=> array('timeout' => $timeout  ) ));
			$buff=htmlspecialchars_decode(urldecode(file_get_contents($service,false,$ctx)));
			//$buff=htmlspecialchars_decode(urldecode(file_get_contents($service)));
			
		
			$buff_out=str_replace("<","| ",$buff);
			$buff_out=str_replace(">","| ",$buff_out);
			dbg('enrol_jics',__FILE__,__LINE__,"START BUFF-->") ;
			dbg('enrol_jics',' ',' ',"{$buff_out}") ;
			dbg('enrol_jics',__FILE__,__LINE__,"<--END BUFF") ;


			
			// parse as XML
			libxml_use_internal_errors(true);
			$xml = simplexml_load_string($buff) ;

			// detect XML errors here
			$xml_lines = explode("\n", $buff);
			if (!$xml) {
				error_log('[ENROL_JICS] Error parsing XML. Rerun with debug on to see errors.' );
				mtrace(html_br().'Error parsing XML stream. See error log for more details. Use debug mode to see further details on screen.'.html_br()); 
				$errors = libxml_get_errors();
				foreach ($errors as $error) {
						$xmlerr_line  = $xml_lines[$error->line - 1] . "<br />\n";
						$xmlerr_line .= str_repeat('-', $error->column) . "^<br />\n";
						switch ($error->level) {
							case LIBXML_ERR_WARNING:
								$xmlerr_line .= "Warning $error->code: ";
								break;
							 case LIBXML_ERR_ERROR:
								$xmlerr_line .= "Error $error->code: ";
								break;
							case LIBXML_ERR_FATAL:
								$xmlerr_line .= "Fatal Error $error->code: ";
								break;
						}

						$xmlerr_line .= trim($error->message) .
								   "<br />\n  Line: $error->line" .
								   "<br />\n  Column: $error->column";

						if ($error->file) {
							 "<br />\n  File: $error->file";
						}

						//$xmlerr_line .= "<br /><br />\n\n--------------------------------------------<br /><br />\n\n";
						error_log('[ENROL_JICS] XML Parsing error: ' . $xmlerr_line );
						if (is_dbg('enrol_jics')){dbg('enrol_jics',__FILE__,__LINE__,"{$xmlerr_line}");}
				}
				libxml_clear_errors();
			}
			
			return $xml;
		}
		catch (Exception $e) {
			dbg('enrol_jics',__FILE__,__LINE__,"Exception is ". $e->getMessage());
			error_log('[ENROL_JICS] Error status from remote enrollment web service:' . $e->getMessage() );
			return null;
		}
 	}
	function display_xml_error($error, $xml){
		$xmlerr_line  = $xml[$error->line - 1] . "<br />\n";
		$xmlerr_line .= str_repeat('-', $error->column) . "^<br />\n";

		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$xmlerr_line .= "Warning $error->code: ";
				break;
			 case LIBXML_ERR_ERROR:
				$xmlerr_line .= "Error $error->code: ";
				break;
			case LIBXML_ERR_FATAL:
				$xmlerr_line .= "Fatal Error $error->code: ";
				break;
		}

		$xmlerr_line .= trim($error->message) .
				   "<br />\n  Line: $error->line" .
				   "<br />\n  Column: $error->column";

		if ($error->file) {
			$xmlerr_line .= "<br />\n  File: $error->file";
		}

		return "$xmlerr_line<br /><br />\n\n--------------------------------------------<br /><br />\n\n";
	}
	// Generate OAuth signature for enrollment requests to JICS web service.
	// We are not (yet) using BLTI for this purpose but only the OAuth code and data in place.
	function jics_moodle_enroll_oauth($service_source,$service_endpoint,$qstring){
		// Retrieve the secret from mdl_blti_config based on the $service_source parameter.
		// Construct the URL from the $service_source and $service_endpoint parameters.
		// Explode the $qstring parameter to get the other parameters that will be signed, but
		// first add the OAuth-required parameters (version, nonce). There is no need to use OAuth
		// timestamp on the Moodle side since JICS will be checking for a timeout using the "timeout" parameter value. 
		// OAuth will encode and sort as needed before signing.
		// Return: a qstring fragment holding all the OAuth parameters including the OAuth signature 
		// or null in case of an error.
		global $CFG;
		require_once "{$CFG->dirroot}/local/jics/blti/OAuth.php";
		require_once "{$CFG->dirroot}/local/jics/blti/datastore.php";
		require_once "{$CFG->libdir}/dml/moodle_database.php";
		require_once "{$CFG->libdir}/setuplib.php"; // for invalid_parameter_exception class
		
		$consumer_key = $this->OauthKeyFromDBHost($service_source ) ;
		//dbg('enrol_jics',__FILE__,__LINE__,"consumer_key={$consumer_key}") ;
		$consumer_secret = get_config("local_jics","oauth_secret");
		//dbg('enrol_jics',__FILE__,__LINE__,"consumer_secret={$consumer_secret}") ;

		if (empty($consumer_key) ||  empty($consumer_secret) ) {
			dbg('enrol_jics',__FILE__,__LINE__,"The oauth_secret or oauth_key is not configured!");
			return null;
		}
		try {
				// Add the consumer-secret pair to the datastore
				$store = new NtsOAuthDataStore();
				$store->add_consumer($consumer_key, $consumer_secret);
				// get it right back for later use in calling request->sign()
				$consumer = $store->lookup_consumer($consumer_key) ;
				$server = new OAuthServer($store); // use default for timeout since it doesn't matter in this case
				
				// we always use HMAC_SHA1 in this signing!
				$method = new OAuthSignatureMethod_HMAC_SHA1();
				$server->add_signature_method($method);
		
				// Construct URL without the qstring
				$target_url = "{$service_source}/{$service_endpoint}" ;
				
				// Create parameter array for OAuth
				$oauth_params=array();
				$param_pairs = explode('&',$qstring) ;
				foreach ($param_pairs as $one_param_pair){
					$one_param = explode('=',$one_param_pair);
					$oauth_params[trim($one_param[0])]=trim($one_param[1]);
				}
				// add the target url destined for the signature_base_string so it can be used by JICS
				// in generating its own signature. we cannot assume that the portlet will know how it appeared
				// as a target url for moodle.
				$oauth_params["target_url"] = $target_url ;

				// add some required parameters
				$oauth_params["oauth_consumer_key"] = $service_source ;
				$oauth_params["oauth_version"] = OAuthRequest::$version ;
				$oauth_params["oauth_nonce"] = OAuthRequest::get_nonce() ; // even though we won't use it
				$oauth_params["oauth_timestamp"] = OAuthRequest::get_timestamp() ;	// even though we won't use it
				$oauth_params["oauth_signature_method"] = "HMAC-SHA1" ;	// even though we won't use it

				// Create OAuth Request object
				$request = new OAuthRequest ('GET',$target_url,$oauth_params);
				$base = $request->get_signature_base_string();
				//dbg("enrol_jics",__FILE__, __LINE__,"base= {$base}") ;

				// sign the base string
				$request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, null);
				$sig = $request->get_parameter('oauth_signature');
				
				// add signature to $oauth_params array
				$oauth_params["oauth_signature"] = $sig ;
				// convert $oauth_params to string and append signature parameter
				$service_qstring = "";
				foreach ($oauth_params as $key=>$val){
					$service_qstring .= "&{$key}={$val}";
				}
				$service_qstring = substr($service_qstring,1);
				//dbg("enrol_jics",__FILE__,__LINE__, "service_qstring= {$service_qstring}") ;
				if (get_config('local_jics','oauth_test')) {
					dbg("enrol_jics",__FILE__,__LINE__, "Forcing exit per local_jics config setting \'oauth_test\')") ;
					exit;
				}
		}
		catch (Exception $e) {
			$service_qstring = null ;
			dbg("enrol_jics",__FILE__,__LINE__, "oauth exception=". $e->getMessage() ) ;
			error_log("[ENROL_JICS]} oauth exception=". $e->getMessage() ) ;
			if (get_config('local_jics','oauth_test')) {
				dbg("enrol_jics",__FILE__,__LINE__, "Forcing exit per local_jics config setting \'oauth_test\')") ;
				exit;
			}
		} 
		return $service_qstring;
	 }
	function OauthKeyFromDBHost($url)	{
		// skip over the protocol and just pull out the server Host name, e.g. 'cam-azaitchik.jenzabar.net'
		// if url does not contain "http://" or "https://" then it is illegitimate. We need to save the protocol.
		if (strpos($url,"http://") !== FALSE) 
		{
			$start_domain = 7 ;
		}
		else if (strpos($url,"https://") !== FALSE) 
		{
			$start_domain = 8 ;
		}
		else
		{
			throw (new Exception("Invalid URL for JICS system. Missing protocol."));
		}
		// stop at next ':' (port) or at next '/' (JICS root context)
		if ($domain_end = strpos($url,':',$start_domain) === FALSE)
		{	
			$domain_end = strpos($url,'/',$start_domain) ;
		}
		$domain_len = ($domain_end !== FALSE) ? ($domain_end - $start_domain) : (strlen($url) - $start_domain) ;
		return (substr($url,$start_domain,$domain_len));
	}
} // end of class

 ?>
