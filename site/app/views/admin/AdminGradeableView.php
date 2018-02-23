<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\models\AdminGradeable;

class AdminGradeableView extends AbstractView {
    /**
     * The one and only function that shows the entire page
     */
	public function show_add_gradeable($type_of_action, AdminGradeable $admin_gradeable) {

        $action = "upload_new_gradeable"; //decides how the page's data is displayed
        $button_string = "Add";
        $extra = "";
        $have_old = false;
        $edit = json_encode($type_of_action === "edit");
        $gradeables_array = array();

        foreach ($admin_gradeable->getTemplateList() as $g_id_title) { //makes an array of gradeable ids for javascript
            array_push($gradeables_array, $g_id_title['g_id']);
        }
        $js_gradeables_array = json_encode($gradeables_array);

        // //if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $have_old = true;
            $action = "upload_edit_gradeable";
            $button_string = "Save changes to";
            $extra = ($admin_gradeable->getHasGrades()) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
        }

		$html_output = <<<HTML
		<style type="text/css">

    body {
        overflow: scroll;
    }

    select {
        margin-top:7px;
        width: 60px;
        min-width: 60px;
    }

    #container-rubric {
        width:1200px;
        margin:100px auto;
        margin-top: 130px;
        background-color: #fff;
        border: 1px solid #999;
        border: 1px solid rgba(0,0,0,0.3);
        -webkit-border-radius: 6px;
        -moz-border-radius: 6px;
        border-radius: 6px;outline: 0;
        -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -webkit-background-clip: padding-box;
        -moz-background-clip: padding-box;
        background-clip: padding-box;
        padding-top: 20px;
        padding-right: 20px;
        padding-left: 20px;
        padding-bottom: 20px;
    }

    .question-icon {
        display: block;
        float: left;
        margin-top: 5px;
        margin-left: 5px;
        position: relative;
        overflow: hidden;
    }

    .question-icon-cross {
        max-width: none;
        position: absolute;
        top:0;
        left:-313px;
    }

    .question-icon-up {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -290px;
    }

    .question-icon-down {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -313px;
    }

    .ui_tpicker_unit_hide {
        display: none;
    }
    
    /* align the radio, buttons and checkboxes with labels */
    input[type="radio"],input[type="checkbox"] {
        margin-top: -1px;
        vertical-align: middle;
    }
    
    fieldset {
        margin: 8px;
        border: 1px solid silver;
        padding: 8px;    
        border-radius: 4px;
    }
    
    legend{
        padding: 2px;  
        font-size: 12pt;
    }
        
</style>
<div id="container-rubric">
    <form id="gradeable-form" class="form-signin" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => $action))}" 
          method="post" enctype="multipart/form-data" onsubmit="return checkForm();"> 

        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$button_string} Gradeable {$extra}</h3>
HTML;
if ($type_of_action === "add" || $type_of_action === "add_template"){
  $html_output .= <<<HTML
            <div style="padding-left: 200px;">
                From Template: <select name="gradeable_template" style='width: 170px;' value=''>
            </div>
            <option>--None--</option>
HTML;

    foreach ($admin_gradeable->getTemplateList() as $g_id_title){
     $html_output .= <<<HTML
        <option 
HTML;
        if ($type_of_action === "add_template" && $admin_gradeable->getGId()===$g_id_title['g_id']) { $html_output .= "selected"; }
        $html_output .= <<<HTML
        value="{$g_id_title['g_id']}">{$g_id_title['g_title']}</option>
HTML;
    }
  $html_output .= <<<HTML
          </select>          
HTML;
}
  $html_output .= <<<HTML
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$button_string} Gradeable</button>
HTML;
    $html_output .= <<<HTML
        </div>

<div class="modal-body">
<b>Please Read: <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable">Submitty Instructions on "Create or Edit a Gradeable"</a></b>
</div>

		<div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
HTML;
if ($type_of_action === "edit"){
    $html_output .= <<<HTML
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px; background-color: #999999' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$admin_gradeable->getGId()}" placeholder="(Required)"/>
HTML;
}
else {
    $html_output .= <<<HTML
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$admin_gradeable->getGId()}" placeholder="(Required)" required/>
HTML;
}
        $html_output .= <<<HTML
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' id='gradeable_title_id' class="required" value="{$admin_gradeable->getGTitle()}" placeholder="(Required)" required/>
            <br />
            What is the URL to the assignment instructions? (shown to student) <input style='width: 227px' type='text' name='instructions_url' value="{$admin_gradeable->getGInstructionsUrl()}" placeholder="(Optional)" />
            <br />
            What is the <em style='color: orange;'><b>TA Beta Testing Date</b></em>? (gradeable visible to TAs):
            <input name="date_ta_view" id="date_ta_view" class="date_picker" type="text" value="{$admin_gradeable->getGTaViewStartDate()}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <br />
            <br /> 
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#types-of-gradeables">type of the gradeable</a>?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File"
HTML;
    if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===0) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===1) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===2) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable_type_options electronic_file" id="electronic_file" >
                <br />
                Is this a team assignment? <em style='color:green;'>Team assignments are new as of Fall 2017.  Email questions/bugs/feedback to: submitty@cs.rpi.edu.</em>
                <fieldset>
                    <input type="radio" id = "team_yes_radio" class="team_yes" name="team_assignment" value="true"
HTML;
                if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getEgTeamAssignment()) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > Yes
                    <input type="radio" id = "team_no_radio" class="team_no" name="team_assignment" value ="false"
