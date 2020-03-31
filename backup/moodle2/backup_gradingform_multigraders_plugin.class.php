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
 * Support for backup API
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines marking form backup structures
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_gradingform_multigraders_plugin extends backup_gradingform_plugin {

    /**
     * Declares marking form structures to append to the grading form definition
     * @return backup_plugin_element
     */
    protected function define_definition_plugin_structure() {

        // Append data only if the grand-parent element has 'method' set to 'multigraders'.
        $plugin = $this->get_plugin_element(null, '../../method', 'multigraders');

        // Create a visible container for our data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name(),Array('id'),Array(
            'secondary_graders_id_list',
            'criteria',
            'blind_marking',
            'show_intermediary_to_students',
            'auto_calculate_final_method'));

        // Connect our visible container to the parent.
        $plugin->add_child($pluginwrapper);

        // Set sources to populate the data.
        $pluginwrapper->set_source_table('gradingform_multigraders_def',
                array('id' => backup::VAR_PARENTID));

        return $plugin;
    }

    /**
     * Declares marking form structures to append to the grading form instances
     * @return backup_plugin_element
     */
    protected function define_instance_plugin_structure() {

        // Append data only if the ancestor 'definition' element has 'method' set to 'multigraders'.
        $plugin = $this->get_plugin_element(null, '../../../../method', 'multigraders');

        // Create a visible container for our data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect our visible container to the parent.
        $plugin->add_child($pluginwrapper);

        // Define our elements.

        $grades = new backup_nested_element('grades');

        $grade = new backup_nested_element('grade', array('id'), array(
            'instanceid', 'itemid', 'grader', 'grade','feedback','type','timestamp','visible_to_students','outcomes','require_second_grader'));
        // Build elements hierarchy.

        $pluginwrapper->add_child($grades);
        $grades->add_child($grade);

        // Set sources to populate the data.

        $grade->set_source_table('gradingform_multigraders_gra',
            array('instanceid' => backup::VAR_PARENTID));

        // No need to annotate ids or files yet (one day when remark field supports
        // embedded fileds, they must be annotated here).

        return $plugin;
    }
}
