<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;

class UsersView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function listStudents($students) {
        $section = -1;
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 20px;">
        <a onclick="newDownloadForm()" class="btn btn-primary">Download Users</a>
        <a onclick="newClassListForm()" class="btn btn-primary">Upload Classlist</a>
        <a onclick="newUserForm()" class="btn btn-primary">New Student</a>
    </div>
    <h2>View Students</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="4%"></td>
                <td width="15%">Registration Section</td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td width="15%">Rotating Section</td>
                <td width="13%">Manual Registration</td>
                <td width="3%"></td>
            </tr>
        </thead>
HTML;
        if (count($students) > 0) {
            $count = 1;
            $tbody_open = false;
            foreach ($students as $student) {
                $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
                if($section !== $student->getRegistrationSection()) {
                    $count = 1;
                    if ($tbody_open) {
                        $return .= <<<HTML

        </tbody>
HTML;
                    }
                    $return .= <<<HTML

        <tr class="info persist-header">
            <td colspan="8" style="text-align: center">Students Enrolled in Registration Section {$registration}</td>
        </tr>
        <tbody id="section-{$registration}">
HTML;
                    $tbody_open = true;
                    $section = $student->getRegistrationSection();
                }
                $manual = ($student->isManualRegistration()) ? "TRUE" : "FALSE";
                $rotating_section = ($student->getRotatingSection() === null) ? "NULL" : $student->getRotatingSection();
                $style = "";
                if ($student->accessGrading()) {
                    $style = "style='background: #7bd0f7;'";
                }
                $return .= <<<HTML

            <tr id="user-{$student->getId()}" {$style}>
                <td>{$count}</td>
                <td>{$registration}</td>
                <td style="text-align: left">{$student->getId()}</td>
                <td style="text-align: left">{$student->getDisplayedFirstName()}</td>
                <td style="text-align: left">{$student->getLastName()}</td>
                <td>{$rotating_section}</td>
                <td>{$manual}</td>
                <td><a onclick="editUserForm('{$student->getId()}');"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML

        </tbody>
HTML;
        }
        else {
            $return .= <<<HTML
        <tr>
            <td colspan="8">No students found</td>
        </tr>
HTML;
        }

        $return .= <<<HTML

    </table>
</div>
HTML;

        return $return;
    }

