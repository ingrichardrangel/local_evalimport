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
 * Library callbacks for local_evalimport.
 *
 * @package    local_evalimport
 * @copyright  2026 Richard Rangel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends course navigation to add the import entry point.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 * @package local_evalimport
 */
function local_evalimport_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    global $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/evalimport:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/evalimport/import.php', ['id' => $course->id]);

    $node = $navigation->add(
        get_string('importinstrument', 'local_evalimport'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_evalimport_importinstrument',
        new pix_icon('i/settings', '')
    );

    if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
        $node->make_active();
    }
}