<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\Team;
use app\models\User;
use app\models\LateDaysCalculation;
use app\views\AbstractView;
use app\libraries\FileUtils;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array     $sections
     * @return string
     */
    public function statusPage(
        $gradeable,
        $sections,
        $component_averages,
        $autograded_average,
        $overall_average,
        $total_submissions,
        $registered_but_not_rotating,
        $rotating_but_not_registered,
        $viewed_grade,
        $section_type) {

        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
            $peer = true;
        }
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $graded = 0;
        $total = 0;
        $no_team_total = 0;
        $team_total=0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_components'];
            $total += $section['total_components'];
            if ($gradeable->isTeamAssignment()) {
               $no_team_total += $section['no_team'];
               $team_total += $section['team'];
            }
        }
        if ($total === 0 && $no_team_total === 0){
            $percentage = -1;
        }
        else if ($total === 0 && $no_team_total > 0){
            $percentage = 0;
        }
        else{
            $percentage = number_format(($graded / $total) * 100, 1);
        }
        $return = <<<HTML
<div class="content">
    <h2>Status of {$gradeable->getName()}</h2>
HTML;
        if($percentage === -1){
            $view = 'all';
            $return .= <<<HTML
    <div class="sub">
        No Grading To Be Done! :)
    </div>
HTML;
        }
        else{
            $view = null;
            if ($gradeable->isTeamAssignment()) {
                $total_students = $team_total + $no_team_total;
            } else {
                $total_students = $total_submissions;
            }
            $change_value = $gradeable->getNumTAComponents();
            $show_total = $total/$change_value;
            $show_graded = round($graded/$change_value, 2);
            if($peer) {
                $change_value = $gradeable->getNumPeerComponents() * $gradeable->getPeerGradeSet();
                $show_graded = $graded/$change_value;
                $show_total = $total/$change_value;
            }
            $submitted_percentage = 0;
            if($total_submissions!=0){
                $submitted_percentage = round(($show_total / $total_submissions) * 100, 1);
            }
            //Add warnings to the warnings array to display them to the instructor.
            $warnings = array();
            if($section_type === "rotating_section" && $this->core->getUser()->accessFullGrading()){
                if ($registered_but_not_rotating > 0){
                    array_push($warnings, "There are ".$registered_but_not_rotating." registered students without a rotating section.");
                }
                if($rotating_but_not_registered > 0){
                    array_push($warnings, "There are ".$rotating_but_not_registered." unregistered students with a rotating section.");
                }
            }

            $return .= <<<HTML
    <div class="sub">
        <div class="box half">
HTML;
            if(count($warnings) > 0){
                $return .= <<<HTML
                <ul>
HTML;
                foreach ($warnings as $warning){
                    $return .= <<<HTML
                    <li style="color:red; margin-left:1em">{$warning}</li>
HTML;
                }
                $return .= <<<HTML
                </ul>
                <br/>
HTML;
            }
            if($gradeable->isTeamAssignment()){
            $team_percentage = round(($team_total/$total_students) * 100, 1);
            $return .= <<<HTML
            Students on a team: {$team_total}/{$total_students} ({$team_percentage}%)
            <br />
            <br />
            Number of teams: {$total_submissions}
            <br />
            <br />
            Teams who have submitted: {$show_total} / {$total_submissions} ({$submitted_percentage}%)
HTML;
            }
            else{
            $return .= <<<HTML
            Students who have submitted: {$show_total} / {$total_submissions} ({$submitted_percentage}%)
            <br />
            <br />
            Current percentage of grading done: {$show_graded}/{$show_total} ({$percentage}%)
HTML;
            }
            $return .= <<<HTML
            <br />
            <br />
HTML;
            if ($peer) {
                $show_total = floor($sections['stu_grad']['total_components']/$gradeable->getNumPeerComponents());
                $show_graded = floor($sections['stu_grad']['graded_components']/$gradeable->getNumPeerComponents());
                $percentage = number_format(($sections['stu_grad']['graded_components']/$sections['stu_grad']['total_components']) * 100, 1);
                $return .= <<<HTML
            Current percentage of students grading done: {$percentage}% ({$show_graded}/{$show_total})
        </div>
            <br />
HTML;
            }
            else {
                $return .= <<<HTML
            By Grading Sections:
            <div style="margin-left: 20px">
HTML;
                foreach ($sections as $key => $section) {
                    if($section['total_components'] == 0) {
                        $percentage = 0;
                    }
                    else {
                        $percentage = number_format(($section['graded_components'] / $section['total_components']) * 100, 1);
                    }
                    $show_graded = round($section['graded_components']/$change_value, 1);
                    $show_total = $section['total_components']/$change_value;
                    $return .= <<<HTML
                Section {$key}: {$show_graded} / {$show_total} ({$percentage}%)<br />
HTML;
                    if ($gradeable->isTeamAssignment() && $section['no_team'] > 0) {
                        $return .= <<<HTML
HTML;
                    }
                }
                $return .= <<<HTML
            </div>
            <br />
            Graders:
            <div style="margin-left: 20px">
HTML;
                foreach ($sections as $key => $section) {
                    if ($key === "NULL") {
                        continue;
                    }
                    $valid_graders = array();
                    foreach($section['graders'] as $valid_grader){
                        if($valid_grader->getGroup() <= $gradeable->getMinimumGradingGroup()){
                            $valid_graders[] = $valid_grader->getDisplayedFirstName();
                        }
                    }
                    $graders = (count($valid_graders) > 0) ? implode(', ', $valid_graders) : 'Nobody';

                    $return .= <<<HTML
                Section {$key}: {$graders}<br />
HTML;
                }
                $return .= <<<HTML
            </div>
HTML;
                if ($gradeable->taGradesReleased()) {
                    $show_total = $total/$change_value;
                    $viewed_percent = number_format(($viewed_grade / max($show_total, 1)) * 100, 1);
                    if ($gradeable->isTeamAssignment()) {
                        $return .= <<<HTML
            <br />
            Number of teams who have viewed their grade: {$viewed_grade} / {$show_total} ({$viewed_percent}%)
HTML;
                    } else {
                        $return .= <<<HTML
            <br />
            Number of students who have viewed their grade: {$viewed_grade} / {$show_total} ({$viewed_percent}%)
HTML;
                    }
                }
                $return .= <<<HTML
        </div>
HTML;
            }
            if(!$peer) {
                    $return .= <<<HTML
        <div class="box half">
            <b>Statistics for Completely Graded Assignments: </b><br/>
            <div style="margin-left: 20px">
HTML;
                    if($overall_average == null) {
                        $return .= <<<HTML
                There are no students completely graded yet.
            </div>
HTML;
                    }
                    else {
                        if($gradeable->getTotalAutograderNonExtraCreditPoints() == null) {
                            $total = $overall_average->getMaxValue();
                        }
                        else {
                            $total = $overall_average->getMaxValue() + $gradeable->getTotalAutograderNonExtraCreditPoints();
                        }
                        $percentage = 0;
                        if ($total != 0) {
                            $percentage = round($overall_average->getAverageScore()/$total*100);
                        }
                        $return .= <<< HTML
                Average: {$overall_average->getAverageScore()} / {$total} ({$percentage}%)<br/>
                Standard Deviation: {$overall_average->getStandardDeviation()} <br/>
                Count: {$overall_average->getCount()} <br/>
            </div>
HTML;
                    }
                    if($gradeable->getTotalAutograderNonExtraCreditPoints() == 0) {
                        // Don't display any autograding statistics since this gradeable has none
                    } else {
                        $return .= <<<HTML
            <br/><b>Statistics for Auto-Grading: </b><br/>
            <div style="margin-left: 20px">
HTML;
                        if($autograded_average->getCount() == 0) {
                            $return .= <<<HTML
                There are no submitted assignments yet.
            </div>
HTML;
                        }
                        else {
			    $percentage = 0;
                            if($gradeable->getTotalAutograderNonExtraCreditPoints() != 0) {
                                $percentage = round($autograded_average->getAverageScore()/$gradeable->getTotalAutograderNonExtraCreditPoints()*100);
			    }
                            $return .= <<<HTML
                Average: {$autograded_average->getAverageScore()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()} ({$percentage}%)<br/>
                Standard Deviation: {$autograded_average->getStandardDeviation()} <br/>
                Count: {$autograded_average->getCount()} <br/>
            </div>
HTML;
                        }
                    }
                    $return .= <<<HTML
            <br/><b>Statistics for Manually Graded Components: </b><br/>
            <div style="margin-left: 20px">
HTML;
                    if(count($component_averages) == 0) {
                        $return .= <<<HTML
            No components have been graded yet.
HTML;
                    }
                    else {
                        $overall_score = 0;
                        $overall_max = 0;
                        foreach($component_averages as $comp) {
                            $overall_score += $comp->getAverageScore();
                            $overall_max += $comp->getMaxValue();
                            $percentage = 0;
			                if ($comp->getMaxValue() != 0) {
			                    $percentage = round($comp->getAverageScore() / $comp->getMaxValue() * 100);
                            }
                            $average_string = ($comp->getMaxValue() > 0 ? "{$comp->getAverageScore()} / {$comp->getMaxValue()} ({$percentage}%)" : "{$comp->getAverageScore()}");
                            $return .= <<<HTML
                {$comp->getTitle()}:<br/>
                <div style="margin-left: 40px">
                    Average: {$average_string}<br/>
                    Standard Deviation: {$comp->getStandardDeviation()} <br/>
                    Count: {$comp->getCount()} <br/>
                </div>
HTML;
                        }
                        if($overall_max !=0){
                            $percentage = round($overall_score / $overall_max *100);
                            $return .= <<<HTML
                <br/>Overall Average:  {$overall_score} / {$overall_max} ({$percentage}%)
HTML;
                        }
                    }
                //This else encompasses the above calculations for Teamss
                //END OF ELSE
                $return .= <<<HTML
            </div>
        </div>
HTML;
            }
            $return .= <<<HTML
    </div>
HTML;
        }
        $return .= <<<HTML
    <div style="margin-top: 20px; vertical-align:bottom;">