    /**
     * @param User[] $graders
     * @return string
     */
    public function listGraders($graders) {
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 20px;">
        <a onclick="newDownloadForm()" class="btn btn-primary">Download Graders</a>
        <a onclick="newGraderListForm()" class="btn btn-primary">Upload Grader List</a>
        <a onclick="newUserForm();
            $('[name=\'user_group\'] option[value=\'3\']').prop('selected', true);" class="btn btn-primary">New Grader</a>
    </div>
    <h2>View Graders</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="4%"></td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td width="23%">User Group</td>
                <td width="20%">Registration Sections</td>
                <td width="3%"></td>
            </tr>
        </thead>
HTML;
        if (count($graders) > 0) {
            $count = 1;
            foreach ($graders as $grader) {
                switch ($grader->getGroup()) {
                    case 0:
                        $group = "Developer";
                        $registration_sections = "All";
                        break;
                    case 1:
                        $group = "Instructor";
                        $registration_sections = "All";
                        break;
                    case 2:
                        $group = "Full Access Grader (Grad TA)";
                        $registration_sections = implode(", ", $grader->getGradingRegistrationSections());
                        break;
                    case 3:
                        $group = "Limited Access Grader (Mentor)";
                        $registration_sections = implode(", ", $grader->getGradingRegistrationSections());
                        break;
                    default:
                        $group = "UNKNOWN";
                        $registration_sections = "";
                        break;
                }
                $return .= <<<HTML

            <tr id="user-{$grader->getId()}">
                <td>{$count}</td>
                <td style="text-align: left">{$grader->getId()}</td>
                <td style="text-align: left">{$grader->getDisplayedFirstName()}</td>
                <td style="text-align: left">{$grader->getLastName()}</td>
                <td>{$group}</td>
                <td>{$registration_sections}</td>
                <td><a onclick="editUserForm('{$grader->getId()}');"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML

        </tbody>
HTML;
        }
        else {
            $return .= <<<HTML
        <tr>
            <td colspan="3">No graders found</td>
        </tr>
HTML;
        }

        $return .= <<<HTML

    </table>
</div>
HTML;

        return $return;
    }

    /**
     * Creates the user form box to be displayed when creating or editing a user on the students/graders pages
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param string $action what action to go to after hitting the submit button (different for student vs grader page)
     * @param bool   $use_database
     * @return string
     */
    public function userForm($reg_sections, $rot_sections, $action, $use_database=false) {
        $url = array('component' => 'admin', 'page' => 'users', 'action' => $action);

        $reg_select_html = "";
        foreach ($reg_sections as $section) {
            $section = $section['sections_registration_id'];
            $reg_select_html .= "<option value='{$section}'>Section {$section}</option>\n";
        }

        $rot_select_html = "";
        foreach ($rot_sections as $section) {
            $section = $section['sections_rotating_id'];
            $rot_select_html .= "<option value='{$section}'>Section {$section}</option>\n";
        }

        $return = <<<HTML
<div class="popup-form" id="edit-user-form">
<form method="post" action="{$this->core->buildUrl($url)}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <input type="hidden" name="edit_user" value="false" />
    <div style="">
            User ID:<br />
        <input class="readonly" type="text" name="user_id" readonly="readonly" />
    </div>
    <div>
        First Name:<br />
        <input type="text" name="user_firstname" />
    </div>
    <div>
        Preferred First Name:<br />
        <input type="text" name="user_preferred_firstname" />
    </div>
    <div>
        Last Name:<br />
        <input type="text" name="user_lastname" />
    </div>
    <div>
        Email:<br />
        <input type="text" name="user_email" />
    </div>
    <div>
        Registered Section:<br />
        <select name="registered_section">
            <option value="null">Not Registered</option>
            {$reg_select_html}
        </select>
    </div>
    <div style="width: 62%">
            Group:<br />
        <select name="user_group">
            <option value="1">Instructor</option>
            <option value="2">Full Access Grader (Grad TA)</option>
            <option value="3">Limited Access Grader (Mentor)</option>
            <option value="4">Student</option>
        </select>
    </div>
    <div>
        Rotating Section:<br />
        <select name="rotating_section">
            <option value="null">No Section</option>
            {$rot_select_html}
        </select>
    </div>
    <div style="width: 70%">
        <input type="checkbox" id="manual_registration" name="manual_registration">
        <label for="manual_registration">Manually Registered User (no automatic updates)</label>
    </div>
    <div style="width: 100%">
        <h3>Assigned Sections (Graders Only)</h3>
HTML;
        foreach ($reg_sections as $section) {
            $section = $section['sections_registration_id'];
            $return .= <<<HTML

        <div style="width: 20%">
            <input type="checkbox" id="grs_{$section}" name="grading_registration_section[]" value="{$section}">
            <label for="grs_{$section}">Section {$section}</label>
        </div>
HTML;
        }
        $return .= <<<HTML
    </div>
HTML;
        if ($use_database) {
            $return .= <<<HTML
    <div style="width: 60%">
        Password:<br />
        <input type="password" name="user_password" placeholder="New Password" />    
    </div>
HTML;
        }

        $return .= <<<HTML
    <div style="float: right; width: auto; margin-top: 10px">
        <a onclick="$('#edit-user-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
        <input class="btn btn-primary" type="submit" value="Submit" />
    </div>
</form>
</div>
HTML;
        return $return;
    }

    /**
     * Creates the form box to be displayed when copying or downloading emails on the students/graders pages
     * @param $code to specify whether it is grader tab or student tab
     * @param User[] $students
     * @param Grader[] $graders  
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param bool   $use_database
     * @return string
     */
    public function downloadForm($code, $students, $graders, $reg_sections, $use_database=false) {
        $download_info = array();
        $first_name = "";
        $last_name = "";
        if ($code === 'user') {
            foreach ($students as $student) {
                $rot_sec = ($student->getRotatingSection() === null) ? 'NULL' : $student->getRotatingSection();
                $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
                $grp = "";
                switch ($student->getGroup()) {
                    case 0:
                        $grp = 'Developer';
                        break;
                    case 1:
                        $grp = 'Instructor';
                        break;
                    case 2:
                        $grp = 'Full Access Grader (Grad TA)';
                        break;
                    case 3:
                        $grp = 'Limited Access Grader (Mentor)';
                        break;
                    default:
                        $grp = 'Student';
                        break;
                }
                $first_name = str_replace("'", "&#039;", $student->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $student->getLastName());
                array_push($download_info, ['first_name' => $first_name, 'last_name' => $last_name, 'user_id' => $student->getId(), 'email' => $student->getEmail(), 'reg_section' => "$reg_sec", 'rot_section' => "$rot_sec", 'group' => "$grp"]);
            }
        }
        else if ($code === 'grader') {
            foreach ($graders as $grader) {
                $rot_sec = ($grader->getRotatingSection() === null) ? 'NULL' : $grader->getRotatingSection();
                $reg_sec = "";
                $grp = "";
                switch ($grader->getGroup()) {
                    case 0:
                        $reg_sec = 'All';
                        $grp = 'Developer';
                        break;
                    case 1:
                        $reg_sec = 'All';
                        $grp = 'Instructor';
                        break;
                    case 2:
                        $grp = 'Full Access Grader (Grad TA)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    case 3:
                        $grp = 'Limited Access Grader (Mentor)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    default:
                        $grp = 'UNKNOWN';
                        $reg_sec = "";
                        break;
                }
                $first_name = str_replace("'", "&#039;", $grader->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $grader->getLastName());
                array_push($download_info, ['first_name' => $first_name, 'last_name' => $last_name, 'user_id' => $grader->getId(), 'email' => $grader->getEmail(), 'reg_section' => "$reg_sec", 'rot_section' => "$rot_sec", 'group' => $grp]);
            }   
        }
        $download_info_json = json_encode($download_info);
        $return = <<<HTML
<div class="popup-form" id="download-form">                                                                   
<form>
    <input type="hidden" value='{$download_info_json}' id="download_info_json_id"><br /><br />
    <div style="width:300px">
HTML;
        foreach ($reg_sections as $section) {
            $section = $section['sections_registration_id'];
            $return .= <<<HTML
            <input type="checkbox" value="{$section}">Registration Section {$section}<br \>
HTML;
        }
        $return .= <<<HTML
        <input type="checkbox" value="NULL">Registration Section NULL<br \>
    </div>
HTML;
        $return .= <<<HTML
    <div style="width:300px">
        <input type="checkbox" value="instructor">Instructor<br \>
        <input type="checkbox" value="full_access_grader">Full Access Graders<br \>
        <input type="checkbox" value="limited_access_grader">Limited Access Graders<br \>
    </div><br />
HTML;
        if ($use_database) {
            $return .= <<<HTML
    <div style="width: 60%">
        Password:<br />
        <input type="password" name="user_password" placeholder="New Password" />    
    </div>
HTML;
        }
        $return .= <<<HTML
    <div style="float: right; width: auto; margin-top: 10px">
        <a onclick="$('#download-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
        <input class="btn btn-primary" type="button" value="Download CSV" onclick="downloadCSV('{$code}');" />
        <input class="btn btn-primary" type="button" id="copybuttonid" value="Copy Emails to Clipboard" onclick="copyToClipboard('{$code}');" />
    </div>
</form>
</div>
HTML;
        return $return;
    }

    public function rotatingUserForm($not_null_counts, $null_counts, $max_section) {
        $return = <<<HTML
<script type="text/javascript">
$(function() {
    $("[name='rotating_type']").change(function() {
        if ($(this).val() == "alphabetically") {
            $("[name='fewest']").prop('checked', false).attr('onclick', 'return false').addClass("disabled");

        }
        else {
            $("[name='fewest']").attr('onclick', '').removeClass('disabled');
        }
    });

    $("[name='sort_type']").change(function() {
        var val = $(this).val();
        if (val == "fewest") {
            $("[name='sections']").val({$max_section}).addClass('disabled').attr('readonly', 'readonly');
            $("[name='rotating_type']").val("random").addClass('disabled').attr('disabled', 'true');
        }
        else if (val == "drop_null") {
            $("[name='sections']").addClass('disabled').attr('readonly', 'readonly');
            $("[name='rotating_type']").addClass('disabled').attr('disabled', 'true');
        }
        else {
            $("[name='sections']").removeClass('disabled').removeAttr('readonly');
            $("[name='rotating_type']").removeClass('disabled').removeAttr('disabled');
        }
    });
});
</script>
<div class="content">
    <h2>Setup Rotating Sections</h2>
    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'update_rotating_sections'))}" method="POST">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="sub">
        <div class="box half">
            Place students in <input type="text" name="sections" placeholder="#" style="width: 25px" /> rotating sections
            <select name="rotating_type">
                <option value="random">randomly</option>
                <option value="alphabetically">alphabetically</option>
            </select><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="drop_null" /> Only remove unregistered students (registration section=NULL) from rotating sections
            </label><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="fewest" /> Remove unregistered students (registration section=NULL) from rotating sections and put newly registered students into rotating section with fewest members
            </label><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="redo" /> Redo rotating sections completely
            </label><br />
            <input style="margin-top: 20px; margin-right: 20px; float:right" type="submit" class="btn btn-primary" value="Submit" />
        </div>
        <div class="box half">
            <h2>Student Counts in Rotating Sections</h2>
            <div class="half">
                <h3>Registered Students (non NULL registration section)</h3>
                <table class="table table-bordered table-striped">