HTML;
                if ((($type_of_action === "edit" || $type_of_action === "add_template") && !$admin_gradeable->getEgTeamAssignment()) || $type_of_action === "add") { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > No
                    <div class="team_assignment team_yes" id="team_yes">
                        <br />
                        What is the maximum team size? <input style="width: 50px" name="eg_max_team_size" class="int_val" type="text" value="{$admin_gradeable->getEgMaxTeamSize()}"/>
                        <br />
                        What is the <em style='color: orange;'><b>Team Lock Date</b></em>? (Instructors can still manually manage teams):
                        <input name="date_team_lock" id="date_team_lock" class="date_picker" type="text" value="{$admin_gradeable->getEgTeamLockDate()}"
                        style="cursor: auto; background-color: #FFF; width: 250px;">
                        <br />
                    </div>
                    <div class="team_assignment team_no" id="team_no"></div>
                </fieldset>      
                <br />
                What is the <em style='color: orange;'><b>Submission Open Date</b></em>? (submission available to students):
                <input id="date_submit" name="date_submit" class="date_picker" type="text" value="{$admin_gradeable->getEgSubmissionOpenDate()}"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= TA Beta Testing Date</em>
                <br />

                What is the <em style='color: orange;'><b>Due Date</b></em>?
                <input id="date_due" name="date_due" class="date_picker" type="text" value="{$admin_gradeable->getEgSubmissionDueDate()}"
                style="cursor: auto; background-color: #FFF; width: 250px;">
                <em style='color: orange;'>must be >= Submission Open Date</em>
                <br />

                How many late days may students use on this assignment? <input style="width: 50px" name="eg_late_days" class="int_val"
                                                                         type="text"/>
                <br /> <br />

                Are students uploading files or submitting to a Version Control System (VCS) repository?<br />
                <fieldset>

                    <input type="radio" id="upload_file_radio" class="upload_file" name="upload_type" value="upload_file"
HTML;
                    if ($admin_gradeable->getEgIsRepository() === false) { $html_output .= ' checked="checked"'; }

                $html_output .= <<<HTML
                    > Upload File(s)

                    <input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="repository"
HTML;
                    if ($admin_gradeable->getEgIsRepository() === true) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                    > Version Control System (VCS) Repository
                      
                    <div class="upload_type upload_file" id="upload_file"></div>
                     
                    <div class="upload_type upload_repo" id="repository">
                        <br />
                        <b>Path for the Version Control System (VCS) repository:</b><br />
                        VCS base URL: <kbd>{$admin_gradeable->getVcsBaseUrl()}</kbd><br />
                        The VCS base URL is configured in Course Settings. If there is a base URL, you can define the rest of the path below. If there is no base URL because the entire path changes for each assignment, you can input the full path below. If the entire URL is decided by the student, you can leave this input blank.<br />
                        You are allowed to use the following string replacement variables in format $&#123;&hellip;&#125;<br />
                        <ul style="list-style-position: inside;">
                            <li>gradeable_id</li>
                            <li>user_id OR team_id OR repo_id (only use one)</li>
                        </ul>
                        ex. <kbd>/&#123;&#36;gradeable_id&#125;/&#123;&#36;user_id&#125;</kbd> or <kbd>https://github.com/test-course/&#123;&#36;gradeable_id&#125;/&#123;&#36;repo_id&#125;</kbd><br />
                        <input style='width: 83%' type='text' name='subdirectory' value="" placeholder="(Optional)"/><br />
                        VCS URL: <kbd id="vcs_url"></kbd>
                        <br />
                    </div>
                    
                </fieldset>

		<br />
                <b>Full path to the directory containing the autograding config.json file:</b><br>
                See samples here: <a target=_blank href="https://github.com/Submitty/Tutorial/tree/master/examples">Submitty GitHub sample assignment configurations</a><br>
		<kbd>/usr/local/submitty/more_autograding_examples/upload_only/config</kbd>  (an assignment without autograding)<br>
		<kbd>/var/local/submitty/private_course_repositories/MY_COURSE_NAME/MY_HOMEWORK_NAME/</kbd> (for a custom autograded homework)<br>
		<kbd>/var/local/submitty/courses/{$_GET['semester']}/{$_GET['course']}/config_upload/#</kbd> (for an web uploaded configuration)<br>

                <input style='width: 83%' type='text' name='config_path' value="" class="required" placeholder="(Required)" />
                <br /> <br />

                Should students be able to view submissions?
                <fieldset>
                    <input type="radio" id="yes_student_view" name="student_view" value="true"
HTML;
                    if ($admin_gradeable->getEgStudentView()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="no_student_view" name="student_view" value="false"
HTML;
                    if ($admin_gradeable->getEgStudentView()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> No  &nbsp;&nbsp;&nbsp; (Select 'No' during grading of a bulk upload pdf quiz/exam.)

                    <div id="student_submit_download_view">

                        <br />
                        Should students be able to make submissions? (Select 'No' if this is a bulk upload pdf quiz/exam.)
                        <input type="radio" id="yes_student_submit" name="student_submit" value="true" 
HTML;
                        if ($admin_gradeable->getEgStudentSubmit()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Yes
                        <input type="radio" id="no_student_submit" name="student_submit" value="false"
HTML;
                        if ($admin_gradeable->getEgStudentSubmit()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> No 
                        <br /> <br />

                        Should students be able to download submitted files? (Select 'Yes' to allow download of uploaded pdf quiz/exam.)
                        <input type="radio" id="yes_student_download" name="student_download" value="true"
HTML;
                        if ($admin_gradeable->getEgStudentDownload()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Yes
                        <input type="radio" id="no_student_download" name="student_download" value="false"
HTML;
                        if ($admin_gradeable->getEgStudentDownload()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> No
                        <br /> <br />

                        Should students be able to view/download any version or just the active version ? (Select 'Active version only' if this is an uploaded pdf quiz/exam.)
                        <input type="radio" id="yes_student_any_version" name="student_any_version" value="true"
HTML;
                        if ($admin_gradeable->getEgStudentAnyVersion()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Any version
                        <input type="radio" id="no_student_any_version" name="student_any_version" value="false"
HTML;
                        if ($admin_gradeable->getEgStudentAnyVersion()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Active version only

                    </div>
                </fieldset>
                <br />

          Will any or all of this assignment be manually graded (e.g., by TAs or the instructor)?
                <input type="radio" id="yes_ta_grade" name="ta_grading" value="true" class="bool_val rubric_questions"
HTML;
                if ($admin_gradeable->getEgUseTaGrading()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                /> Yes
                <input type="radio" id="no_ta_grade" name="ta_grading" value="false"
HTML;
                if ($admin_gradeable->getEgUseTaGrading()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                /> No 
                <br /><br />
                
                <div id="rubric_questions" class="bool_val rubric_questions">
<!--
                Will this assignment have peer grading?
                <fieldset>
                    <input type="radio" id="peer_yes_radio" name="peer_grading" value="true" class="peer_yes"
HTML;
        $display_peer_checkboxes = "";
                    if(($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getEgPeerGrading()) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="peer_no_radio" name="peer_grading" value="false" class="peer_no"
HTML;
                    if ((($type_of_action === "edit" || $type_of_action === "add_template") && !$admin_gradeable->getEgPeerGrading()) || $type_of_action === "add") {
                        $html_output .= ' checked="checked"';
                        $display_peer_checkboxes = 'style="display:none"';
                    }
        $display_pdf_page_input = "";
        $html_output .= <<<HTML
                    /> No
                    <div class="peer_input" style="display:none;">
                        <br />
                        How many peers should each student grade?
                        <input style='width: 50px' type='text' name="peer_grade_set" value="{$admin_gradeable->getEgPeerGradeSet()}" class='int_val' />
                        <br />
                        How many points should be associated with a students completion of their grading?
                        <input style='width: 50px' type='text' name="peer_grade_complete_score" value="{$admin_gradeable->getPeerGradeCompleteScore()}" class='int_val' />
                    </div>
                </fieldset>
                <br /> -->

                Is this a PDF with a page assigned to each component?
                <fieldset>
                    <input type="radio" id="yes_pdf_page" name="pdf_page" value="true" 
HTML;
                    if ($admin_gradeable->getPdfPage()===true) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                    /> Yes
                    <input type="radio" id="no_pdf_page" name="pdf_page" value="false"
HTML;
                    if ($admin_gradeable->getPdfPage()===false) { 
                        $html_output .= ' checked="checked"';
                        $display_pdf_page_input = 'style="display:none"';
                    }
        $html_output .= <<<HTML
                    /> No 

                    <div id="pdf_page">
                        <br />
                        Who will assign pages to components?
                        <input type="radio" id="no_pdf_page_student" name="pdf_page_student" value="false"
HTML;
                        if ($admin_gradeable->getPdfPageStudent()===false) { $html_output .= ' checked="checked"'; }
        $html_output .= <<<HTML
                        /> Instructor
                        <input type="radio" id="yes_pdf_page_student" name="pdf_page_student" value="true"
HTML;
                        if ($admin_gradeable->getPdfPageStudent()===true) {
                            $html_output .= ' checked="checked"';
                            $display_pdf_page_input = 'style="display:none"';
                        }
        $html_output .= <<<HTML
                        /> Student
                    </div>

                </fieldset>
                <br />

                Point precision (for manual grading): 
                <input style='width: 50px' type='text' id="point_precision_id" name='point_precision' onchange="fixPointPrecision(this);" value="{$admin_gradeable->getEgPrecision()}" class="float_val" />
                <br /><br />
                
                <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                    <thead style="background: #E1E1E1;">
                        <tr>
                            <th>Manual/TA/Peer Grading Rubric</th>
                            <th style="width:210px;">Points</th>
                        </tr>
                    </thead>
                    <tbody style="background: #f9f9f9;">
HTML;
    

    $num = 1;
    foreach ($admin_gradeable->getOldComponents() as $question) {
        if($question->getOrder() == -1) continue;
        $html_output .= <<<HTML
            <tr class="rubric-row" id="row-{$num}">
HTML;
        $html_output .= <<<HTML
                <td style="overflow: hidden;">
                	<input type="hidden" name="component_id_{$num}" value="{$question->getId()}">
                	<input type="hidden" name="component_deleted_marks_{$num}" value="">
                    <textarea name="comment_title_{$num}" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px; height: auto;" 
                              placeholder="Rubric Item Title">{$question->getTitle()}</textarea>
                    <textarea name="ta_comment_{$num}" id="individual_{$num}" class="ta_comment complex_type" rows="1" placeholder=" Message to TA/Grader (seen only by TAs/Graders)"  onkeyup="autoResizeComment(event);"
                                               style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                                               display: block; height: auto;">{$question->getTaComment()}</textarea>
                    <textarea name="student_comment_{$num}" id="student_{$num}" class="student_comment complex_type" rows="1" placeholder=" Message to Student (seen by both students and graders)" onkeyup="autoResizeComment(event);"
                              style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; 
                              display: block; height: auto;">{$question->getStudentComment()}</textarea>
                    <div id="mark_questions_{$num}">
HTML;

        if(!($type_of_action === "edit" || $type_of_action === "add_template")) {
            $html_output .= <<<HTML
                <div id="mark_id-{$num}-0" name="mark_{$num}" data-gcm_id="NEW" class="gradeable_display">
                <input type="hidden" name="mark_gcmid_{$num}_0" value="NEW">
                <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" class="points2" name="mark_points_{$num}_0" value="0" step="{$admin_gradeable->getEgPrecision()}" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> 
                <textarea rows="1" placeholder="Comment" name="mark_text_{$num}_0" class="comment_display">Full Credit</textarea> 
                <!--
                <a onclick="moveMarkDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                <a onclick="moveMarkUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                -->
                <br> 
            </div>
HTML;
        }
        if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType() === 0 && $admin_gradeable->getEgUseTaGrading() === true) {
            $marks = $this->core->getQueries()->getGradeableComponentsMarks($question->getId());
            $first = true;
            $hide_icons = "";
            foreach ($marks as $mark) {
            	$first_publish = false;
            	if ($mark->getPublish()) {
            		$publish_checked = "checked";
            	} else {
            		$publish_checked = "";
            	}
                if($first === true) {
                    $first = false;
                    $hidden = "background-color:#EBEBE4";
                    $read_only = "readonly";
                    $hide_icons = "hidden";
                    $first_publish = true;
                    //$hidden = "display: none;";
                } else {
                    $hidden = "";
                    $read_only = "";
                    $hide_icons = "";
                }
                $html_output .= <<<HTML
                    <div id="mark_id-{$num}-{$mark->getOrder()}" name="mark_{$num}" data-gcm_id="{$mark->getId()}" class="gradeable_display" style="{$hidden}">
                    <input type="hidden" name="mark_gcmid_{$num}_{$mark->getOrder()}" value="{$mark->getId()}">
                    <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" onchange="fixMarkPointValue(this);" class="points2" name="mark_points_{$num}_{$mark->getOrder()}" value="{$mark->getPoints()}" step="{$admin_gradeable->getEgPrecision()}" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> 
                    <textarea rows="1" placeholder="Comment" name="mark_text_{$num}_{$mark->getOrder()}" class="comment_display">{$mark->getNote()}</textarea>
HTML;
				if($first_publish === false) {
					$html_output .= <<<HTML
						<input type="checkbox" name="mark_publish_{$num}_{$mark->getOrder()}" {$publish_checked}> Publish
HTML;
				}

                $html_output .= <<<HTML
                    <a onclick="deleteMark(this)" {$hide_icons}> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a>
                    <!-- 
                    <a onclick="moveMarkDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                    <a onclick="moveMarkUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> 
                    -->
                    <br> 
                </div>
HTML;
            }

        }
        $html_output .= <<<HTML
                    <div class="btn btn-xs btn-primary" id="rubric_add_mark_{$num}" onclick="addMark(this,{$num});" style="overflow: hidden; text-align: left;float: left;">Add Common Deduction/Addition</div></div>
                </td>

                <td style="background-color:#EEE;">
HTML;
        $old_lower_clamp = $question->getLowerClamp();
        $old_default = $question->getDefault();
        $old_max = $question->getMaxValue();
        $old_upper_clamp = $question->getUpperClamp();
        $extra_credit_yes = "";
        $extra_credit_no = "";
        $extra_credit_hidden = "";
        $extra_credit_points = 0;
        $penalty_yes = "";
        $penalty_no = "";
        $penalty_hidden = "";
        $grade_by_up = "";
        $grade_by_down = "";
        if ($type_of_action === "add") {
            $extra_credit_yes = "";
            $extra_credit_no = "checked";
            $extra_credit_hidden = "display: none;";
            $penalty_yes = "";
            $penalty_no = "checked";
            $penalty_hidden = "display: none;";
            $grade_by_up = "checked";
            $grade_by_down = "";
        } else {
            $old_lower_clamp = floatval($old_lower_clamp);
            $old_default = floatval($old_default);
            $old_max = floatval($old_max);
            $old_upper_clamp = floatval($old_upper_clamp);
            $extra_credit_points = $old_upper_clamp - $old_max;
            if ($old_upper_clamp > $old_max) {
                $extra_credit_yes = "checked";
                $extra_credit_no = "";
                $extra_credit_hidden = "";
            } else {
                $extra_credit_yes = "";
                $extra_credit_no = "checked";
                $extra_credit_hidden = "display: none;";
            }
            if ($old_lower_clamp < 0) {
                $penalty_yes = "checked";
                $penalty_no = "";
                $penalty_hidden = "";
            } else {
                $penalty_yes = "";
                $penalty_no = "checked";
                $penalty_hidden = "display: none;";
            }
            if ($old_default != 0) {
                $grade_by_up = "";
                $grade_by_down = "checked";
            } else {
                $grade_by_up = "checked";
                $grade_by_down = "";
            }
        }  
        $html_output .= <<<HTML
        Points: <input type="number" id="grade-{$num}" class="points" name="points_{$num}" value="{$old_max}" min="0" step="{$admin_gradeable->getEgPrecision()}" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:40px; resize:none;">
        <br>
        Extra Credit: 
        <input type="radio" id="rad_id_extra_credit_yes-{$num}" name="rad_extra_credit-{$num}" value="yes" data-question_num="{$num}" onclick="openExtra(this);" {$extra_credit_yes}> Yes 
        <input type="radio" id="rad_id_extra_credit_no-{$num}" name="rad_extra_credit-{$num}" value="no" data-question_num="{$num}" onclick="closeExtra(this);" {$extra_credit_no}> No 
        <div id="extra_credit_{$num}" style="{$extra_credit_hidden}">
            Extra Credit Points: <input type="number" class="points3" name="upper_{$num}" value="{$extra_credit_points}" min="0" step="{$admin_gradeable->getEgPrecision()}" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:40px; resize:none;">
        </div>
        Penalty: 
        <input type="radio" id="rad_id_penalty_yes-{$num}" name="rad_penalty-{$num}" value="yes" data-question_num="{$num}" onclick="openPenalty(this);" {$penalty_yes}> Yes 
        <input type="radio" id="rad_id_penalty_no-{$num}" name="rad_penalty-{$num}" value="no" data-question_num="{$num}" onclick="closePenalty(this);" {$penalty_no}> No 
        <div id="penalty_{$num}" style="{$penalty_hidden}">
            Penalty Points: <input type="number" class="points2" name="lower_{$num}" value="{$old_lower_clamp}" max="0" step="{$admin_gradeable->getEgPrecision()}" placeholder="±0.5" style="width:40px; resize:none;">
        </div>
        <br>
        <input type="radio" id="id_grade_by_up-{$num}" name="grade_by-{$num}" value="count_up" data-question_num="{$num}" onclick="onAddition(this);" {$grade_by_up}> Grade by count up 
        <br>
        <input type="radio" id="id_grade_by_down-{$num}" name="grade_by-{$num}" value="count_down" data-question_num="{$num}" onclick="onDeduction(this);" {$grade_by_down}> Grade by count down
        <br>         
HTML;

        $peer_checked = $question->getIsPeer() ? ' checked="checked"' : "";
        $pdf_page = $question->getPage();
        $pdf_page_display = 'style="display:none"';
        if ($pdf_page >= 0 && $admin_gradeable->getPdfPage()===true) {
            $pdf_page_display = "";
        }
        $html_output .= <<<HTML
            <div id="pdf_page_{$num}" class="pdf_page_input" {$pdf_page_display}>Page:&nbsp;&nbsp;<input type="number" name="page_component_{$num}" value="{$pdf_page}" class="page_component" max="1000" step="1" style="width:50px; resize:none;"/></div>
HTML;
        /*
        $html_output .= <<<HTML
                <div id="peer_checkbox_{$num}" class="peer_input" {$display_peer_checkboxes}>Peer Component:&nbsp;&nbsp;<input type="checkbox" name="peer_component_{$num}" value="on" class="peer_component" {$peer_checked} /></div>
                <div id="pdf_page_{$num}" class="pdf_page_input" {$display_pdf_page_input}>Page:&nbsp;&nbsp;<input type="number" name="page_component_{$num}" value={$pdf_page} class="page_component" max="1000" step="1" style="width:50px; resize:none;" /></div>
HTML;*/
        if ($num > 1){
        $html_output .= <<<HTML
                <!--
                <a id="delete-{$num}" class="question-icon" onclick="deleteQuestion({$num});">
                <i class="fa fa-times" aria-hidden="true"></i></a>
                <a id="down-{$num}" class="question-icon" onclick="moveQuestionDown({$num});">
                <i class="fa fa-arrow-down" aria-hidden="true"></i></a>       
                <a id="up-{$num}" class="question-icon" onclick="moveQuestionUp({$num});">
                <i class="fa fa-arrow-up" aria-hidden="true"></i></a>
                -->
HTML;
        }
        
        $html_output .= <<<HTML
                </td>
            </tr>
HTML;
        $num++;
    }
        $html_output .= <<<HTML
            <tr id="add-question">
                <td colspan="2" style="overflow: hidden; text-align: left;">
                    <div class="btn btn-small btn-success" id="rubric-add-button" onclick="addQuestion()"><i class="fa fa-plus-circle" aria-hidden="true"></i> Rubric Item</div>
                </td>
            </tr>
HTML;
        $html_output .= <<<HTML
                    <tr>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC; border-left: 1px solid #EEE;"><strong>TOTAL POINTS</strong></td>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"><strong id="totalCalculation"></strong></td>
                    </tr>
                </tbody>
            </table>
            </div>
HTML;
    $html_output .= <<<HTML
            </div>
            <div class="gradeable_type_options checkpoints" id="checkpoints">
                <br />
                <div class="multi-field-wrapper-checkpoints">
                  <table class="checkpoints-table table table-bordered" style=" border: 1px solid #AAA; max-width:50% !important;">
                        <!-- Headings -->
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                                <th> Extra Credit? </th>
                            </tr>
                        </thead>
                        <tbody style="background: #f9f9f9;">
                      
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="checkpoint_label_0" type="text" class="checkpoint_label" value="Checkpoint 0"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint_extra_0" class="checkpoint_extra extra" value="true" />
                           </td> 
                        </tr>
                      
                       <tr class="multi-field" id="mult-field-1">
                           <td>
                               <input style="width: 200px" name="checkpoint_label_1" type="text" class="checkpoint_label" value="Checkpoint 1"/> 
                           </td>     
                           <td>     
                                <input type="checkbox" name="checkpoint_extra_1" class="checkpoint_extra extra" value="true" />
                           </td> 
                        </tr>
                  </table>
                  <button type="button" id="add-checkpoint_field">Add </button>  
                  <button type="button" id="remove-checkpoint_field" id="remove-checkpoint" style="visibilty:hidden;">Remove</button>   
                </div> 
                <br />
                <!--Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="checkpoint_opt_ta_messg" value="yes" /> Yes
                <input type="radio" name="checkpoint_opt_ta_messg" value="no" /> No-->
            </div>
            <div class="gradeable_type_options numeric" id="numeric">
                <br />
                How many numeric items? <input style="width: 50px" id="numeric_num-items" name="num_numeric_items" type="text" value="0" class="int_val" onchange="calculateTotalScore();"/> 
                &emsp;&emsp;
                
                How many text items? <input style="width: 50px" id="numeric_num_text_items" name="num_text_items" type="text" value="0" class="int_val"/>
                <br /> <br />
                
                <div class="multi-field-wrapper-numeric">
                    <h5>Numeric Items</h5>
                    <table class="numerics-table table table-bordered" style=" border: 1px solid #AAA; max-width:50% !important;">
                        <!-- Headings -->
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                                <th> Max Score </th>
                                <th> Extra Credit?</th>
                            </tr>
                        </thead>
                        <!-- Footers -->
                        <tfoot style="background: #E1E1E1;">
                            <tr>
                                <td><strong> MAX SCORE </strong></td>
                                <td><strong id="totalScore"></strong></td>
                                <td><strong id="totalEC"></strong></td>
                            </tr>
                        </tfoot>
                        <tbody style="background: #f9f9f9;">
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="numeric_label_0" type="text" class="numeric_label" value="0" /> 
                           </td>  
                            <td>     
                                <input style="width: 60px" type="text" name="max_score_0" class="max_score" value="0" onchange="calculateTotalScore();"/> 
                           </td>                           
                           <td>     
                                <input type="checkbox" name="numeric_extra_0" class="numeric_extra extra" value="" onchange="calculateTotalScore();"/>
                           </td> 
                        </tr>
                    </table>
                    
                    <h5>Text Items</h5>
                    <table class="text-table table table-bordered" style=" border: 1px solid #AAA; max-width:25% !important;">
                        <thead style="background: #E1E1E1;">
                             <tr>
                                <th> Label </th>
                            </tr>
                        </thead>
                        <tbody style="background: #f9f9f9;">
                        <!-- This is a bit of a hack, but it works (^_^) -->
                        <tr class="multi-field" id="mult-field-0" style="display:none;">
                           <td>
                               <input style="width: 200px" name="text_label_0" type="text" class="text_label" value="0"/> 
                           </td>  
                        </tr>
                    </table>
                </div>  
                <br />
                <!--Do you want a box for an (optional) message from the TA to the student?
                <input type="radio" name="opt_ta_messg" value="yes" /> Yes
                <input type="radio" name="opt_ta_messg" value="no" /> No-->
            </div>  
            </fieldset>
            <div id="grading_questions">
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-user-groups">
	    lowest privileged user group</a> that can grade this?
            <select name="minimum_grading_group" class="int_val" style="width:180px;">
HTML;

    $grading_groups = array('1' => 'Instructor','2' => 'Full Access Grader','3' => 'Limited Access Grader');
    foreach ($grading_groups as $num => $role){
        $html_output .= <<<HTML
                <option value='{$num}'
HTML;
        ($admin_gradeable->getGMinGradingGroup() === $num)? $html_output .= 'selected':'';
        $html_output .= <<<HTML
            >{$role}</option>
HTML;
    }
    
    $html_output .= <<<HTML
            </select>
            <br />
            <div id="ta_instructions_id">
            What overall instructions should be provided to the TA?:<br /><textarea rows="4" cols="200" name="ta_instructions" placeholder="(Optional)" style="width: 500px;">
HTML;
    $tmp = htmlspecialchars($admin_gradeable->getGOverallTaInstructions());
    $html_output .= <<<HTML
{$tmp}
</textarea>
            </div>
            <br />
            <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#grading-by-registration-section-or-rotating-section">How should graders be assigned</a> to grade this item?:
            <br />
            <fieldset>
                <input type="radio" name="section_type" value="reg_section" id="registration-section"
HTML;
    ($admin_gradeable->getGGradeByRegistration()===true || $type_of_action === "add")? $html_output .= 'checked':'';
    $html_output .= <<<HTML
                /> Registration Section
                <input type="radio" name="section_type" value="rotating-section" id="rotating-section" class="graders"
HTML;
    ($admin_gradeable->getGGradeByRegistration()===false)? $html_output .= 'checked':'';
    $html_output .= <<<HTML
                /> Rotating Section
HTML;

if ($admin_gradeable->getNumSections() > 0) {
        $all_sections = str_replace(array('[', ']'), '',
            htmlspecialchars(json_encode(range(1,$admin_gradeable->getNumSections())), ENT_NOQUOTES));
    }
    else {
        $all_sections = "";
    }

    $graders_to_sections = array();

    foreach($admin_gradeable->getGradersAllSection() as $grader){
        //parses the data correctly
        $graders_to_sections[$grader['user_id']] = $grader['sections'];
        $graders_to_sections[$grader['user_id']] = ltrim($graders_to_sections[$grader['user_id']], '{');
        $graders_to_sections[$grader['user_id']] = rtrim($graders_to_sections[$grader['user_id']], "}");
    }

$html_output .= <<<HTML
  <div id="rotating-sections" class="graders" style="display:none; width: 1000px; overflow-x:scroll">
  <br />
  <table id="grader-history" style="border: 3px solid black; display:none;">
HTML;
$html_output .= <<<HTML
        <tr>
        <th></th>
HTML;
  foreach($admin_gradeable->getRotatingGradeables() as $row){
    $html_output .= <<< HTML
      <th style="padding: 8px; border: 3px solid black;">{$row['g_id']}</th>
HTML;
  }

  $html_output .= <<<HTML
        </tr>
        <tr>
HTML;
//display the appropriate graders for each user group 
function display_graders($graders, $have_old, $g_grade_by_registration, $graders_to_sections, $all_sections, &$html_output, $type_of_action){
    foreach($graders as $grader){
       $html_output .= <<<HTML
        <tr>
            <td>{$grader['user_id']}</td>
            <td><input style="width: 227px" type="text" name="grader_{$grader['user_id']}" class="grader" disabled value="
HTML;
        if(($have_old && !$g_grade_by_registration) || $type_of_action === "add_template") {
            $html_output .= (isset($graders_to_sections[$grader['user_id']])) ? $graders_to_sections[$grader['user_id']] : '';
        }
        else{
            $html_output .= $all_sections;
        }
        $html_output .= <<<HTML
"></td>
        </tr>
HTML;
    }
  }
  
  $last = '';
  foreach($admin_gradeable->getGradeableSectionHistory() as $row){
    $new_row = false;
    $u_group = $row['user_group'];
    if (strcmp($row['user_id'],$last) != 0){
      $new_row = true;
    }
    if($new_row){
      $html_output .= <<<HTML
          </tr>
          <tr class="g_history g_group_{$u_group}">     
          <th style="padding: 8px; border: 3px solid black;">{$row['user_id']}</th>
HTML;

    }
    //parses $sections correctly
    $sections = ($row['sections_rotating_id']);
    $sections = ltrim($sections, '{');
    $sections = rtrim($sections, "}");
    $html_output .= <<<HTML
          <td style="padding: 8px; border: 3px solid black; text-align: center;">{$sections}</td>      
HTML;
    $last = $row['user_id'];
  }

  $html_output .= <<<HTML
            </table>
        <br /> 
        Available rotating sections: {$admin_gradeable->getNumSections()}
        <br /> <br />
        <div id="instructor-graders">
        <table>
                <th>Instructor Graders</th>
HTML;
    display_graders($admin_gradeable->getGradersFromUsertypes()[0], $have_old, $admin_gradeable->getGGradeByRegistration(), $graders_to_sections, $all_sections, $html_output, $type_of_action);
    
  $html_output .= <<<HTML
        </table>
        </div>
        <br />
        <div id="full-access-graders" style="display:none;">
            <table>
                <th>Full Access Graders</th>
HTML;
    
  display_graders($admin_gradeable->getGradersFromUsertypes()[1], $have_old, $admin_gradeable->getGGradeByRegistration(), $graders_to_sections, $all_sections, $html_output, $type_of_action);
    
  $html_output .= <<<HTML
            </table>
HTML;

  $html_output .= <<<HTML
        </div>
        <div id="limited-access-graders" style="display:none;">
            <br />
            <table>
                <th>Limited Access Graders</th>
HTML;

  display_graders($admin_gradeable->getGradersFromUsertypes()[2], $have_old, $admin_gradeable->getGGradeByRegistration(), $graders_to_sections, $all_sections, $html_output, $type_of_action);    
  
    $html_output .= <<<HTML
        </table>

    </div> 
        <br />
    </div>
    </fieldset>
HTML;

    $html_output .= <<<HTML
            <!-- TODO default to the submission + late days for electronic -->
            What is the <em style='color: orange;'><b>Manual Grading Open Date</b></em>? (graders may begin grading)
            <input name="date_grade" id="date_grade" class="date_picker" type="text" value="{$admin_gradeable->getGGradeStartDate()}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
              <em style='color: orange;'>must be >= <span id="ta_grading_compare_date">Due Date (+ max allowed late days)</span></em>
            <br />
            </div>

            What is the <em style='color: orange;'><b>Grades Released Date</b></em>? (manual grades will be visible to students)
            <input name="date_released" id="date_released" class="date_picker" type="text" value="{$admin_gradeable->getGGradeReleasedDate()}"
            style="cursor: auto; background-color: #FFF; width: 250px;">
            <em style='color: orange;'>must be >= <span id="grades_released_compare_date">Due Date (+ max allowed late days) and Manual Grading Open Date</span></em>
            <br />
            
            What <a target=_blank href="http://submitty.org/instructor/rainbow_rainbow_grades">syllabus category</a> does this item belong to?:
            
            <select name="gradeable_buckets" style="width: 170px;">
HTML;

    $valid_assignment_type = array('homework','assignment','problem-set',
                                   'quiz','test','exam',
                                   'exercise','lecture-exercise','reading','lab','recitation', 
                                   'project',                                   
                                   'participation','note',
                                   'none (for practice only)');
    foreach ($valid_assignment_type as $type){
        $html_output .= <<<HTML
                <option value="{$type}"
HTML;
        ($admin_gradeable->getGSyllabusBucket() === $type)? $html_output .= 'selected':'';
        $title = ucwords($type);
        $html_output .= <<<HTML
                >{$title}</option>
HTML;
    }
    $html_output .= <<<HTML
            </select>
            <!-- When the form is completed and the "SAVE GRADEABLE" button is pushed
                If this is an electronic assignment:
                    Generate a new config/class.json
                    NOTE: similar to the current format with this new gradeable and all other electonic gradeables
                    Writes the inner contents for BUILD_csciXXXX.sh script
                    (probably can't do this due to security concerns) Run BUILD_csciXXXX.sh script
                If this is an edit of an existing AND there are existing grades this gradeable
                regenerates the grade reports. And possibly re-runs the generate grade summaries?
            -->
        <class="modal-footer">
                <button class="btn btn-primary" type="submit" style="margin-top: 10px; float: right;">{$button_string} Gradeable</button>
HTML;
    
    $html_output .= <<<HTML
        </div>
    </form>
</div>

<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" />
<link type='text/css' rel='stylesheet' href="http://trentrichardson.com/examples/timepicker/jquery-ui-timepicker-addon.css" />
<script type="text/javascript" language="javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript">

function createCrossBrowserJSDate(val){
        // Create a Date object that is cross-platform supported.
        // Safari's Date object constructor is more restrictive that Chrome and 
        // Firefox and will treat some dates as invalid.  Implementation details
        // vary by browser and JavaScript engine.
        // To solve this, we use Moment.js to standardize the parsing of the 
        // datetime string and convert it into a RFC2822 / IETF date format.
        //
        // For example, ""2013-05-12 20:00:00"" is converted with Moment into 
        // "Sun May 12 2013 00:00:00 GMT-0500 (EST)" and correctly parsed by
        // Safari, Chrome, and Firefox.
        //
        // Ref: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/parse
        // Ref: http://stackoverflow.com/questions/16616950/date-function-returning-invalid-date-in-safari-and-firefox
        var timeParseString = "YYYY-MM-DD HH:mm:ss.S" // Expected string format given by the server
        var momentDate = moment(val, timeParseString) // Parse raw datetime string
        return new Date(momentDate.toString()) // Convert moment into RFC2822 and construct browser-specific jQuery Date object
    }

    function calculateTotalScore(){
        var total_score = 0;
        var total_ec = 0;

        $('.numerics-table').find('.multi-field').each(function(){
            max_score = 0;
            extra_credit = false;

            max_score = parseFloat($(this).find('.max_score').val());
            extra_credit = $(this).find('.numeric_extra').is(':checked') == true;

            if (extra_credit === true) total_ec += max_score;
            else total_score += max_score;
        });

        $("#totalScore").html(total_score);
        $("#totalEC").html("(" + total_ec + ")");
    }

    $(document).ready(function() {
        $(function() {
            $( ".date_picker" ).datetimepicker({
                dateFormat: 'yy-mm-dd',
                timeFormat: "HH:mm:ssz",
                showButtonPanel: true,
                showTimezone: false,
                showMillisec: false,
                showMicrosec: false,
                beforeShow: function( input ) {
                    setTimeout(function() {
                        var buttonPane = $( input )
                            .datepicker( "widget" )
                            .find( ".ui-datepicker-buttonpane" );

                        $( "<button>", {
                            text: "Infinity",
                            click: function() {
                                $.datepicker._curInst.input.datepicker('setDate', "9999-12-31 23:59:59-0400").datepicker('hide');
                            }
                        }).appendTo( buttonPane ).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
                    }, 1 );
                }
            });
        });

        var numCheckpoints=1;
        
        function addCheckpoint(label, extra_credit){
            var wrapper = $('.checkpoints-table');
            ++numCheckpoints;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numCheckpoints).find('.checkpoint_label').val(label).focus();
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_label').attr('name','checkpoint_label_'+numCheckpoints);
            $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_extra').attr('name','checkpoint_extra_'+numCheckpoints);
            if(extra_credit){
                $('#mult-field-' + numCheckpoints,wrapper).find('.checkpoint_extra').attr('checked',true); 
            }
            $('#remove-checkpoint_field').show();
            $('#mult-field-' + numCheckpoints,wrapper).show();
        }
        
        function removeCheckpoint(){
            if (numCheckpoints > 0){
                $('#mult-field-'+numCheckpoints,'.checkpoints-table').remove();
                if(--numCheckpoints === 1){
                    $('#remove-checkpoint_field').hide();
                }
            }
        }
        
        $('.multi-field-wrapper-checkpoints').each(function() {
            $("#add-checkpoint_field", $(this)).click(function(e) {
                addCheckpoint('Checkpoint '+(numCheckpoints+1),false);
            });
            $('#remove-checkpoint_field').click(function() {
                removeCheckpoint();
            });
        });
        
        $('#remove-checkpoint_field').hide();

        var numNumeric=0;
        var numText=0;
        
        function addNumeric(label, max_score, extra_credit){
            var wrapper = $('.numerics-table');
            numNumeric++;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numNumeric).find('.numeric_label').val(label).focus();
            $('#mult-field-' + numNumeric,wrapper).find('.numeric_extra').attr('name','numeric_extra_'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.numeric_label').attr('name','numeric_label_'+numNumeric);
            $('#mult-field-' + numNumeric,wrapper).find('.max_score').attr('name','max_score_'+numNumeric).val(max_score);
            if(extra_credit){
                $('#mult-field-' + numNumeric,wrapper).find('.numeric_extra').attr('checked',true); 
            }
            $('#mult-field-' + numNumeric,wrapper).show();
            calculateTotalScore();
        }
        
        function removeNumeric(){
            if (numNumeric > 0){
                $('#mult-field-'+numNumeric,'.numerics-table').remove();
            }
            --numNumeric;
        }
        
        function addText(label){
            var wrapper = $('.text-table');
            numText++;
            $('#mult-field-0', wrapper).clone(true).appendTo(wrapper).attr('id','mult-field-'+numText).find('.text_label').val(label).focus();
            $('#mult-field-' + numText,wrapper).find('.text_label').attr('name','text_label_'+numText);
            $('#mult-field-' + numText,wrapper).show();
        }
        function removeText(){
            if (numText > 0){
               $('#mult-field-'+numText,'.text-table').remove(); 
            }
            --numText;
        }
        
        $('#numeric_num_text_items').on('input', function(e){
            var requestedText = this.value;
            if (isNaN(requestedText) || requestedText < 0){
               requestedText = 0;
            }
            while(numText < requestedText){
                addText('');   
            }
            while(numText > requestedText){
               removeText();
            }
        });

        $('#numeric_num-items').on('input',function(e){
           var requestedNumeric = this.value;
           if (isNaN(requestedNumeric) || requestedNumeric < 0){
               requestedNumeric = 0;
           }
           while(numNumeric < requestedNumeric){
                addNumeric(numNumeric+1,0,false);   
           }
           while(numNumeric > requestedNumeric){
               removeNumeric();
           }
        });

        function showHistory(val){
          $('#grader-history').show();
          // hide all rows in history
          $('.g_history').hide();
          // show relevant rows
          for (var i=1; i<=parseInt(val); ++i){
              $('.g_group_'+i).show();
          }
        }

        function showGroups(val){
            var graders = ['','instructor-graders','full-access-graders', 'limited-access-graders']; 
            for(var i=parseInt(val)+1; i<graders.length; ++i){
                $('#'+graders[i]+' :input').prop('disabled',true);
                $('#'+graders[i]).hide();
            }
            for(var i=1; i <= parseInt(val) ; ++i){
                $('#'+graders[i]).show();
                $('#'+graders[i]+' :input').prop('disabled',false);
            }

            // show specific groups
            showHistory(val);
        }
        
        showGroups($('select[name="minimum_grading_group"] option:selected').attr('value'));
        
        $('select[name="minimum_grading_group"]').change(
        function(){
            showGroups(this.value);
        });

        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
        }

        if ($('input:radio[name="student_view"]:checked').attr('value') === 'false') {
            $('#no_student_submit').prop('checked', true);
            $('#no_student_download').prop('checked',true);
            $('#yes_student_any_version').prop('checked',true);
            $('#student_submit_download_view').hide();
        }

        if ($('input:radio[name="upload_type"]:checked').attr('value') === 'upload_file') {
            $('#repository').hide();
        }

        if ($('input:radio[name="pdf_page"]:checked').attr('value') === 'false') {
            $("input[name^='page_component']").each(function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
            $('#pdf_page').hide();
        }

        if ($('input:radio[name="pdf_page_student"]:checked').attr('value') === 'true') {
            $("input[name^='page_component']").each(function() {
                if (this.value < -1) {
                    this.value = -1;
                }
            });
            $('.pdf_page_input').hide();
        }

        $('.gradeable_type_options').hide();
        
        if ($('input[name="gradeable_type"]').is(':checked')){
            $('input[name="gradeable_type"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }

        if ($('input[name="team_assignment"]').is(':checked')){
            $('input[name="team_assignment"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }

        $( "input" ).change(function() {
           var max = parseFloat($(this).attr('max'));
           var skip1 = (isNaN(max)) ? true : false;
           var min = parseFloat($(this).attr('min'));
           var skip2 = (isNaN(min)) ? true : false;
           if (!skip1 && $(this).val() > max)
           {
              $(this).val(max);
           }
           else if (!skip2 && $(this).val() < min)
           {
              $(this).val(min);
           }       
         }); 
          
        $('input:radio[name="ta_grading"]').change(function(){
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                    $('#grading_questions').show();
                    $('#ta_instructions_id').hide();
                    $('#grades_released_compare_date').html('Manual Grading Open Date');
                } else {
                    $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                }
            }
        });

        $('input:radio[name="peer_grading"]').change(function() {
            $('.peer_input').hide();
            $('#peer_averaging_scheme').hide();
            if ($(this).is(':checked')) {
                if($(this).val() == 'true') {
                    $('.peer_input').show();
                    $('#peer_averaging_scheme').show();
                    if($('#team_yes_radio').is(':checked')) {
                        $('#team_yes_radio').prop('checked', false);
                        $('#team_no_radio').prop('checked', true);
                        $('input:radio[name="team_assignment"]').trigger("change");
                    }
                }
            }
        });

        $('input:radio[name="ta_grading"]').change(function(){
            $('#rubric_questions').hide();
            $('#grading_questions').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'true'){ 
                    $('#rubric_questions').show();
                    $('#grading_questions').show();
                    $('#ta_instructions_id').hide();
                    $('#grades_released_compare_date').html('Manual Grading Open Date');
                } else {
                    $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                }
            }
        });

        $('input:radio[name="student_view"]').change(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'true') {
                    $('#student_submit_download_view').show();
                } else {
                    $('#no_student_submit').prop('checked', true);
                    $('#no_student_download').prop('checked',true);
                    $('#yes_student_any_version').prop('checked',true);
                    $('#student_submit_download_view').hide();
                }
            }
        });

        $('input:radio[name="upload_type"]').change(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'repository') {
                    $('#repository').show();
                } else {
                    $('#repository').hide();
                }
            }
        });

        $('input:radio[name="pdf_page"]').change(function() {
            $("input[name^='page_component']").each(function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
            $('.pdf_page_input').hide();
            $('#pdf_page').hide();
            if ($(this).is(':checked')) {
                if ($(this).val() == 'true') {
                    $("input[name^='page_component']").each(function() {
                        if (this.value < 1) {
                            this.value = 1;
                        }
                    });
                    $('.pdf_page_input').show();
                    $('#pdf_page').show();
                }
            }
        });

        $('input:radio[name="pdf_page_student"]').change(function() {
            $("input[name^='page_component']").each(function() {
                if (this.value < -1) {
                    this.value = -1;
                }
            });
            $('.pdf_page_input').hide();
            if ($(this).is(':checked')) {
                if ($(this).val() == 'false') {
                    $("input[name^='page_component']").each(function() {
                        if (this.value < 1) {
                            this.value = 1;
                        }
                    });
                    $('.pdf_page_input').show();
                }
            }
        });
        
        $('[name="gradeable_template"]').change(
        function(){
            var arrayUrlParts = [];
            arrayUrlParts["component"] = ["admin"];
            arrayUrlParts["page"] = ["admin_gradeable"];
            arrayUrlParts["action"] = ["upload_new_template"];
            arrayUrlParts["template_id"] = [this.value];

            var new_url = buildUrl(arrayUrlParts);
            window.location.href = new_url;
        });
        
        if({$admin_gradeable->getDefaultLateDays()} != -1){
            $('input[name="eg_late_days"]').val('{$admin_gradeable->getDefaultLateDays()}');
        }
        
        if($('#radio_electronic_file').is(':checked')){ 
            
            $('input[name="subdirectory"]').val('{$admin_gradeable->getEgSubdirectory()}');
            $('input[name="config_path"]').val('{$admin_gradeable->getEgConfigPath()}');
            $('input[name="eg_late_days"]').val('{$admin_gradeable->getEgLateDays()}');
            $('input[name="point_precision"]').val('{$admin_gradeable->getEgPrecision()}');
            $('#ta_instructions_id').hide();
            
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic_file').show();
            
            if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                $('#rubric_questions').hide();
                $('#grading_questions').hide();
            }

            if($('#team_yes_radio').is(':checked')){
                $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
                $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
                $('#team_yes').show();
            }
            else {
                $('#team_yes').hide();
            }
        }
        else if ($('#radio_checkpoints').is(':checked')){
            var components = {$admin_gradeable->getOldComponentsJson()};
            // remove the default checkpoint
            removeCheckpoint(); 
            $.each(components, function(i,elem){
                var extra_credit = false;
                if (elem.gc_max_value == 0) extra_credit = true;
                addCheckpoint(elem.gc_title, extra_credit);
            });
            $('#checkpoints').show();
            $('#grading_questions').show();
        }
        else if ($('#radio_numeric').is(':checked')){ 
            var components = {$admin_gradeable->getOldComponentsJson()};
            $.each(components, function(i,elem){
                if(i < {$admin_gradeable->getNumNumeric()}){
                    var extra_credit = false;
                    if (elem.gc_upper_clamp > elem.gc_max_value){
                        addNumeric(elem.gc_title,elem.gc_upper_clamp,true);
                    }
                    else{
                        addNumeric(elem.gc_title,elem.gc_max_value,false);
                    }
                }
                else{
                    addText(elem.gc_title);
                }
            });
            $('#numeric_num-items').val({$admin_gradeable->getNumNumeric()});
            $('#numeric_num_text_items').val({$admin_gradeable->getNumText()});
            $('#numeric').show();
            $('#grading_questions').show();
        }
        if({$edit}){
            $('input[name="gradeable_id"]').attr('readonly', true);
        }

        $('input:radio[name="team_assignment"]').change(
    function(){
        if($('#team_yes_radio').is(':checked')){
            $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
            $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
            $('#team_yes').show();
            if($('#peer_yes_radio').is(':checked')) {
                $('#peer_yes_radio').prop('checked', false);
                $('#peer_no_radio').prop('checked', true);
                $('input:radio[name="peer_grading"]').trigger("change");
            }
        }
        else {
            $('#team_yes').hide();
        }
    });

         $('input:radio[name="gradeable_type"]').change(
    function(){
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();
                $('#ta_instructions_id').hide();
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                    $('#rubric_questions').hide();
                    $('#grading_questions').hide();
                }

                $('#ta_grading_compare_date').html('Due Date (+ max allowed late days)');
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                   $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                } else { 
                   $('#grades_released_compare_date').html('Manual Grading Open Date');
                }

                if($('#team_yes_radio').is(':checked')){
                    $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
                    $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
                    $('#team_yes').show();
                }
                else {
                    $('#team_yes').hide();
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#ta_instructions_id').show();
                $('#checkpoints').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#ta_instructions_id').show();
                $('#numeric').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
        }
    });

       if($('#rotating-section').is(':checked')){
            $('#rotating-sections').show();
        }
        $('input:radio[name="section_type"]').change(
        function(){
            $('#rotating-sections').hide();
            if ($(this).is(':checked')){
                if($(this).val() == 'rotating-section'){ 
                    $('#rotating-sections').show();
                }
            }
        });

    });

$('#gradeable-form').on('submit', function(e){
         $('<input />').attr('type', 'hidden')
            .attr('name', 'gradeableJSON')
            .attr('value', JSON.stringify($('form').serializeObject()))
            .appendTo('#gradeable-form');
         if ($("input[name='section_type']:checked").val() == 'reg_section'){
            $('#rotating-sections :input').prop('disabled',true);
         }
});

 $.fn.serializeObject = function(){
        var o = {};
        var a = this.serializeArray();
        var ignore = ["numeric_label_0", "max_score_0", "numeric_extra_0", "numeric_extra_0",
                       "text_label_0", "checkpoint_label_0", "num_numeric_items", "num_text_items"];

        $('.ignore').each(function(){
            ignore.push($(this).attr('name'));
        });
        
        // export appropriate users 
        if ($('[name="minimum_grading_group"]').prop('value') == 1){
          $('#full-access-graders').find('.grader').each(function(){
                      ignore.push($(this).attr('name'));
          });
        }

        if ($('[name="minimum_grading_group"]').prop('value') <= 2){
          $('#limited-access-graders').find('.grader').each(function(){
                      ignore.push($(this).attr('name'));
          });
        }
        
        $(':radio').each(function(){
           if(! $(this).is(':checked')){
               if($(this).attr('class') !== undefined){
                  // now remove all of the child elements names for the radio button
                  $('.' + $(this).attr('class')).find('input, textarea, select').each(function(){
                      ignore.push($(this).attr('name'));
                  });
               }
           } 
        }); 
        
        //parse checkpoints 
        
        $('.checkpoints-table').find('.multi-field').each(function(){
            var label = '';
            var extra_credit = false;
            var skip = false;
            
            $(this).find('.checkpoint_label').each(function(){
               label = $(this).val();
               if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
            });
            
            if (skip){
                return;
            }
            
            $(this).find('.checkpoint_extra').each(function(){
                extra_credit = $(this).attr('checked') === 'checked';
                ignore.push($(this).attr('name'));
            });
            
            if (o['checkpoints'] === undefined){
                o['checkpoints'] = [];
            }
            o['checkpoints'].push({"label": label, "extra_credit": extra_credit});
        });
        
        
        // parse text items
        
        $('.text-table').find('.multi-field').each(function(){
           var label = '';
           var skip = false;
           
           $(this).find('.text_label').each(function(){
                label = $(this).val();
                if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
           });
           
           if (skip){
              return;
           }
           
           if (o['text_questions'] === undefined){
               o['text_questions'] = [];
           }
           o['text_questions'].push({'label' : label});
        });
        
        // parse numeric items
                
        $('.numerics-table').find('.multi-field').each(function(){
            var label = '';  
            var max_score = 0;
            var extra_credit = false;
            var skip = false;
            
            $(this).find('.numeric_label').each(function(){
               label = $(this).val();
               if ($.inArray($(this).attr('name'),ignore) !== -1){
                   skip = true;
               }
               ignore.push($(this).attr('name'));
            });

            if (skip){
                return;
            }
            
            $(this).find('.max_score').each(function(){
               max_score = parseFloat($(this).val());
               ignore.push($(this).attr('name'));
            });

            $(this).find('.numeric_extra').each(function(){
                extra_credit = $(this).attr('checked') === 'checked';
                ignore.push($(this).attr('name'));
            });

            if (o['numeric_questions'] === undefined){
                o['numeric_questions'] = [];
            }
            o['numeric_questions'].push({"label": label, "max_score": max_score, "extra_credit": extra_credit});
           
        });
        
        
        $.each(a, function() {
            if($.inArray(this.name,ignore) !== -1) {
                return;
            }
            var val = this.value;
            if($("[name="+this.name+"]").hasClass('int_val')){
                val = parseInt(val);
            }
            else if($("[name="+this.name+"]").hasClass('float_val')){
                val = parseFloat(val);
            }

            else if($("[name="+this.name+"]").hasClass('bool_val')){
                val = (this.value === 'true');
            }
           
            if($("[name="+this.name+"]").hasClass('grader')){
                var tmp = this.name.split('_');
                var grader = tmp[1];
                if (o['grader'] === undefined){
                    o['grader'] = [];
                }
                var arr = {};
                arr[grader] = this.value.trim();
                o['grader'].push(arr);
            }
            else if ($("[name="+this.name+"]").hasClass('points')){
                if (o['points'] === undefined){
                    o['points'] = [];
                }
                o['points'].push(parseFloat(this.value));
            }
            else if($("[name="+this.name+"]").hasClass('complex_type')){
                var classes = $("[name="+this.name+"]").closest('.complex_type').prop('class').split(" ");
                classes.splice( classes.indexOf('complex_type'), 1);
                var complex_type = classes[0];
                
                if (o[complex_type] === undefined){
                    o[complex_type] = [];
                }
                o[complex_type].push(val);
            } 
            else if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(val || '');
            } else {
                o[this.name] = val || '';
            }
        });
        return o;
    };

    function toggleQuestion(question, role) {
        if(document.getElementById(role +"_" + question ).style.display == "block") {
            $("#" + role + "_" + question ).animate({marginBottom:"-80px"});
            setTimeout(function(){document.getElementById(role + "_"+ question ).style.display = "none";}, 175);
        }
        else {
            $("#" + role + "_" + question ).animate({marginBottom:"5px"});
            setTimeout(function(){document.getElementById(role+"_" + question ).style.display = "block";}, 175);
        }
        calculatePercentageTotal();
    }

     // autoresize the comment
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    function selectBox(question){
        var step = $('#point_precision_id').val();
        // should be the increment value
        return '<input type="number" id="grade-'+question+'" class="points" name="points_' + question +'" value="0" max="1000" step="'+step+'" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:50px; resize:none;">';
    }

    function openExtra(me) {
        $('#extra_credit_' + me.dataset.question_num)[0].style.display = '';
        calculatePercentageTotal();
    }

    function closeExtra(me) {
        $('#extra_credit_' + me.dataset.question_num)[0].style.display = 'none';
        calculatePercentageTotal();
    }

    function openPenalty(me) {
        $('#penalty_' + me.dataset.question_num)[0].style.display = '';
    }

    function closePenalty(me) {
        $('#penalty_' + me.dataset.question_num)[0].style.display = 'none';
    }

    function fixPointPrecision(me) {
        var step = $(me).val();
        var index = 1;
        var exists = true;
        while(exists){
            if($("#grade-"+index).length) {
                $("#grade-"+index).attr('step', step);
                $("#extra_credit_"+index).find('input[name=upper_'+index+']').attr('step', step);
                $("#penalty_"+index).find('input[name=lower_'+index+']').attr('step', step);
                var exists2 = ($('#mark_id-'+index+'-0').length) ? true : false;
                var index2 = 0;
                while (exists2) {
                    $('#mark_id-'+index+'-'+index2).find('input[name=mark_points_'+index+'_'+index2+']').attr('step', step);
                    index2++;
                    exists2 = ($('#mark_id-'+index+'-'+index2).length) ? true : false;
                }
            }
            else {
                exists = false;
            }
            index++;
        }
    }

    function fixMarkPointValue(me) {
        var max = parseFloat($(me).attr('max'));
        var min = parseFloat($(me).attr('min'));
        var current_value = parseFloat($(me).val());
        if (current_value > max) {
            $(me).val(max);
        } else if (current_value < min) {
            $(me).val(min);
        }
    }

    function calculatePercentageTotal() {
        var total = 0;
        var ec = 0;
        $('input.points').each(function(){
            if ($(this).val() > 0){
                total += +($(this).val());
            }
        });
        $('input.points3').each(function() {
            var num = ($(this).attr('name').split('_')[1]);
            if ($('input[name=rad_extra_credit-'+num+']:radio:checked').val() === 'yes') {
                if ($(this).val() > 0) {
                    ec += +($(this).val());
                }
            }
        });
        document.getElementById("totalCalculation").innerHTML = total + " (" + ec + ")";
    }

    function updateMarkIds(elem, old_id, new_id) {
        elem.find('div[name=mark_'+old_id+']').each(function () {
            var mark_id = $(this).attr('id');
            var question_id = mark_id.split('-')[1];
            var current_id = mark_id.split('-')[2];
            $(this).attr('name', 'mark_' + new_id);
            $(this).attr('id', 'mark_id-'+new_id+'-'+current_id+'');
            $(this).find('input[name=mark_points_'+old_id+'_'+current_id+']').attr('name', 'mark_points_'+new_id+'_'+current_id);
            $(this).find('textarea[name=mark_text_'+old_id+'_'+current_id+']').attr('name', 'mark_text_'+new_id+'_'+current_id);
        });
    }

    function deleteQuestion(question) {
        if (question <= 0) {
            return;
        }
        var row = $('tr#row-'+ question);
        row.remove();
        var totalQ = parseInt($('.rubric-row').last().attr('id').split('-')[1]);
        for(var i=question+1; i<= totalQ; ++i){
            updateRow(i,i-1);
        }
        calculatePercentageTotal();
    }

    function updateRow(oldNum, newNum) {
        var row = $('tr#row-'+ oldNum);
        row.attr('id', 'row-' + newNum);
        row.find('textarea[name=comment_title_' + oldNum + ']').attr('name', 'comment_title_' + newNum);
        row.find('div.btn').attr('onclick', 'toggleQuestion(' + newNum + ',"individual"' + ')');
        row.find('textarea[name=ta_comment_' + oldNum + ']').attr('name', 'ta_comment_' + newNum).attr('id', 'individual_' + newNum);
        row.find('textarea[name=student_comment_' + oldNum + ']').attr('name', 'student_comment_' + newNum).attr('id', 'student_' + newNum);
        row.find('input[name=points_' + oldNum + ']').attr({
            name: 'points_' + newNum,
            id: 'grade-' + newNum
        });
        row.find('input[name=eg_extra_' + oldNum + ']').attr('name', 'eg_extra_' + newNum);
        row.find('div[id=peer_checkbox_' + oldNum +']').attr('id', 'peer_checkbox_' + newNum);
        row.find('input[name=peer_component_'+ oldNum + ']').attr('name', 'peer_component_' + newNum);
        row.find('div[id=pdf_page_' + oldNum +']').attr('id', 'pdf_page_' + newNum);
        row.find('input[name=page_component_' + oldNum + ']').attr('name', 'page_component_' + newNum);
        row.find('a[id=delete-' + oldNum + ']').attr('id', 'delete-' + newNum).attr('onclick', 'deleteQuestion(' + newNum + ')');
        row.find('a[id=down-' + oldNum + ']').attr('id', 'down-' + newNum).attr('onclick', 'moveQuestionDown(' + newNum + ')');
        row.find('a[id=up-' + oldNum + ']').attr('id', 'up-' + newNum).attr('onclick', 'moveQuestionUp(' + newNum + ')');
        row.find('input[id=rad_id_extra_credit_yes-' + oldNum + ']').attr({
            id: 'rad_id_extra_credit_yes-' + newNum,
            name: 'rad_extra_credit-' + newNum,
            'data-question_num': newNum
        });
        row.find('input[id=rad_id_extra_credit_no-' + oldNum + ']').attr({
            id: 'rad_id_extra_credit_no-' + newNum,
            name: 'rad_extra_credit-' + newNum,
            'data-question_num': newNum
        });
        row.find('div[id=extra_credit_' + oldNum + ']').attr('id','extra_credit_' + newNum);
        row.find('input[name=upper_' + oldNum + ']').attr('name', 'upper_' + newNum);
        row.find('input[id=rad_id_penalty_yes-' + oldNum + ']').attr({
            id: 'rad_id_penalty_yes-' + newNum,
            name: 'rad_penalty-' + newNum,
            'data-question_num': newNum
        });
        row.find('input[id=rad_id_penalty_no-' + oldNum + ']').attr({
            id: 'rad_id_penalty_no-' + newNum,
            name: 'rad_penalty-' + newNum,
            'data-question_num': newNum
        });
        row.find('div[id=penalty_' + oldNum + ']').attr('id', 'penalty_'+ newNum);
        row.find('input[name=lower_' + oldNum + ']').attr('name', 'lower_' + newNum);
        row.find('input[id=id_grade_by_up-' + oldNum + ']').attr({
            id: 'id_grade_by_up-' + newNum,
            name: 'grade_by-' + newNum,
            'data-question_num': newNum
        });
        row.find('input[id=id_grade_by_down-' + oldNum + ']').attr({
            id: 'id_grade_by_down-' + newNum,
            name: 'grade_by-' + newNum,
            'data-question_num': newNum
        });
        row.find('div[id=mark_questions_'+oldNum+']').attr('id', 'mark_questions_'+newNum);
        row.find('div[id=rubric_add_mark_' + oldNum + ']').attr('id','rubric_add_mark_' + newNum).attr('onclick', 'addMark(this,' + newNum + ')'); 
        updateMarkIds(row,oldNum,newNum);
    }

    function moveQuestionDown(question) {
        if (question < 1) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question+1));
        var child = 0;
        if (question == 1) {
            child = 1;
        }
        var new_question = parseInt(question) + 1;

        if(!newRow.length) {
            return false;
        }

        //Move Question title
        var temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[0].children[0].value;
        newRow.children()[0].children[0].value = temp;

        //Move Ta Comment
        temp = currentRow.children()[child].children[1].value;
        currentRow.children()[child].children[1].value = newRow.children()[0].children[1].value;
        newRow.children()[0].children[1].value = temp;

        //Move Student Comment
        temp = currentRow.children()[child].children[2].value;
        currentRow.children()[child].children[2].value = newRow.children()[0].children[2].value;
        newRow.children()[0].children[2].value = temp;

        child += 1;

        //Move points
        temp = currentRow.find('input[name=points_' + question +']').val();
        currentRow.find('input[name=points_' + question +']').val(newRow.find('input[name=points_' + new_question +']').val());
        newRow.find('input[name=points_' + new_question +']').val(temp);

        //Move extra credit box
        temp = currentRow.find('input[name=upper_' + question +']').val();
        currentRow.find('input[name=upper_' + question +']').val(newRow.find('input[name=upper_' + new_question +']').val());
        newRow.find('input[name=upper_' + new_question +']').val(temp);

        //Move penalty box
        temp = currentRow.find('input[name=lower_' + question +']').val();
        currentRow.find('input[name=lower_' + question +']').val(newRow.find('input[name=lower_' + new_question +']').val());
        newRow.find('input[name=lower_' + new_question +']').val(temp);

        //Move peer grading box
        temp = currentRow.find('input[name=peer_component_' + question +']')[0].checked;
        currentRow.find('input[name=peer_component_' + question +']')[0].checked = newRow.find('input[name=peer_component_' + new_question +']')[0].checked;
        newRow.find('input[name=peer_component_' + new_question +']')[0].checked = temp;

        //Move the radio buttons
        temp1 = $('#rad_id_extra_credit_yes-' + question)[0].checked;
        temp2 = $('#rad_id_extra_credit_no-' + question)[0].checked;
        temp3 = $('#rad_id_penalty_yes-' + question)[0].checked;
        temp4 = $('#rad_id_penalty_no-' + question)[0].checked;
        temp5 = $('#id_grade_by_up-' + question)[0].checked;
        temp6 = $('#id_grade_by_down-' + question)[0].checked;
        $('#rad_id_extra_credit_yes-' + question)[0].checked = $('#rad_id_extra_credit_yes-' + new_question)[0].checked;
        $('#rad_id_extra_credit_no-' + question)[0].checked = $('#rad_id_extra_credit_no-' + new_question)[0].checked;
        $('#rad_id_penalty_yes-' + question)[0].checked = $('#rad_id_penalty_yes-' + new_question)[0].checked;
        $('#rad_id_penalty_no-' + question)[0].checked = $('#rad_id_penalty_no-' + new_question)[0].checked;
        $('#id_grade_by_up-' + question)[0].checked = $('#id_grade_by_up-' + new_question)[0].checked;
        $('#id_grade_by_down-' + question)[0].checked = $('#id_grade_by_down-' + new_question)[0].checked;
        $('#rad_id_extra_credit_yes-' + new_question)[0].checked = temp1;
        $('#rad_id_extra_credit_no-' + new_question)[0].checked = temp2;
        $('#rad_id_penalty_yes-' + new_question)[0].checked = temp3;
        $('#rad_id_penalty_no-' + new_question)[0].checked = temp4;
        $('#id_grade_by_up-' + new_question)[0].checked = temp5;
        $('#id_grade_by_down-' + new_question)[0].checked = temp6;

        //open and closes the right radio button's boxes
        if($('#rad_id_extra_credit_yes-' + question)[0].checked) {
            $('#rad_id_extra_credit_yes-' + question).trigger("onclick");
        } else {
            $('#rad_id_extra_credit_no-' + question).trigger("onclick");
        }
        if ($('#rad_id_penalty_yes-' + question)[0].checked) {
            $('#rad_id_penalty_yes-' + question).trigger("onclick");
        } else {
            $('#rad_id_penalty_no-' + question).trigger("onclick");
        }
        if ($('#id_grade_by_up-' + question)[0].checked) {
            $('#id_grade_by_up-' + question).trigger("onclick");
        } else {
            $('#id_grade_by_down-' + question).trigger("onclick");
        }
        if($('#rad_id_extra_credit_yes-' + new_question)[0].checked) {
            $('#rad_id_extra_credit_yes-' + new_question).trigger("onclick");
        } else {
            $('#rad_id_extra_credit_no-' + new_question).trigger("onclick");
        }
        if ($('#rad_id_penalty_yes-' + new_question)[0].checked) {
            $('#rad_id_penalty_yes-' + new_question).trigger("onclick");
        } else {
            $('#rad_id_penalty_no-' + new_question).trigger("onclick");
        }
        if ($('#id_grade_by_up-' + new_question)[0].checked) {
            $('#id_grade_by_up-' + new_question).trigger("onclick");
        } else {
            $('#id_grade_by_down-' + new_question).trigger("onclick");
        }

        //stores the point and text data so it can readded; the html earses it once moved
        var current_mark_points = [];
        var current_mark_texts = [];
        currentRow.find('div[name=mark_'+question+']').each(function () {
            current_mark_points.push($(this).find("input").val());
            current_mark_texts.push($(this).find("textarea").val());
        });
        var new_mark_points = [];
        var new_mark_texts = [];
        newRow.find('div[name=mark_'+new_question+']').each(function () {
            new_mark_points.push($(this).find("input").val());
            new_mark_texts.push($(this).find("textarea").val());
        });

        //switchs the html between the table rows
        var temp_html = currentRow.find('div[id=mark_questions_'+question+']').html();
        currentRow.find('div[id=mark_questions_'+question+']').html(newRow.find('div[id=mark_questions_'+new_question+']').html());
        newRow.find('div[id=mark_questions_'+new_question+']').html(temp_html);

        //fixes the ids once switched
        currentRow.find('div[id=rubric_add_mark_' + new_question + ']').attr('id','rubric_add_mark_' + question).attr('onclick', 'addMark(this,' + question + ')'); 
        updateMarkIds(currentRow,new_question,question);
        newRow.find('div[id=rubric_add_mark_' + question + ']').attr('id','rubric_add_mark_' + new_question).attr('onclick', 'addMark(this,' + new_question + ')'); 
        updateMarkIds(newRow,question,new_question);

        //readds the data
        currentRow.find('div[name=mark_'+question+']').each(function (index) {
            $(this).find("input").val(new_mark_points[index]);
            $(this).find("textarea").val(new_mark_texts[index]);
        });
        newRow.find('div[name=mark_'+new_question+']').each(function (index) {
            $(this).find("input").val(current_mark_points[index]);
            $(this).find("textarea").val(current_mark_texts[index]);
        });
    }

    function moveQuestionUp(question) {
        if (question < 1) {
            return;
        }

        var currentRow = $('tr#row-' + question);
        var newRow = $('tr#row-' + (question-1));
        var child = 0;
        var new_question = parseInt(question) - 1;

        //Move Question title
        var temp = currentRow.children()[0].children[0].value; 
        currentRow.children()[0].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        //Move Ta Comment
        temp = currentRow.children()[0].children[1].value; 
        currentRow.children()[0].children[1].value = newRow.children()[child].children[1].value;
        newRow.children()[child].children[1].value = temp;

        //Move Student Comment
        temp = currentRow.children()[0].children[2].value; 
        currentRow.children()[0].children[2].value = newRow.children()[child].children[2].value;
        newRow.children()[child].children[2].value = temp;

        child += 1;

        //Move points
        temp = currentRow.find('input[name=points_' + question +']').val();
        currentRow.find('input[name=points_' + question +']').val(newRow.find('input[name=points_' + new_question +']').val());
        newRow.find('input[name=points_' + new_question +']').val(temp);

        //Move extra credit box
        temp = currentRow.find('input[name=upper_' + question +']').val();
        currentRow.find('input[name=upper_' + question +']').val(newRow.find('input[name=upper_' + new_question +']').val());
        newRow.find('input[name=upper_' + new_question +']').val(temp);

        //Move penalty box
        temp = currentRow.find('input[name=lower_' + question +']').val();
        currentRow.find('input[name=lower_' + question +']').val(newRow.find('input[name=lower_' + new_question +']').val());
        newRow.find('input[name=lower_' + new_question +']').val(temp);

        //Move peer grading box
        temp = currentRow.find('input[name=peer_component_' + question +']')[0].checked;
        currentRow.find('input[name=peer_component_' + question +']')[0].checked = newRow.find('input[name=peer_component_' + (question-1) +']')[0].checked;
        newRow.find('input[name=peer_component_' + (question-1) +']')[0].checked = temp;

        //Move the radio buttons
        temp1 = $('#rad_id_extra_credit_yes-' + question)[0].checked;
        temp2 = $('#rad_id_extra_credit_no-' + question)[0].checked;
        temp3 = $('#rad_id_penalty_yes-' + question)[0].checked;
        temp4 = $('#rad_id_penalty_no-' + question)[0].checked;
        temp5 = $('#id_grade_by_up-' + question)[0].checked;
        temp6 = $('#id_grade_by_down-' + question)[0].checked;
        $('#rad_id_extra_credit_yes-' + question)[0].checked = $('#rad_id_extra_credit_yes-' + new_question)[0].checked;
        $('#rad_id_extra_credit_no-' + question)[0].checked = $('#rad_id_extra_credit_no-' + new_question)[0].checked;
        $('#rad_id_penalty_yes-' + question)[0].checked = $('#rad_id_penalty_yes-' + new_question)[0].checked;
        $('#rad_id_penalty_no-' + question)[0].checked = $('#rad_id_penalty_no-' + new_question)[0].checked;
        $('#id_grade_by_up-' + question)[0].checked = $('#id_grade_by_up-' + new_question)[0].checked;
        $('#id_grade_by_down-' + question)[0].checked = $('#id_grade_by_down-' + new_question)[0].checked;
        $('#rad_id_extra_credit_yes-' + new_question)[0].checked = temp1;
        $('#rad_id_extra_credit_no-' + new_question)[0].checked = temp2;
        $('#rad_id_penalty_yes-' + new_question)[0].checked = temp3;
        $('#rad_id_penalty_no-' + new_question)[0].checked = temp4;
        $('#id_grade_by_up-' + new_question)[0].checked = temp5;
        $('#id_grade_by_down-' + new_question)[0].checked = temp6;

        //open and closes the right radio button's boxes
        if($('#rad_id_extra_credit_yes-' + question)[0].checked) {
            $('#rad_id_extra_credit_yes-' + question).trigger("onclick");
        } else {
            $('#rad_id_extra_credit_no-' + question).trigger("onclick");
        }
        if ($('#rad_id_penalty_yes-' + question)[0].checked) {
            $('#rad_id_penalty_yes-' + question).trigger("onclick");
        } else {
            $('#rad_id_penalty_no-' + question).trigger("onclick");
        }
        if ($('#id_grade_by_up-' + question)[0].checked) {
            $('#id_grade_by_up-' + question).trigger("onclick");
        } else {
            $('#id_grade_by_down-' + question).trigger("onclick");
        }
        if($('#rad_id_extra_credit_yes-' + new_question)[0].checked) {
            $('#rad_id_extra_credit_yes-' + new_question).trigger("onclick");
        } else {
            $('#rad_id_extra_credit_no-' + new_question).trigger("onclick");
        }
        if ($('#rad_id_penalty_yes-' + new_question)[0].checked) {
            $('#rad_id_penalty_yes-' + new_question).trigger("onclick");
        } else {
            $('#rad_id_penalty_no-' + new_question).trigger("onclick");
        }
        if ($('#id_grade_by_up-' + new_question)[0].checked) {
            $('#id_grade_by_up-' + new_question).trigger("onclick");
        } else {
            $('#id_grade_by_down-' + new_question).trigger("onclick");
        }

        //stores the point and text data so it can readded; the html earses it once moved
        var current_mark_points = [];
        var current_mark_texts = [];
        currentRow.find('div[name=mark_'+question+']').each(function () {
            current_mark_points.push($(this).find("input").val());
            current_mark_texts.push($(this).find("textarea").val());
        });
        var new_mark_points = [];
        var new_mark_texts = [];
        newRow.find('div[name=mark_'+(question-1)+']').each(function () {
            new_mark_points.push($(this).find("input").val());
            new_mark_texts.push($(this).find("textarea").val());
        });

        //switchs the html between the table rows
        var temp_html = currentRow.find('div[id=mark_questions_'+question+']').html();
        currentRow.find('div[id=mark_questions_'+question+']').html(newRow.find('div[id=mark_questions_'+(question-1)+']').html());
        newRow.find('div[id=mark_questions_'+(question-1)+']').html(temp_html);

        //fixes the ids once switched
        currentRow.find('div[id=rubric_add_mark_' + (question-1) + ']').attr('id','rubric_add_mark_' + question).attr('onclick', 'addMark(this,' + question + ')'); 
        updateMarkIds(currentRow,(question-1),question);
        newRow.find('div[id=rubric_add_mark_' + question + ']').attr('id','rubric_add_mark_' + (question-1)).attr('onclick', 'addMark(this,' + (question-1) + ')'); 
        updateMarkIds(newRow,question,(question-1));

        //readds the data
        currentRow.find('div[name=mark_'+question+']').each(function (index) {
            $(this).find("input").val(new_mark_points[index]);
            $(this).find("textarea").val(new_mark_texts[index]);
        });
        newRow.find('div[name=mark_'+(question-1)+']').each(function (index) {
            $(this).find("input").val(current_mark_points[index]);
            $(this).find("textarea").val(current_mark_texts[index]);
        });
    }

    function addQuestion(){
        //get the last question number
        var num = parseInt($('.rubric-row').last().attr('id').split('-')[1]);
        var newQ = num+1;
        var sBox = selectBox(newQ);
        var display = "";
        var step = $('#point_precision_id').val();
        if($('input[id=peer_no_radio]').is(':checked')) {
            display = 'style="display:none"';
        }
        //Please do not add any characters after the \ including spaces!
        var displayPage = "";
        if($('input[id=peer_no_radio]').is(':checked')) {
            display = 'style="display:none"';
        }
        if($('input[id=no_pdf_page]').is(':checked') || $('input[id=yes_pdf_page_student]').is(':checked')) {
            displayPage = 'style="display:none"';
        }
        $('#row-'+num).after('<tr class="rubric-row" id="row-'+newQ+'"> \
            <td style="overflow: hidden; border-top: 5px solid #dddddd;"> \
            <input type="hidden" name="component_id_'+newQ+'" value="NEW"> \
            <input type="hidden" name="component_deleted_marks_'+newQ+'" value=""> \
                <textarea name="comment_title_'+newQ+'" rows="1" class="comment_title complex_type" style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px; height: auto;" placeholder="Rubric Item Title"></textarea> \
                <textarea name="ta_comment_'+newQ+'" id="individual_'+newQ+'" rows="1" class="ta_comment complex_type" placeholder=" Message to TA/Grader (seen only by TAs/Graders)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; height: auto;"></textarea> \
                <textarea name="student_comment_'+newQ+'" id="student_'+newQ+'" rows="1" class="student_comment complex_type" placeholder=" Message to Student (seen by both students and graders)"  onkeyup="autoResizeComment(event);" \
                          style="width: 99%; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; height: auto;"></textarea> \
                <div id=mark_questions_'+newQ+'> \
                <div class="btn btn-xs btn-primary" id="rubric_add_mark_'+newQ+'" onclick="addMark(this,'+newQ+')" style="overflow: hidden; text-align: left;float: left;">Add Common Deduction/Addition</div> </div> \
            </td> \
            <td style="background-color:#EEE; border-top: 5px solid #dddddd;"> \
            Points: <input type="number" id="grade-'+newQ+'" class="points" name="points_'+newQ+'" value="0" min="0" step="'+step+'" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:40px; resize:none;"> \
            <br> \
            Extra Credit: \
            <input type="radio" id="rad_id_extra_credit_yes-'+newQ+'" name="rad_extra_credit-'+newQ+'" value="yes" data-question_num="'+newQ+'" onclick="openExtra(this);"> Yes \
            <input type="radio" id="rad_id_extra_credit_no-'+newQ+'" name="rad_extra_credit-'+newQ+'" value="no" data-question_num="'+newQ+'" onclick="closeExtra(this);" checked> No \
            <div id="extra_credit_'+newQ+'" style="display: none;"> \
                Extra Credit Points: <input type="number" class="points3" name="upper_'+newQ+'" value="0" min="0" step="'+step+'" placeholder="±0.5" onchange="calculatePercentageTotal();" style="width:40px; resize:none;"> \
            </div> \
            Penalty:  \
            <input type="radio" id="rad_id_penalty_yes-'+newQ+'" name="rad_penalty-'+newQ+'" value="yes" data-question_num="'+newQ+'" onclick="openPenalty(this);"> Yes \
            <input type="radio" id="rad_id_penalty_no-'+newQ+'" name="rad_penalty-'+newQ+'" value="no" data-question_num="'+newQ+'" onclick="closePenalty(this);" checked> No \
            <div id="penalty_'+newQ+'" style="display: none;"> \
                Penalty Points: <input type="number" class="points2" name="lower_'+newQ+'" value="0" max="0" step="'+step+'" placeholder="±0.5" style="width:40px; resize:none;"> \
            </div> \
            <br> \
            <input type="radio" id="id_grade_by_up-'+newQ+'" name="grade_by-'+newQ+'" value="count_up" data-question_num="'+newQ+'" onclick="onAddition(this);" checked> Grade by count up \
            <br> \
            <input type="radio" id="id_grade_by_down-'+newQ+'" name="grade_by-'+newQ+'" value="count_down" data-question_num="'+newQ+'" onclick="onDeduction(this);"> Grade by count down \
                <br /> \
                <!--\
                <div id="peer_checkbox_'+newQ+'" class="peer_input" '+display+'>Peer Component:&nbsp;&nbsp;<input type="checkbox" name="peer_component_'+newQ+'" value="on" class="peer_component" /></div> \
                -->\
                <div id="pdf_page_'+newQ+'" class="pdf_page_input" '+displayPage+'>Page:&nbsp;&nbsp;<input type="number" name="page_component_'+newQ+'" value="1" class="page_component" max="1000" step="1" style="width:50px; resize:none;"/></div> \
                <!--\
                <a id="delete-'+newQ+'" class="question-icon" onclick="deleteQuestion('+newQ+');"> \
                    <i class="fa fa-times" aria-hidden="true"></i></a> \
                <a id="down-'+newQ+'" class="question-icon" onclick="moveQuestionDown('+newQ+');"> \
                    <i class="fa fa-arrow-down" aria-hidden="true"></i></a> \
                <a id="up-'+newQ+'" class="question-icon" onclick="moveQuestionUp('+newQ+');"> \
                    <i class="fa fa-arrow-up" aria-hidden="true"></i></a> \
                -->\
            </td> \
        </tr>');
        $("#rubric_add_mark_" + newQ).before(' \
            <div id="mark_id-'+newQ+'-0" name="mark_'+newQ+'" class="gradeable_display" style="background-color:#EBEBE4"> \
            <i class="fa fa-circle" aria-hidden="true"></i> <input type="number" class="points2" name="mark_points_'+newQ+'_0" data-gcm_id="NEW" value="0" step="0.5" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> \
            <input type="hidden" name="mark_gcmid_'+newQ+'_0" value="NEW"> \
            <textarea rows="1" placeholder="Comment" name="mark_text_'+newQ+'_0" class="comment_display">No Credit</textarea> \
            <a onclick="deleteMark(this)" hidden> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            <!--\
            <a onclick="moveMarkDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            <a onclick="moveMarkUp(this)"> <i class="fa fa-arrow-up" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
            -->\
            <br> \
        </div> \
            ');
    }

    function deleteMark(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        var current_row = $('#mark_id-'+question_id+'-'+current_id);
        var gcm_id = $('[name=mark_gcmid_'+question_id+'_'+current_id+']').attr("value");
        current_row.remove();
        var last_mark = $('[name=mark_'+question_id+']').last().attr('id');
        var totalD = -1;
        if (last_mark == null) {
            totalD = -1;
        } 
        else {
            totalD = parseInt($('[name=mark_'+question_id+']').last().attr('id').split('-')[2]);
        }

        var deleted_marks = $('[name=component_deleted_marks_'+question_id+']').attr("value");
        if(deleted_marks == "") {
        	deleted_marks = gcm_id;
        } else {
        	deleted_marks = deleted_marks + "," + gcm_id;
        }
        $('[name=component_deleted_marks_'+question_id+']').attr("value",deleted_marks);


        current_id = parseInt(current_id);
        for(var i=current_id+1; i<= totalD; ++i){
            updateMark(i,i-1, question_id);
        }
    }

    function updateMark(old_num, new_num, question_num) {
        var current_mark = $('#mark_id-'+question_num+'-'+old_num);
        current_mark.find('input[name=mark_gcmid_'+question_num+'_'+old_num+']').attr('name', 'mark_gcmid_'+question_num+'_'+new_num);
        current_mark.find('input[name=mark_points_'+question_num+'_'+old_num+']').attr('name', 'mark_points_'+question_num+'_'+new_num);
        current_mark.find('textarea[name=mark_text_'+question_num+'_'+old_num+']').attr('name', 'mark_text_'+question_num+'_'+new_num);
        current_mark.attr('id', 'mark_id-'+question_num+'-'+new_num);
    }

    function moveMarkDown(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        current_id = parseInt(current_id);
        //checks if the element exists
        if (!($('#mark_id-'+question_id+'-'+(current_id+1)).length)) {
            return false;
        }
        var current_row = $('#mark_id-'+question_id+'-'+current_id);
        var current_textarea_value = current_row.find("textarea").val();
        var current_input_value = current_row.find("input").val();

        var new_row = $('#mark_id-'+question_id+'-'+(current_id+1));
        var new_textarea_value = new_row.find("textarea").val();
        var new_input_value = new_row.find("input").val();

        var temp_textarea_value = new_textarea_value;
        var temp_input_value = new_input_value;

        new_row.find("textarea").val(current_textarea_value);
        new_row.find("input").val(current_input_value);

        current_row.find("textarea").val(temp_textarea_value);
        current_row.find("input").val(temp_input_value);
    }

    function moveMarkUp(me) {
        var question_id = me.parentElement.id.split('-')[1];
        var current_id = me.parentElement.id.split('-')[2];
        current_id = parseInt(current_id);
        if (current_id == 0 || current_id == 1) {
            return false;
        }
        var current_row = $('#mark_id-'+question_id+'-'+current_id);
        var current_textarea_value = current_row.find("textarea").val();
        var current_input_value = current_row.find("input").val();

        var new_row = $('#mark_id-'+question_id+'-'+(current_id-1));
        var new_textarea_value = new_row.find("textarea").val();
        var new_input_value = new_row.find("input").val();

        var temp_textarea_value = new_textarea_value;
        var temp_input_value = new_input_value;

        new_row.find("textarea").val(current_textarea_value);
        new_row.find("input").val(current_input_value);

        current_row.find("textarea").val(temp_textarea_value);
        current_row.find("input").val(temp_input_value);
    }

     function onDeduction(me) {
        var current_row = $(me.parentElement.parentElement);
        var current_question = parseInt(current_row.attr('id').split('-')[1]);
        current_row.find('textarea[name=mark_text_'+current_question+'_0]').val('Full Credit');
    }

    function onAddition(me) {
        var current_row = $(me.parentElement.parentElement);
        var current_question = parseInt(current_row.attr('id').split('-')[1]);
        current_row.find('textarea[name=mark_text_'+current_question+'_0]').val('No Credit');
    }

    function addMark(me, num){
        var last_num = -10;
        var current_row = $(me.parentElement.parentElement.parentElement);
        var lower_clamp = current_row.find('input[name=lower_'+num+']').val();
        var mydefault = current_row.find('input[name=default_'+num+']').val(); //default is a keyword
        var upper_clamp = current_row.find('input[name=upper_'+num+']').val();

        var precision = $('#point_precision_id').val();

        var current = $('[name=mark_'+num+']').last().attr('id');
        if (current == null) {
            last_num = -1;
        } 
        else {
            last_num = parseInt($('[name=mark_'+num+']').last().attr('id').split('-')[2]);
        }
        var new_num = last_num + 1;
        $("#rubric_add_mark_" + num).before('\
<div id="mark_id-'+num+'-'+new_num+'" name="mark_'+num+'" data-gcm_id="NEW" class="gradeable_display">\
<input type="hidden" name="mark_gcmid_'+num+'_'+new_num+'" value="NEW"> \
<i class="fa fa-circle" aria-hidden="true"></i> <input onchange="fixMarkPointValue(this);" type="number" class="points2" name="mark_points_'+num+'_'+new_num+'" value="0" step="'+precision+'" placeholder="±0.5" style="width:50px; resize:none; margin: 5px;"> \
<textarea rows="1" placeholder="Comment" name="mark_text_'+num+'_'+new_num+'" class="comment_display"></textarea> \
<input type="checkbox" name="mark_publish_'+num+'_'+new_num+'"> Publish \
<a onclick="deleteMark(this)"> <i class="fa fa-times" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
<!--\
<a onclick="moveMarkDown(this)"> <i class="fa fa-arrow-down" aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
<a onclick="moveMarkUp(this)"> <i class="fa fa-arrow-up"  aria-hidden="true" style="font-size: 16px; margin: 5px;"></i></a> \
-->\
<br> \
</div>');
    }

    $('input:radio[name="gradeable_type"]').change(function() {
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                    $('#rubric_questions').hide();
                    $('#grading_questions').hide();
                }

                $('#ta_grading_compare_date').html('Due Date (+ max allowed late days)');
                if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'false') {
                   $('#grades_released_compare_date').html('Due Date (+ max allowed late days)');
                } else { 
                   $('#grades_released_compare_date').html('Manual Grading Open Date');
                }

                if($('#team_yes_radio').is(':checked')){
                    $('input[name=eg_max_team_size]').val('{$admin_gradeable->getEgMaxTeamSize()}');
                    $('input[name=date_team_lock]').val('{$admin_gradeable->getEgTeamLockDate()}');
                    $('#team_yes').show();
                }
                else {
                    $('#team_yes').hide();
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#checkpoints').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#numeric').show();
                $('#grading_questions').show();
                $('#ta_grading_compare_date').html('TA Beta Testing Date');
                $('#grades_released_compare_date').html('Manual Grading Open Date');
            }
        }
    });

    var vcs_base_url = "{$admin_gradeable->getVcsBaseUrl()}";
    function setVcsUrl(subdirectory) {
        if (subdirectory.indexOf('://') > -1 || subdirectory[0] == '/') {
            $('#vcs_url').text(subdirectory);
        }
        else {
            $('#vcs_url').text(vcs_base_url.replace(/[\/]+$/g, '') + '/' + subdirectory);
        }
    }
    
    $(function () {
        $('input[name="subdirectory"]').on('change paste keyup', function() {
            setVcsUrl(this.value);
        });
        setVcsUrl($('input[name="subdirectory"]').val());
        $("#alert-message").dialog({
            modal: true,
            autoOpen: false,
            buttons: {
                Ok: function () {
                     $(this).dialog("close");
                 }
             }
         });
    });

    function checkForm() {
        var gradeable_id = $('#gradeable_id').val();
        var gradeable_title = $('gradeable_title_id').val();
        var date_submit = Date.parse($('#date_submit').val());
        var date_due = Date.parse($('#date_due').val());
        var date_ta_view = Date.parse($('#date_ta_view').val());
        var date_grade = Date.parse($('#date_grade').val());
        var date_released = Date.parse($('#date_released').val());
        var vcs_url = $('#vcs_url').text();
        var subdirectory = $('input[name="subdirectory"]').val();
        var config_path = $('input[name=config_path]').val();
        var has_space = gradeable_id.includes(" ");
        var test = /^[a-zA-Z0-9_-]*$/.test(gradeable_id);
        var unique_gradeable = false;
        var bad_max_score = false;
        var check1 = $('#radio_electronic_file').is(':checked');
        var check2 = $('#radio_checkpoints').is(':checked');
        var check3 = $('#radio_numeric').is(':checked');
        var checkRegister = $('#registration-section').is(':checked');
        var checkRotate = $('#rotating-section').is(':checked');
        var all_gradeable_ids = $js_gradeables_array;
        if($('#peer_yes_radio').is(':checked')) {
            var found_peer_component = false;
            var found_reg_component = false;
            $("input[name^='peer_component']").each(function() {
                console.log(this);
                if (this.checked) {
                    found_peer_component = true;
                }
                else {
                    found_reg_component = true;
                }
            });
            if (!found_peer_component) {
                alert("At least one component must be for peer_grading");
                return false;
            }
            if (!found_reg_component) {
                alert("At least one component must be for manual grading");
                return false;
            }
        }
        if($('#yes_pdf_page').is(':checked') && $('#no_pdf_page_student').is(':checked')) {
            var invalid = false;
            $("input[name^='page_component']").each(function() {
                if (this.value < 1) {
                    alert("Page number for component cannot be less than 1");
                    invalid = true;
                }
            });
            if (invalid) {
                return false;
            }
        }
        else {
            var invalid = false;
            $("input[name^='page_component']").each(function() {
                if (this.value < -1) {
                    alert("Page number for component cannot be less than -1");
                    invalid = true;
                }
            });
            if (invalid) {
                return false;
            }
        }
        // return false;
        if($('#team_yes_radio').is(':checked')) {
            if ($("input[name^='eg_max_team_size']").val() < 2) {
                alert("Maximum team size must be at least 2");
                return false;
            }
        }
        if (!($edit)) {
            var x;
            for (x = 0; x < all_gradeable_ids.length; x++) {
                if (all_gradeable_ids[x] === gradeable_id) {
                    alert("Gradeable already exists");
                    return false;
                }
            }
        }
        if (!test || has_space || gradeable_id == "" || gradeable_id === null) {
            $( "#alert-message" ).dialog( "open" );
            return false;
        }
        if(check1) {
            if(date_submit < date_ta_view) {
                alert("DATE CONSISTENCY:  Submission Open Date must be >= TA Beta Testing Date");
                return false;
            }   
            if(date_due < date_submit) {
                alert("DATE CONSISTENCY:  Due Date must be >= Submission Open Date");
                return false;
            }
            if ($('input:radio[name="upload_type"]:checked').attr('value') === 'repository') {
                var subdirectory_parts = subdirectory.split("{");
                var x=0;
                // if this is a vcs path extension, make sure it starts with '/'
                console.log(vcs_url);
                if (vcs_url.indexOf('://') === -1 && vcs_url[0] !== "/") {
                    alert("VCS path needs to either be a URL or start with a /");
                    return false;
                }
                // check that path is made up of valid variables
                var allowed_variables = ["\$gradeable_id", "\$user_id", "\$team_id", "\$repo_id"];
                var used_id = false;
                for (x = 1; x < subdirectory_parts.length; x++) {
                    subdirectory_part = subdirectory_parts[x].substring(0, subdirectory_parts[x].lastIndexOf("}"));
                    if (allowed_variables.indexOf(subdirectory_part) === -1) {
                        alert("For the VCS path, '" + subdirectory_part + "' is not a valid variable name.")
                        return false;
                    }
                    if (!used_id && ((subdirectory_part === "\$user_id") || (subdirectory_part === "\$team_id") || (subdirectory_part === "\$repo_id")))  {
                        used_id = true;
                        continue;
                    }
                    if (used_id && ((subdirectory_part === "\$user_id") || (subdirectory_part === "\$team_id") || (subdirectory_part === "\$repo_id"))) {
                        alert("You can only use one of \$user_id, \$team_id and \$repo_id in VCS path");
                        return false;
                    }
                }
                
            }
            if(config_path == "" || config_path === null) {
                alert("The config path should not be empty");
                return false;
            }
            // if view false while either submit or download true
            if ($('input:radio[name="student_view"]:checked').attr('value') === 'false' &&
               ($('input:radio[name="student_submit"]:checked').attr('value') === 'true' ||
                $('input:radio[name="student_download"]:checked').attr('value') === 'true')) {
                alert("Student_view cannot be false while student_submit or student_download is true");
                return false;
            }
        }
        if ($('input:radio[name="ta_grading"]:checked').attr('value') === 'true') {
            if(date_grade < date_due) {
                alert("DATE CONSISTENCY:  Manual Grading Open Date must be >= Due Date (+ max allowed late days)");
                return false;
            }
            if(date_released < date_due) {
                alert("DATE CONSISTENCY:  Grades Released Date must be >= Manual Grading Open Date");
                return false;
            }
        }
        else {
            if(check1) {
                if(date_released < date_due) {
                    alert("DATE CONSISTENCY:  Grades Released Date must be >= Due Date (+ max allowed late days)");
                    return false;
                }
            }
        }
        if($('input:radio[name="ta_grading"]:checked').attr('value') === 'true' || check2 || check3) {
            if(date_grade < date_ta_view) {
                alert("DATE CONSISTENCY:  Manual Grading Open Date must be >= TA Beta Testing Date");
                return false;
            }
            if(date_released < date_grade) {
                alert("DATE CONSISTENCY:  Grade Released Date must be >= Manual Grading Open Date");
                return false;
            }

            if(!checkRegister && !checkRotate) {
                alert("A type of way for TAs to grade must be selected");
                return false;
            }
        }
        if(!check1 && !check2 && !check3) {
            alert("A type of gradeable must be selected");
            return false;
        }

        var numOfNumeric = 0;
        var wrapper = $('.numerics-table');
        var i;
        if (check3) {
                for (i = 0; i < $('#numeric_num-items').val(); i++) {
                    numOfNumeric++;
                    if ($('#mult-field-' + numOfNumeric,wrapper).find('.max_score').attr('name','max_score_'+numOfNumeric).val() == 0) {
                        alert("Max score cannot be 0 [Question "+ numOfNumeric + "]");
                        return false;
                }
            }
        }

        if (check1 && $('input:radio[name="ta_grading"]:checked').attr('value') === 'true') {
            var index = 1;
            var exists = true;
            var error = false;
            var error_message = ``;
            while(exists){ //goes through questions
                if($("#grade-"+index).length) {                   
                    var type = 0;
                    if ($('input[name=grade_by-'+index+']:radio:checked').val() === 'count_up') {
                        type = 1;
                    } else {
                        type = 0;
                    }
                    var points = parseFloat($("#grade-"+index).val());
                    var temp_points = 0;
                    var temp_num = -1;
                    var exists2 = ($('#mark_id-'+index+'-0').length) ? true : false;
                    var index2 = 0;
                    while (exists2) { //goes through marks
                        temp_num = parseFloat($('#mark_id-'+index+'-'+index2).find('input[name=mark_points_'+index+'_'+index2+']').val());
                        if (type === 1) {
                            if (temp_num > 0) {
                                temp_points += temp_num;
                            }
                        } else {
                            if (temp_num < 0) {
                                temp_points += (temp_num * -1);
                            }
                        }
                        index2++;
                        exists2 = ($('#mark_id-'+index+'-'+index2).length) ? true : false;
                    }

                    //fun fact between caution and warning: http://www.stevensstrategic.com/technical-writing-the-difference-between-warnings-and-cautions/
                    if (temp_points < points && index2 > 1) { //display caution message if points are not enough and more than 1 mark
                        if (error === false) {
                            error_message = error_message + `Caution! \n`;
                        } else {
                            error_message = error_message + `\n`;
                        }
                        error = true;
                        var temp_error_message = ``;
                        if (type === 1) {
                            temp_error_message = `Component ` + index + ` is count up but the marks' values are not enough to reach the point value.`;
                        } else {
                            temp_error_message = `Component ` + index + ` is count down but the marks' values are not enough to drop to the point value.`;
                        }
                        error_message = error_message + temp_error_message;
                    }
                }
                else {
                    exists = false;
                }
                index++;
            }
            if (error === true) {
                error_message = error_message + `\n` + `Do you still wish to submit this gradeable?`;
                return confirm(error_message);
            }
        }
    }
    calculatePercentageTotal();
    calculateTotalScore();
    </script>
HTML;
    $html_output .= <<<HTML
<div id="alert-message" title="WARNING">
  <p>Gradeable ID must not be blank and only contain characters <strong> a-z A-Z 0-9 _ - </strong> </p>
</div>
HTML;

	return $html_output;
	}



}