HTML;
        if($percentage !== -1 || $this->core->getUser()->accessFullGrading() || $peer){
            $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'details', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}"">
            Grading Details
        </a>
HTML;
            if(count($this->core->getUser()->getGradingRegistrationSections()) !== 0){
                $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId()))}">
            Grade Next Student
        </a>
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId()))}">
            Download Zip of All Assigned Students
        </a>
HTML;
            }
            if($this->core->getUser()->accessFullGrading()) {
                $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId(), 'type'=>'All'))}">
            Download Zip of All Students
        </a>
HTML;
            }
        }
        $return .= <<<HTML
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function detailsPage(Gradeable $gradeable, $rows, $graders, $all_teams, $empty_teams) {
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';

        $peer = false;
        if ($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
            $peer = true;
        }
        if ($peer) {
            $grading_count = $gradeable->getPeerGradeSet();
        } else if ($gradeable->isGradeByRegistration()) {
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        } else {
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(), $this->core->getUser()->getId()));
        }

        $show_all_sections_button = $this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0);
        $show_import_teams_button = $gradeable->isTeamAssignment() && (count($all_teams) > count($empty_teams));
        $show_export_teams_button = $gradeable->isTeamAssignment() && (count($all_teams) == count($empty_teams));

        //Each table column is represented as an array with the following entries:
        // width => how wide the column should be on the page, <td width=X>
        // title => displayed title in the table header
        // function => maps to a macro in Details.twig:render_student
        $columns = [];
        if($peer) {
            $columns[]         = ["width" => "5%",  "title" => "",                 "function" => "index"];
            $columns[]         = ["width" => "30%", "title" => "Student",          "function" => "user_id_anon"];

            if ($gradeable->getTotalNonHiddenNonExtraCreditPoints() !== 0) {
                $columns[]     = ["width" => "15%", "title" => "Autograding",      "function" => "autograding_peer"];
                $columns[]     = ["width" => "20%", "title" => "Grading",          "function" => "grading"];
                $columns[]     = ["width" => "15%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            } else {
                $columns[]     = ["width" => "30%", "title" => "Grading",          "function" => "grading"];
                $columns[]     = ["width" => "20%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            }
        } else {
            if ($gradeable->isTeamAssignment()) {
                if ($this->core->getUser()->accessAdmin()) {
                    $columns[] = ["width" => "3%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "6%",  "title" => "Edit Teams",       "function" => "team_edit"];
                    $columns[] = ["width" => "12%", "title" => "Team Id",          "function" => "team_id"];
                    $columns[] = ["width" => "32%", "title" => "Team Members",     "function" => "team_members"];
                } else {
                    $columns[] = ["width" => "3%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "50%", "title" => "Team Members",     "function" => "team_members"];
                }
            } else {
                $columns[]     = ["width" => "3%",  "title" => "",                 "function" => "index"];
                $columns[]     = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                $columns[]     = ["width" => "20%", "title" => "User ID",          "function" => "user_id"];
                $columns[]     = ["width" => "15%", "title" => "First Name",       "function" => "user_first"];
                $columns[]     = ["width" => "15%", "title" => "Last Name",        "function" => "user_last"];
            }
            if ($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0) {
                $columns[]     = ["width" => "9%",  "title" => "Autograding",      "function" => "autograding"];
                $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                $columns[]     = ["width" => "8%",  "title" => "TA Grading",       "function" => "grading"];
                $columns[]     = ["width" => "7%",  "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->taGradesReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            } else {
                $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                $columns[]     = ["width" => "12%", "title" => "TA Grading",       "function" => "grading"];
                $columns[]     = ["width" => "12%", "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->taGradesReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            }
        }

        //Convert rows into sections and prepare extra row info for things that
        // are too messy to calculate in the template.
        $sections = [];
        foreach ($rows as $row) {
            //Extra info for the template
            $info = [
                "gradeable" => $row
            ];

            if ($peer) {
                $section_title = "PEER STUDENT GRADER";
            } else if ($row->isGradeByRegistration()) {
                $section_title = $row->getTeam() === null ? $row->getUser()->getRegistrationSection() : $row->getTeam()->getRegistrationSection();
            } else {
                $section_title = $row->getTeam() === null ? $row->getUser()->getRotatingSection() : $row->getTeam()->getRotatingSection();
            }
            if ($section_title === null) {
                $section_title = "NULL";
            }

            if (isset($graders[$section_title]) && count($graders[$section_title]) > 0) {
                $section_graders = implode(", ", array_map(function (User $user) {
                    return $user->getId();
                }, $graders[$section_title]));
            } else {
                $section_graders = "Nobody";
            }
            if ($peer) {
                $section_graders = $this->core->getUser()->getId();
            }

            //Team edit button, specifically the onclick event.
            if ($row->isTeamAssignment()) {
                if ($row->getTeam() === null) {
                    $reg_section = ($row->getUser()->getRegistrationSection() === null) ? "NULL" : $row->getUser()->getRegistrationSection();
                    $rot_section = ($row->getUser()->getRotatingSection() === null) ? "NULL" : $row->getUser()->getRegistrationSection();
                    $info["team_edit_onclick"] = "adminTeamForm(true, '{$row->getUser()->getId()}', '{$reg_section}', '{$rot_section}', [], [], {$gradeable->getMaxTeamSize()});";
                } else {
                    $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $row->getTeam()->getId(), "user_assignment_settings.json");
                    $user_assignment_setting = FileUtils::readJsonFile($settings_file);
                    $user_assignment_setting_json = json_encode($user_assignment_setting);
                    $members = json_encode($row->getTeam()->getMembers());
                    $reg_section = ($row->getTeam()->getRegistrationSection() === null) ? "NULL" : $row->getTeam()->getRegistrationSection();
                    $rot_section = ($row->getTeam()->getRotatingSection() === null) ? "NULL" : $row->getTeam()->getRotatingSection();

                    $info["team_edit_onclick"] = "adminTeamForm(false, '{$row->getTeam()->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, {$members}, {$gradeable->getMaxTeamSize()});";
                }
            }

            //List of graded components
            $info["graded_components"] = [];
            foreach ($row->getComponents() as $component) {
                if (is_array($component)) {
                    foreach ($component as $cmpt) {
                        if ($cmpt->getGrader() == null) {
                            $question = $cmpt;
                            break;
                        }
                        if ($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                            $question = $cmpt;
                            break;
                        }
                    }
                    if ($question === null) {
                        $question = $component[0];
                    }
                } else {
                    $question = $component;
                }
                if ($question->getGrader() !== null && $question !== null) {
                    $info["graded_components"][] = $question;
                }
            }

            //More complicated info generation should go here


            //-----------------------------------------------------------------
            // Now insert this student into the list of sections

            $found = false;
            for ($i = 0; $i < count($sections); $i++) {
                if ($sections[$i]["title"] === $section_title) {
                    $found = true;
                    $sections[$i]["rows"][] = $info;
                    break;
                }
            }
            //Not found? Create it
            if (!$found) {
                $sections[] = ["title" => $section_title, "rows" => [$info], "graders" => $section_graders];
            }
        }

        $empty_team_info = [];
        foreach ($empty_teams as $team) {
            /* @var Team $team */
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable->getId(), $team->getId(), "user_assignment_settings.json");
            $user_assignment_setting = FileUtils::readJsonFile($settings_file);
            $user_assignment_setting_json = json_encode($user_assignment_setting);
            $reg_section = ($team->getRegistrationSection() === null) ? "NULL" : $team->getRegistrationSection();
            $rot_section = ($team->getRotatingSection() === null) ? "NULL" : $team->getRotatingSection();

            $empty_team_info[] = [
                "team_edit_onclick" => "adminTeamForm(false, '{$team->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, [], {$gradeable->getMaxTeamSize()});"
            ];
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/Details.twig", [
            "gradeable" => $gradeable,
            "sections" => $sections,
            "graders" => $graders,
            "empty_teams" => $empty_teams,
            "empty_team_info" => $empty_team_info,
            "view_all" => $view_all,
            "show_all_sections_button" => $show_all_sections_button,
            "show_import_teams_button" => $show_import_teams_button,
            "show_export_teams_button" => $show_export_teams_button,
            "columns" => $columns,
            "peer" => $peer
        ]);
    }

    public function adminTeamForm($gradeable, $all_reg_sections, $all_rot_sections) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => str_replace("'","&#039;",$student->getDisplayedFirstName()).' '.str_replace("'","&#039;",$student->getLastName()).' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);

        return $this->core->getOutput()->renderTwigTemplate("grading/AdminTeamForm.twig", [
            "gradeable" => $gradeable,
            "student_full" => $student_full,
            "view" => isset($_REQUEST["view"]) ? $_REQUEST["view"] : null,
            "all_reg_sections" => $all_reg_sections,
            "all_rot_sections" => $all_rot_sections,
        ]);
    }

    public function importTeamForm($gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/ImportTeamForm.twig", [
            "gradeable" => $gradeable
        ]);
    }


    //The student not in section variable indicates that an full access grader is viewing a student that is not in their
    //assigned section. canViewWholeGradeable determines whether hidden testcases can be viewed.
    public function hwGradingPage(Gradeable $gradeable, float $progress, string $prev_id, string $next_id, $studentNotInSection=false, $canViewWholeGradeable=false) {
        $peer = false;
        if($this->core->getUser()->getGroup()==4 && $gradeable->getPeerGrading()) {
            $peer = true;
        }
        $user = $gradeable->getUser();
        $your_user_id = $this->core->getUser()->getId();
        $prev_href = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$prev_id));
        $next_href = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$next_id));
        $return = <<<HTML
