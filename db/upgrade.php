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
 * This file keeps track of upgrades to multigraders grading method.
 *
 * @package    gradingform_multigraders
 * @category  upgrade
 * @copyright 2016 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Multigraders grading method upgrade task.
 *
 * @param int $oldversion The version we are upgrading form.
 * @return bool Returns true on success.
 * @throws coding_exception
 * @throws downgrade_exception
 * @throws upgrade_exception
 */
function xmldb_gradingform_multigraders_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2018052801) {
        $dbman = $DB->get_manager();
        // Define field blind_marking to be added to multigraders_definitions.
        $table = new xmldb_table('multigraders_definitions');
        $field = new xmldb_field('secondary_graders_id_list', XMLDB_TYPE_CHAR, '100', null, null, null, '0', 'blind_marking');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('blind_marking', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'secondary_graders_id_list');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('show_intermediary_to_students', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'blind_marking');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('auto_calculate_final_method', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'show_intermediary_to_students');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('multigraders_grades');
        $field = new xmldb_field('timestamp', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('visible_to_students', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'timestamp');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('outcomes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'visible_to_students');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Multigraders savepoint reached.
        upgrade_plugin_savepoint(true, 2018052801, 'gradingform', 'multigraders');
    }
    if ($oldversion < 2018071801) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('multigraders_definitions');

        $field = new xmldb_field('no_of_graders');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('grading_type');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('previous_graders_cant_change');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('secondary_graders_id_list', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'auto_calculate_final_method');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('multigraders_grades');
        $field = new xmldb_field('require_second_grader', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'outcomes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        upgrade_plugin_savepoint(true, 2018071801, 'gradingform', 'multigraders');
    }

    return true;
}