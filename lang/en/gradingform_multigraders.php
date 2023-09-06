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
 * Strings for the multiple graders advanced grading plugin
 *
 * @package     gradingform_multigraders
 * @copyright   2018 Lucian Pricop <contact@lucianpricop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Multiple graders';
$string['addcomment'] = 'Add frequently used comment';
$string['backtoediting'] = 'Back to editing';
$string['clicktoedit'] = 'Click to edit';
$string['clicktocopy'] = 'Copy notes to feedback comments';
$string['clicktodeleteadmin'] = 'Delete all grade data for this assignment';
$string['comment'] = 'Comment';
$string['editdefinition'] = 'Edit multiple graders options';
$string['description'] = 'Template Description';
$string['err_gradeinvalid'] = 'Invalid grade';
$string['err_gradeoutofbounds'] = 'Grade is not in the allowed range';
$string['err_noformula'] = 'There is no formula defined for calculating the grade from the outcomes. Please visit Gradebook setup to define the formula.';
$string['needregrademessage'] = 'The multigraders definition was changed after this student had been graded. The student can not see the outcome until {$a} checks the published grade.';
$string['gradingof'] = '{$a} grading';
$string['definition'] = 'Definition';
$string['criteria'] = 'Grading criteria';
$string['maxscore'] = 'Maximum score';
$string['secondary_graders_list'] = 'Secondary graders defined: {$a}.';
$string['secondary_graders'] = 'Secondary graders';
$string['secondary_graders_help'] = 'This is a list of teachers to be notified in case a second grader is required for an assignment';
$string['require_second_grader'] = 'Request second grader';
$string['final_grade_check'] = 'Publish grade?';
$string['final_grade_message'] = 'Grade is published';
//$string['no_of_graders'] = 'Number of graders';
//$string['no_of_gradersexplained'] = '{$a} graders required for the final grade.';
//$string['grading_type'] = 'How to determine the final grade';
//$string['grading_type_average'] = 'Average grade over all grades.';
//$string['grading_type_last_grader'] = 'Last grader decides the final';
$string['blind_marking'] = 'Blind marking' ;
$string['blind_marking_help'] = 'If checked, intermediary graders can not see previous grades, only the original grader can.' ;
$string['blind_marking_explained'] = 'Blind marking is activated, secondary graders can not see previous grades, only the initial/primary grader can. However, when grade is published, everyone involved can see all grades.' ;
$string['show_intermediary_to_students'] = 'Show second graders notes to students?' ;
$string['show_intermediary_to_students_help'] = 'If checked, second graders may choose if their notes can be seen by the students when the grade is published.' ;
$string['show_intermediary_to_students_explained'] = 'The student can also see all secondary notes when the grade is published.' ;
//$string['previous_graders_cant_change'] = 'Don\'t allow graders to change their grade and feedback after another graded.' ;
//$string['previous_graders_cant_change_help'] = 'If checked, graders can not change their own grade and feed back after another grader marked the assignment and gave feed back.' ;
//$string['previous_graders_cant_change_explained'] = 'Graders can not change their grade and feed back after another grader marked the assignment and gave feed back.' ;
$string['auto_calculate_final_method'] = 'Method of auto calculating the next grade and outcomes' ;
$string['auto_calculate_final_method_help'] = 'The final grade is decided by the last grader, however the system will auto calculate the next grade depending on the previous based on the algorithm chosen here. The same method is used for deciding the nest outcome as well.' ;
$string['auto_calculate_final_method_0'] = 'last previous grade' ;
$string['auto_calculate_final_method_1'] = 'minimum previous grade' ;
$string['auto_calculate_final_method_2'] = 'maximum previous grade' ;
$string['auto_calculate_final_method_3'] = 'average over previous grades' ;
$string['visible_to_students'] = 'Show notes to student?' ;
$string['feedback_label'] = 'Notes' ;
$string['previewdefinition'] = 'Preview definition';
$string['restoredfromdraft'] = 'NOTE: The last attempt to grade this person was not saved properly so draft grades have been restored.';
$string['timestamp_format'] = 'd/m/Y H:i:s';
$string['save'] = 'Save';
$string['score'] = 'score';
$string['no_grade'] = 'No grade';
$string['finalgradenotdecidedyet'] = 'Final grade not yet published';
$string['gradingdisabled'] = 'Grading is disabled for this item, it was either locked or overridden.';
$string['finalgradestarted_noaccess'] = '{$a} started grading this item and you are not in the list of second graders. You are not allowed to make changes.';
$string['finalgradestarted_nosecond'] = '{$a} started grading this item and no further grading was requested.';
$string['finalgradefinished_noaccess'] = '{$a} completed grading this item. You are not allowed to make changes.';
$string['useralreadygradedthisitem'] = 'You already graded this item, someone else needs to decide the final grade and feedback comments.';
$string['useralreadygradedthisitemfinal'] = 'Only {$a} may change the final grade and notes.';
$string['graderdetails_display'] = 'Graded by {$a}';
$string['now_grading'] = 'Now grading: {$a}';
$string['instancedetails_display'] = '{$a} grades added.';
$string['messageprovider:secondgrading'] = 'Notifications of assignments that require second grading.';
$string['message_subject'] = 'Second grading required for {$a}';
$string['message_subject_to_initial'] = 'Second grading completed for {$a}';
$string['message_assign_name'] = 'Assignment {$a}';
$string['message_student_name'] = 'Student {$a}';
$string['message_smallmessage1'] = '{$a} has requested second grading.';
$string['message_smallmessage2'] = 'Please take a moment to grade this item.';
$string['message_smallmessage3'] = '{$a} has completed second grading.';
$string['message_smallmessage4'] = 'Please take a look and decide the final grade.';
$string['message_header'] = '<br/>';
$string['message_footer'] = '<br/><span style="font-size:80%">[automated message generated by multigraders plugin]</span><br/>';
$string['privacy:metadata'] = 'This plugin does not store any personal user data. Any user information displayed is stored elsewhere in Moodle.';