<div id="bar_wrapper" class="draggable">
<div class="grading_toolbar">
HTML;
    //If the student is in our section, add a clickable previous arrow, else add a grayed out one.
    if(!$studentNotInSection){
    $return .= <<< HTML
        <a href="javascript:void(0);" onclick="gotoPrevStudent();" data-href="{$prev_href}" id="prev-student"><i title="Go to the previous student" class="fa fa-chevron-left icon-header"></i></a>
HTML;
    }
    else{
        $return .= <<< HTML
        <i title="Go to the previous student" class="fa fa-chevron-left icon-header" style="color:grey"></i>
HTML;
    }
    $return .= <<< HTML
    <a href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details', 'gradeable_id'=>$gradeable->getId()))}"><i title="Go to the main page" class="fa fa-home icon-header" ></i></a>
HTML;
    //If the student is in our section, add a clickable next arrow, else add a grayed out one.
    if(!$studentNotInSection){
    $return .= <<<HTML
    <a href="javascript:void(0);" onclick="gotoNextStudent();" data-href="{$next_href}" id="next-student"><i title="Go to the next student" class="fa fa-chevron-right icon-header"></i></a>
HTML;
    }
    else{
        $return .= <<< HTML
        <i title="Go to the next student" class="fa fa-chevron-right icon-header" style="color:grey"></i>
HTML;
    }
    $return .= <<< HTML

    <i title="Reset Rubric Panel Positions (Press R)" class="fa fa-refresh icon-header" onclick="resetModules(); updateCookies();"></i>
    <i title="Show/Hide Auto-Grading Testcases (Press A)" class="fa fa-list-alt icon-header" onclick="toggleAutograding(); updateCookies();"></i>
