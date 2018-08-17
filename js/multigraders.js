M.gradingform_multigraders = {};

/**
 * This function is called for each form on page.
 */
M.gradingform_multigraders.init = function(Y, options) {
    this.grading_final = false;
    this.user_is_allowed_edit = false;
    this.number_of_grades = 0;

    if(Y.all('input[name="multigraders_allow_final_edit"').get('value') =='true'){
        this.grading_final = true;
    }
    if(Y.all('input[name="multigraders_user_is_allowed_edit"').get('value') =='true'){
        this.user_is_allowed_edit = true;
    }
    this.number_of_grades = Y.all('.multigraders_grade').size();
    console.log('init multigraders ' + this.grading_final + '  ' + this.user_is_allowed_edit + '  ' + this.number_of_grades);
    //hide global outcomes
    var advancedGradeContainer = Y.one('.gradingform_multigraders').ancestor('.fitem');
    var currentGradeContainer = Y.one('.currentgrade').ancestor('.fitem');

    while(advancedGradeContainer && advancedGradeContainer.next() && advancedGradeContainer.next() != currentGradeContainer){
        advancedGradeContainer = advancedGradeContainer.next();
        advancedGradeContainer.setStyle('display','none');
    }
    if(!this.grading_final){
        Y.all('input[name="sendstudentnotifications"]').set('checked',false).set('disabled','disabled');
        //Y.one('#id_assignfeedbackcomments_editoreditable').setAttribute('contenteditable','false');

        //hide elements that should not be editable by intermediary graders
        while(currentGradeContainer && currentGradeContainer.next()){
            currentGradeContainer = currentGradeContainer.next();
            currentGradeContainer.setStyle('display','none');
        }
    }else{
        //copy values from final outcomes to core outcomes
        Y.all('.multigraders_grade.finalGrade .outcome select').each(function(node,index){
            var outcomeIndex = node.getAttribute('data-index');
            var coreOutcome = Y.one('#menuoutcome_' + outcomeIndex);
            if(coreOutcome){
                coreOutcome.set('value',node.get('value'));
            }
            //add change event for final outcomes so on each change, the core gets updated as well.
            node.on('change',function(e){
                var outcomeIndex = e.target.getAttribute('data-index');
                var coreOutcome = Y.one('#menuoutcome_' + outcomeIndex);
                if(coreOutcome){
                    coreOutcome.set('value',e.target.get('value'));
                }
            });
        });
    }
    if(!this.user_is_allowed_edit){
        Y.all('div[data-region="grade-actions"] button').set('disabled','disabled');
    }else{
        Y.all('div[data-region="grade-actions"] button').removeAttribute('disabled');
    }

    Y.all('.multigraders_grade .edit_button').on('click', function(e) {
        if(Y.one(e.currentTarget).ancestor(".multigraders_grade").one('input,textarea').hasAttribute('disabled')) {
            Y.one(e.currentTarget).ancestor(".multigraders_grade").all('input,textarea,select').removeAttribute('disabled');
            Y.one(e.currentTarget).addClass('active');
            Y.all('div[data-region="grade-actions"] button').removeAttribute('disabled');
        }else{
            Y.one(e.currentTarget).ancestor(".multigraders_grade").all('input,textarea,select').set('disabled','disabled');
            Y.one(e.currentTarget).removeClass('active');
            if(this.user_is_allowed_edit) {
                Y.all('div[data-region="grade-actions"] button').set('disabled', 'disabled');
            }
        }
    });
    Y.all('.multigraders_grade .copy_button').on('click', function(e) {
        var graderName = Y.one(e.currentTarget).ancestor(".multigraders_grade").one('.grader_name').get('text');
        var graderFeedback = Y.one(e.currentTarget).ancestor(".multigraders_grade").one('.grader_feedback').get('value');
        var currFeedback = Y.one('#id_assignfeedbackcomments_editoreditable').getHTML();
        if(Y.one('#id_assignfeedbackcomments_editoreditable').get('text') != ''){
            currFeedback += "<br/><br/>";
        }
        var newFeedback = currFeedback + graderName + "<br/>---------------<br/>"+ M.gradingform_multigraders.nl2br(graderFeedback);
        Y.one('#id_assignfeedbackcomments_editoreditable').setHTML(newFeedback);
        Y.one('#id_assignfeedbackcomments_editoreditable').focus();
    });
    //this code makes sure that the value of the input consists only of digits
    Y.all('.multigraders_grade input.grade').on('keypress', function(event) {
        if(event.ctrlKey){
            return;
        }
        //event.charCode != 0 means the key has a character result that would show in the input
        //allowing all digits, commas and dots
        if(event.charCode != 0 && (event.which < 8 || event.which > 57) &&
            event.charCode != 188 &&
            event.charCode != 190){
            event.preventDefault();
            return null;
        }
    });
    //this code updates the grade for each outcome update depending on the formula
    Y.all('.multigraders_grade .outcome select[data-id]').on('change', function(event) {
        M.gradingform_multigraders.updateGrade(event.currentTarget);
    });
    //handle change of require_second_grader check box
    Y.all('.multigraders_grade input.require_second_grader').each(function(node,index){
        if(index == M.gradingform_multigraders.number_of_grades -1){
            node.on('change', function(event) {
                if(!M.gradingform_multigraders.grading_final){
                    return;
                }
                var checkbox = event.target;
                if(checkbox.get('checked')) {
                    Y.all('input[name="sendstudentnotifications"]').set('checked',false).set('disabled','disabled');
                    Y.one('.multigraders_grade input.final_grade_check').set('checked',false);
                }else{
                    Y.all('input[name="sendstudentnotifications"]').set('checked',true).removeAttribute('disabled');
                }
            });
        }else{
            node.on('click', function(event) {
               event.preventDefault();
                return false;
            });
        }
    });
};
M.gradingform_multigraders.nl2br = function(str) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    var breakTag = '<br />';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}
M.gradingform_multigraders.updateGrade = function(element) {
    var formula = Y.one(element).ancestor(".multigraders_grade").one('input.grade').getAttribute('data-formula');
    var gradeRangeMin = parseInt(Y.one(element).ancestor(".multigraders_grade").one('input.grade').getAttribute('data-grade-range-min'),10);
    var gradeRangeMax = parseInt(Y.one(element).ancestor(".multigraders_grade").one('input.grade').getAttribute('data-grade-range-max'),10);
    var grade;
    if(!formula){
        return;
    }
    //get all outcome values
    Y.one(element).ancestor(".multigraders_grade").all('.outcome select[data-id]').each(function(node,index){
        var outcomeId = node.getAttribute('data-id');
        var outcomeVal = node.get('value');
        var rangeMin = parseInt(node.getAttribute('data-range-min'),10);
        var rangeMax = parseInt(node.getAttribute('data-range-max'),10);
        //scale the value of the outcome to the range of the grade
        outcomeVal = (outcomeVal - rangeMin)/(rangeMax-rangeMin)*(gradeRangeMax-gradeRangeMin)+gradeRangeMin;
        outcomeVal = outcomeVal.toFixed(2);
        formula = formula.replace('##gi'+outcomeId+'##',outcomeVal);
    });
    //replace non existing outcomes in the formula
    formula = formula.replace(/##gi(\d+)##/gi,0);
    Y.one(element).ancestor(".multigraders_grade").one('input.grade').set("title", formula);
    formula = formula.replace('=sum','');
    try {
        grade = eval(formula);
    }catch(anything){
        //nothing
    }
    //update grade value
    if(grade == null || (typeof grade == Math.NaN) ){
        grade = '';
    }else{
        grade = grade.toFixed(1);
    }
    Y.one(element).ancestor(".multigraders_grade").one('input.grade').set("value", grade);
}
