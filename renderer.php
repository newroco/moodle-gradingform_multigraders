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
 * Contains the grading form renderer in all of its glory
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grading method plugin renderer
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_multigraders_renderer extends plugin_renderer_base {
/** @var stdClass with minRange of maxRange of the grading range */
    public $gradeRange;
/** @var array of errors per <grader><record type> values */
    public $validationErrors;
/** @var string the name of the form element (in editor mode) or the prefix for div ids (in view mode) */
    public $elementName;
/** @var float stores the calculation of the next grade based on the previous */
    protected $defaultNextGrade;
/** @var stdClass stores the calculations of the next outcomes base don the previous */
    protected $defaultNextOutcomes;
    /** @var array of outcomes */
    protected $outcomes;
    /** @var array of options defined in the gradingform definition */
    protected $options;
    /** @var int id of the scale used for grade calculations */
    protected $scaleid;
    /** @var boolean - tells if grading is disabled for this item */
    protected $gradingDisabled;
    /** @var string the calculation formula for outcomes */
    protected $outcomesCalculationFormula;
/** @var stdClass with minRange of maxRange of the value range for outcomes */
    protected $outcomesValueRange;

    /**
     * This function returns html code for displaying the form. Depending on $mode it may be the code
     * to edit form, to preview the form, etc
     *
     * @param int $mode form display mode, one of  gradingform_multigraders_controller::DISPLAY_* {@link  gradingform_multigraders_controller}
     * @param object $options
     * @param array $values evaluation result
     * @param string $elementName the name of the form element (in editor mode) or the prefix for div ids (in view mode)
* @param array $validationErrors array of errors per <grader><record type> values
     * @param stdClass $gradeRange with minRange of maxRange of the grading range
     * @return string
     */
    public function display_form($mode, $options, $values = null, $elementName = 'multigraders', $validationErrors = Array(), $gradeRange = null) {
        global $USER,$CFG,$PAGE,$DB;
        $output = '';
        $this->validationErrors = $validationErrors;
        $this->gradeRange = $gradeRange;
        $this->elementName = $elementName;
        $finalGradeMessage = '';
        $this->outcomes = null;
        $this->outcomesCalculationFormula = null;
        $this->outcomesValueRange = new stdClass();
        $this->options = $options;
        $this->scaleid = null;
        $this->gradingDisabled = false;
       
                if(isset($options->criteria)) {
            $id_user=$options->userID;
            $user = $DB->get_record('user', array('id' => $id_user));
            $user_name= fullname($user);

            $url_grading = new moodle_url('/mod/assign/view.php',  array('id' => $PAGE->cm->id,'action'=>'grader','userid' => $id_user));
            $html = get_string('now_grading','gradingform_multigraders','<a href="'.$url_grading.'">'. $user_name.'</a>');
        
            $output .= html_writer::tag('h4',$html,array('class' => 'nowgrading'));
            $output .= html_writer::tag('div',$options->criteria, array('class' => 'coursebox multigraders_criteria'));
        }
        $gradinginfo = grade_get_grades($PAGE->cm->get_course()->id,
            'mod',
            'assign',
            $PAGE->cm->instance,
            $options->userID);

        if(isset($gradinginfo->items) && isset($gradinginfo->items[0])) {
            $this->scaleid = $gradinginfo->items[0]->scaleid;
            if(!$this->scaleid){
                $this->scaleid = null;
            }
            $this->gradingDisabled = $gradinginfo->items[0]->grades[$options->userID]->locked ||
                                $gradinginfo->items[0]->grades[$options->userID]->overridden;
        }

        //obtain the grade calculation formula from outcomes if available
        if (!empty($CFG->enableoutcomes)) {
            $this->outcomes = $gradinginfo->outcomes;

            $assignmentInstance = $PAGE->cm->instance;
            $courseid = $PAGE->cm->get_course()->id;
            $gtree = new grade_tree($courseid, false, false);
            $assignment_category = null;
            foreach($gtree->items as $id => $item){
                if($item->iteminstance == $assignmentInstance){
                    $assignment_category = $item->categoryid;
                }
            }
            foreach($gtree->items as $id => $item){
                if($item->calculation != null && $item->iteminstance == $assignment_category){
                    $this->outcomesCalculationFormula = $item->calculation;
                    break;
                }
            }
        }

        /**
         * Check current user:
         * 2. teacher (has grading capability) -  show previous grades and feed backs with grader name if any
         * 2.a) has submitted a grading - show boxes disabled with button to allow editing
         * 2.b) has not submitted any grade yet for this itemid - show grade and feedback box
         * 2.b)b) the final grade has not been added yet -> this teacher may decide the final grade
         * 2.b)c) the final grade has been added already -> only show previous grades and final
         */

        $firstGradeRecord = null;
        $currentRecord = null;
        $sumOfPreviousGrades = 0;
        $sumOfPreviousOutcomes = new stdClass();
        $this->defaultNextGrade = null;
        $this->defaultNextOutcomes = new stdClass();
        $allowFinalGradeEdit = true;
        $userIsAllowedToGrade = true;
        $previousRecord = null;
        $currentUserIsInSecondGradersList = false;

        if(isset($this->options->secondary_graders_id_list) &&
            strstr($this->options->secondary_graders_id_list,$USER->id) !== FALSE ){
            $currentUserIsInSecondGradersList = true;
        }

        //determining the default values for grade and outcome depending on the previous grade(s)
        if($values !== null){
            //the grades are in timestamp order!
            foreach ($values['grades'] as $grader => $record) {
                switch($this->options->auto_calculate_final_method){
                    case 0://last previous grade
                        $this->defaultNextGrade = $record->grade;
                        break;
                    case 1://min previous grade
                        if($this->defaultNextGrade === null || $this->defaultNextGrade > $record->grade){
                            $this->defaultNextGrade = $record->grade;
                        }
                        break;
                    case 2://max previous grade
                        if($this->defaultNextGrade === null || $this->defaultNextGrade < $record->grade){
                            $this->defaultNextGrade = $record->grade;
                        }
                        break;
                    case 3://avg previous grade
                        $sumOfPreviousGrades += $record->grade;
                        break;
                }
                if(isset($record->outcomes)) {
                    foreach ($this->outcomes as $index => $outcome){
                        switch($this->options->auto_calculate_final_method){
                            case 0://last previous grade
                                $this->defaultNextOutcomes->$index = $record->outcomes->$index;
                                break;
                            case 1://min previous grade
                                if(isset($record->outcomes->$index) && ($this->defaultNextOutcomes->$index === null || $this->defaultNextOutcomes->$index > $record->outcomes->$index)){
                                    $this->defaultNextOutcomes->$index = $record->outcomes->$index;
                                }
                                break;
                            case 2://max previous grade
                                if(isset($record->outcomes->$index) && ($this->defaultNextOutcomes->$index === null || $this->defaultNextOutcomes->$index < $record->outcomes->$index)){
                                    $this->defaultNextOutcomes->$index = $record->outcomes->$index;
                                }
                                break;
                            case 3://avg previous grade
                                if(!isset($sumOfPreviousOutcomes->$index)){
                                    $sumOfPreviousOutcomes->$index = 0;
                                }
                                $sumOfPreviousOutcomes->$index += $record->outcomes->$index;
                                break;
                        }
                    }
                }
                if(!$firstGradeRecord){
                    $firstGradeRecord = $record;
                }
                if($record->grader == $USER->id){
                    $currentRecord = $record;
                }
                if(!$currentRecord) {
                    $previousRecord = $record;
                }
            }
            switch($this->options->auto_calculate_final_method){
                case 0://last previous grade
                    break;
                case 1://min previous grade
                    break;
                case 2://max previous grade
                    break;
                case 3://avg previous grade
                    if($sumOfPreviousGrades) {
                        $this->defaultNextGrade = number_format(doubleval($sumOfPreviousGrades / count($values['grades'])), $decimals = 1, '.', ',');
                    }
                    if(isset($record->outcomes)) {
                        foreach ($this->outcomes as $index => $outcome){
                            if($sumOfPreviousOutcomes->$index) {
                                $this->defaultNextOutcomes->$index = floor($sumOfPreviousOutcomes->$index / count($values['grades']));
                            }
                        }
                    }
                    break;
            }
        }
        /*
        ********FOR DEBUGGING************

        ob_start();
        var_dump($this->gradingDisabled);
        $dmp = ob_get_contents();
        ob_end_clean();
        $output .= html_writer::tag('div',$dmp , array('class' => 'multigraders_grade finalGrade'));
*/

        //alter $allowFinalGradeEdit depending on current user relation to grading this item
        if($mode == gradingform_multigraders_controller::DISPLAY_VIEW ||
           $mode == gradingform_multigraders_controller::DISPLAY_REVIEW ||
           $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FROZEN){
            $allowFinalGradeEdit = false;
            $userIsAllowedToGrade = false;
            if($firstGradeRecord === null){
                $finalGradeMessage = html_writer::tag('div', get_string('finalgradenotdecidedyet', 'gradingform_multigraders'), array('class' => 'multigraders_grade finalGrade'));
            }
        }
        if(($mode == gradingform_multigraders_controller::DISPLAY_EVAL_FULL ||
            $mode == gradingform_multigraders_controller::DISPLAY_EVAL) && //current user is an ADMIN or a teacher
            $currentRecord !== null &&                                      //that already graded this item
            $firstGradeRecord !== $currentRecord){                          //but the grade they gave was not the final one
            $allowFinalGradeEdit = false;
            if($firstGradeRecord) {//final grade was added
                $finalGradeMessage = html_writer::tag('div', get_string('useralreadygradedthisitemfinal', 'gradingform_multigraders',gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader)), array('class' => 'alert-error'));
            }else{//the final grade was not added yet
             //   $finalGradeMessage = html_writer::tag('div', get_string('useralreadygradedthisitem', 'gradingform_multigraders'), array('class' => 'alert-error'));
            }
        }
        if (($mode == gradingform_multigraders_controller::DISPLAY_EVAL_FULL ||
             $mode == gradingform_multigraders_controller::DISPLAY_EVAL ) && //current user is an ADMIN or a teacher
            $firstGradeRecord){
            $allowFinalGradeEdit = false;
            $userIsAllowedToGrade = false;
            if($firstGradeRecord != $currentRecord) {//current grader is not the initial grader
                if ($firstGradeRecord->type == gradingform_multigraders_instance::GRADE_TYPE_FINAL) {
                    $finalGradeMessage = html_writer::tag('div',
                        get_string('finalgradefinished_noaccess', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader)),
                        array('class' => 'alert-error'));
                } elseif ($previousRecord && $previousRecord->require_second_grader) {
                    //if user is in the secondary graders list and the grade is not final, allow them to add a grade
                    if ($currentUserIsInSecondGradersList) {
                        $userIsAllowedToGrade = true;
                    } else {
                        $finalGradeMessage = html_writer::tag('div',
                            get_string('finalgradestarted_noaccess', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader)),
                            array('class' => 'alert-error'));
                    }
                } else {
                    $finalGradeMessage = html_writer::tag('div',
                        get_string('finalgradestarted_nosecond', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader)),
                        array('class' => 'alert-error'));
                }
            }
        }
        if($this->gradingDisabled) {
            $finalGradeMessage = html_writer::tag('div', get_string('gradingdisabled', 'gradingform_multigraders'), array('class' => 'multigraders_grade finalGrade'));
        }

        //current user is the one that gave the final grade or is grading at the moment
        if($firstGradeRecord && $firstGradeRecord == $currentRecord){
            $firstGradeRecord->gradingFinal = true;
            $firstGradeRecord->allowCopyingOfDataToFinal = true;
            $userIsAllowedToGrade = true;
            $allowFinalGradeEdit = true;
        }

        //display the number of grades added
       /* if ($mode == gradingform_multigraders_controller::DISPLAY_EVAL ||
            $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FULL){
            if(count($values['grades']) > 0){
                $gradeNoText = count($values['grades']);
            }else{
                $gradeNoText = 'no';
            }

            $out = get_string('instancedetails_display', 'gradingform_multigraders',$gradeNoText);
            $class = 'instance_details';
            if(count($values['grades']) > 1) {
                $class .= ' highlight_green';
            }else{
                $class .= ' highlight_red';
            }
            $output .= html_writer::tag('div',$out , array('class' => $class));
        }*/

        //previous grades part
        if($currentRecord && $userIsAllowedToGrade){
            $currentRecord->allowEdit = true;
        }
        if ($mode == gradingform_multigraders_controller::DISPLAY_VIEW ||
            $mode == gradingform_multigraders_controller::DISPLAY_REVIEW ||
            $mode == gradingform_multigraders_controller::DISPLAY_EVAL ||
            $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FROZEN ||
            $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FULL
        ) {
            if ($values !== null) {
                //display all previous grading records in view mode
                //if final grade was not given and this user added a grade, allow editing
                foreach ($values['grades'] as $grader => $record) {
                    $additionalClass = '';
                    if($this->options->blind_marking &&
                        !$allowFinalGradeEdit &&
                        $record != $currentRecord &&
                        ($firstGradeRecord == null || $firstGradeRecord->type != gradingform_multigraders_instance::GRADE_TYPE_FINAL)) {
                        continue;
                    }
                    if ($allowFinalGradeEdit && $record != $firstGradeRecord) {
                        //allow copying the feedback to final grade comments
                        $record->allowCopyingOfDataToFinal = true;
                    }
                    if($record == $firstGradeRecord){
                        $additionalClass = 'finalGrade';
                    }
                    $output .= $this->display_grade($record, $additionalClass, $mode);
                }
            }
        }

        //current grade part
        // if this is the first time this item is being graded
        if(($userIsAllowedToGrade || $allowFinalGradeEdit) &&
            $currentRecord === null &&
            !$this->gradingDisabled){
            echo 'in';

            $additionalClass = '';
            $newRecord = new stdClass();
            $newRecord->grade = '';
            $newRecord->feedback = '';
            $newRecord->type = gradingform_multigraders_instance::GRADE_TYPE_INTERMEDIARY;
            $newRecord->grader = $USER->id;
            $newRecord->dontdisable = true;
            $newRecord->timestamp = time();
            $newRecord->allowEdit = true;
            $newRecord->visible_to_students = false;
            $newRecord->require_second_grader = false;

            if($firstGradeRecord == null){
                $allowFinalGradeEdit = true;
                $newRecord->gradingFinal = true;
                $newRecord->allowCopyingOfDataToFinal = true;
                $newRecord->allowEdit = true;
                $additionalClass = 'finalGrade';
                $firstGradeRecord = $newRecord;
            }

            $output .= $this->display_grade($newRecord,$additionalClass);
        }

        //multigraders_allow_final_edit
        if($allowFinalGradeEdit){
            $userIsAllowedToGrade = true;
            $atts = array('type' => 'hidden',
                'name' => 'multigraders_allow_final_edit',
                'value' => 'true');
            $output .= html_writer::empty_tag('input', array_merge($atts));
        }
        if($this->gradingDisabled){
            $userIsAllowedToGrade = false;
        }
        //multigraders_user_is_allowed_edit
        $atts = array('type' => 'hidden',
            'name' => 'multigraders_user_is_allowed_edit',
            'value' => ($userIsAllowedToGrade ? 'true' : 'false'));
        $output .= html_writer::empty_tag('input', array_merge($atts));

        //delete button for admins
        $systemcontext = context_system::instance();
        if (!$this->gradingDisabled && has_capability('moodle/site:config', $systemcontext)) {
            $atts = array('href' => 'javascript:void(null)',
                'title' => get_string('clicktodeleteadmin', 'gradingform_multigraders'),
                'class' => 'delete_button');
            $deleteButton = html_writer::tag('a', get_string('clicktodeleteadmin', 'gradingform_multigraders'), $atts);
            $output .= $deleteButton;
            $atts = array('type' => 'hidden',
                'name' => $this->elementName . '[multigraders_delete_all]',
                'class' => 'multigraders_delete_all',
                'value' => 'false');
            $output .= html_writer::empty_tag('input', array_merge($atts));
        }

        return html_writer::tag('div',$output . $finalGradeMessage , array('class' => 'gradingform_multigraders'));
    }

