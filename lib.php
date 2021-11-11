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
 * Grading method controller for the multigraders plugin
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/grade/grading/form/lib.php');
require_once($CFG->libdir.'/mathslib.php');
require_once($CFG->libdir . '/messagelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/message/lib.php');

/**
 * This controller encapsulates the multi grading logic
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_multigraders_controller extends gradingform_controller {
    // Modes of displaying the form (used in  gradingform_multigraders_renderer).
    /** form display mode: For editing (moderator or teacher creates a form) */
    const DISPLAY_EDIT_FULL     = 1;
    /** form display mode: Preview the form design with hidden fields */
    const DISPLAY_EDIT_FROZEN   = 2;
    /** form display mode: Preview the form design (for person with manage permission) */
    const DISPLAY_PREVIEW       = 3;
    /** form display mode: Preview the form (for people being graded) */
    const DISPLAY_PREVIEW_GRADED = 8;
    /** form display mode: For evaluation, enabled (teacher grades a student) */
    const DISPLAY_EVAL          = 4;
    /** form display mode: For evaluation, enabled and allow to change the final (admin grades a student) */
    const DISPLAY_EVAL_FULL     = 9;
    /** form display mode: For evaluation, with hidden fields */
    const DISPLAY_EVAL_FROZEN   = 5;
    /** form display mode: Teacher reviews filled form */
    const DISPLAY_REVIEW        = 6;
    /** form display mode: Display filled form (i.e. students see their grades) */
    const DISPLAY_VIEW          = 7;

    /** @var stdClass|false the definition structure */
    protected $moduleinstance = false;

    /**
     * Extends the module settings navigation with the multigraders settings
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
     * and there is an area with the active grading method set to 'multigraders'.
     *
     * @param settings_navigation $settingsnav {@link settings_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node=null) {
        $node->add(get_string('editdefinition', 'gradingform_multigraders'),
            $this->get_editor_url(), settings_navigation::TYPE_CUSTOM,
            null, null, new pix_icon('icon', '', 'gradingform_multigraders'));
    }

    /**
     * Extends the module navigation
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
     *
     * @param global_navigation $navigation {@link global_navigation}
     * @param navigation_node $node {@link navigation_node}
     * @return void
     */
    public function extend_navigation(global_navigation $navigation, navigation_node $node=null) {
        // no need to extra details in menu
    }

    /**
     * Saves the definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition definition data as coming from  gradingform_multigraders_controller::get_data()
     * @param int $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $newdefinition->status = gradingform_controller::DEFINITION_STATUS_READY;
        $changes = $this->update_or_check_definition($newdefinition, $usermodified, true);
        if ($changes == 5) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the definition into the database or check if it has been changed.
     *
     * Returns the level of changes:
     * 0 - no changes
     * 1 - changes made
     * 5 - major changes made - all students require manual re-grading
     *
     * @param stdClass $newdefinition definition data as coming from  gradingform_multigraders_controller::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param bool $doupdate if true actually updates DB, otherwise performs a check
     * @return int
     */
    public function update_or_check_definition(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        // Firstly update the common definition data in the {grading_definition} table.
        if ($this->definition === false) {
            if (!$doupdate) {
                // If we create the new definition there is no such thing as re-grading anyway.
                return 5;
            }
            // If definition does not exist yet, create a blank one
            // (we need id to save files embedded in description).
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
        // Reload the definition from the database.
        $this->get_definition(true);
        $haschanges = Array();

        $newdefinition->blind_marking = isset($newdefinition->blind_marking) ? $newdefinition->blind_marking : 0;
        $newdefinition->show_intermediary_to_students = isset($newdefinition->show_intermediary_to_students) ? $newdefinition->show_intermediary_to_students : 0;

        $set = $newdefinition->secondary_graders_id_list;
        $setd = implode(',', $set); // implode ids with comma
        $newdefinition->secondary_graders_id_list = $setd;// stored in database table.

        foreach (array('status', 'name', 'description', 'secondary_graders_id_list', 'criteria', 'blind_marking', 'show_intermediary_to_students', 'auto_calculate_final_method') as $key) {
            if (isset($newdefinition->$key) && isset($this->definition->$key) && $newdefinition->$key != $this->definition->$key) {
                $haschanges[1] = true;
            }
        }
        /*if (isset($newdefinition->no_of_graders) && $newdefinition->no_of_graders != $this->definition->no_of_graders) {
            $haschanges[5] = true;
        }*/
        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $haschanges[1] = true;
        }
        if (!count($haschanges)) {
            return 0;
        }
        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            // add/update attributes in custom table
            $data = new stdClass();
            $data->id = $this->definition->id;
            if (!isset($newdefinition->blind_marking)) {
                $data->blind_marking = 0;
                $data->show_intermediary_to_students = 1;
                $data->auto_calculate_final_method = 0;
                $data->secondary_graders_id_list = Array();
                $data->criteria = '';
            } else {
                $data->blind_marking = $newdefinition->blind_marking;
                $data->show_intermediary_to_students = $newdefinition->show_intermediary_to_students;
                $data->auto_calculate_final_method = $newdefinition->auto_calculate_final_method;
                $data->secondary_graders_id_list = $newdefinition->secondary_graders_id_list;
                $data->criteria = $newdefinition->criteria['text'];
            }
            if (isset($this->definition->empty)) {
                $DB->insert_record_raw('gradingform_multigraders_def', $data, false, false, true);
            } else{
                $DB->update_record('gradingform_multigraders_def', $data);
            }
            $this->load_definition();
        }
        // Return the maximum level of changes.
        $changelevels = array_keys($haschanges);
        sort($changelevels);
        return array_pop($changelevels);
    }

    /**
     * Marks all instances filled with this form with the status INSTANCE_STATUS_NEEDUPDATE
     */
    public function mark_for_regrade() {
        global $DB;
        // if ($this->has_active_instances()) {
            $conditions = array('definitionid'  => $this->definition->id,
                        'status'  => gradingform_instance::INSTANCE_STATUS_ACTIVE);
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);

            // change the final grade type from final to intermediary
            /*$results = $DB->get_records('grading_instances',
                array('definitionid'  => $this->definition->id),null,'id,itemid');
            $arrItemIDs = Array();
            foreach ($results as $record) {
                $arrItemIDs[$record->itemid] = 1;
            }
            foreach (array_keys($arrItemIDs) as $itemID) {
                $conditions = array('itemid' => $itemID);
                $DB->set_field('gradingform_multigraders_gra', 'type', gradingform_multigraders_instance::GRADE_TYPE_INTERMEDIARY, $conditions);
            }*/
        // }
    }

    /**
     * Loads the form definition if it exists
     *
     */
    protected function load_definition() {
        global $DB;

        // Check to see if the user prefs have changed - putting here as this function is called on post even when
        // validation on the page fails. - hard to find a better place to locate this as it is specific to the form.
        // Get definition.
        $definition = $DB->get_record('grading_definitions', array('areaid' => $this->areaid,
            'method' => $this->get_method_name()), '*');
        if (!$definition) {
            // The definition doesn't have to exist. It may be that we are only now creating it.
            $this->definition = false;
            return false;
        }

        $this->definition = $definition;
        $definitionExtras = $DB->get_record('gradingform_multigraders_def', array('id' => $this->definition->id), '*');
        if (!$definitionExtras) {
            //Populate with defaults
            $this->definition->blind_marking = 0;
            $this->definition->show_intermediary_to_students = 1;
            $this->definition->auto_calculate_final_method = 0;
            $this->definition->secondary_graders_id_list = Array();
            $this->definition->empty = true;
            $this->definition->criteria = '';
        }else{
            $this->definition->blind_marking = $definitionExtras->blind_marking;
            $this->definition->show_intermediary_to_students = $definitionExtras->show_intermediary_to_students;
            $this->definition->auto_calculate_final_method = $definitionExtras->auto_calculate_final_method;
            $this->definition->secondary_graders_id_list = $definitionExtras->secondary_graders_id_list;
            $this->definition->criteria = $definitionExtras->criteria;
            unset($this->definition->empty);
        }


        $this->definition = $definition;
        if (empty($this->moduleinstance)) { // Only set if empty.
            $modulename = $this->get_component();
            $context = $this->get_context();
            if (strpos($modulename, 'mod_') === 0) {
                $dbman = $DB->get_manager();
                $modulename = substr($modulename, 4);
                if ($dbman->table_exists($modulename)) {
                    $cm = get_coursemodule_from_id($modulename, $context->instanceid);
                    if (!empty($cm)) { // This should only occur when the course is being deleted.
                        $this->moduleinstance = $DB->get_record($modulename, array("id"=>$cm->instance));
                    }
                }
            }
        }
    }

    /**
     * Returns the default options for display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = array(
            'alwaysshowdefinition' => 1
        );
        return $options;
    }

    /**
     * Gets the options of the definition, fills the missing options with default values
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = json_decode($this->definition->options);
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
        }
        return $options;
    }

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     *
     * @return stdClass
     */
    public function get_definition_for_editing() {

        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if (isset($this->moduleinstance->grade)) {
            $properties->modulegrade = $this->moduleinstance->grade;
        }
        if ($definition) {
            foreach (array('id', 'name', 'description','secondary_graders_id_list','criteria', 'blind_marking','show_intermediary_to_students','auto_calculate_final_method') as $key) {
                $properties->$key = $definition->$key;
                if($key == 'criteria'){
                    $properties->$key = Array();
                    $properties->$key['text'] = $definition->criteria;
                    $properties->$key['format'] = 1;
                }
            }
            /*$options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor($properties, 'description', $options, $this->get_context(),
                'grading', 'description', $definition->id);*/
        }
        return $properties;
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's {@link update_definition()}
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        return $new;
    }

    /**
     * Options for displaying the form description field in the form
     *
     * @param context $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return array(
            'maxfiles' => -1,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes),
            'context'  => $context,
        );
    }

    /**
     * Formats the definition description for display on page
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }

        $context = $this->get_context();

        $formatoptions = array(
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context
        );
        $text = get_string('pluginname','gradingform_multigraders');
        $text .= "\n".$this->definition->description;
        if($this->definition->criteria) {
            $text .= "\n" . get_string('criteria', 'gradingform_multigraders') . ": ";
            $text .= $this->definition->criteria;
        }
        if(isset($this->definition->secondary_graders_id_list) &&
            $this->definition->secondary_graders_id_list != ''){
            //transform list of grader ids into name list

            $mainuserfields = user_picture::fields();

            $dbUsers = get_users(true,'',true,null,'lastname ASC,firstname ASC',
                $firstinitial='', $lastinitial='', $page=0, $recordsperpage=100, $fields=$mainuserfields,
                $extraselect='id IN ('.$this->definition->secondary_graders_id_list.')');
            $secondaryGraders = '';

            foreach($dbUsers as $id => $oUser){
                $secondaryGraders .= fullname($oUser).', ';
            }
            $secondaryGraders = substr($secondaryGraders,0,-2);
            $text .= "\n".get_string('secondary_graders_list','gradingform_multigraders',$secondaryGraders);
        }

        if(isset($this->definition->blind_marking) && $this->definition->blind_marking == 1){
            $text .= "\n".get_string('blind_marking_explained','gradingform_multigraders');
        }
        if(isset($this->definition->show_intermediary_to_students) && $this->definition->show_intermediary_to_students == 1){
            $text .= "\n".get_string('show_intermediary_to_students_explained','gradingform_multigraders');
        }
        /*if(isset($this->definition->previous_graders_cant_change) && $this->definition->previous_graders_cant_change == 1){
            $text .= "\n".get_string('previous_graders_cant_change_explained','gradingform_multigraders');
        }*/
        if(isset($this->definition->auto_calculate_final_method)){
            $text .= "\n".get_string('auto_calculate_final_method','gradingform_multigraders').": ";
            switch($this->definition->auto_calculate_final_method){
                case 0:
                    $text .= get_string('auto_calculate_final_method_0','gradingform_multigraders');
                    break;
                case 1:
                    $text .= get_string('auto_calculate_final_method_1','gradingform_multigraders');
                    break;
                case 2:
                    $text .= get_string('auto_calculate_final_method_2','gradingform_multigraders');
                    break;
                case 3:
                    $text .= get_string('auto_calculate_final_method_3','gradingform_multigraders');
                    break;
            }

        }

        return format_text($text, FORMAT_MOODLE, $formatoptions);
    }

    /**
     * Returns the plugin renderer
     *
     * @param moodle_page $page the target page
     * @return  gradingform_multigraders_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_'. $this->get_method_name());
    }

    /**
     * Returns the HTML code displaying the preview of the grading form
     *
     * @param moodle_page $page the target page
     * @return string
     */
    public function render_preview(moodle_page $page) {

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        // Check if current user is able to see preview
        $options = $this->get_options();
        if (empty($options['alwaysshowdefinition']) && !has_capability('moodle/grade:managegradingforms', $page->context))  {
            return '';
        }
        $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        if (has_capability('moodle/grade:manage', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_EVAL_FULL;
        }elseif (has_capability('moodle/grade:edit', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_EVAL;
        }elseif (has_capability('moodle/grade:viewall', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        }elseif (has_capability('moodle/grade:view', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        }
        if($mode == gradingform_multigraders_controller::DISPLAY_VIEW){
            $html = get_string('pluginname','gradingform_multigraders');
            if($this->definition->criteria){
                $html = $this->definition->criteria;
            }
            return $html;
        }


        return $this->get_formatted_description($page);
    }

    /**
     * Deletes the form definition and all the associated information
     */
    protected function delete_plugin_definition() {
        global $DB;

        // Get the list of instances.
        $instances = array_keys($DB->get_records('grading_instances', array('definitionid' => $this->definition->id), '', 'id'));
        // Delete instances.
        $DB->delete_records_list('gradingform_multigraders_gra', 'id', $instances);
        //delete extra defition details
        $DB->delete_records('gradingform_multigraders_def', array('id' => $this->definition->id));
    }

    /**
     * If instanceid is specified and grading instance exists and it is created by this rater for
     * this item, this instance is returned.
     * If there exists a draft for this raterid+itemid, take this draft (this is the change from parent)
     * Otherwise new instance is created for the specified rater and itemid
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {
        global $DB;
        if ($instanceid &&
                $instance = $DB->get_record('grading_instances',
                    array('id'  => $instanceid, 'raterid' => $raterid, 'itemid' => $itemid), '*', IGNORE_MISSING)) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            $params = array('definitionid' => $this->definition->id, 'raterid' => $raterid, 'itemid' => $itemid);
            if ($rs = $DB->get_records('grading_instances', $params, 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if ($record->status == gradingform_multigraders_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }
        return $this->create_instance($raterid, $itemid);
    }

    /**
     * Returns html code to be included in student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo result of function grade_get_grades
     * @param string $defaultcontent default string to be returned if no active grading is found
     * @param bool $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid),$defaultcontent, $cangrade);
    }

    // Full-text search support.

    /**
     * Prepare the part of the search query to append to the FROM statement
     *
     * @param string $gdid the alias of grading_definitions.id column used by the caller
     * @return string
     */
    public static function sql_search_from_tables($gdid) {
        return "";
        //return " LEFT JOIN {gradingform_multigraders_criteria} gc ON (gc.definitionid = $gdid)";
    }

    /**
     * Prepare the parts of the SQL WHERE statement to search for the given token
     *
     * The returned array consists of the list of SQL comparisons and the list of
     * respective parameters for the comparisons. The returned chunks will be joined
     * with other conditions using the OR operator.
     *
     * @param string $token token to search for
     * @return array An array containing two more arrays
     *     Array of search SQL fragments
     *     Array of params for the search fragments
     */
    public static function sql_search_where($token) {
        global $DB;

        $subsql = array();
        $params = array();

        return array($subsql, $params);
    }


    /**
     * @return array An array containing 1 key/value pairs which hold the external_multiple_structure
     * @see gradingform_controller::get_external_definition_details()
     * @since Moodle 2.5
     */
    public static function get_external_definition_details() {
        $grades_criteria = new external_multiple_structure(
                            new external_single_structure(
                                  array(
                                      'secondary_graders_id_list'   => new external_value(PARAM_CHAR, '', VALUE_REQUIRED),
                                      'criteria'   => new external_value(PARAM_RAW, '', VALUE_REQUIRED),
                                      'blind_marking'   => new external_value(PARAM_INT, 'if blind grading is enabled', VALUE_REQUIRED),
                                      'show_intermediary_to_students'   => new external_value(PARAM_INT, 'if intermediary grades are shown to students', VALUE_REQUIRED),
                                      'auto_calculate_final_method'   => new external_value(PARAM_INT, 'method of calculating the final grade', VALUE_REQUIRED),
                                      )
                                  ));
        return array('grades_criteria' => $grades_criteria);
    }

    /**
     * Returns an array that defines the structure of the form's filling. This function is used by
     * the web service function core_grading_external::get_gradingform_instances().
     *
     * @return An array containing a single key/value pair with the 'grades' external_multiple_structure
     * @see gradingform_controller::get_external_instance_filling_details()
     * @since Moodle 2.6
     */
    public static function get_external_instance_filling_details() {
        $grades = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'filling id'),
                    'instanceid' => new external_value(PARAM_INT, 'instance id'),
                    'itemid' => new external_value(PARAM_INT, 'item id', VALUE_OPTIONAL),
                    'grader' => new external_value(PARAM_INT, 'grader id', VALUE_OPTIONAL),
                    'grade' => new external_value(PARAM_FLOAT, 'the grade',VALUE_OPTIONAL),
                    'feedback' => new external_value(PARAM_RAW, 'feedback', VALUE_OPTIONAL),
                    'type' => new external_value(PARAM_INT, 'type', VALUE_OPTIONAL),
                    'visible_to_students' => new external_value(PARAM_INT, 'visible_to_students', VALUE_OPTIONAL),
                    'outcomes' => new external_value(PARAM_RAW, 'outcomes', VALUE_OPTIONAL),
                    'require_second_grader' => new external_value(PARAM_INT, 'require_second_grader', VALUE_OPTIONAL)
                )
            ), 'grade', VALUE_OPTIONAL
        );
        return array ('grades' => $grades);
    }

}

