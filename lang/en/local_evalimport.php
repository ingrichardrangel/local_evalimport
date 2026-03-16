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
 * English language strings for local_evalimport.
 *
 * @package    local_evalimport
 * @category   string
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activitynotvisible'] = 'You do not have access to this activity.';
$string['choosecsvfile'] = 'Rubric CSV file';
$string['continueimporting'] = 'Continue importing';
$string['csvmissingcolumns'] = 'The CSV file is missing required columns: criterion, level, level_description, score.';
$string['csvrequired'] = 'You must upload a CSV file.';
$string['enablemaxlevelscore'] = 'Enable maximum score validation per level';
$string['enablemaxlevelscore_desc'] = 'If enabled, no level score can exceed the configured maximum value.';
$string['enableminlevelscore'] = 'Enable minimum score validation per criterion';
$string['enableminlevelscore_desc'] = 'If enabled, each criterion must include the configured minimum score.';
$string['errormaxexceeded'] = 'In the criterion "{$a->criterion}" the score {$a->score} exceeds the maximum allowed ({$a->max}).';
$string['errorminmissing'] = 'In the criterion "{$a->criterion}" the required minimum score ({$a->min}) is missing.';
$string['errormismatchtotal'] = 'The sum of the rubric maximum scores ({$a->sum}) does not match the activity maximum grade ({$a->grademax}).';
$string['errorrepeatedscores'] = 'There are repeated scores within the criterion "{$a}".';
$string['errorrubricexists'] = 'This activity already has a rubric defined.';
$string['evalimport:view'] = 'View evaluation instrument importer';
$string['gotoadvancedgrading'] = 'Go to advanced grading';
$string['importedrubricname'] = 'Imported rubric {$a}';
$string['importinstrument'] = 'Import evaluation instrument';
$string['importrubric'] = 'Import';
$string['importsuccess'] = 'The rubric was successfully imported.';
$string['maxlevelscore'] = 'Maximum score per level';
$string['maxlevelscore_desc'] = 'Maximum allowed value for an individual level.';
$string['minlevelscore'] = 'Minimum score per criterion';
$string['minlevelscore_desc'] = 'Minimum value that must exist within each criterion.';
$string['noactivitiesavailable'] = 'No compatible activities available for importing evaluation instruments.';
$string['nogradingarea'] = 'No compatible advanced grading area was found for this activity.';
$string['pageheading'] = 'Import evaluation instrument';
$string['pluginname'] = 'Evaluation instrument importer';
$string['privacy:metadata'] = 'The plugin does not store any additional personal data.';
$string['selectactivity'] = 'Evaluation activity';
