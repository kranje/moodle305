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
 * Ensemble Video repository plugin.
 *
 * @package    repository_ensemble
 * @copyright  2012 Liam Moran, Nathan Baxley, University of Illinois
 *             2013 Symphony Video, Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_ensemble extends repository {

    public static function get_instance_option_names() {
        return array('ensembleURL', 'consumerKey', 'sharedSecret', 'additionalParams');
    }

    public static function instance_config_form($mform) {
        $required = get_string('required');
        $mform->addElement('text', 'ensembleURL', get_string('ensembleURL', 'repository_ensemble'));
        $mform->setType('ensembleURL', PARAM_URL);
        $mform->addRule('ensembleURL', $required, 'required', null, 'client');
        $mform->addElement('static', null, '', get_string('ensembleURLHelp', 'repository_ensemble'));
        $mform->addElement('text', 'consumerKey', get_string('consumerKey', 'repository_ensemble'));
        $mform->setType('consumerKey', PARAM_TEXT);
        $mform->addElement('static', null, '', get_string('consumerKeyHelp', 'repository_ensemble'));
        $mform->addElement('passwordunmask', 'sharedSecret', get_string('sharedSecret', 'repository_ensemble'));
        $mform->addElement('static', null, '', get_string('sharedSecretHelp', 'repository_ensemble'));
        $mform->addElement('textarea', 'additionalParams', get_string('additionalParams', 'repository_ensemble'),
            'rows="10" cols="100"');
        $mform->setType('additionalParams', PARAM_TEXT);
        $mform->addElement('static', null, '', get_string('additionalParamsHelp', 'repository_ensemble'));
    }

    public function get_listing($path='', $page='0') {
        global $CFG;

        $url = new moodle_url('/repository/ensemble/launch.php', array(
            'repo_id' => $this->id,
            'context_id' => $this->context->id,
            'sesskey' => sesskey()
        ));

        $list = array();
        $list['object'] = array();
        $list['object']['type'] = 'text/html';
        $list['object']['src'] = $url->out(false);
        $list['nologin']  = true;
        $list['nosearch'] = true;
        $list['norefresh'] = true;
        return $list;
    }

    public function supported_filetypes() {
        return '*';
    }

    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
}