HTML;
    if ($gradeable->useTAGrading()) {
            $return .= <<<HTML
    <i title="Show/Hide Grading Rubric (Press G)" class="fa fa fa-pencil-square-o icon-header" onclick="toggleRubric(); updateCookies();"></i>
HTML;
        }
        $return .= <<<HTML
    <i title="Show/Hide Submission and Results Browser (Press O)" class="fa fa-folder-open icon-header" onclick="toggleSubmissions(); updateCookies();"></i>
HTML;
        if(!$peer) {
            $return .= <<<HTML
    <i title="Show/Hide Student Information (Press S)" class="fa fa-user icon-header" onclick="toggleInfo(); updateCookies();"></i>
HTML;
        }
        $return .= <<<HTML
</div>

<div class="progress_bar">
    <progress class="progressbar" max="100" value="{$progress}" style="width:70%; height: 100%;"></progress>
    <div class="progress-value" style="display:inline;"></div>
</div>
</div>


<div id="autograding_results" class="draggable rubric_panel" style="left:15px; top:170px; width:48%; height:36%;">
    <div class="draggable_content">
    <span class="grading_label">Auto-Grading Testcases</span>
    <button class="btn btn-default" onclick="openAllAutoGrading()">Expand All</button>
    <button class="btn btn-default" onclick="closeAllAutoGrading()">Close All</button>
    <div class="inner-container">
