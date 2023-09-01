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
 * The form used at the definition editor page is defined here
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Defines the definition edit form
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_multigraders_editform extends moodleform {

    /**
     * Form element definition
     */
    public function definition() {
        $form = $this->_form;

        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        // Template Name.
        $form->addElement('text', 'name', get_string('name'),
            array('size' => 52, 'maxlength' => 255));
        $form->addRule('name', get_string('required'), 'required', null, 'client');
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'maxlength', 255, 'client');

        // Template Description.
        $form->addElement('textarea', 'description', get_string('description'), 'wrap="virtual" rows="1" cols="50"');
        $form->setType('description', PARAM_TEXT );

        $areaid = required_param('areaid', PARAM_INT);
        $manager = get_grading_manager($areaid);

        $context = $manager->get_context();
        $graders = get_enrolled_users($context, 'mod/assign:grade', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
        foreach ($graders as $grader) {
            $graders[$grader->id] = fullname($grader);
        }

        $select = $form->addElement('select', 'secondary_graders_id_list',
                get_string('secondary_graders', 'gradingform_multigraders'), $graders);
        $select->setMultiple(true);
        $form->addHelpButton('secondary_graders_id_list', 'secondary_graders', 'gradingform_multigraders');

        //Grading criteria
        $form->addElement('editor', 'criteria', get_string('criteria', 'gradingform_multigraders'));
        $form->setType('criteria', PARAM_RAW );

        // Show intermediary grades to students
        $arrOptions = Array();
        $arrOptions['0'] = get_string('auto_calculate_final_method_0', 'gradingform_multigraders');
        $arrOptions['1'] = get_string('auto_calculate_final_method_1', 'gradingform_multigraders');
        $arrOptions['2'] = get_string('auto_calculate_final_method_2', 'gradingform_multigraders');
        $arrOptions['3'] = get_string('auto_calculate_final_method_3', 'gradingform_multigraders');
        $form->addElement('select', 'auto_calculate_final_method', get_string('auto_calculate_final_method', 'gradingform_multigraders'),
            $arrOptions);
        $form->addHelpButton('auto_calculate_final_method', 'auto_calculate_final_method', 'gradingform_multigraders');
        $form->addRule('auto_calculate_final_method', get_string('required'), 'required', null, 'client');
        $form->setType('auto_calculate_final_method', PARAM_INT);

        // Blind marking
        $form->addElement('checkbox', 'blind_marking', get_string('blind_marking', 'gradingform_multigraders'));
        $form->addHelpButton('blind_marking', 'blind_marking', 'gradingform_multigraders');
        $form->setDefault('blind_marking', 0);

        // Show intermediary grades to students
        $form->addElement('checkbox', 'show_intermediary_to_students', get_string('show_intermediary_to_students', 'gradingform_multigraders'));
        $form->addHelpButton('show_intermediary_to_students', 'show_intermediary_to_students', 'gradingform_multigraders');
        $form->setDefault('show_intermediary_to_students', 1);

        // Can graders change their grade and feedback after another graded?
        /*$form->addElement('checkbox', 'previous_graders_cant_change', get_string('previous_graders_cant_change', 'gradingform_multigraders'));
        $form->addHelpButton('previous_graders_cant_change','previous_graders_cant_change', 'gradingform_multigraders');
        $form->setDefault('previous_graders_cant_change', 0);*/

        $buttonarray = array();
        $buttonarray[] = &$form->createElement('submit', 'savedefinition', get_string('save', 'gradingform_multigraders'));
        $editbutton = &$form->createElement('submit', 'editdefinition', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $form->closeHeaderBefore('buttonar');
    }

    /**
     * Setup the form depending on current values. This method is called after definition(),
     * data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     *
     */
    public function definition_after_data() {
        //$form = $this->_form;
    }

    /**
     * Form validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $err = parent::validation($data, $files);
        return $err;
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        $data = parent::get_data();
        return $data;
    }

    /**
     * Check if there are changes in the definition and it is needed to ask user whether to
     * mark the current grades for re-grading. User may confirm re-grading and continue,
     * return to editing or cancel the changes
     *
     * @param  gradingform_multigraders_controller $controller
     */
    public function need_confirm_regrading($controller) {
        $data = $this->get_data();
        if (!isset($data->savedefinition) || !$data->savedefinition) {
            // We only need confirmation when button 'Save definition' is pressed.
            return false;
        }
        if (!$controller->has_active_instances()) {
            // Nothing to re-grade, confirmation not needed.
            return false;
        }
        return false;
    }

    /**
     * Returns a form element (submit button) with the name $elementname
     *
     * @param string $elementname
     * @return HTML_QuickForm_element
     */
    protected function &findbutton($elementname) {
        $form = $this->_form;
        $buttonar =& $form->getElement('buttonar');
        $elements =& $buttonar->getElements();
        foreach ($elements as $el) {
            if ($el->getName() == $elementname) {
                return $el;
            }
        }
        return null;
    }
}
