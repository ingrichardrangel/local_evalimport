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
 * Local helper library for local_evalimport.
 *
 * @package    local_evalimport
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Returns importable activities from the course respecting
 * visibility, group restrictions and grading capabilities.
 *
 * @param stdClass $course The course object.
 * @return array
 * @package local_evalimport
 */
function local_evalimport_get_importable_activities(stdClass $course): array {
    global $DB, $USER;

    $options = [];

    $modinfo = get_fast_modinfo($course);

    foreach ($modinfo->get_cms() as $cminfo) {
        // Skip invisible modules.
        if (!$cminfo->uservisible) {
            continue;
        }

        // Only allow supported modules.
        if (!in_array($cminfo->modname, ['assign', 'forum'], true)) {
            continue;
        }

        $context = context_module::instance($cminfo->id);

        // User must be able to manage grading forms.
        if (!has_capability('moodle/grade:managegradingforms', $context)) {
            continue;
        }

        // Respect group mode restrictions.
        if ($cminfo->groupmode != NOGROUPS) {
            // Groups allowed in this activity.
            $allowedgroups = groups_get_activity_allowed_groups($cminfo);

            // If the activity uses groups and the user does not belong
            // to any allowed group, hide it.
            if (!empty($allowedgroups)) {
                $usergroups = groups_get_all_groups(
                    $course->id,
                    $USER->id,
                    $cminfo->groupingid
                );

                if (empty($usergroups)) {
                    continue;
                }

                $intersection = array_intersect_key($allowedgroups, $usergroups);

                if (empty($intersection)) {
                    continue;
                }
            }
        }

        // Check that the activity has a valid grade item.
        $gradeitem = $DB->get_record('grade_items', [
            'itemmodule'   => $cminfo->modname,
            'iteminstance' => $cminfo->instance,
            'itemnumber'   => 0,
        ]);

        if (!$gradeitem) {
            continue;
        }

        // Only numeric grading supported.
        if ((int)$gradeitem->gradetype !== GRADE_TYPE_VALUE) {
            continue;
        }

        // Create a readable label including the section name.
        $sectioninfo = $modinfo->get_section_info($cminfo->sectionnum);
        $sectionname = get_section_name($course, $sectioninfo);

        $label = '[' . $cminfo->modplural . '] ' .
                 format_string($cminfo->name) .
                 ' (' . $sectionname . ')';

        $options[$cminfo->id] = $label;
    }

    return $options;
}

/**
 * Resolves or creates the grading area for a supported activity.
 *
 * @param stdClass $cm The course module record.
 * @return stdClass|null
 * @package local_evalimport
 */
function local_evalimport_resolve_grading_area(stdClass $cm): ?stdClass {
    global $DB;

    $context = context_module::instance($cm->id);
    $component = 'mod_' . $cm->modname;

    if ($cm->modname === 'assign') {
        $area = 'submissions';

        $gradingarea = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => $component,
            'areaname' => $area,
        ]);

        if (!$gradingarea) {
            $gradingarea = (object) [
                'contextid' => $context->id,
                'component' => $component,
                'areaname' => $area,
                'activemethod' => 'rubric',
            ];
            $gradingarea->id = $DB->insert_record('grading_areas', $gradingarea);
        } else if ($gradingarea->activemethod !== 'rubric') {
            $gradingarea->activemethod = 'rubric';
            $DB->update_record('grading_areas', $gradingarea);
        }

        return $gradingarea;
    }

    if ($cm->modname === 'forum') {
        $areas = $DB->get_records('grading_areas', [
            'contextid' => $context->id,
            'component' => $component,
        ]);

        if (!$areas) {
            return null;
        }

        foreach ($areas as $area) {
            if ($area->activemethod === 'rubric' || empty($area->activemethod)) {
                if ($area->activemethod !== 'rubric') {
                    $area->activemethod = 'rubric';
                    $DB->update_record('grading_areas', $area);
                }
                return $area;
            }
        }

        $area = reset($areas);
        $area->activemethod = 'rubric';
        $DB->update_record('grading_areas', $area);
        return $area;
    }

    return null;
}

/**
 * Returns the uploaded CSV content from the user's draft area.
 *
 * @param int $draftitemid The draft item id.
 * @param context_user $usercontext The user context.
 * @return string
 * @package local_evalimport
 */
function local_evalimport_get_uploaded_csv_content(
    int $draftitemid,
    context_user $usercontext
): string {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $usercontext->id,
        'user',
        'draft',
        $draftitemid,
        'id DESC',
        false
    );

    if (empty($files)) {
        throw new moodle_exception('csvrequired', 'local_evalimport');
    }

    $file = reset($files);
    return $file->get_content();
}

/**
 * Imports the CSV rubric into the grading area.
 *
 * @param stdClass $gradingarea The grading area record.
 * @param stdClass $cm The course module record.
 * @param context_module $context The module context.
 * @param string $content The CSV content.
 * @return array
 * @package local_evalimport
 */