/**
     * Returns compiled html to display one grader record
     *
     * @param gradingform_multigraders_instance $record
     * @param string $additionalClass
     * @param int $mode
     * @return string
     */
    public function display_grade($record,$additionalClass = '',$mode = gradingform_multigraders_controller::DISPLAY_EVAL) {
        if($record === null){
            return '';
        }
        $commonAtts = Array('disabled' => 'disabled');
        if(isset($record->allowEdit) && $record->allowEdit){
            unset($commonAtts['disabled']);
        }

        //outcomes
$outcomesDiv = '';
        if($this->outcomes) {
            $outcomesDiv = html_writer::tag('div', $this->display_outcomes($record, $mode), array('class' => 'grade-outcomes'));
        }

        //show second feedback to students
        if( $mode == gradingform_multigraders_controller::DISPLAY_VIEW &&
            $record !== null) {
            if($this->options->show_intermediary_to_students) {
                $time = date(get_string('timestamp_format', 'gradingform_multigraders'), $record->timestamp);
                $timeDiv = html_writer::tag('div', $time, array('class' => 'timestamp'));
                $userDetails = html_writer::tag('div', '&nbsp;'.gradingform_multigraders_instance::get_user_url($record->grader), array('class' => 'grader'));
                //$gradeDiv = html_writer::tag('div', get_string('score', 'gradingform_multigraders').': '. $record->grade, array('class' => 'grade'));
                $feedbackDiv = html_writer::tag('div', nl2br($record->feedback), array('class' => 'grade_feedback'));
                return html_writer::tag('div', $timeDiv . $userDetails. $feedbackDiv , array('class' => 'multigraders_grade review ' . $additionalClass));
            }else{
                return '';
            }
        }

        //grade
        $grade = '';
        $atts = array('type' => 'text',
            'name' => $this->elementName.'[grade]',
            'value' => $record->grade,
            'data-formula' => $this->outcomesCalculationFormula,
            'title' => $this->outcomesCalculationFormula,
            'data-grade-range-min' => $this->gradeRange ? $this->gradeRange->minGrade : '',
            'data-grade-range-max' => $this->gradeRange ? $this->gradeRange->maxGrade : '',
            'class' => 'grade');
        //in case we need a select box
        if($this->scaleid) {
            if($this->outcomes) {
                $atts2 = array('type' => 'hidden',
                    'name' => $this->elementName.'[grade_hidden]',
                    'value' => $record->grade,
                    'class' => 'grade_hidden');
                $grade .= html_writer::empty_tag('input', array_merge($atts2, $commonAtts));
                $atts['disabled'] = 'disabled';
            }
            unset($atts['value']);
            unset($atts['type']);
            unset($atts['name']);
            $opts = make_grades_menu(-$this->scaleid);
            $grade .= html_writer::select($opts,$this->elementName.'[grade]',$record->grade,$nothing=array('-1'=>get_string('no_grade', 'gradingform_multigraders')),array_merge($atts, $commonAtts));
        }
        if(!$this->scaleid) {
            if($this->outcomes) {
                $atts['readonly'] = 'readonly';
            }
            $grade = html_writer::empty_tag('input', array_merge($atts, $commonAtts));
        }
        $gradeRange = '';
        if($this->gradeRange) {
            $atts = array('class' => 'grade_range');
            $gradeRange = html_writer::tag('span', $this->gradeRange->minGrade.'-'.$this->gradeRange->maxGrade, $atts);
        }
        $atts = array('type' => 'hidden',
            'name' => $this->elementName.'[type]',
            'value' => $record->type);
        $type = html_writer::empty_tag('input', array_merge($atts, $commonAtts));
        $editButton = '';
        /*if(isset($record->allowEdit) && $record->allowEdit && !isset($record->dontdisable)){
            $atts = array('href' => 'javascript:void(null)',
                'title' => get_string('clicktoedit', 'gradingform_multigraders'),
                'class' => 'edit_button');
            $editButton = html_writer::tag('a',' ', $atts);
        }*/
        $gradeDiv = html_writer::tag('div', $grade . $gradeRange . $type , array('class' => 'grade'));
        $userDetails = html_writer::tag('div',$this->display_grader_details($record) , array('class' => 'grader'));
        $time = date(get_string('timestamp_format', 'gradingform_multigraders'),$record->timestamp);
        $timeDiv = html_writer::tag('div',$time , array('class' => 'timestamp'));
        $gradeWrapDiv = html_writer::tag('div', $gradeDiv . $editButton . $userDetails . $timeDiv, array('class' => 'grade-wrap'));
        //feedback
        $atts = array(
            'for' => $this->elementName.'_feedback_'.$record->grader,
            'class' => 'col-form-label d-inline');
        $feedbackLabel = html_writer::tag('label', get_string('feedback_label', 'gradingform_multigraders'), $atts);
        $atts = array('rows' => '3',
            'id' => $this->elementName.'_feedback_'.$record->grader,
            'name' => $this->elementName.'[feedback]',
            'class' => 'grader_feedback');
        $feedback = html_writer::tag('textarea', $record->feedback, array_merge($atts,$commonAtts));
        $copyButton = '';
        if(isset($record->allowCopyingOfDataToFinal) && $record->allowCopyingOfDataToFinal) {
            $atts = array('href' => 'javascript:void(null)',
                'title' => get_string('clicktocopy', 'gradingform_multigraders'),
                'class' => 'copy_button');
            $copyButton = html_writer::tag('a', get_string('clicktocopy', 'gradingform_multigraders'), $atts);
        }
        $feedbackDiv = html_writer::tag('div', $feedbackLabel. $feedback . $copyButton, array('class' => 'grade_feedback'));
        //show to students
        if($this->options->show_intermediary_to_students) {
            $atts = array(
                'name' => $this->elementName . '[visible_to_students]',
                'type' => 'checkbox',
                'value' => 1);
            if ($record->visible_to_students) {
                $atts['checked']= 'checked';
            }
            $checkbox = html_writer::empty_tag('input', array_merge($atts, $commonAtts));
            $checkboxLabel = html_writer::tag('span', get_string('visible_to_students', 'gradingform_multigraders'));
            $showToStudents = html_writer::tag('div', $checkbox . $checkboxLabel, array('class' => 'visible_to_students'));
        }else{
            $showToStudents = '';
        }
        if($this->options->show_notify_student_box){
            $atts_notify = array(
                'id' => 'input_notify_student',
                'name' => $this->elementName . '[notify_student]',
                'type' => 'hidden',
                'value' => 'true');              
                $input_not= html_writer::empty_tag('input',array_merge( $atts_notify));
                $inputNotify= html_writer::tag('div', $input_not, array('class'=> 'int_notify_student'));
            }else{
                $atts_notify = array(
                    'id' => 'input_notify_student',
                    'name' => $this->elementName . '[notify_student]',
                    'type' => 'hidden',
                    'value' => 'false');              
                    $input_not= html_writer::empty_tag('input',array_merge( $atts_notify));
                    $inputNotify= html_writer::tag('div', $input_not, array('class'=> 'int_notify_student'));
            }
        //final grade
        $finalGrade = '';
        if(isset($record->gradingFinal) && $record->gradingFinal) {
            $atts = array(
                'name' => $this->elementName . '[grading_final]',
                'type' => 'hidden',
                'value' => 1);
            $hiddenFinal = html_writer::empty_tag('input', $atts);
            $atts = array(
                'name' => $this->elementName . '[final_grade]',
                'type' => 'checkbox',
                'class' => 'final_grade_check',
                'value' => 1,
                'checked' => 'checked');
            if ($record->type != gradingform_multigraders_instance::GRADE_TYPE_FINAL) {
                unset($atts['checked']);
                $buttonAtts = array(
                    'name' => $this->elementName . '[final_grade_publish]',
                    'type' => 'submit',
                    'class' => 'btn btn-primary',
                );
                $checkboxLabelorButton = html_writer::tag('button',get_string('final_grade_check', 'gradingform_multigraders'),array_merge($buttonAtts, $commonAtts));
            }else{
                $checkboxLabelorButton = html_writer::tag('span',get_string('final_grade_message', 'gradingform_multigraders'));
            }
            $checkbox = html_writer::empty_tag('input', array_merge($atts, $commonAtts));
            $finalGrade = html_writer::tag('div', $hiddenFinal . $checkbox . $checkboxLabelorButton);
        }
        //require second grader
        $atts = array(
            'name' => $this->elementName . '[require_second_grader]',
            'type' => 'checkbox',
            'class' => 'require_second_grader',
            'value' => 1,
            'checked' => 'checked');
        if (!$record->require_second_grader) {
            unset($atts['checked']);
        }
        $checkbox = html_writer::empty_tag('input', array_merge($atts, $commonAtts));
        $checkboxLabel = html_writer::tag('span', get_string('require_second_grader', 'gradingform_multigraders'));
        $requireSecondGrader = html_writer::tag('div', $checkbox . $checkboxLabel);
        //errors
        $errorDiv = '';
        $error = '';
        if(isset($this->validationErrors[$record->grader.$record->type])){
            $error .= $this->validationErrors[$record->grader.$record->type];
        }
        if($this->outcomes && !$this->outcomesCalculationFormula){
            $error .= get_string('err_noformula', 'gradingform_multigraders');
        }
        if($error){
            $errorDiv = html_writer::tag('div', $error, array('class' => 'gradingform_multigraders-error'));
        }
        return html_writer::tag('div', $outcomesDiv . $gradeWrapDiv. $feedbackDiv . $showToStudents . $inputNotify. $requireSecondGrader. $finalGrade. $errorDiv, array('class' => 'coursebox multigraders_grade '.$additionalClass));
    }

