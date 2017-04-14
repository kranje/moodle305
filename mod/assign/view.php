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
 * This file is the entry point to the assign module. All pages are rendered from here
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$id = required_param('id', PARAM_INT);
//Longfei-OIT, 04-08-2016, jira 768, download all instructor files from a course
$courseid = optional_param('downloadcourseid', 0, PARAM_INT);
if ($courseid) {
    $urlparams = array('id' => $id,
        'action' => optional_param('action', '', PARAM_TEXT),
        'rownum' => optional_param('rownum', 0, PARAM_INT),
        'useridlistid' => optional_param('action', 0, PARAM_INT));

    $url = new moodle_url('/mod/assign/view.php', $urlparams);
    $cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $PAGE->set_url($url);
    $assign = new assign(null, $cm, $course);

// Get the assign class to
// render the page.
    echo $assign->view(optional_param('action', '', PARAM_TEXT));
} else {
//=================================end 768============================

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

$assign = new assign($context, $cm, $course);
$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_TEXT),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $assign->get_useridlist_key_id(), PARAM_ALPHANUM));

$url = new moodle_url('/mod/assign/view.php', $urlparams);
$PAGE->set_url($url);

$completion=new completion_info($course);
$completion->set_module_viewed($cm);

// Get the assign class to
// render the page.
echo $assign->view(optional_param('action', '', PARAM_TEXT));
//Longfei-OIT, 04-09-2015, jira 768, download all instructor files from a course
}
//===================================
