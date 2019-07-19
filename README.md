MultiGraders form written by Lucian Pricop <contact@lucianpricop.com>

based on Marking Guide grading form written by Dan Marsden <dan@danmarsden.com>

# How to install:

Add the multigrades archive to install plugin section of moodle Administration or Extract the "multigraders" folder from archive into /<your moodle folder>/grade/grading/form/
From admin dashboard, check for updates, approve the installation of the new plugin it discovers.

# How to activate:

When editing the settings of an assignment, open the grade section and for "Grading method" choose "Multiple graders".
When you save the assignment settings, you will be asked to create a form definition for this assignment. If this doesn't happen, you need to create one from Administration menu
section -> Assignment administration -> Advanced grading -> Edit multiple graders options. Some other themes may display an option "Edit multiple graders options" from the cog menu of an assignment.

When defining a new form definition, you are asked for:
 - a list of graders, these will be allowed to second-grade the assignment and will also receive notifications of where second grading was requested.
 - grading criteria : rich text displayed at the top of the advanced grading method as a guide to teacher on how/what to grade
 - the method of auto calculating the next grade and outcomes. So when a second grader arrives to grade, the outcomes and the grade is automatically set based on this method, they only need to change what they don't agree with:
   - last previous grade: copies the data from the previous grader
   - minimum previous grade: copies the minimum from all previous grades
   - maximum previous grade: copies the maximum from all previous grades
   - average previous grade: sets the average values from all previous grades
 - blind marking: if checked, graders don't see the grades from previous graders
 - show second graders notes to students: if checked students will see along with the final grade also the intermediary grades. (Every grader may choose wheter their notes are visible to students as well or only to other techers)

# How it works:

1. Select an assignment setup with Multiplte graders
2. First teacher that grades becomes "the first grader" which acts like an owner of that assignment/person grade. They are the ones to assign the final grade by checking the button/checkbox "publish grade".
3. After first teach is done grading, they need to use the checkboxes to decide:
 - if the notes they added should be visible to students or not
 - if they want to ask for seconds graders
 - if the grade is final or not
4. If they requested second grading, all teachers defined as second graders will receive a moodle notifications with a link to the Assignment
5. Second graders after adding their grade and notes may choose
 - if the notes they added should be visible to students or not
 - if they want to ask for seconds graders - this will effectively call to a third grader
6. If they requested second grading, all teachers defined as second graders will receive a moodle notifications with a link to the Assignment
7. The first grader can decide at all times the final grade and to publish the grade in the gradebook.
8. Any account can grade only once a certain assignment/user

# Other features

- The plugin takes care of outcomes as well and decides the grade based on the defined calculation from Gradebook setup. 
The assignment and outcomes need to be under the same category, the plugin takes the formula of the category total and applies it to assignment total. 
- Admins may delete all data related to a grade, all comments and grades of all graders.
- If a grade is locked or overridden the plugin loses control and can't make changes; if this is the case, the plugin will notify users. 




