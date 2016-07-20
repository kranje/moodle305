<?php
/** sync_users.php
 *
 * This script is meant to be called from a cronjob to sync moodle with the LDAP
 * backend in those setups where the LDAP backend acts as 'master'.
 *
 * Recommended cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * /usr/bin/php -c /etc/php4/cli/php.ini /var/www/moodle/auth/jics/sync_users.php
 *
 * Notes:
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d momory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *
 * Performance notes:
 * We have optimized it as best as we could for Postgres and mySQL, with 27K students
 * we have seen this take 10 minutes.
 *
 */

define('CLI_SCRIPT', true);
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->dirroot.'/course/lib.php');
// Ensure errors are well explained
$CFG->debug = DEBUG_NORMAL;

if (!is_enabled_auth('jics')) {
    echo 'auth/jics plugin is disabled',"\n";
    exit(1);
}

require_once($CFG->dirroot. '/auth/jics/auth.php'); // for the dbg function
require_once($CFG->dirroot. '/local/jics/jics_lib.php'); // for the dbg function

// get a auth/jics plugin object and initialize it
$authjics = new auth_plugin_jics();

$nomoodlecookie = true; // cookie not needed

$authjics->cli_cron(); 

?>