HTML;
        if ($gradeable->getActiveVersion() === 0){
            $return .= <<<HTML
        <h4>No Submission</h4>
HTML;
        }
        else if (count($gradeable->getTestcases()) === 0) {
            $return .= <<<HTML
        <h4>No Autograding For This Assignment</h4>
HTML;
        }
        else{
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $gradeable, $canViewWholeGradeable);
        }
        $return .= <<<HTML
    </div>
    </div>
</div>

<div id="submission_browser" class="draggable rubric_panel" style="left:15px; bottom:40px; width:48%; height:30%">
    <div class="draggable_content">
    <span class="grading_label">Submissions and Results Browser</span>
    <button class="btn btn-default expand-button" data-linked-type="submissions" data-clicked-state="wasntClicked" id="toggleSubmissionButton">Open/Close Submissions</button>
HTML;

    if(count($gradeable->getVcsFiles()) != 0) { //check if there are vcs files, if yes display the toggle button, else don't display it
        $return .= <<<HTML
        <button class="btn btn-default expand-button" data-linked-type="checkout" data-clicked-state="wasntClicked"  id="togglCheckoutButton">Open/Close Checkout</button>
HTML;
    }

$return .= <<<HTML
    <button class="btn btn-default expand-button" data-linked-type="results" data-clicked-state="wasntClicked"  id="toggleResultButton">Open/Close Results</button>

    <script type="text/javascript">
        $(document).ready(function(){
            //note the commented out code here along with the code where files are displayed that is commented out
            //is intended to allow open and close to change dynamically on click
            //the problem is currently if you click the submissions folder then the text won't change b/c it's being double clicked effectively.
            $(".expand-button").on('click', function(){
                // $(this).attr('clicked-state', "clicked");
                // updateValue($(this), "Open", "Close");
                openAll( 'openable-element-', $(this).data('linked-type'))
                // $.when(openAll( 'openable-element-', $(this).data('linked-type'))).then(function(){
                //     console.log('HELLLO');
                // });
            })

            var currentCodeStyle = localStorage.getItem('codeDisplayStyle');
            var currentCodeStyleRadio = (currentCodeStyle == null || currentCodeStyle == "light") ? "style_light" : "style_dark";
            $('#' + currentCodeStyleRadio).parent().addClass('active');
            $('#' + currentCodeStyleRadio).prop('checked', true);
        });
    </script>
HTML;
        if(!$peer) {
        $return .= <<<HTML
    <button class="btn btn-default" onclick="downloadZip('{$gradeable->getId()}','{$gradeable->getUser()->getId()}')">Download Zip File</button>
HTML;
        }
        $return .= <<<HTML
        <div id="changeCodeStyle" class="btn-group btn-group-toggle" style="display:inline-block;" onchange="changeEditorStyle($('[name=codeStyle]:checked')[0].id);" data-toggle="buttons">
            <label class="btn btn-secondary">
                <input type="radio" name="codeStyle" id="style_light" autocomplete="off" checked> Light
            </label>
            <label class="btn btn-secondary">
                <input type="radio" name="codeStyle" id="style_dark" autocomplete="off"> Dark
            </label>
        </div>

    <br />
    <div class="inner-container" id="file-container">