/**
     * Returns the text to show for the grader of a record
     *
     * @param gradingform_multigraders_instance $record
     * @return mixed
     */
    public function display_grader_details($record){
        if($record === null || !isset($record->grader)){
            return get_string('graderdetails_display', 'gradingform_multigraders','??');
        }

        return get_string('graderdetails_display', 'gradingform_multigraders',gradingform_multigraders_instance::get_user_url($record->grader));
    }

/**
     * Returns the html to display the outcome data for a particular record
     *
     * @param gradingform_multigraders_instance $record
     * @param int $mode
     * @return string
     */
    public function display_outcomes($record,$mode = gradingform_multigraders_controller::DISPLAY_EVAL) {
        if(!$this->outcomes) {
            return '';
        }
        $attributes = Array('disabled' => 'disabled');
        if(isset($record->allowEdit) && $record->allowEdit){
            unset($attributes['disabled']);
        }
        $output = '';
        foreach ($this->outcomes as $index => $outcome) {
            $outcomes = null;
            if(isset($record->outcomes)) {
                $outcomes = $record->outcomes;
            }
            /*$echo = highlight_string("<?php\n\$obj =\n" . var_export($outcome, true) . ";\n?>");*/

            $val = -1;
            if($outcomes && isset($outcomes->$index)){
                $val = $outcomes->$index;
            }elseif(isset($this->defaultNextOutcomes->$index)){
                $val = $this->defaultNextOutcomes->$index;
            }

            if($mode == gradingform_multigraders_controller::DISPLAY_VIEW ||
                $mode == gradingform_multigraders_controller::DISPLAY_REVIEW ||
                $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FROZEN){
                $outcomeText =  html_writer::tag('div',
                    html_writer::tag('span', $outcome->name.': ').
                    html_writer::tag('span', $val,Array('class'=>'outcome_value')),
                    array('class' => ''));
                $outcomeSelect = '';
            }else {
                $outcomeText =  html_writer::tag('div',
                    html_writer::tag('span', $outcome->name.':'),
                    array('class' => ''));
                $opts = make_grades_menu(-$outcome->scaleid);
                $attributes['data-index'] = $index;
                $attributes['data-id'] = $outcome->id;
                /*$arrRange = array_values($opts);
                sort($arrRange);
                $attributes['data-range-min'] = $arrRange[0];
                $attributes['data-range-max'] = $arrRange[count($arrRange) - 1];
                $cutPos = strpos($attributes['data-range-min'],'/');
                if($cutPos !== FALSE){
                    $attributes['data-range-min'] = floatval(substr($attributes['data-range-min'],0,$cutPos));
                }
                $cutPos = strpos($attributes['data-range-max'],'/');
                if($cutPos !== FALSE){
                    $attributes['data-range-max'] = floatval(substr($attributes['data-range-max'],$cutPos+1));
                }
                if($attributes['data-range-min'] == 1){
                    $attributes['data-range-min'] = 0;
                }*/

                $opts[-1] = get_string('nooutcome', 'grades');
                $outcomeSelect = html_writer::tag('div', html_writer::select($opts, $this->elementName . '[outcome][' . $index . ']', $val, null, $attributes), array('class' => 'fselect fitemtitle'));
            }
            $output .= html_writer::tag('div', $outcomeText . $outcomeSelect, Array('class'=>'outcome'));
        }
        return $output;
    }


    /**
     * Displays for the student the list of instances or default content if no instances found
     *
     * @param array $instances array of objects of type gradingform_multigraders_instance
     * @param string $defaultcontent default string that would be displayed without advanced grading
     * @param bool $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function display_instances($instances, $defaultcontent, $cangrade) {
        $return = '';
        if (count($instances)) {
            $return .= html_writer::start_tag('div', array('class' => 'advancedgrade'));
            $idx = 0;
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance, $idx++, $cangrade);
            }
            $return .= html_writer::end_tag('div');
        }
        return $return. $defaultcontent;
    }

    /**
     * Displays one grading instance
     *
     * @param gradingform_multigraders_instance $instance
     * @param int $idx unique number of instance on page
     * @param bool $cangrade whether current user has capability to grade in this context
     */
    public function display_instance(gradingform_multigraders_instance $instance, $idx, $cangrade) {
        $values = $instance->get_instance_grades();
        $definition = $instance->get_controller()->get_definition();
        $options = new stdClass();
        if($definition) {
            foreach (array('secondary_graders_id_list','criteria','blind_marking','show_intermediary_to_students','auto_calculate_final_method','show_notify_student_box') as $key) {
                if (isset($definition->$key)) {
                    $options->$key = $definition->$key;
                }
            }
        }
        if ($cangrade) {
            $mode = gradingform_multigraders_controller::DISPLAY_REVIEW;
        } else {
            $mode = gradingform_multigraders_controller::DISPLAY_VIEW;
        }
        $output = '';
        $finalGradeRecord = null;
        if($values !== null){
            $output .= $this->display_form($mode, $options, $values);
        }
        return $output;
    }

    /**
     * Help function to return CSS class names for element (first/last/even/odd) with leading space
     *
     * @param int $idx index of this element in the row/column
     * @param int $maxidx maximum index of the element in the row/column
     * @return string
     */
    protected function get_css_class_suffix($idx, $maxidx) {
        $class = '';
        if ($idx == 0) {
            $class .= ' first';
        }
        if ($idx == $maxidx) {
            $class .= ' last';
        }
        if ($idx % 2) {
            $class .= ' odd';
        } else {
            $class .= ' even';
        }
        return $class;
    }


}
