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
require_once($CFG->dirroot .'/lib/formslib.php');

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
    public function display_form($form,$mode, $options, $values = null, $elementName = 'multigraders', $validationErrors = Array(), $gradeRange = null) {
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

        if($form == null && $mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
            return;
        }
        if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
            $form->addElement('html','<div id="gradingform_multigraders" class="gradingform_multigraders">');
                $form->addElement('html','<div class="gradingform_total">');

                    if(isset($options->criteria)) {
                        $id_user=$options->userID;
                        $user = $DB->get_record('user', array('id' => $id_user));
                        $user_name= fullname($user);

                        $url_grading = new moodle_url('/mod/assign/view.php',  array('id' => $PAGE->cm->id,'action'=>'grader','userid' => $id_user));
                        $html = get_string('now_grading','gradingform_multigraders','<a href="'.$url_grading.'"><span class="student_name">'. $user_name.'</span></a>');

                        $form->addElement('html', "<h4 class='nowgrading'>".$html."</h4>");
                        $form->addElement('html',"<div class='coursebox multigraders_criteria'>".$options->criteria."</div>");
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
                    if (isset($this->options->auto_calculate_final_method)) {
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
                    }
                    if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
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
                        if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
                            if(isset($record->outcomes)) {
                                foreach ($this->outcomes as $index => $outcome){
                                    if($sumOfPreviousOutcomes->$index) {
                                        $this->defaultNextOutcomes->$index = floor($sumOfPreviousOutcomes->$index / count($values['grades']));
                                    }
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



            //current user is the one that gave the final grade or is grading at the moment
            if($firstGradeRecord && $firstGradeRecord == $currentRecord){
                $firstGradeRecord->gradingFinal = true;
                $firstGradeRecord->allowCopyingOfDataToFinal = true;
                $userIsAllowedToGrade = true;
                $allowFinalGradeEdit = true;
                $currentUserIsInSecondGradersList = true;
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
            if( $currentRecord &&
                $firstGradeRecord !== $currentRecord && //current user is not the initial grader
                $firstGradeRecord->type == gradingform_multigraders_instance::GRADE_TYPE_FINAL //the grade is not published
                ){
                $userIsAllowedToGrade = false;
                $allowFinalGradeEdit = false;
            }
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

                        if($mode == gradingform_multigraders_controller::DISPLAY_VIEW){

                             $output .= $this->display_student($record, $additionalClass);
                        }else{
                            if(strstr($this->options->secondary_graders_id_list,$record->grader) || $record->grader == $firstGradeRecord->grader){
                                $this->display_grade($form, $record, $additionalClass, $mode);
                            }
                        }
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

                    if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
                        $this->display_grade($form, $newRecord, $additionalClass);
                    }
                }
                else{
                    if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW &&
                     $firstGradeRecord != $currentRecord &&
                    $previousRecord &&
                     $previousRecord->require_second_grader &&
                      $firstGradeRecord->require_second_grader &&
                      (strstr($this->options->secondary_graders_id_list,$USER->id) || $USER->id == $firstGradeRecord->grader)
                      ){
                        $this->display_grade($form, $newRecord, $additionalClass);
                    }
                }
            }

    if($mode !== gradingform_multigraders_controller::DISPLAY_VIEW){
            //multigraders_allow_final_edit
            if($allowFinalGradeEdit){
                $userIsAllowedToGrade = true;
                $form->addElement('html',"<input type='hidden' name='multigraders_allow_final_edit' value='true'>");
            }
            if($this->gradingDisabled){
                $userIsAllowedToGrade = false;
            }
            //multigraders_user_is_allowed_edit
            $value_multigraders_user_is_allowed_edit = $userIsAllowedToGrade ? 'true' : 'false';
            $form->addElement('html','<input type="hidden" name="multigraders_user_is_allowed_edit" value="'.$value_multigraders_user_is_allowed_edit.'">');

            //delete button for admins
            $systemcontext = context_system::instance();
            if (!$this->gradingDisabled && has_capability('moodle/site:config', $systemcontext)) {

                $title_deleteButton=get_string('clicktodeleteadmin', 'gradingform_multigraders');
                $form->addElement('html','<a href="javascript:void(null)" title="'.$title_deleteButton.'" class="delete_button">'.$title_deleteButton.'</a>');

                $name_multigraders_delete_all = $this->elementName . '[multigraders_delete_all]';
                $form->addElement('html','<input type="hidden" name="'.$name_multigraders_delete_all.'" class="multigraders_delete_all" value="false">');
            }

            //alter $allowFinalGradeEdit depending on current user relation to grading this item
            if($mode == gradingform_multigraders_controller::DISPLAY_VIEW ||
                $mode == gradingform_multigraders_controller::DISPLAY_REVIEW ||
                $mode == gradingform_multigraders_controller::DISPLAY_EVAL_FROZEN){
                $allowFinalGradeEdit = false;
                $userIsAllowedToGrade = false;
                if($firstGradeRecord === null){
                    $form->addElement('html','<div class="multigraders_grade finalGrade">'.get_string('finalgradenotdecidedyet', 'gradingform_multigraders'.'</div>'));
                }
            }
            if(($mode == gradingform_multigraders_controller::DISPLAY_EVAL_FULL ||
                $mode == gradingform_multigraders_controller::DISPLAY_EVAL) && //current user is an ADMIN or a teacher
                $currentRecord !== null &&                                      //that already graded this item
                $firstGradeRecord !== $currentRecord){                          //but the grade they gave was not the final one
                $allowFinalGradeEdit = false;
                if($firstGradeRecord) {//final grade was added
                    $finalGradeMessage = get_string('useralreadygradedthisitemfinal', 'gradingform_multigraders',gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader));
                    $form->addElement('html','<div class="alert-error">'.$finalGradeMessage.'</div>');
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
                        $finalGradeMessage = get_string('finalgradefinished_noaccess', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader));
                        $form->addElement('html','<div class="alert-error">'.$finalGradeMessage.'</div>');
                    } elseif ($previousRecord && $previousRecord->require_second_grader) {
                        //if user is in the secondary graders list and the grade is not final, allow them to add a grade
                        if ($currentUserIsInSecondGradersList) {
                            $userIsAllowedToGrade = true;
                        } else {
                            $finalGradeMessage =get_string('finalgradestarted_noaccess', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader));
                            $form->addElement('html','<div class="alert-error">'.$finalGradeMessage.'</div>');
                        }
                    } else {
                        $finalGradeMessage =get_string('finalgradestarted_nosecond', 'gradingform_multigraders', gradingform_multigraders_instance::get_user_url($firstGradeRecord->grader));
                        $form->addElement('html','<div class="alert-error">'.$finalGradeMessage.'</div>');
                    }
                }
            }
            if($this->gradingDisabled) {
             $form->addElement('html','<div class="multigraders_grade finalGrade">'.get_string('gradingdisabled', 'gradingform_multigraders').'</div>');
            }
            $form->addElement('html','</div>');

        }else{
            if($output != ''){
                return html_writer::tag('div',$output , array('class' => 'gradingform_multigraders'));
            }
        }
    }

    public function display_student($record,$additionalClass){
        //show second feedback to students
         if( $record !== null){
            if($this->options->show_intermediary_to_students && $record->visible_to_students) {
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
    }

    /**
     * Returns compiled html to display one grader record
     *
     * @param gradingform_multigraders_instance $record
     * @param string $additionalClass
     * @param int $mode
     * @return string
     */
    public function display_grade($form,$record,$additionalClass = '',$mode = gradingform_multigraders_controller::DISPLAY_EVAL) {
        if($record === null){
            return '';
        }
        if(isset($record->allowEdit) && $record->allowEdit){
            $commonAtts='';
        }else{
            $commonAtts='disabled';
        }
        $form->addElement('html','<div class="coursebox multigraders_grade '. $additionalClass.'">');

            //outcomes
            if($this->outcomes) {
                $form->addElement('html','<div class="grade-outcomes">'.$this->display_outcomes($record, $mode).'</div>');
            }

            //grade
            $form->addElement('html','<div class="grade-wrap">');
                $form->addElement('html','<div class="grade">');

                $value=$record->grade;
                $name_grade= $this->elementName.'[grade]'. '['.$record->grader.']';
                $data_grade_range_min=$this->gradeRange ? $this->gradeRange->minGrade : '';
                $data_grade_range_max=$this->gradeRange ? $this->gradeRange->maxGrade : '';

                //there is a selected scale
                //in case we need a select box
                    if($this->scaleid) {
                        if($this->outcomes) {
                            $name_grade_hidden=$this->elementName.'[grade_hidden]';
                            $form->addElement('html','<input type="hidden" name="'.$name_grade_hidden.'" value="'.$value.'" class="grade_hidden" '.$commonAtts.'>');
                        }

                        $type='text';
                        $opts = make_grades_menu(-$this->scaleid);
                        $data_formula=$this->outcomesCalculationFormula;
                        unset($type);
                        unset($name);

                        $myselect =$form->addElement('select',$name_grade,'',$opts);
                        $myselect->_attributes['name'] =$name_grade;
                        $myselect->_attributes['type'] ='text';
                        if($this->outcomes) {
                            $myselect->_attributes['disabled'] = 'disabled';
                        }
                        $myselect->_attributes['title'] = $data_formula;
                        $myselect->_attributes['data-formula'] = $data_formula;
                        $myselect->_attributes['data-grade-range-min'] =$data_grade_range_min;
                        $myselect->_attributes['data-grade-range-max'] =$data_grade_range_max;
                        $myselect->setSelected($value);
                        $form->setType($name_grade,PARAM_TEXT);
                    }

                    //no selected scale
                    //display an input
                    if(!$this->scaleid) {
                        if($this->outcomes) {
                            $readonly = 'readonly';
                            $disabled_scale='disabled';
                            $type='text';
                            if($commonAtts != 'disabled'){
                                $name_grade_hidden=$this->elementName.'[grade_hidden]';
                                $form->addElement('html','<input type="hidden" name="'.$name_grade_hidden.'" value="'. strval($value).'" class="grade_hidden">');
                            }
                            $class="grade_input_".$record->grader;
                        }else{
                            $readonly = '';
                            $disabled_scale= $commonAtts;
                            $type='number';
                            $class="grade_input";
                        }
                        $data_formula = $form->addElement('html', '<input type="' . $type . '" name="' . $name_grade . '" step="1"  value="' .  strval($value) . '" data-formula="' . $this->outcomesCalculationFormula . '"
                        title="' . $this->outcomesCalculationFormula . '" ' . ($data_grade_range_min !== null ? 'data-grade-range-min="' . $data_grade_range_min . '"' : '') . ' ' . ($data_grade_range_max !== null ? 'data-grade-range-max="' . $data_grade_range_max . '"' : '') . '
                        ' . $disabled_scale . ' class='.$class.' ' . $readonly . ' >');
                    }

                    if($this->gradeRange) {
                        $form->addElement('html','<span class="grade_range">'.$this->gradeRange->minGrade.'-'.$this->gradeRange->maxGrade."</span>");
                    }

                    $form->addElement('html','<input type="hidden" name="'.$this->elementName.'[type]'.'" value="'.$record->type.'" '.$commonAtts.'>');

                $form->addElement('html','</div>');
                $form->addElement('html','<div class="grader">"'.$this->display_grader_details($record).'"</div>');

                $time = date(get_string('timestamp_format', 'gradingform_multigraders'),$record->timestamp);
                $form->addElement('html','<div class="timestamp">'. $time.'</div>');
                $form->addElement('html','<div class="grader_id" style="display:none">'. $record->grader.'</div>');

            $form->addElement('html','</div>');

            //feedback
            $form->addElement('html','<div class="grade_feedback">');

                $for_feedbackLabel= $this->elementName . '_feedback_' . $record->grader;
                $form->addElement('html','<label class="col-form-label d-inline" for="'.$for_feedbackLabel.'">'.get_string('feedback_label', 'gradingform_multigraders').'</label>');

                $id_grader_feedback = $this->elementName . '_feedback_' . $record->grader;
                $name_grader_feedback=$this->elementName . '[feedback]'. '['.$record->grader.']';

                if($commonAtts == 'disabled'){
                    $form->addElement('html','<div id="'.$id_grader_feedback.'" name="'.$name_grader_feedback.'" class="grader_feedback" >'. $record->feedback.'</div>');
                }else{
                    $editor = $form->addElement('editor', $id_grader_feedback);
                    $editor->setValue( array('text' => $record->feedback) );
                    $editor->_attributes['name'] = $name_grader_feedback;
                    $editor->_attributes['id'] = $id_grader_feedback;
                    $form->setType($id_grader_feedback, PARAM_RAW );
                }

                if(isset($record->allowCopyingOfDataToFinal) && $record->allowCopyingOfDataToFinal) {
                    $title = get_string('clicktocopy', 'gradingform_multigraders');
                    $form->addElement('html','<a href="javascript:void(null)" title="'.$title.'" class="copy_button">'.get_string('clicktocopy', 'gradingform_multigraders').'</a>');
                }
            $form->addElement('html','</div>');

            //show to students
            if($this->options->show_intermediary_to_students) {

                $checked_visible_to_students='';
                if ($record->visible_to_students) {
                    $checked_visible_to_students .= 'checked="checked"';
                }

                $name_visible_to_students=$this->elementName . '[visible_to_students]';
                $form->addElement('html','<div class="visible_to_students">');
                    $form->addElement('html','<input name="'.$name_visible_to_students.'" type="checkbox" value="1" ' .$checked_visible_to_students.' '.$commonAtts.'>');
                    $form->addElement('html',"<span>".get_string('visible_to_students', 'gradingform_multigraders')."</span>");
                $form->addElement('html','</div>');
            }

            //require second grader

            if (!$record->require_second_grader) {
                $checked_require_second_grader = '';
            }else{
                $checked_require_second_grader= 'checked="checked"';
            }

            $name_checkbox_require_second_grader = $this->elementName . '[require_second_grader]';
            $form->addElement('html','<div id="require_second_grader">');
                $form->addElement('html','<input name="'.$name_checkbox_require_second_grader.'" type="checkbox" class="require_second_grader" value="1"  '.$checked_require_second_grader.' '.$commonAtts.'>');
                $form->addElement('html','<span>'.get_string('require_second_grader', 'gradingform_multigraders').'</span>');
            $form->addElement('html','</div>');


            $value_ntf='false';
            $checked_ntf='';
            if($this->options->show_notify_student_box){
                $value_ntf='true';
                $checked_ntf= 'checked';
            }

            $name_notify=$this->elementName . '[notify_student]';
            $form->addElement('html','<div class="int_notify_student">');
                $form->addElement('html','<input id="input_notify_student" name="'.$name_notify.'" type="hidden" value="'.$value_ntf.'" ' .$checked_ntf.'>');
            $form->addElement('html','</div>');

            //final grade
            $form->addElement('html','<div class="final_grade">');
                if(isset($record->gradingFinal) && $record->gradingFinal) {

                    $name_grading_final=$this->elementName . '[grading_final]';
                    $form->addElement('html','<input name="'.$name_grading_final.'" type="hidden" value="1">');

                    $checked= 'checked';
                    if ($record->type != gradingform_multigraders_instance::GRADE_TYPE_FINAL) {
                        // unset($checked);
                        $checked='';
                        $name_LabelorButton = $this->elementName . '[final_grade_publish]';
                        $form->addElement('html','<button name="'.$name_LabelorButton.'" type="submit" class="btn btn-primary" "'.$commonAtts.'">'.get_string('final_grade_check', 'gradingform_multigraders').'</button>');
                    }else{
                        $form->addElement('html','<span>'.get_string('final_grade_message', 'gradingform_multigraders').'</span>');
                    }
                    $name_checkbox=$this->elementName . '[final_grade]';
                    $form->addElement('html','<input name="'.$name_checkbox.'" type="checkbox" class="final_grade_check" value="1" '.$checked.$commonAtts.'>');
                }
            $form->addElement('html','</div>');


            //errors
            $error = '';
            if(isset($this->validationErrors[$record->grader.$record->type])){
                $error .= $this->validationErrors[$record->grader.$record->type];
            }
            if($this->outcomes && !$this->outcomesCalculationFormula){
                $error .= get_string('err_noformula', 'gradingform_multigraders');
            }
            if($error){
                $form->addElement('html','<div class="gradingform_multigraders-error">'.$error.'</div>');
            }

        $form->addElement('html','</div>');
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

            if(isset($attributes['disabled']) && $attributes['disabled'] =="disabled" && $outcome->name == "No grade" ){
                $output .='';
            }else{
                $output .= html_writer::tag('div', $outcomeText . $outcomeSelect, Array('class'=>'outcome'));
            }
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
                $table= $this->display_instance($instance, $idx++, $cangrade);
                if($table != ''){
                    $return .= $table;
                }else{
                    //if null, does not display "Grade breakdown" in student view
                    return;
                }
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
            $output .= $this->display_form(null,$mode, $options, $values);
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