HTML;
        function add_files(&$files, $new_files, $start_dir_name) {
            $files[$start_dir_name] = array();
            foreach($new_files as $file) {
                $path = explode('/', $file['relative_name']);
                array_pop($path);
                $working_dir = &$files[$start_dir_name];
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }
                $working_dir[$file['name']] = $file['path'];
            }
        }
        function display_files($files, &$count, $indent, &$return, $filename) {
            $name = "a" . $filename;
            foreach ($files as $dir => $path) {
                if (!is_array($path)) {
                    $name = htmlentities($dir);
                    $dir = rawurlencode(htmlspecialchars($dir));
                    $path = rawurlencode(htmlspecialchars($path));
                    $indent_offset = $indent * -15;
                    $return .= <<<HTML
                <div>
                    <div class="file-viewer">
                        <a class='openAllFile{$filename} openable-element-{$filename}' onclick='openFrame("{$dir}", "{$path}", {$count}); updateCookies();'>
                            <span class="fa fa-plus-circle" style='vertical-align:text-bottom;'></span>
                        {$name}</a> &nbsp;
                        <a onclick='openFile("{$dir}", "{$path}")'><i class="fa fa-window-restore" aria-hidden="true" title="Pop up the file in a new window"></i></a>
                        <a onclick='downloadFile("{$dir}", "{$path}")'><i class="fa fa-download" aria-hidden="true" title="Download the file"></i></a>
                    </div>
                    <div id="file_viewer_{$count}" style="margin-left:{$indent_offset}px" data-file_name="{$dir}" data-file_url="{$path}"></div>
                </div>
HTML;
                    $count++;
                }
            }
            foreach ($files as $dir => $contents) {
                if (is_array($contents)) {
                    $dir = htmlentities($dir);
                    $url = reset($contents);
                    $return .= <<<HTML
            <div>
                <div class="div-viewer">
                    <a class='openAllDiv openAllDiv{$filename} openable-element-{$filename}' id={$dir} onclick='openDiv({$count}); updateCookies();'>
                        <span class="fa fa-folder open-all-folder" style='vertical-align:text-top;'></span>
                    {$dir}</a>
                </div><br/>
                <div id='div_viewer_{$count}' style='margin-left:15px; display: none' data-file_name="{$dir}">
HTML;
                    $count++;
                    display_files($contents, $count, $indent+1, $return, $filename);
                    $return .= <<<HTML
                </div>
            </div>
HTML;
                }
            }
        }
        $files = array();
        $submissions = array();
        $results = array();
        $checkout = array();

        // NOTE TO FUTURE DEVS: There is code around line 830 (ctrl-f openAll) which depends on these names,
        // if you change here, then change there as well
        // order of these statements matter I believe

        add_files($submissions, array_merge($gradeable->getMetaFiles(), $gradeable->getSubmittedFiles()), 'submissions');

        $vcsFiles = $gradeable->getVcsFiles();
        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            add_files($checkout,  $vcsFiles, 'checkout');
        }

        add_files($results, $gradeable->getResultsFiles(), 'results');

        $count = 1;
        display_files($submissions,$count,1,$return, "submissions"); //modifies the count var here within display_files

        if( count( $vcsFiles ) != 0 ) { //if there are checkout files, then display folder, otherwise don't
            display_files($checkout,$count,1,$return, "checkout");
        }

        display_files($results,$count,1,$return, "results"); //uses the modified count variable b/c old code did this not sure if needed
        $files = array_merge($submissions, $checkout, $results );

        $return .= <<<HTML
        <script type="text/javascript">
            // $(document).ready(function(){
            //     $(".openAllDiv").on('click', function(){
            //         if($(this).attr('id') == 'results' || $(this).attr('id') == 'submissions' || $(this).attr('id') =='checkout'){
            //             var elem = $('[data-linked-type="' + $(this).attr('id') + '"]');
            //             if(elem.data('clicked-state') == "wasntClicked"){
            //                 updateValue(elem, "Open", "Close");
            //             }
            //         }
            //     });
            // });
        </script>
    </div>
    </div>
</div>
HTML;

        $user = $gradeable->getUser();
        if(!$peer) {
            $return .= <<<HTML

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <div class="draggable_content">
    <span class="grading_label">Student Information</span>
    <div class="inner-container">
        <h5 class='label' style="float:right; padding-right:15px;">Browse Student Submissions:</h5>
        <div class="rubric-title">
HTML;
            $who = $gradeable->getUser()->getId();
            $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'gradeable_version' => ""))}', this)";
            $formatting = "font-size: 13px;";
            $return .= <<<HTML
            <div style="float:right;">
HTML;
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showVersionChoice', $gradeable, $onChange, $formatting);

            // If viewing the active version, show cancel button, otherwise show button to switch active
            if ($gradeable->getCurrentVersionNumber() > 0) {
                if ($gradeable->getCurrentVersionNumber() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Cancel Student Submission">';
                }
                else {
                    $version = $gradeable->getCurrentVersionNumber();
                    $button = '<input type="submit" class="btn btn-default btn-xs" style="float:right; margin: 0 10px;" value="Grade This Version">';
                }
                $return .= <<<HTML
                <br/><br/>
                <form style="display: inline;" method="post" onsubmit='return checkTaVersionChange();'
                        action="{$this->core->buildUrl(array('component' => 'student',
                                                             'action' => 'update',
                                                             'gradeable_id' => $gradeable->getId(),
                                                             'new_version' => $version, 'ta' => true, 'who' => $who))}">
                    <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
                    {$button}
                </form>
HTML;
            }
            $return .= <<<HTML
            </div>
            <div>
HTML;

            if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            $return .= <<<HTML
                <b>Team:<br/>
HTML;
                foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                    $team_member = $this->core->getQueries()->getUserById($team_member);
                    $return .= <<<HTML
                &emsp;{$team_member->getDisplayedFirstName()} {$team_member->getLastName()} ({$team_member->getId()})<br/>
