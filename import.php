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
 * Course-level rubric import page.
 *
 * @package    local_evalimport
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/classes/form/import_form.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/evalimport:view', $coursecontext);

$PAGE->set_url(new moodle_url('/local/evalimport/import.php', ['id' => $course->id]));
$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pageheading', 'local_evalimport'));
$PAGE->set_heading(format_string($course->fullname));

$activities = local_evalimport_get_importable_activities($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pageheading', 'local_evalimport'));

if (empty($activities)) {
    echo $OUTPUT->notification(
        get_string('noactivitiesavailable', 'local_evalimport'),
        'notifyproblem'
    );
    echo $OUTPUT->footer();
    exit;
}

$mform = new \local_evalimport\form\import_form(
    null,
    [
        'activities' => $activities,
        'courseid' => $course->id,
    ]
);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $mform->get_data()) {
    try {
        $cm = get_coursemodule_from_id(null, $data->cmid, $course->id, false, MUST_EXIST);
        $modcontext = context_module::instance($cm->id);

        require_capability('moodle/grade:managegradingforms', $modcontext);

        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        if (!$cminfo->uservisible) {
            throw new moodle_exception('activitynotvisible', 'local_evalimport');
        }

        $gradingarea = local_evalimport_resolve_grading_area($cm);
        if (!$gradingarea) {
            throw new moodle_exception('nogradingarea', 'local_evalimport');
        }

        $draftitemid = file_get_submitted_draft_itemid('rubriccsv');
        $usercontext = context_user::instance($USER->id);
        $content = local_evalimport_get_uploaded_csv_content($draftitemid, $usercontext);

        $result = local_evalimport_import_csv_into_area(
            $gradingarea,
            $cm,
            $modcontext,
            $content
        );

        echo $OUTPUT->notification(get_string('importsuccess', 'local_evalimport'), 'notifysuccess');

        $buttons = [];
        $buttons[] = $OUTPUT->single_button(
            $result['manageurl'],
            get_string('gotoadvancedgrading', 'local_evalimport'),
            'get'
        );
        $buttons[] = $OUTPUT->single_button(
            new moodle_url('/local/evalimport/import.php', ['id' => $course->id]),
            get_string('continueimporting', 'local_evalimport'),
            'get'
        );

        echo html_writer::div(implode(' ', $buttons), 'local-evalimport-actions');
    } catch (Throwable $e) {
        echo $OUTPUT->notification($e->getMessage(), 'notifyproblem');
        $mform->display();
    }
} else {
    $mform->display();
}

echo $OUTPUT->footer();