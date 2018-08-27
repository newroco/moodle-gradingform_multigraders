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
 * Support for restore API
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the form specific data from grading.xml file
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_gradingform_multigraders_plugin extends restore_gradingform_plugin {

    /**
     * Declares the XML paths attached to the form definition element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_definition_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element('gradingform_multigraders_definition',$this->get_pathfor('/'));

        return $paths;
    }

    /**
     * Declares the XML paths attached to the form instance element
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_instance_plugin_structure() {

        $paths = array();

        $paths[] = new restore_path_element('gradingform_multigraders_grade',
            $this->get_pathfor('/grades/grade'));

        return $paths;
    }

    /**
     * Processes definition element data
     *
     * Sets the mapping 'radingform_multigraders_definition' to be used later by
     * {@link self::process_gradinform_multigraders_grade()}
     *
     * @param array|stdClass $data
     */
    public function process_gradingform_multigraders_definition($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->id = $this->get_new_parentid('grading_definition');

        $newid = $DB->insert_record_raw('multigraders_definitions', $data,true,false,true);
        $this->set_mapping('gradingform_multigraders_definition', $oldid, $newid);
    }

    /**
     * Processes filling element data
     *
     * @param array|stdClass $data The data to insert as a filling
     */
    public function process_gradingform_multigraders_grade($data) {
        global $DB;

        $data = (object)$data;
        $data->instanceid = $this->get_new_parentid('grading_instance');
        $instance = $DB->get_record('grading_instances',array('id'  => $data->instanceid), '*', IGNORE_MISSING);
        //$instance = $this->get_mapping('grading_instance', $data->instanceid);
        $data->itemid = $instance->itemid;

        $DB->insert_record('multigraders_grades', $data);
    }
}