HTML;
                }
            }
            else {
                $return .= <<<HTML
                <b>{$user->getDisplayedFirstName()} {$user->getLastName()} ({$user->getId()})<br/>
HTML;
            }

            $return .= <<<HTML
                Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}<br/>
                Submitted: {$gradeable->getSubmissionTime()->format("m/d/Y H:i:s")}<br/></b>
            </div>
HTML;
            $return .= <<<HTML
            <form id="rubric_form">
                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                <input type="hidden" name="g_id" value="{$gradeable->getId()}" />
                <input type="hidden" name="u_id" value="{$user->getId()}" />
                <input type="hidden" name="graded_version" value="{$gradeable->getActiveVersion()}" />
HTML;

            //Late day calculation
            $status = "Good";
            $color = "green";
            if($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null){
                foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                    $team_member = $this->core->getQueries()->getUserById($team_member);
                    $return .= $this->makeTable($team_member->getId(), $gradeable, $status);
                }
                
            } else {
                $return .= $this->makeTable($user->getId(), $gradeable, $status);
                if($status != "Good" && $status != "Late" && $status != "No submission") {
                    $color = "red";
                    $my_color="'#F62817'"; // fire engine red
                    $my_message="Late Submission";
                    $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <b>Status:</b> <span style="color:{$color};">{$status}</span><br />
HTML;
                }
            }
            
            

            $return .= <<<HTML
        </div>
    </div>
    </div>
</div>
HTML;
        }
        if($peer) {
            $span_style = 'style="display:none;"';
            $checked = 'disabled';
        }
        else {
            $span_style = '';
            $checked = 'checked';
        }
        $empty = "";
        if(!$gradeable->useTAGrading()) {
            $empty = "empty";
        }
        $display_verify_all = false;
        //check if verify all button should be shown or not
        foreach ($gradeable->getComponents() as $component) {
            if(!$component->getGrader()){
              continue;
            }
            if($component->getGrader()->getId() !== $this->core->getUser()->getId() && $this->core->getUser()->accessFullGrading()){
                $display_verify_all = true;
                break;
            }
        }
        $return .= <<<HTML
<div id="grading_rubric" class="draggable rubric_panel {$empty}" style="right:15px; top:140px; width:48%; height:42%;">
    <div class="draggable_content">
    <span class="grading_label">Grading Rubric</span>
HTML;
        if($gradeable->useTAGrading()) {
          $return .= <<<HTML
    <div style="float: right; float: right; position: relative; top: 10px; right: 1%;">
HTML;
          if($display_verify_all){
            $return .= <<<HTML
        <input id='verifyAllButton' type='button' style="display: inline;" class="btn btn-default" value='Verify All' onclick='verifyMark("{$gradeable->getId()}",-1,"{$user->getAnonId()}",true);'/>
HTML;
          }
          $return .= <<<HTML
        <span style="padding-right: 10px"> <input type="checkbox" id="autoscroll_id" onclick="updateCookies();"> Auto scroll / Auto open </span>
        <span {$span_style}> <input type='checkbox' id="overwrite-id" name='overwrite' value='1' onclick="updateCookies();" {$checked}/> Overwrite Grader </span>
    </div>
HTML;
        $disabled = '';
        if($gradeable->getActiveVersion() == 0){
            $disabled='disabled';
            $my_color="'#FF8040'"; // mango orange
            $my_message="Cancelled Submission";
            if($gradeable->hasSubmitted()){
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <div class="red-message" style="text-align: center">$my_message</div>
HTML;
            } else {
                $my_color="'#C38189'";  // lipstick pink (purple)
                $my_message="No Submission";
                $return .= <<<HTML
                <script>
                    $('body').css('background', $my_color);
                    $('#bar_wrapper').append("<div id='bar_banner' class='banner'>$my_message</div>");
                    $('#bar_banner').css('background-color', $my_color);
                    $('#bar_banner').css('color', 'black');
                </script>
                <div class="red-message" style="text-align: center">$my_message</div>
HTML;
            }
        } else if($gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion()){
            $disabled='disabled';
            $return .= <<<HTML
            <div class="red-message" style="text-align: center">Select the correct submission version to grade</div>
HTML;
        }
            // if use student components, get the values for pages from the student's submissions
            $files = $gradeable->getSubmittedFiles();
            $student_pages = array();
            foreach ($files as $filename => $content) {
                if ($filename == "student_pages.json") {
                    $path = $content["path"];
                    $student_pages = FileUtils::readJsonFile($content["path"]);
                }
            }

            $grading_data = [
                "gradeable" => $gradeable->getGradedData(),
                "your_user_id" => $this->core->getUser()->getId(),
                "disabled" => $disabled === "disabled",
                "can_verify" => $display_verify_all // If any can be then this is set
            ];

            foreach ($grading_data["gradeable"]["components"] as &$component) {
                $page = intval($component["page"]);
                // if the page is determined by the student json
                if ($page == -1) {
                    // usually the order matches the json
                    if ($student_pages[intval($component["order"])]["order"] == intval($component["order"])) {
                        $page = intval($student_pages[intval($component["order"])]["page #"]);
                    }
                    // otherwise, iterate through until the order matches
                    else {
                        foreach ($student_pages as $student_page) {
                            if ($student_page["order"] == intval($component["order"])) {
                                $page = intval($student_page["page #"]);
                                $component["page"] = $page;
                                break;
                            }
                        }
                    }
                }
            }
            //References need to be cleaned up
            unset($component);


            $grading_data = json_encode($grading_data, JSON_PRETTY_PRINT);


            $return .= <<<HTML
    <div class="inner-container" id="grading-box">

                    </div>
    <script type="application/javascript">
        var grading_data = {$grading_data};
        renderGradeable(grading_data)
            .then(function(elements) {
                $("#grading-box").append(elements);
                updateAllProgressPoints();
            })
            .catch(function(err) {
                alert("Could not render gradeable: " + err.message);
                console.error(err);
            });
    </script>
HTML;

            $this->core->getOutput()->addInternalJs('ta-grading.js');
            $this->core->getOutput()->addInternalJs('ta-grading-mark.js');
            $this->core->getOutput()->addInternalJs('twig.min.js');
            $this->core->getOutput()->addInternalJs('gradeable.js');

        $return .= <<<HTML
        </div>
        </div>
    </div>
HTML;
        }

        $return .= <<<HTML