function local_evalimport_import_csv_into_area(
    stdClass $gradingarea,
    stdClass $cm,
    context_module $context,
    string $content
): array {
    global $DB, $USER;

    $exists = $DB->record_exists('grading_definitions', [
        'areaid' => $gradingarea->id,
        'method' => 'rubric',
    ]);

    if ($exists) {
        throw new moodle_exception('errorrubricexists', 'local_evalimport');
    }

    $lines = preg_split("/\r\n|\n|\r/", $content);
    $headerline = array_shift($lines);
    $headers = array_map('trim', str_getcsv((string) $headerline));
    $headerslower = array_map('strtolower', $headers);

    $expectedheaders = ['criterion', 'level', 'level_description', 'score'];
    $headerdiff = array_diff($expectedheaders, $headerslower);

    if (!empty($headerdiff)) {
        throw new moodle_exception('csvmissingcolumns', 'local_evalimport');
    }

    $rows = [];
    foreach ($lines as $rawline) {
        if (trim($rawline) === '') {
            continue;
        }

        $row = str_getcsv((string) $rawline);
        if (count($row) < 4) {
            continue;
        }

        $rows[] = array_combine($headerslower, $row);
    }

    $rubric = [];
    foreach ($rows as $row) {
        $criterion = clean_param(trim($row['criterion']), PARAM_TEXT);
        $description = clean_param(trim($row['level_description']), PARAM_TEXT);
        $score = (float) clean_param($row['score'], PARAM_FLOAT);

        if (!isset($rubric[$criterion])) {
            $rubric[$criterion] = [];
        }

        $rubric[$criterion][] = [
            'definition' => $description,
            'score' => $score,
        ];
    }

    $gradeitem = $DB->get_record(
        'grade_items',
        [
            'iteminstance' => $cm->instance,
            'itemmodule' => $cm->modname,
            'itemnumber' => 0,
        ],
        '*',
        MUST_EXIST
    );

    $grademax = (float) $gradeitem->grademax;
    $sum = 0;

    $enablemax = get_config('local_evalimport', 'enablemaxlevelscore');
    $maxscore = (float) get_config('local_evalimport', 'maxlevelscore');
    $enablemin = get_config('local_evalimport', 'enableminlevelscore');
    $minscore = (float) get_config('local_evalimport', 'minlevelscore');

    foreach ($rubric as $criterion => $levels) {
        $scores = array_column($levels, 'score');
        $sum += max($scores);

        if (count(array_unique($scores)) < count($scores)) {
            throw new moodle_exception('errorrepeatedscores', 'local_evalimport', '', $criterion);
        }

        if ($enablemin && !in_array($minscore, $scores)) {
            throw new moodle_exception(
                'errorminmissing',
                'local_evalimport',
                '',
                [
                    'criterion' => $criterion,
                    'min' => $minscore,
                ]
            );
        }

        if ($enablemax) {
            foreach ($scores as $score) {
                if ($score > $maxscore) {
                    throw new moodle_exception(
                        'errormaxexceeded',
                        'local_evalimport',
                        '',
                        [
                            'criterion' => $criterion,
                            'score' => $score,
                            'max' => $maxscore,
                        ]
                    );
                }
            }
        }
    }

    if (round($sum, 2) !== round($grademax, 2)) {
        throw new moodle_exception(
            'errormismatchtotal',
            'local_evalimport',
            '',
            [
                'sum' => $sum,
                'grademax' => $grademax,
            ]
        );
    }

    $definition = (object) [
        'areaid' => $gradingarea->id,
        'method' => 'rubric',
        'name' => get_string(
            'importedrubricname',
            'local_evalimport',
            userdate(time(), '%d/%m/%Y %H:%M:%S')
        ),
        'status' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
        'usercreated' => $USER->id,
        'usermodified' => $USER->id,
        'options' => json_encode([
            'sortlevelsasc' => '0',
            'lockzeropoints' => '1',
            'alwaysshowdefinition' => '1',
            'showdescriptionteacher' => null,
            'showdescriptionstudent' => '1',
            'showscoreteacher' => '1',
            'showscorestudent' => '1',
            'enableremarks' => '1',
            'showremarksstudent' => '1',
        ]),
    ];

    $definitionid = $DB->insert_record('grading_definitions', $definition);

    $criteriaorder = 0;
    foreach ($rubric as $criteriontext => $levels) {
        $criterion = (object) [
            'definitionid' => $definitionid,
            'description' => $criteriontext,
            'descriptionformat' => FORMAT_HTML,
            'sortorder' => $criteriaorder++,
        ];
        $criterionid = $DB->insert_record('gradingform_rubric_criteria', $criterion);

        $levelsort = 0;
        foreach ($levels as $level) {
            $levelobj = (object) [
                'criterionid' => $criterionid,
                'definition' => $level['definition'],
                'definitionformat' => FORMAT_HTML,
                'score' => $level['score'],
                'sortorder' => $levelsort++,
            ];
            $DB->insert_record('gradingform_rubric_levels', $levelobj);
        }
    }

    return [
        'definitionid' => $definitionid,
        'manageurl' => new moodle_url('/grade/grading/manage.php', ['areaid' => $gradingarea->id]),
    ];
}
