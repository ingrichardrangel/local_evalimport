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
 * Import form for evaluation instruments.
 *
 * @package    local_evalimport
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_evalimport\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to import a rubric CSV into a supported activity.
 *
 * @package    local_evalimport
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $activities = $this->_customdata['activities'] ?? [];
        $courseid = $this->_customdata['courseid'] ?? 0;

        $mform->addElement(
            'select',
            'cmid',
            get_string('selectactivity', 'local_evalimport'),
            $activities
        );
        $mform->addRule('cmid', null, 'required', null, 'client');

        $mform->addElement(
            'filepicker',
            'rubriccsv',
            get_string('choosecsvfile', 'local_evalimport'),
            null,
            [
                'accepted_types' => ['.csv'],
                'maxbytes' => 0,
                'subdirs' => 0,
            ]
        );
        $mform->addRule('rubriccsv', null, 'required', null, 'client');

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, get_string('importrubric', 'local_evalimport'));
    }
}