</div>
<script type="text/javascript">
    function openFrame(html_file, url_file, num) {
        var iframe = $('#file_viewer_' + num);
        if (!iframe.hasClass('open')) {
            var iframeId = "file_viewer_" + num + "_iframe";
            var directory = "";
            if (url_file.includes("submissions")) {
                directory = "submissions";
            }
            else if (url_file.includes("results")) {
                directory = "results";
            }
            else if (url_file.includes("checkout")) {
                directory = "checkout";
            }
            // handle pdf
            if (url_file.substring(url_file.length - 3) === "pdf") {
                iframe.html("<iframe id='" + iframeId + "' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true' width='95%' height='1200px' style='border: 0'></iframe>");
            }
            else {
                iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true' width='95%' style='border: 0'></iframe>");
            }
            iframe.addClass('open');
        }

        if (!iframe.hasClass('shown')) {
            iframe.show();
            iframe.addClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-plus-circle').addClass('fa-minus-circle');
        }
        else {
            iframe.hide();
            iframe.removeClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-minus-circle').addClass('fa-plus-circle');
        }
        return false;
    }

    function openFile(html_file, url_file) {
        var directory = "";
        if (url_file.includes("submissions")) {
            directory = "submissions";
        }
        else if (url_file.includes("results")) {
            directory = "results";
        }
        else if (url_file.includes("checkout")) {
            directory = "checkout";
        }
        window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + html_file + "&path=" + url_file + "&ta_grading=true","_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }
</script>
<script type="text/javascript">
        function adjustSize(name) {
          var textarea = document.getElementById(name);
          textarea.style.height = "";
          textarea.style.height = Math.min(textarea.scrollHeight, 300) + "px";
        };
</script>
HTML;
        return $return;
    }

    public function popupStudents() {
        $return = <<<HTML
<div class="popup-form" id="student-marklist-popup" style="display: none; width: 500px; margin-left: -250px;">
    <div style="width: auto; height: 450px; overflow-y: auto;" id="student-marklist-popup-content">
        <h3>Students who received
            <br><br>
            <span id="student-marklist-popup-question-name">Name:</span>
            <br>
            <em id="student-marklist-popup-mark-note">"Title"</em>
        </h3>
        <br>
        # of students with mark: <span id="student-marklist-popup-student-amount">0</span>
        <br>
        # of graded components: <span id="student-marklist-popup-graded-components">0</span>
        <br>
        # of total components: <span id="student-marklist-popup-total-components">0</span>
        <br>
        <span id="student-marklist-popup-student-names">
            <br>Name1
        </span>
    </div>
    <div style="float: right; width: auto">
        <a onclick="$('#student-marklist-popup').css('display', 'none');" class="btn btn-danger">Cancel</a>
    </div>
</div>
</div>
HTML;
        return $return;
    }

    public function popupNewMark() {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/NewMarkForm.twig");
    }

    private function makeTable($user_id, $gradeable, &$status){
        $return = <<<HTML
        <h3>Overall Late Day Usage for {$user_id}</h3><br/>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per term</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Allowed per assignment</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Submitted days after deadline</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Extensions</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Status</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Late Days Charged</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Total Late Days Used</th>
                    <th style="padding:5px; border:thin solid black; vertical-align:middle">Remaining Days</th>
                </tr>
            </thead>
            <tbody>
HTML;
        $total_late_used = 0;
        $status = "Good";
        $order_by = [ 
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END' 
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $g) { 
            $g->calculateLateDays($total_late_used);
            $class = "";
            if($g->getId() == $gradeable->getId()){
                $class = "class='yellow-background'";
                $status = $g->getLateStatus();
            }
            if(!$g->hasSubmitted()){
                $status = "No submission";
            }
            $remaining = max(0, $g->getStudentAllowedLateDays() - $total_late_used);
            $return .= <<<HTML
                <tr>
                    <th $class style="padding:5px; border:thin solid black">{$g->getName()}</th>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getStudentAllowedLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getAllowedLateDays()}</td> 
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDays()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getLateDayExceptions()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$status}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$g->getCurrLateCharged()}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$total_late_used}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$remaining}</td>
                </tr>
HTML;
        }
        $return .= <<<HTML
            </tbody>
        </table>
HTML;
        return $return;
    }
    
}