/**
 * Class to manage one form grading instance.
 *
 * Stores information and performs actions like update, copy, validate, submit, etc.
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_multigraders_instance extends gradingform_instance {
    /** @var array of errors per <grader><record type> values */
    public $validationErrors;
    /** @var array of options defined in the gradingform definition */
    public $options;
    /** @var stdClass with minRange of maxRange of the grading range */
    protected $gradeRange;
    /** @var array of grades for this instance */
    protected $instanceGrades;
    /** @var string debugging log */
    protected $log;

    /** Intermediate grade type - e.g not the final one */
    const GRADE_TYPE_INTERMEDIARY = 0;
    /** Final grade */
    const GRADE_TYPE_FINAL = 1;

    /**
     * Creates a gradingform_multigraders instance
     *
     * @param gradingform_controller $controller
     * @param stdClass $data
     */
    public function __construct($controller, $data) {
        parent::__construct($controller,$data);
        $definition = $this->get_controller()->get_definition();
        $this->options = new stdClass();
        if($definition) {
            foreach (array('secondary_graders_id_list','criteria', 'blind_marking','show_intermediary_to_students','auto_calculate_final_method') as $key) {
                if (isset($definition->$key)) {
                    $this->options->$key = $definition->$key;
                }
            }
        }
        $this->log = '';
    }

    /**
     * Returns a class with minGrade and maxGrade for attributes
     * @param bool $forceRefresh
     * @return stdClass
     */
    public function getGradeRange($forceRefresh = false){
        if($this->gradeRange == null || $forceRefresh) {
            $graderange = array_values($this->get_controller()->get_grade_range());
            //handle non-int grade range
            if(!is_numeric($graderange[0]) && !is_numeric($graderange[count($graderange) - 1])){
                return null;
            }
            if (!empty($graderange)) {
                $this->gradeRange = new stdClass();
                sort($graderange);
                $this->gradeRange->minGrade = $graderange[0];
                $this->gradeRange->maxGrade = $graderange[count($graderange) - 1];
                $cutPos = strpos($this->gradeRange->minGrade,'/');
                if($cutPos !== FALSE){
                    $this->gradeRange->minGrade = floatval(substr($this->gradeRange->minGrade,0,$cutPos));
                }
                $cutPos = strpos($this->gradeRange->maxGrade,'/');
                if($cutPos !== FALSE){
                    $this->gradeRange->maxGrade = floatval(substr($this->gradeRange->maxGrade,$cutPos+1));
                }
                if($this->gradeRange->minGrade == 1){
                    $this->gradeRange->minGrade = 0;
                }
            }
        }
        return $this->gradeRange;
    }

    /**
     * Returns the item id of the current instance
     * @return int itemid
     */
    public function getItemID(){
        return intval($this->get_data('itemid'));
    }

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_multigraders_gra', array('instanceid' => $this->get_id()));
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        global $DB,$USER;
        $instanceid = parent::copy($raterid, $itemid);
        /*$currentgrade = $this->get_instance_grades();
        foreach ($currentgrade['grades'] as $grader => $record) {
            if($grader == $USER->id) {
                $record['instanceid'] = $instanceid;
                $record['itemid'] = $itemid;
                $DB->insert_record('gradingform_multigraders_gra', $record);
            }
        }*/
        return $instanceid;
    }

    /**
     * Determines whether the submitted form was empty.
     *
     * @param array $elementvalue value of element submitted from the form
     * @return boolean true if the form is empty
     */
    public function is_empty_form($elementvalue) {
        /*if (!isset($elementvalue['grade']) && !isset($elementvalue['feedback'])) {
            return true;
        }*/
        return false;//let update handle the form submit
    }

    /**
     * Validates that form contains valid grade
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        global $USER;

        $this->log .= 'validate_grading_element multigraders_delete_all:'.$elementvalue['multigraders_delete_all'].'. ';
        if(isset($elementvalue['multigraders_delete_all']) && $elementvalue['multigraders_delete_all']=='true') {
            $this->log .= 'validate_grading_element ret true. ';
            return true;
        }

        if (!isset($elementvalue['grade']) || trim($elementvalue['grade']) == ''){
            return true;
        }

        // Reset validation errors.
        $this->validationErrors = Array();
        if(!isset($elementvalue['grader'])){
            $elementvalue['grader'] = $USER->id;
        }
        if (!is_numeric($elementvalue['grade']) || $elementvalue['grade'] < 0) {
            $this->validationErrors[$elementvalue['grader'].$elementvalue['type']] = get_string('err_gradeinvalid','gradingform_multigraders');
            return false;
        }
        $elementvalue['grade'] = floatval($elementvalue['grade']);
        if($this->getGradeRange()) {
            if ($this->getGradeRange()->minGrade && $elementvalue['grade'] < $this->getGradeRange()->minGrade
                ||
                $this->getGradeRange()->maxGrade && $elementvalue['grade'] > $this->getGradeRange()->maxGrade) {
                ob_start();
                var_dump($this->getGradeRange());
                $echo = ob_get_contents();
                ob_end_clean();
                $this->validationErrors[$elementvalue['grader'] . $elementvalue['type']] = $elementvalue['grade'] . ' ' . get_string('err_gradeoutofbounds', 'gradingform_multigraders') . ' ' . $echo;
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieves from DB and returns the data for this form
     *
     * @param bool $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_instance_grades($force = false) {
        global $DB;
        if ($this->instanceGrades === null || $force) {
            $records = $DB->get_records('gradingform_multigraders_gra', array('itemid' => $this->getItemID()), 'timestamp');
            $this->instanceGrades = array('grades' => array());
            foreach ($records as $record) {
                $record->grade = (float)$record->grade; // Strip trailing 0.
                $record->type = intval($record->type);
                $record->visible_to_students = (intval($record->visible_to_students)==1); //make DB int val into boolean.
                $record->require_second_grader = (intval($record->require_second_grader)==1); //make DB int val into boolean.
                $record->outcomes = json_decode($record->outcomes); //transform outcomes from JSON to object
                $this->instanceGrades['grades'][$record->grader] = $record;
            }
        }
        return $this->instanceGrades;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB,$USER;
        $currentFormData = $this->get_instance_grades();
        $currentRecordID = null;

        $firstGradeRecord = null;
        $currentRecord = null;
        $finalGradeRecord = null;

        //check first if an admin wants to delete everything for this grade
        //check if multigraders_delete_all parameter was sent
        if(isset($data['multigraders_delete_all']) && $data['multigraders_delete_all']=='true') {
            //check if user is admin
            $systemcontext = context_system::instance();
            if(has_capability('moodle/site:config', $systemcontext)) {
                $this->log .= 'update() moodle/site:config is true. ';
                parent::update($data);
                $DB->delete_records('gradingform_multigraders_gra',array('itemid' => $this->getItemID()));
                $this->data->rawgrade = -1;
                $newdata = new stdClass();
                $newdata->id = $this->get_id();
                $newdata->rawgrade = -1;
                $DB->update_record('grading_instances', $newdata);
            }
            $this->get_instance_grades(true);
            return;
        }

        foreach ($currentFormData['grades'] as $grader=> $record) {
            if(!$firstGradeRecord){
                $firstGradeRecord = $record;
            }
            if($grader == $USER->id){
                $currentRecord = $record;
            }
            if($record->type == gradingform_multigraders_instance::GRADE_TYPE_FINAL){
                $finalGradeRecord = $record;
            }
        }

        //if the final grade is already added for this instance and it wasn't given by the current teacher, then they can't edit anything.
        if($finalGradeRecord !==null && $currentRecord === null ){
            return;
        }
        //if the final grade is already added for this instance, but by a different teacher, don't allow any saves
        if($finalGradeRecord !==null && $finalGradeRecord->grader != $USER->id){
            return;
        }
        $outcomes = null;
        if(isset($data['outcome'])) {
            $outcomes =  json_encode($data['outcome']);
        }
        if(isset($data['grade_hidden'])) {
            $data['grade'] = $data['grade_hidden'];
        }
        //updating instanceid for all records of the same item
        $conditions = array('itemid' => $data['itemid']);
        $DB->set_field('gradingform_multigraders_gra', 'instanceid', $this->get_id(), $conditions);

        $gradeType = gradingform_multigraders_instance::GRADE_TYPE_INTERMEDIARY;
        $gradingFinal = false;
        if(isset($data['grading_final'])){
            $gradingFinal = true;
            if(isset($data['final_grade'])) {
                $gradeType = gradingform_multigraders_instance::GRADE_TYPE_FINAL;
            }
        }
        //adding a new record
        if(isset($data['grade']) && $data['grade'] !='') {

        }
        if($currentRecord !== null){
            $currentRecordID = $DB->get_field('gradingform_multigraders_gra','id',
                array('itemid' => $data['itemid'],'grader' => $USER->id));
        }

        parent::update($data);

        $newrecord = array('instanceid' => $this->get_id(),
            'itemid' => $data['itemid'],
            'grader' => $USER->id,
            'grade' => $data['grade'],
            'feedback' => $data['feedback'],
            'type' => $gradeType,
            'timestamp' => time(),
            'visible_to_students' => $data['visible_to_students'],
            'require_second_grader' => $data['require_second_grader'],
            'outcomes' => $outcomes);
        if($currentRecordID){
            $newrecord['id'] = $currentRecordID;
            unset($newrecord['timestamp']);
            $DB->update_record('gradingform_multigraders_gra', $newrecord);
        }else {
            $DB->insert_record('gradingform_multigraders_gra', $newrecord);
        }
        //grade type is not null only when the grading owner(or first/final grader) is saving the data
        if($gradingFinal){
            if($gradeType == gradingform_multigraders_instance::GRADE_TYPE_FINAL) {
                $this->data->rawgrade = $data['grade'];
                $this->data->grade = $data['grade'];
            }else{
                $this->data->rawgrade = -1;
            }
            $newdata = new stdClass();
            $newdata->id = $this->get_id();
            $newdata->rawgrade = $this->data->rawgrade;
            $DB->update_record('grading_instances', $newdata);
        }
        //if no previous grade or previous grade did not request second grading or it changed type from final
        //and if the current grade is not final
        if((!$currentRecord || !$currentRecord->require_second_grader || $currentRecord->type != $gradeType) &&
            $gradeType != gradingform_multigraders_instance::GRADE_TYPE_FINAL &&
            $data['require_second_grader']){
            $this->log .= ' in for notification ';
            $this->send_second_graders_notification($gradingFinal,$firstGradeRecord,$data['itemid']);
        }elseif($firstGradeRecord->grader != $USER->id){
            $this->send_initial_grader_notification($firstGradeRecord,$data['itemid']);
        }

        /*$newdata = new stdClass();
        $newdata->id = $currentRecordID;
        $newdata->feedback = $this->log;
        $DB->update_record('gradingform_multigraders_gra', $newdata);*/

        $this->get_instance_grades(true);
    }

    /**
     * Removes the attempt from the  gradingform_multigraders_gra table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        global $DB;
        $DB->delete_records('gradingform_multigraders_gra',array('grader' => $data['grader'], 'instanceid' => $this->get_id()));
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return float|int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {

        $graderange = array_keys($this->get_controller()->get_grade_range());
        if (empty($graderange)) {
            return -1;
        }
        /*
        if ($this->get_controller()->get_allow_grade_decimals()) {
            return $this->data->rawgrade;
        }
        return floor($this->data->rawgrade);
        */

        $visibleGradeRange = $this->getGradeRange();
        sort($graderange);
        $mingrade = $graderange[0];
        $maxgrade = $graderange[count($graderange) - 1];

        $currGrade = $this->data->rawgrade;
        return floor($this->data->rawgrade);
        $gradeoffset = ($currGrade - $visibleGradeRange->minGrade)/($visibleGradeRange->maxGrade - $visibleGradeRange->minGrade)*($maxgrade-$mingrade);
        if ($this->get_controller()->get_allow_grade_decimals()) {
            return $gradeoffset + $mingrade;
        }
        return round($gradeoffset, 0) + $mingrade;
    }

    /**
     * Returns the id of the user who gave the final grade
     *
     * @param  int|null $value the form value or null to use the current instance value
     * @return int
     */
    public function get_final_grader($value = null) {
        if($value === null){
            $value = $this->get_instance_grades();
        }
        foreach ($value['grades'] as $grader => $record) {
            if($record->type == gradingform_multigraders_instance::GRADE_TYPE_FINAL){
                return $grader;
            }
        }
        return null;
    }

    /**
     * Returns the URL of the user's profile with their name as text
     *
     * @param int $id the id of the user
     * @return string URL of the user's profile
     */
    static public function get_user_url($id){
        global $DB,$USER;
        if(!$id){
            return '';
        }

        $graderName = '';
        if($id == $USER->id){
            $graderName = fullname($USER);
        }else {
            $user = $DB->get_record('user', array('id' => $id));
            if ($user) {
                $graderName = fullname($user);
            }else{
                $graderName = get_string('not found');
            }
        }
        if($graderName != ''){
            $url = new moodle_url('/user/profile.php', array('id'=>$id));
            $graderName = html_writer::tag('span', $graderName, array('class' => 'grader_name'));
            $graderName = html_writer::link($url, $graderName);
        }

        return $graderName;
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global $USER,$PAGE,$CFG;

        $module = array('name'=>'gradingform_multigraders', 'fullpath'=>'/grade/grading/form/multigraders/js/multigraders.js');
        $page->requires->js_init_call('M.gradingform_multigraders.init', null, false, $module);

        $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        if (has_capability('moodle/grade:manage', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_EVAL_FULL;
        }elseif (has_capability('moodle/grade:edit', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_EVAL;
        }elseif (has_capability('moodle/grade:viewall', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_REVIEW;
        }elseif (has_capability('moodle/grade:view', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        }
        if ($gradingformelement->_flagFrozen && $gradingformelement->_persistantFreeze && has_capability('moodle/grade:edit', $page->context)) {
            $mode = gradingform_multigraders_controller::DISPLAY_EVAL_FROZEN;
        }


        $html = '';

        if($this->options->blind_marking){
            $html .= html_writer::tag('div', get_string('blind_marking_explained', 'gradingform_multigraders'),
                array('class' => 'gradingform_multigraders-notice', 'role' => 'alert'));
        }
        $values = $this->get_instance_grades();
        $value = $gradingformelement->getValue();
        if ($value !== null){
            //go through previous grades and update only the new one
            foreach ($values['grades'] as $grader => $record) {
                if($grader == $USER->id){
                    $values['grades'][$grader]->grade = $value['grade'];
                    $values['grades'][$grader]->type = $value['type'];
                    $values['grades'][$grader]->feedback = $value['feedback'];
                    $values['grades'][$grader]->require_second_grader = $value['require_second_grader'];
                    $values['grades'][$grader]->grade = $value['grade'];
                    $values['grades'][$grader]->outcomes =  (object)$value['outcome'];
                }
            }
            if($err = $this->validate_grading_element($value)) {
                $html .= html_writer::tag('div', $err, array('class' => 'gradingform_multigraders-error'));
            }
        }

        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $this->options->status = gradingform_instance::INSTANCE_STATUS_NEEDUPDATE;
            $finalGraderId = $this->get_final_grader($value);
            if($finalGraderId == null){
                $finalGraderName = 'someone';
            }else{
                $finalGraderName = self::get_user_url($finalGraderId);
            }
            $html .= html_writer::tag('div', get_string('needregrademessage', 'gradingform_multigraders',$finalGraderName),
                array('class' => 'gradingform_multigraders-regrade', 'role' => 'alert'));
        }



        $this->options->itemID = $this->getItemID();
        $this->options->userID = self::get_userID_for_itemID($this->options->itemID);

         /* if($USER->id == 634 || $USER->id == 652){
            /*$gradinginfo = grade_get_grades($PAGE->cm->get_course()->id,
                 'mod',
                 'assign',
                 $PAGE->cm->instance,
                 $USER->id);
             $ctrl = $gradinginfo->outcomes;

            $methods = get_class_methods($ctrl);
            $vars = get_object_vars($ctrl);
            asort($methods);
            asort($vars);
            $echo = highlight_string("<?php\n\$obj =\n" . var_export($ctrl, true) . ";\n?>");
            $echo .= highlight_string("<?php\n\$methods =\n" . var_export($methods, true) . ";\n?>");
            $echo .= highlight_string("<?php\n\$vars =\n" . var_export(array_keys($vars), true) . ";\n?>");

            ob_start();
            var_dump($this->log);
            $echo = ob_get_contents();
            ob_end_clean();
            $html .= html_writer::tag('div', $echo, array('class' => 'dump'));
        }*/

        $html .= $this->get_controller()->get_renderer($page)->display_form( $mode,$this->options, $values,  $gradingformelement->getName(),$this->validationErrors,$this->getGradeRange() );
        return $html;
    }

    /**
     * Returns the User ID of a grading item ID
     * @param int $itemID
     * @return int|null
     */
    static public function get_userID_for_itemID($itemID){
        global $DB;

        $userID = null;
        //obtain the user being graded
        $records = $DB->get_records('assign_grades', array('id' => $itemID), 'userid');
        foreach ($records as $record) {
            $userID = (int)$record->userid;
            break;
        }
        //$userID is now the ID of the user graded
        return $userID;
    }

    /**
     * Sends notifications to all users listed as second graders in the definition.
     * $sentByOwner tells if the action was triggered by the grader assigning the final grade or by an intermediary grader
     * @param bool $sentByOwner
     * @param stdClass $firstGradeRecord
     * @param int $itemID
     */
    public function send_second_graders_notification($sentByOwner = false, $firstGradeRecord = null,$itemID = null){
        global $USER, $PAGE;

        $userID = self::get_userID_for_itemID($itemID);
        //$userID is now the ID of the user graded
        $gradeeURL = self::get_user_url($userID);

        $grader = self::get_user_url($USER->id);
        $contextUrl = new moodle_url('/mod/assign/view.php', array('id' => $PAGE->cm->id,'action'=>'grader','userid' => $userID));
        $contexturlname = get_string('message_assign_name', 'gradingform_multigraders','<a href="'.$contextUrl.'">'.$PAGE->cm->name.'</a><br/>');
        $contexturlname .= ' '.get_string('message_student_name', 'gradingform_multigraders',$gradeeURL.'<br/>');
        $subject = get_string('message_subject', 'gradingform_multigraders',$contexturlname);//$PAGE->cm->name);
        $arrSecondGradingList = explode(',', $this->options->secondary_graders_id_list);

        $fullmessagehtml = $contexturlname;
        if($sentByOwner) {
            $fullmessagehtml .= get_string('message_smallmessage1', 'gradingform_multigraders', $grader);
            $fullmessagehtml .= get_string('message_smallmessage2', 'gradingform_multigraders').'<br/>';
        }else{
            //this notification is generated by a secondary grader, it should be sent to owner/first grader as well
            if($firstGradeRecord && $firstGradeRecord->grader){
                array_push($arrSecondGradingList,$firstGradeRecord->grader);
            }
            $fullmessagehtml .= get_string('message_smallmessage1', 'gradingform_multigraders', $grader);
            $fullmessagehtml .= get_string('message_smallmessage2', 'gradingform_multigraders').'<br/>';
        }
        $smallmessage = $subject;

        if ($arrSecondGradingList) {
            foreach ($arrSecondGradingList as $userID) {
                if(!$userID || $userID == $USER->id){
                    continue;
                }

                $message = new \core\message\message();
                $message->component = 'gradingform_multigraders';
                $message->name = 'secondgrading';
                $message->notification = 1;
                $message->userfrom = $USER;
                $message->userto = $userID;
                $message->subject = $subject;
                $message->fullmessage = strip_tags($fullmessagehtml);
                $message->fullmessageformat = FORMAT_HTML;
                $message->fullmessagehtml = $fullmessagehtml;
                $message->smallmessage = $smallmessage;
                $message->contexturl = $contextUrl;
                $message->contexturlname = $contexturlname;
                $message->replyto = core_user::get_noreply_user();
                $content = array('*' => array(
                    'header' => get_string('message_header', 'gradingform_multigraders'),
                    'footer' => get_string('message_footer', 'gradingform_multigraders'))); // Extra content for specific processor
                $message->set_additional_content('email', $content);
                $message->courseid = $PAGE->cm->get_course()->id; // This is required in recent versions, use it from 3.2 on https://tracker.moodle.org/browse/MDL-47162
/*
                ob_start();
                var_dump($message);
                $this->log .= ob_get_contents();
                ob_end_clean();*/

                $messageid = message_send($message);

                $this->log .= ' msgid '.$messageid;
            }
        }
    }

    /**
     * Sends notifications to first graders of this assignment
     * @param stdClass $firstGradeRecord
     * @param int $itemID
     */
    public function send_initial_grader_notification($firstGradeRecord = null,$itemID = null){
        global $USER, $PAGE;

        if($firstGradeRecord->grader == $USER->id){
            return;
        }

        $userID = self::get_userID_for_itemID($itemID);
        //$userID is now the ID of the user graded
        $gradeeURL = self::get_user_url($userID);

        $grader = self::get_user_url($USER->id);
        $contextUrl = new moodle_url('/mod/assign/view.php', array('id' => $PAGE->cm->id,'action'=>'grader','userid' => $userID));
        $contexturlname = get_string('message_assign_name', 'gradingform_multigraders','<a href="'.$contextUrl.'">'.$PAGE->cm->name.'</a><br/>');
        $contexturlname .= ' '.get_string('message_student_name', 'gradingform_multigraders',$gradeeURL.'<br/>');
        $subject = get_string('message_subject_to_initial', 'gradingform_multigraders',$contexturlname);


        $fullmessagehtml = $contexturlname;
        $fullmessagehtml .= get_string('message_smallmessage3', 'gradingform_multigraders', $grader);
        $fullmessagehtml .= get_string('message_smallmessage4', 'gradingform_multigraders').'<br/>';

        $smallmessage = $subject;

        $message = new \core\message\message();
        $message->component = 'gradingform_multigraders';
        $message->name = 'secondgrading';
        $message->notification = 1;
        $message->userfrom = $USER;
        $message->userto = $firstGradeRecord->grader;
        $message->subject = $subject;
        $message->fullmessage = strip_tags($fullmessagehtml);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $fullmessagehtml;
        $message->smallmessage = $smallmessage;

        $message->contexturl = $contextUrl;
        $message->contexturlname = $contexturlname;
        $message->replyto = core_user::get_noreply_user();
        $content = array('*' => array(
            'header' => get_string('message_header', 'gradingform_multigraders'),
            'footer' => get_string('message_footer', 'gradingform_multigraders'))); // Extra content for specific processor
        $message->set_additional_content('email', $content);
        $message->courseid = $PAGE->cm->get_course()->id; // This is required in recent versions, use it from 3.2 on https://tracker.moodle.org/browse/MDL-47162
        message_send($message);
    }

}