HTML;
        foreach($not_null_counts as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return .= <<<HTML
                    <tr>
                        <td>Section {$row['rotating_section']}</td>
                        <td>{$row['count']}</td>
HTML;
        }
        $return .= <<<HTML
                </table>
            </div>
            <div class="half">
                <h3>Students with Registration Section=NULL</h3>
                <table class="table table-bordered table-striped">
HTML;
        foreach ($null_counts as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return .= <<<HTML
                    <tr>
                        <td>Section {$row['rotating_section']}</td>
                        <td>{$row['count']}</td>
HTML;
        }
        $return .= <<<HTML
                </table>
            </div>
        </div>
    </div>
    </form>
</div>
HTML;
        return $return;
    }

    public function graderListForm($use_database) {
        if ($use_database) {
            $num_cols = 7;
            $password_col = "password, ";
        }
        else {
            $num_cols = 6;
            $password_col = "";
        }
        $return = <<<HTML
<div class="popup-form" id="grader-list-form">
    <h2>Upload Grader List</h2>
    <p>&emsp;</p>
    <p>
        Format your grader data as an .xlsx or .csv file with {$num_cols} columns:<br>
        &emsp;username, first name, last name, email, grader group, {$password_col}preferred first name<br>
    </p>
    <p>&emsp;</p>
    <p>
        Where GraderGroup is:<br>
        &emsp;1=Instructor<br>
        &emsp;2=Full Access Grader (graduate teaching assistant)<br>
        &emsp;3=Limited Access Grader (mentor)<br>
        &emsp;4=Student (no grading access)<br>
    </p>
    <p>&emsp;</p>
    <p>
        Preferred first name is optional.<br>
        Do not use a header row.<br>
    </p>
    <br />
    <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'upload_grader_list'))}" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        <div>
            <input type="file" name="upload" accept=".xlsx, .csv">
        </div>
        <div style="float: right; width: auto">
            <a onclick="$('#grader-list-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
            <input class="btn btn-primary" type="submit" value="Submit" />
        </div>
    </form>
</div>
HTML;
        return $return;
    }

    public function classListForm($use_database) {
        if ($use_database) {
            $num_cols = 7;
            $password_col = "password, ";
        }
        else {
            $num_cols = 6;
            $password_col = "";
        }
        $return = <<<HTML
<div class="popup-form" id="class-list-form">
    <h2>Upload Classlist</h2>
    <p>&emsp;</p>
    <p>
        Format your class list as an .xlsx or .csv file with {$num_cols} columns:<br>
        &emsp;username, first name, last name, email, registration section, {$password_col}preferred first name<br>
    </p>
    <p>&emsp;</p>
    <p>
        Preferred first name is optional.<br>
        Registration section can be null.<br>
        Do not use a header row.<br>
    </p>
    <br />
    <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'upload_class_list'))}" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Move students missing from the classlist to NULL section?<input type="checkbox" name="move_missing" /><br>
        <br />
        <div>
            <input type="file" name="upload" accept=".xlsx, .csv">
        </div>
        <div style="float: right; width: auto">
            <a onclick="$('#class-list-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
            <input class="btn btn-primary" type="submit" value="Submit" />
        </div>
    </form>
</div>
HTML;
        return $return;
    }
}
