M.gradingform_multigraders = {};

/**
 * This function is called for each form on page.
 */
M.gradingform_multigraders.init = function(Y, options) {
    this.grading_final = false;
    this.user_is_allowed_edit = false;
    this.number_of_grades = 0;

    if(jQuery('input[name="multigraders_allow_final_edit"]').val() =='true'){
        this.grading_final = true;
    }
    if(jQuery('input[name="multigraders_user_is_allowed_edit"]').val() =='true'){
        this.user_is_allowed_edit = true;
    }
    this.number_of_grades = Y.all('.multigraders_grade').size();
//console.log('init multigraders ' + this.grading_final + '  ' + this.user_is_allowed_edit + '  ' + this.number_of_grades);
    //hide global outcomes
    let advancedGradeContainer = Y.one('#fitem_id_advancedgrading');
    let currentGradeContainer = Y.one('.currentgrade').ancestor('.fitem');
    while(advancedGradeContainer && advancedGradeContainer.next() && advancedGradeContainer.next() != currentGradeContainer){
        advancedGradeContainer = advancedGradeContainer.next();
        advancedGradeContainer.setStyle('display','none');
    }
    if(!this.grading_final){
        Y.all('input[name="sendstudentnotifications"]').set('checked',false).set('disabled','disabled');
        //Y.one('#id_assignfeedbackcomments_editoreditable').setAttribute('contenteditable','false');

        //hide elements that should not be editable by intermediary graders
        /*while(currentGradeContainer && currentGradeContainer.next()){
            currentGradeContainer = currentGradeContainer.next();
            currentGradeContainer.setStyle('display','none');
        }*/
    }else{
        //copy values from final outcomes to core outcomes
        Y.all('.multigraders_grade.finalGrade .outcome select').each(function(node,index){
            const outcomeIndex = node.getAttribute('data-index');
            let coreOutcome = Y.one('#menuoutcome_' + outcomeIndex);
            if(coreOutcome){
                coreOutcome.set('value',node.get('value'));
            }
            //add change event for final outcomes so on each change, the core gets updated as well.
            node.on('change',function(e){
                const outcomeIndex = e.target.getAttribute('data-index');
                let coreOutcome = Y.one('#menuoutcome_' + outcomeIndex);
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

//COPY BUTTON
    Y.all('.multigraders_grade .copy_button').on('click', function(e) {
        const graderId= Y.one(e.currentTarget).ancestor(".multigraders_grade").one('.grader_id').get('text');
        const graderName = Y.one(e.currentTarget).ancestor(".multigraders_grade").one('.grader_name').get('text');
        let textareaFeedback=Y.one("textarea[id^='advancedgrading_feedback_"+graderId+"']");
        let graderFeedback =Y.one("[id^='advancedgrading_feedback_"+graderId+"']");

        //Feedback in the Notes section
        let graderFeedbackElemnt ='';
            if(graderFeedback.hasClass('tox-edit-area__iframe')){ //TinyMCE editor
                textareaFeedback.set('value', graderFeedback.getDOMNode().contentDocument.body.innerHTML);
                graderFeedbackElemnt = textareaFeedback.get('value');
            }else if(graderFeedback.hasClass('editor_atto_content form-control')){ //Atto HTML editor
                textareaFeedback.set('value', graderFeedback.get('innerHTML'));
                graderFeedbackElemnt = textareaFeedback.get('value');
            }else if(graderFeedback == textareaFeedback && graderFeedback.hasClass('form-control')){ //Plain text area
                textareaFeedback.set('value', graderFeedback.get('value'));
                graderFeedbackElemnt = textareaFeedback.get('value');
            }
            else if(graderFeedback.hasClass('grader_feedback')){ //feedback second grader
                graderFeedbackElemnt = graderFeedback.get('innerHTML');
            }

        let currFeedback='';
        let isTinyMCE = false;
        let Atto =false;

        //Feedback comments section
        let textareaFeedbackcomments = Y.one("textarea[id^='id_assignfeedbackcomments_editor']");
        Y.all("[id^='id_assignfeedbackcomments_editor']").each(function(node,index){
            if(node.hasClass('tox-edit-area__iframe')){ //TinyMCE editor
                isTinyMCE = true;
                currFeedbackComments = node.getDOMNode().contentDocument.body;
                if(currFeedbackComments.textContent !== ''){
                    textareaFeedbackcomments.set('value',currFeedbackComments.innerHTML);
                }else{
                    textareaFeedbackcomments.set('value',currFeedbackComments.textContent);
                }
            }else if(node.hasClass('editor_atto_content')){ //Atto HTML editor
                Atto =true;
                if(node.get('text') !== ''){
                    textareaFeedbackcomments.set('value',node.get('innerHTML'));
                }else{
                    textareaFeedbackcomments.set('value', node.get('text'));
                }
            }else if(node == textareaFeedbackcomments){ //Plain text area
                textareaFeedbackcomments.set('value',node.get('innerHTML'));
            }

            currFeedback = textareaFeedbackcomments.get('value');
            if(currFeedback !== ''){
                currFeedback += "<br />";
            }

            let newFeedback = currFeedback + graderName + "<br />---------------<br />"+ M.gradingform_multigraders.nl2br(graderFeedbackElemnt);

            if(isTinyMCE){
                Y.one("textarea[id^='id_assignfeedbackcomments_editor']").set('value', '');
                const iframeBody = node.getDOMNode().contentDocument.body;
                iframeBody.innerHTML = newFeedback;
                tinyMCE.triggerSave();
            }else if(Atto){
                node.setHTML(newFeedback);
                node.focus();
            }else{
                textareaFeedbackcomments.setHTML(newFeedback.replace(/<br\s*\/?>/g, '\n'));
                textareaFeedbackcomments.focus();
            }

        });
    });

//DELETE BUTTON
        Y.all('.gradingform_multigraders .delete_button').on('click', function(e) {
        const yes = confirm('Are you sure?');
        if(yes){
            jQuery('.path-mod-assign [data-region="overlay"]').show();

            jQuery('input.multigraders_delete_all').val('true');
            jQuery('#id_assignfeedbackcomments_editoreditable').html('');

            Y.all('.multigraders_grade.finalGrade .outcome select').each(function(node,index){
                const outcomeIndex = node.getAttribute('data-index');
                let coreOutcome = Y.one('#menuoutcome_' + outcomeIndex);
                if (coreOutcome) {
                    coreOutcome.all('option').each(function(option) {
                        option.removeAttribute('selected');
                        option.set('value', null);
                    });
                }
            });

            setTimeout(function() {
                jQuery('div[data-region="grade-actions"] button[name="savechanges"]').trigger( "click" );
                jQuery('.path-mod-assign [data-region="overlay"]').hide();
            },9000);
        }
    });
    //this code makes sure that the value of the input consists only of digits
    Y.all('.multigraders_grade input.grade_input').on('change', function(event) {
        let value = event.target.get('value');
        let intValue = Math.round(parseFloat(value));
        event.target.set('value', intValue);

       /*  if(event.ctrlKey){
            return;
        }
        //event.charCode != 0 means the key has a character result that would show in the input
        //allowing all digits, commas and dots
        if(event.charCode != 0 && (event.which < 8 || event.which > 57) &&
            event.charCode != 188 &&
            event.charCode != 190){
            event.preventDefault();
            return null;
        } */
    });

    //this code updates the grade for each outcome update depending on the formula
    Y.all('.multigraders_grade .outcome select[data-id]').on('change', function(event) {
        M.gradingform_multigraders.updateGrade(event.currentTarget);
    });
    //handle change of notify_student check box
   if(jQuery('.int_notify_student input#input_notify_student').val() == 'false'){
        Y.all('.flex-grow-1.align-self-center label').setStyle('display','none');
        Y.all('.flex-grow-1.align-self-center .btn.btn-link.p-0').setStyle('display','none');
   }else{
        Y.all('.flex-grow-1.align-self-center label').setStyle('display','initial');
        Y.all('.flex-grow-1.align-self-center .btn.btn-link.p-0').setStyle('display','initial');
    }

    //handle change of require_second_grader check box
        Y.all('.multigraders_grade input.require_second_grader').each(function(node,index){
        if(index == M.gradingform_multigraders.number_of_grades -1){
            node.on('change', function(event) {
                if(!M.gradingform_multigraders.grading_final){
                    return;
                }
                let checkbox = event.target;
                if(checkbox.get('checked')) {
                    Y.all('input[name="sendstudentnotifications"]').set('checked',false).set('disabled','disabled');
                    Y.all('.multigraders_grade button[name="advancedgrading[final_grade_publish]"]').set('disabled','disabled');
                    Y.one('.multigraders_grade input.final_grade_check').set('checked',false);
                }else{
                    Y.all('input[name="sendstudentnotifications"]').set('checked',true).removeAttribute('disabled');
                    Y.all('.multigraders_grade button[name="advancedgrading[final_grade_publish]"]').removeAttribute('disabled');
                }
            });
        }else{
            node.on('click', function(event) {
               event.preventDefault();
                return false;
            });
        }
    });
    Y.all('.multigraders_grade button[name="advancedgrading[final_grade_publish]"]').on('click', function(e) {
        Y.one('.multigraders_grade input.final_grade_check').set('checked',true);
        M.gradingform_multigraders.eventFire(Y.one('div[data-region="grade-actions"] button[name=savechanges]')._node,'click');
    });
};
M.gradingform_multigraders.updateGrade = function(element) {
    const graderId= Y.one(element).ancestor(".multigraders_grade").one('.grader_id').get('text');
    let formula = Y.one(element).ancestor(".multigraders_grade").one('input.grade_input_'+graderId+',select#id_advancedgrading_grade_'+graderId).getAttribute('data-formula');
    let gradeRangeMin = parseInt(Y.one(element).ancestor(".multigraders_grade").one('input.grade_input_'+graderId+',select#id_advancedgrading_grade_'+graderId).getAttribute('data-grade-range-min'),10);
    let gradeRangeMax = parseInt(Y.one(element).ancestor(".multigraders_grade").one('input.grade_input_'+graderId+',select#id_advancedgrading_grade_'+graderId).getAttribute('data-grade-range-max'),10);
    let grade;
    if(!formula){
        return;
    }
    //get all outcome values
    Y.one(element).ancestor(".multigraders_grade").all('.outcome select[data-id]').each(function(node,index){
        const outcomeId = node.getAttribute('data-id');
        let  select_outcomeVal=jQuery(node.getDOMNode()).children(':selected').text();
        if(isNaN(gradeRangeMin) && isNaN(gradeRangeMax)){
            outcomeVal = select_outcomeVal;
            grade=node.get('value');
        }else{
            outcomeVal = parseFloat(select_outcomeVal);
            outcomeVal = outcomeVal.toFixed(2);
        }

       /* const rangeMin = parseInt(node.getAttribute('data-range-min'),10);
        const rangeMax = parseInt(node.getAttribute('data-range-max'),10);
        //scale the value of the outcome to the range of the grade
        outcomeVal = (outcomeVal - rangeMin)/(rangeMax-rangeMin)*(gradeRangeMax-gradeRangeMin)+gradeRangeMin;*/
        formula = formula.replace('##gi'+outcomeId+'##',outcomeVal);
    });

    if(!isNaN(gradeRangeMin) && !isNaN(gradeRangeMax)){
        //replace non existing outcomes in the formula
        formula = formula.replace(/##gi(\d+)##/gi,0);
        Y.one(element).ancestor(".multigraders_grade").one('input.grade_input_'+graderId+',select#id_advancedgrading_grade_'+graderId).set("title", formula);
        formula = formula.replace('=sum','');
        formula = formula.replace('=','');
        try {
            grade = eval(formula);
        } catch (anything) {
            //nothing
        }
        //update grade value
        if(grade == null || (typeof grade == Math.NaN) ){
            grade = '';
        }else{
            grade = grade.toFixed(1);
        }
    }

        const ancestor = Y.one(element).ancestor(".multigraders_grade");
        const gradeElement = ancestor.one('input.grade_input_'+graderId+',select#id_advancedgrading_grade_'+graderId);
        const tag = gradeElement.get('tagName');

        if(tag == "SELECT"){
            let gradeElements = jQuery(ancestor.getDOMNode()).find('select#id_advancedgrading_grade_'+graderId+',input.grade_hidden');
            //check if the computed grade matches one of the values in the select
            let selectedGrade = null;
            let prevGrade = null;
            let prevIntGrade = null;
            gradeElement.get("options").each( function() {
                if(selectedGrade){
                    return;
                }
                let value  = this.get('value');
                let intVal = parseInt(this.get('text'),10);
                if (isNaN(intVal)) {
                    intVal = parseInt(value,10);
                }
                if(intVal <= grade){
                    if(Math.abs(prevIntGrade-grade) < (grade - intVal)){
                        gradeElements.val(prevGrade);
                        selectedGrade = prevIntGrade;
                        return;
                    }
                    gradeElements.val(value);
                    selectedGrade = intVal;
                    return;
                }
                prevGrade = value;
                prevIntGrade = intVal;

            });
        }else {
            let grade_input = jQuery(ancestor.getDOMNode()).find('input.grade_input_'+graderId+',input.grade_hidden');
            let formula_point= (parseFloat(grade)*gradeRangeMax)/100;
            let grade_point= Math.round(formula_point);
            gradeElement.set("value",grade_point);
            grade_input.val(grade_point);
        }
    }
M.gradingform_multigraders.nl2br = function(str) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    const breakTag = '<br />';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}
M.gradingform_multigraders.isNumeric = function(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
}
M.gradingform_multigraders.eventFire = function(el, etype){
    if (el.fireEvent) {
        el.fireEvent('on' + etype);
    } else {
        let evObj = document.createEvent('Events');
        evObj.initEvent(etype, true, false);
        el.dispatchEvent(evObj);
    }
}