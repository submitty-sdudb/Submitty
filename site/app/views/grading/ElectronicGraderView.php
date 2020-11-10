<?php

namespace app\views\grading;

use app\controllers\student\LateDaysTableController;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\gradeable\Gradeable;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\RegradeRequest;
use app\models\SimpleStat;
use app\models\Team;
use app\models\User;
use app\views\AbstractView;
use app\libraries\NumberUtils;
use app\libraries\CodeMirrorUtils;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array[] $sections
     * @param SimpleStat[] $component_averages
     * @param SimpleStat|null $autograded_average
     * @param SimpleStat|null $overall_average
     * @param int $total_submissions
     * @param int $registered_but_not_rotating
     * @param int $rotating_but_not_registered
     * @param int $viewed_grade
     * @param string $section_type
     * @param int $regrade_requests
     * @param bool $show_warnings
     * @return string
     */

    public function statusPage(
        Gradeable $gradeable,
        array $sections,
        array $component_averages,
        $autograded_average,
        $overall_scores,
        $overall_average,
        int $total_submissions,
        int $individual_viewed_grade,
        int $total_students_submitted,
        int $registered_but_not_rotating,
        int $rotating_but_not_registered,
        int $viewed_grade,
        string $section_type,
        int $regrade_requests,
        bool $show_warnings
    ) {

        $peer = false;
        if ($gradeable->isPeerGrading()) {
            $peer = true;
        }
        $graded = 0;
        $total = 0;
        $no_team_total = 0;
        $team_total = 0;
        $team_percentage = 0;
        $total_students = 0;
        $graded_total = 0;
        $submitted_total = 0;
        $submitted_percentage = 0;
        $submitted_percentage_peer = 0;
        $peer_total = 0;
        $peer_graded = 0;
        $peer_percentage = 0;
        $entire_peer_graded = 0;
        $entire_peer_total = 0;
        $total_grading_percentage = 0;
        $entire_peer_percentage = 0;
        $viewed_total = 0;
        $viewed_percent = 0;
        $overall_total = 0;
        $overall_percentage = 0;
        $autograded_percentage = 0;
        $component_percentages = [];
        $component_overall_score = 0;
        $component_overall_max = 0;
        $component_overall_percentage = 0;
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));

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
        if ($total === 0 && $no_team_total === 0) {
            $graded_percentage = -1;
        }
        elseif ($total === 0 && $no_team_total > 0) {
            $graded_percentage = 0;
        }
        else {
            $graded_percentage = number_format(($graded / $total) * 100, 1);
        }

        if ($graded_percentage !== -1) {
            if ($gradeable->isTeamAssignment()) {
                $total_students = $team_total + $no_team_total;
            }
            else {
                $total_students = $total_submissions;
            }
            $num_peer_components = 0;
            $num_non_peer_components = count($gradeable->getNonPeerComponents());
            $num_components = $num_peer_components + $num_non_peer_components;
            $submitted_total = $num_components > 0 ? $total : 0;
            $graded_total = $num_components > 0 ? round($graded / $num_components, 2) : 0;
            if ($submitted_total > 0) {
                $total_grading_percentage =  number_format(($graded_total / $submitted_total ) * 100, 1);
            }
            else {
                $total_grading_percentage = 0;
            }
            if ($peer) {
                $num_peer_components = count($gradeable->getPeerComponents());
                $num_non_peer_components = count($gradeable->getNonPeerComponents());
                $num_components = $num_peer_components + $num_non_peer_components;
                $graded_total = $num_non_peer_components > 0 ? round($graded / $num_non_peer_components, 2) : 0;
                $submitted_total = $num_components > 0 ? $total : 0;
            }
            if ($total_submissions != 0) {
                $submitted_percentage = round((($submitted_total) / $total_submissions) * 100, 1);
            }
            //Add warnings to the warnings array to display them to the instructor.
            $warnings = [];
            if ($section_type === "rotating_section" && $show_warnings) {
                if ($registered_but_not_rotating > 0) {
                    array_push($warnings, "There are " . $registered_but_not_rotating . " registered students without a rotating section.");
                }
                if ($rotating_but_not_registered > 0) {
                    array_push($warnings, "There are " . $rotating_but_not_registered . " unregistered students with a rotating section.");
                }
            }

            if ($gradeable->isTeamAssignment()) {
                $team_percentage = $total_students != 0 ? round(($team_total / $total_students) * 100, 1) : 0;
            }
            if ($peer) {
                $peer_count = count($gradeable->getPeerComponents());
                $entire_peer_total = 0;
                $total_students_submitted = 0;
                $total_grading_percentage = 0;
                $entire_peer_graded = 0;
                $entire_peer_percentage = 0;
                if ($peer_count > 0 && array_key_exists("peer_stu_grad", $sections)) {
                    if ($num_peer_components > 0) {
                        $total_students_submitted =  floor(($sections['peer_stu_grad']['total_who_submitted']));
                        $submitted_percentage_peer = round((($total_students_submitted) / $total_submissions) * 100, 1);
                        $total_grading_percentage =  number_format(($graded_total / $total_students_submitted ) * 100, 1);
                        $entire_peer_total =  floor(($sections['peer_stu_grad']['total_who_submitted']));
                        $entire_peer_graded =  $sections['peer_stu_grad']['view_peer_graded_components'] / $num_peer_components;
                    }
                    if ($entire_peer_total > 0) {
                        $entire_peer_percentage = number_format(($entire_peer_graded / ($entire_peer_total) ) * 100, 1);
                    }
                    else {
                        $entire_peer_percentage = 0;
                    }
                }
                if ($peer_count > 0 && array_key_exists("stu_grad", $sections)) {
                    if ($num_components > 0) {
                        $peer_total =  floor(($sections['stu_grad']['total_components']) / $num_peer_components);
                        $peer_graded =  round($sections['stu_grad']['graded_components'] / $num_peer_components, 2);
                        $peer_percentage = number_format(($sections['stu_grad']['graded_components'] / ($sections['stu_grad']['total_components'] * $sections['stu_grad']['num_gradeables'])) * 100, 1);
                        // Correct the below code when Teams work well with randomization
                        if ($gradeable->isTeamAssignment()) {
                            $peer_total =  floor(($sections['stu_grad']['total_components']) / $num_peer_components);
                            $peer_percentage = number_format(($sections['stu_grad']['graded_components'] / ($sections['stu_grad']['total_components'] * $sections['stu_grad']['num_gradeables'])) * 100, 1);
                        }
                    }
                    if ($peer_total > 0) {
                        $peer_percentage = number_format(($peer_graded / ($peer_total) ) * 100, 1);
                    }
                    else {
                        $peer_percentage = 0;
                    }
                }
            }
            foreach ($sections as $key => &$section) {
                $non_peer_components_count = count($gradeable->getNonPeerComponents());
                $non_zero_non_peer_components_count = $non_peer_components_count != 0 ? $non_peer_components_count : 1;
                $section['graded'] = round($section['graded_components'] / $non_zero_non_peer_components_count, 1);
                $section['total'] = $section['total_components'];
                if ($section['total_components'] == 0) {
                    $section['percentage'] = 0;
                }
                else {
                    $section['percentage'] = number_format(($section['graded'] / $section['total']) * 100, 1);
                }
            }
                unset($section); // Clean up reference

            if ($gradeable->isTaGradeReleased()) {
                if ($peer) {
                    $viewed_total = $entire_peer_total;
                }
                else {
                    $viewed_total = $total;
                }
                $viewed_percent = number_format(($viewed_grade / max($viewed_total, 1)) * 100, 1);
                $individual_viewed_percent = $total_submissions == 0 ? 0 :
                    number_format(($individual_viewed_grade / $total_submissions) * 100, 1);
            }
            if (!$peer) {
                if ($overall_average !== null) {
                    $overall_total = $overall_average->getMaxValue() + $gradeable->getAutogradingConfig()->getTotalNonExtraCredit();
                    if ($overall_total != 0) {
                        $overall_percentage = round($overall_average->getAverageScore() / $overall_total * 100);
                    }
                }
                if ($autograded_average !== null) {
                    if ($gradeable->getAutogradingConfig()->getTotalNonExtraCredit() !== 0 && $autograded_average->getCount() !== 0) {
                        $autograded_percentage = round($autograded_average->getAverageScore() / $gradeable->getAutogradingConfig()->getTotalNonExtraCredit() * 100);
                    }
                }
                if (count($component_averages) !== 0) {
                    foreach ($component_averages as $comp) {
                        /* @var SimpleStat $comp */
                        $component_overall_score += $comp->getAverageScore();
                        $component_overall_max += $comp->getMaxValue();
                        $percentage = 0;
                        if ($comp->getMaxValue() != 0) {
                            $percentage = round($comp->getAverageScore() / $comp->getMaxValue() * 100);
                        }
                        $component_percentages[] = $percentage;
                    }
                    if ($component_overall_max != 0) {
                        $component_overall_percentage = round($component_overall_score / $component_overall_max * 100);
                    }
                }
                //This else encompasses the above calculations for Teamss
                //END OF ELSE
            }
        }

        //determines if there are any valid rotating sections
        $no_rotating_sections = false;
        if (count($sections) === 0) {
            $no_rotating_sections = true;
        }
        else {
            if ($gradeable->isTeamAssignment()) {
                $valid_teams_or_students = 0;
                foreach ($sections as $section) {
                    $valid_teams_or_students += $section['no_team'] + $section['team'];
                }
                $no_rotating_sections = $valid_teams_or_students === 0;
            }
        }
        $details_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/Status.twig", [
            "gradeable_id" => $gradeable->getId(),
            "gradeable_title" => $gradeable->getTitle(),
            "team_assignment" => $gradeable->isTeamAssignment(),
            "ta_grades_released" => $gradeable->isTaGradeReleased(),
            "rotating_sections_error" => (!$gradeable->isGradeByRegistration()) && $no_rotating_sections
                && $this->core->getUser()->getGroup() == User::GROUP_INSTRUCTOR,
            "autograding_non_extra_credit" => $gradeable->getAutogradingConfig()->getTotalNonExtraCredit(),
            "peer" => $peer,
            "team_total" => $team_total,
            "team_percentage" => $team_percentage,
            "total_students" => $total_students,
            "total_submissions" => $total_submissions,
            "no_team_total"   => $no_team_total,
            "submitted_total" => $submitted_total,
            "submitted_percentage" => $submitted_percentage,
            "submitted_percentage_peer" => $submitted_percentage_peer,
            "graded_total" => $graded_total,
            "graded_percentage" => $graded_percentage,
            "peer_total" => $peer_total,
            "peer_graded" => $peer_graded,
            "peer_percentage" => $peer_percentage,
            "entire_peer_total" => $entire_peer_total,
            "total_grading_percentage" => $total_grading_percentage,
            "entire_peer_graded" => $entire_peer_graded,
            "entire_peer_percentage" => $entire_peer_percentage,
            "sections" => $sections,
            "viewed_grade" => $viewed_grade,
            "viewed_total" => $viewed_total,
            "viewed_percent" => $viewed_percent,
            "overall_average" => $overall_average,
            "overall_scores" => $overall_scores,
            "overall_total" => $overall_total,
            "overall_percentage" => $overall_percentage,
            "autograded_percentage" => $autograded_percentage,
            "autograded_average" => $autograded_average,
            "component_averages" => $component_averages,
            "component_percentages" => $component_percentages,
            "component_overall_score" => $component_overall_score,
            "component_overall_max" => $component_overall_max,
            "component_overall_percentage" => $component_overall_percentage,
            "individual_viewed_grade" => $individual_viewed_grade,
            "total_students_submitted" => $total_students_submitted,
            "individual_viewed_percent" => $individual_viewed_percent ?? 0,
            "regrade_requests" => $regrade_requests,
            "download_zip_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'download_zip']),
            "bulk_stats_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'bulk_stats']),
            "details_url" => $details_url,
            "details_view_all_url" => $details_url . '?' . http_build_query(['view' => 'all']),
            "grade_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'grade']),
            "regrade_allowed" => $this->core->getConfig()->isRegradeEnabled(),
            "grade_inquiry_per_component_allowed" => $gradeable->isGradeInquiryPerComponentAllowed(),
        ]);
    }

    public function statPage($users) {

        $gradeable_id = $_REQUEST['gradeable_id'] ?? '';

        $return = <<<HTML

		<div class="content_upload_content">

HTML;
        $this->core->getOutput()->addBreadcrumb("Bulk Upload Forensics", $this->core->buildCourseUrl(['gradeable', $gradeable_id, 'bulk_stats']));

        $return .= <<<HTML
			<div style="padding-left:20px;padding-bottom: 10px;border-radius:3px;padding-right:20px;">
				<table class="table table-striped table-bordered persist-area" id="content_upload_table">
					<tr>
				        <th style = "cursor:pointer;width:25%" id="user_down">User &darr;</th>
				        <th style = "cursor:pointer;width:25%" id="upload_down">Upload Timestamp</th>
				        <th style = "cursor:pointer;width:25%" id="submission_down">Submission Timestamp</th>
				        <th style = "cursor:pointer;width:25%" id="filepath_down">Filepath</th>
					</tr>
HTML;

        foreach ($users as $user => $details) {
            $first_name = htmlspecialchars($details["first_name"]);
            $last_name = htmlspecialchars($details["last_name"]);
            $upload_timestamp = $details["upload_time"];
            $submit_timestamp = $details["submit_time"];
            $filepath = htmlspecialchars($details["file"]);

            $return .= <<<HTML
			<tbody>
				<tr>
					<td>{$last_name}, {$first_name}</td>
                    <td>{$upload_timestamp}</td>
                    <td>{$submit_timestamp}</td>
                    <td>{$filepath}</td>
				</tr>
			</tbody>
HTML;
        }

        $return .= <<<HTML
				</table>
			</div>
			</div>

			<script>
				$("td").click(function(){
					if($(this).attr('id')=="user_down"){
						sortTable(0);
					}
					if($(this).attr('id')=="upload_down"){
						sortTable(1);
					}
					if($(this).attr('id')=="submission_down"){
						sortTable(2);
					}
					if($(this).attr('id')=="filepath_down"){
						sortTable(3);
					}

				});

				function sortTable(sort_element_index){
					var table = document.getElementById("content_upload_table");
					var switching = true;
					while(switching){
						switching=false;
						var rows = table.getElementsByTagName("TBODY");
						for(var i=1;i<rows.length-1;i++){

							var a = rows[i].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
							var b = rows[i+1].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
                            // sorted alphabetically by last name or by earliest time
							if((sort_element_index >= 0 && sort_element_index <= 3) ? a.innerHTML>b.innerHTML : parseInt(a.innerHTML) < parseInt(b.innerHTML)){
								rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
								switching=true;
							}
						}
					}

					var row0 = table.getElementsByTagName("TBODY")[0].getElementsByTagName("TR")[0];
					var headers = row0.getElementsByTagName("TD");

					for(var i = 0;i<headers.length;i++){
						var index = headers[i].innerHTML.indexOf(' ↓');

						if(index> -1){

							headers[i].innerHTML = headers[i].innerHTML.substr(0, index);
							break;
						}
					}

					headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↓';

				}

			</script>
HTML;
        return $return;
    }



    /**
     * @param Gradeable $gradeable
     * @param GradedGradeable[] $graded_gradeables,
     * @param User[] $teamless_users
     * @param array $graders
     * @param Team[] $empty_teams
     * @param bool $show_all_sections_button
     * @param bool $show_import_teams_button
     * @param bool $show_export_teams_button
     * @param bool $show_edit_teams
     * @return string
     */
    public function detailsPage(Gradeable $gradeable, $graded_gradeables, $teamless_users, $graders, $empty_teams, $show_all_sections_button, $show_import_teams_button, $show_export_teams_button, $show_edit_teams, $past_grade_start_date, $view_all, $sort, $direction) {

        $peer = false;
        if ($gradeable->isPeerGrading() && $this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $peer = true;
        }

        //Each table column is represented as an array with the following entries:
        // width => how wide the column should be on the page, <td width=X>
        // title => displayed title in the table header
        // function => maps to a macro in Details.twig:render_student
        $columns = [];
        if ($peer) {
            $columns[]         = ["width" => "5%",  "title" => "",                 "function" => "index"];
            if ($gradeable->isTeamAssignment()) {
                $columns[] = ["width" => "30%", "title" => "Team Members",     "function" => "team_members_anon"];
            }
            else {
                $columns[]         = ["width" => "30%", "title" => "Student",          "function" => "user_id_anon"];
            }
            if ($gradeable->isTaGrading()) {
                $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
            }
            // NOTE/REDESIGN FIXME: We might have autograding that is
            // penalty only.  The available positive autograding
            // points might be zero.  Testing for autograding > 1 is
            // ignoring the submission limit test case... but this is
            // also imperfect.  We want to render the column if any
            // student has received the penalty.  But if no one has
            // received the penalty maybe we omit it?  (expensive?/confusing?)
            // See also note in ElectronicGradeController.php
            if (count($gradeable->getAutogradingConfig()->getTestCases()) > 1) {
                //if ($gradeable->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit() !== 0) {
                $columns[]     = ["width" => "15%", "title" => "Autograding",      "function" => "autograding_peer"];
                $columns[]     = ["width" => "20%", "title" => "Manual Grading",          "function" => "grading_peer"];
                $columns[]     = ["width" => "15%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            }
            else {
                $columns[]     = ["width" => "30%", "title" => "Grading",          "function" => "grading_peer"];
                $columns[]     = ["width" => "20%", "title" => "Total",            "function" => "total_peer"];
                $columns[]     = ["width" => "15%", "title" => "Active Version",   "function" => "active_version"];
            }
        }
        else {
            if ($gradeable->isTeamAssignment()) {
                if ($show_edit_teams) {
                    $columns[] = ["width" => "2%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "8%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "5%",  "title" => "Edit Teams",       "function" => "team_edit"];
                    $columns[] = ["width" => "10%", "title" => "Team Id",          "function" => "team_id", "sort_type" => "id"];
                    $columns[] = ["width" => "32%", "title" => "Team Members",     "function" => "team_members"];
                }
                else {
                    $columns[] = ["width" => "3%",  "title" => "",                 "function" => "index"];
                    $columns[] = ["width" => "5%",  "title" => "Section",          "function" => "section"];
                    $columns[] = ["width" => "50%", "title" => "Team Members",     "function" => "team_members"];
                }
            }
            else {
                $columns[]     = ["width" => "2%",  "title" => "",                 "function" => "index"];
                $columns[]     = ["width" => "8%", "title" => "Section",          "function" => "section"];
                $columns[]     = ["width" => "13%", "title" => "User ID",          "function" => "user_id", "sort_type" => "id"];
                $columns[]     = ["width" => "15%", "title" => "First Name",       "function" => "user_first", "sort_type" => "first"];
                $columns[]     = ["width" => "15%", "title" => "Last Name",        "function" => "user_last", "sort_type" => "last"];
            }
            // NOTE/REDESIGN FIXME: Same note as above.
            if (count($gradeable->getAutogradingConfig()->getTestCases()) > 1) {
                //if ($gradeable->getAutogradingConfig()->getTotalNonExtraCredit() !== 0) {
                $columns[]     = ["width" => "9%",  "title" => "Autograding",      "function" => "autograding"];
                if ($gradeable->isTaGrading()) {
                    $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                }
                $columns[]     = ["width" => "8%",  "title" => "Manual Grading",       "function" => "grading"];
                $columns[]     = ["width" => "7%",  "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->isTaGradeReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            }
            else {
                if ($gradeable->isTaGrading()) {
                    $columns[]     = ["width" => "8%",  "title" => "Graded Questions", "function" => "graded_questions"];
                }
                $columns[]     = ["width" => "12%", "title" => "Manual Grading",       "function" => "grading"];
                $columns[]     = ["width" => "12%", "title" => "Total",            "function" => "total"];
                $columns[]     = ["width" => "10%", "title" => "Active Version",   "function" => "active_version"];
                if ($gradeable->isTaGradeReleased()) {
                    $columns[] = ["width" => "8%",  "title" => "Viewed Grade",     "function" => "viewed_grade"];
                }
            }
        }

        //Convert rows into sections and prepare extra row info for things that
        // are too messy to calculate in the template.
        $sections = [];
        /** @var GradedGradeable $row */
        foreach ($graded_gradeables as $row) {
            //Extra info for the template
            $info = [
                "graded_gradeable" => $row
            ];

            if ($peer) {
                $section_title = "PEER STUDENT GRADER";
            }
            elseif ($gradeable->isGradeByRegistration()) {
                $section_title = $row->getSubmitter()->getRegistrationSection();
            }
            else {
                $section_title = $row->getSubmitter()->getRotatingSection();
            }
            if ($section_title === null) {
                $section_title = "NULL";
            }

            if (isset($graders[$section_title]) && count($graders[$section_title]) > 0) {
                $section_grader_ids = [];
                foreach ($graders[$section_title] as $user) {
                    if ($user->getGroup() <= $gradeable->getMinGradingGroup()) {
                        $section_grader_ids[] = $user->getId();
                    }
                }
                if (count($section_grader_ids) > 0) {
                    $section_graders = implode(", ", $section_grader_ids);
                }
                else {
                    $section_graders = "Nobody";
                }
            }
            else {
                $section_graders = "Nobody";
            }

            if ($peer) {
                $section_graders = $this->core->getUser()->getId();
            }

            //Team edit button, specifically the onclick event.
            if ($gradeable->isTeamAssignment()) {
                $reg_section = ($row->getSubmitter()->getRegistrationSection() === null) ? "NULL" : $row->getSubmitter()->getRegistrationSection();
                $rot_section = ($row->getSubmitter()->getRotatingSection() === null) ? "NULL" : $row->getSubmitter()->getRotatingSection();
                $user_assignment_setting_json = json_encode($row->getSubmitter()->getTeam()->getAssignmentSettings($gradeable));
                $members = json_encode($row->getSubmitter()->getTeam()->getMembers());
                $pending_members = $row->getSubmitter()->getTeam()->getInvitations();
                $pending_members_json = json_encode($pending_members);
                $multiple_invites = [];
                foreach ($pending_members as $pending_member_id) {
                    $pending_member = $this->core->getQueries()->getUserById($pending_member_id);
                    $multiple_invites[] = $pending_member->hasMultipleTeamInvites($gradeable->getId());
                }
                $multiple_invites_json = json_encode($multiple_invites);
                $lock_date = DateUtils::dateTimeToString($gradeable->getTeamLockDate(), false);
                $info["team_edit_onclick"] = "adminTeamForm(false, '{$row->getSubmitter()->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, {$members}, {$pending_members_json}, {$multiple_invites_json}, {$gradeable->getTeamSizeMax()},'{$lock_date}');";
                $team_history = ($row->getSubmitter()->getTeam()->getAssignmentSettings($gradeable))["team_history"];
                $last_edit_date = ($team_history == null || count($team_history) == 0) ? null : $team_history[count($team_history) - 1]["time"];
                $edited_past_lock_date = ($last_edit_date == null) ? false : (DateUtils::calculateDayDiff($last_edit_date, $gradeable->getTeamLockDate()) < 0);
                $info["edited_past_lock_date"] = $edited_past_lock_date;
            }

            //List of graded components
            $info["graded_groups"] = [];
            foreach ($gradeable->getComponents() as $component) {
                $graded_component = $row->getOrCreateTaGradedGradeable()->getGradedComponent($component, $this->core->getUser());
                $grade_inquiry = $graded_component !== null ? $row->getGradeInquiryByGcId($graded_component->getComponentId()) : null;

                if ($component->isPeer() && $row->getOrCreateTaGradedGradeable()->isComplete()) {
                    $info["graded_groups"][] = 4;
                }
                elseif (($component->isPeer() && $graded_component != null)) {
                    //peer submitted and graded
                    $info["graded_groups"][] = 4;
                }
                elseif (($component->isPeer() && $graded_component === null)) {
                    //peer submitted but not graded
                    $info["graded_groups"][] = "peer-null";
                }
                elseif ($component->isPeer() && !$row->getOrCreateTaGradedGradeable()->isComplete()) {
                    //peer not submitted
                    $info["graded_groups"][] = "peer-no-submission";
                }
                elseif ($graded_component === null) {
                    //non-peer not graded
                    $info["graded_groups"][] = "NULL";
                }
                elseif ($grade_inquiry !== null && $grade_inquiry->getStatus() == RegradeRequest::STATUS_ACTIVE && $gradeable->isGradeInquiryPerComponentAllowed()) {
                    $info["graded_groups"][] = "grade-inquiry";
                }
                elseif (!$graded_component->getVerifier()) {
                    //no verifier exists, show the grader group
                    $info["graded_groups"][] = $graded_component->getGrader()->getGroup();
                }
                elseif ($graded_component->getGrader()->accessFullGrading()) {
                    //verifier exists and original grader is full access, show verifier grader group
                    $info["graded_groups"][] = $graded_component->getVerifier()->getGroup();
                }
                else {
                    //verifier exists and limited access grader, change the group to show semicircle on the details page
                    $info["graded_groups"][] = "verified";
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

        // TODO: this duplication is not ideal
        foreach ($teamless_users as $teamless_user) {
            //Extra info for the template
            $info = [
                "user" => $teamless_user
            ];

            if ($peer) {
                $section_title = "PEER STUDENT GRADER";
            }
            elseif ($gradeable->isGradeByRegistration()) {
                $section_title = $teamless_user->getRegistrationSection();
            }
            else {
                $section_title = $teamless_user->getRotatingSection();
            }
            if ($section_title === null) {
                $section_title = "NULL";
            }

            if (isset($graders[$section_title]) && count($graders[$section_title]) > 0) {
                $section_graders = implode(", ", array_map(function (User $user) {
                    return $user->getId();
                }, $graders[$section_title]));
            }
            else {
                $section_graders = "Nobody";
            }
            if ($peer) {
                $section_graders = $this->core->getUser()->getId();
            }

            //Team edit button, specifically the onclick event.
            $reg_section = $teamless_user->getRegistrationSection() ?? 'NULL';
            $rot_section = $teamless_user->getRotatingSection() ?? 'NULL';
            $lock_date = DateUtils::dateTimeToString($gradeable->getTeamLockDate(), false);
            $info['new_team_onclick'] = "adminTeamForm(true, '{$teamless_user->getId()}', '{$reg_section}', '{$rot_section}', [], [], [], [], {$gradeable->getTeamSizeMax()},'{$lock_date}');";

            //-----------------------------------------------------------------
            // Now insert this student into the list of sections

            $found = false;
            for ($i = 0; $i < count($sections); $i++) {
                if ($sections[$i]["title"] === $section_title) {
                    $found = true;
                    $sections[$i]["teamless_users"][] = $info;
                    break;
                }
            }
            //Not found? Create it
            if (!$found) {
                $sections[] = ["title" => $section_title, "teamless_users" => [$info], "graders" => $section_graders];
            }
        }

        //sorts sections numerically, NULL always at the end
        usort($sections, function ($a, $b) {
            return ($a['title'] == 'NULL' || $b['title'] == 'NULL') ? ($a['title'] == 'NULL') : ($a['title'] > $b['title']);
        });


        $empty_team_info = [];
        foreach ($empty_teams as $team) {
            /* @var Team $team */
            $user_assignment_setting_json = json_encode($row->getSubmitter()->getTeam()->getAssignmentSettings($gradeable));
            $reg_section = ($team->getRegistrationSection() === null) ? "NULL" : $team->getRegistrationSection();
            $rot_section = ($team->getRotatingSection() === null) ? "NULL" : $team->getRotatingSection();
            $lock_date = DateUtils::dateTimeToString($gradeable->getTeamLockDate(), false);

            $empty_team_info[] = [
                "team_edit_onclick" => "adminTeamForm(false, '{$team->getId()}', '{$reg_section}', '{$rot_section}', {$user_assignment_setting_json}, [], [], [], {$gradeable->getTeamSizeMax()},'{$lock_date}');"
            ];
        }

        $team_gradeable_view_history = $gradeable->isTeamAssignment() ? $this->core->getQueries()->getAllTeamViewedTimesForGradeable($gradeable) : [];
        foreach ($team_gradeable_view_history as $team_id => $team) {
            $not_viewed_yet = true;
            $hover_over_string = "";
            ksort($team_gradeable_view_history[$team_id]);
            ksort($team);
            foreach ($team as $user => $value) {
                if ($value != null) {
                    $not_viewed_yet = false;
                    $date_object = new \DateTime($value);
                    $hover_over_string .= "Viewed by " . $user . " at " . $date_object->format('F d, Y g:i') . "\n";
                }
                else {
                    $hover_over_string .= "Not viewed by " . $user . "\n";
                }
            }

            if ($not_viewed_yet) {
                $team_gradeable_view_history[$team_id]['hover_string'] = '';
            }
            else {
                $team_gradeable_view_history[$team_id]['hover_string'] = $hover_over_string;
            }
        }
        $details_base_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'details']);
        $this->core->getOutput()->addInternalCss('details.css');
        $this->core->getOutput()->addInternalJs('details.js');
        $this->core->getOutput()->addInternalJs('collapsible-panels.js');

        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/Details.twig", [
            "gradeable" => $gradeable,
            "sections" => $sections,
            "graders" => $graders,
            "empty_teams" => $empty_teams,
            "empty_team_info" => $empty_team_info,
            "team_gradeable_view_history" => $team_gradeable_view_history,
            "view_all" => $view_all,
            "show_all_sections_button" => $show_all_sections_button,
            "show_import_teams_button" => $show_import_teams_button,
            "show_export_teams_button" => $show_export_teams_button,
            "past_grade_start_date" => $past_grade_start_date,
            "columns" => $columns,
            "export_teams_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'teams', 'export']),
            "randomize_team_rotating_sections_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'teams', 'randomize_rotating']),
            "grade_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'grade']),
            "peer" => $peer,
            "details_base_url" => $details_base_url,
            "view_all_toggle_url" => $details_base_url . '?' .
                http_build_query(['view' => $view_all ? null : 'all', 'sort' => $sort, 'direction' => $sort === 'random' ? null : $direction]),
            "order_toggle_url" => $details_base_url . '?' .
                http_build_query(['view' => $view_all ? 'all' : null, 'sort' => $sort === 'random' ? null : 'random']),
            "sort" => $sort,
            "direction" => $direction
        ]);
    }

    public function adminTeamForm(Gradeable $gradeable, $all_reg_sections, $all_rot_sections, $students) {
        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("grading/AdminTeamForm.twig", [
            "gradeable_id" => $gradeable->getId(),
            "student_full" => $student_full,
            "view" => isset($_REQUEST["view"]) ? $_REQUEST["view"] : null,
            "all_reg_sections" => $all_reg_sections,
            "all_rot_sections" => $all_rot_sections,
            "csrf_token" => $this->core->getCsrfToken(),
            "team_submit_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'teams', 'new'])
        ]);
    }

    public function importTeamForm(Gradeable $gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/ImportTeamForm.twig", [
            "gradeable_id" => $gradeable->getId(),
            "csrf_token" => $this->core->getCsrfToken(),
            "team_import_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'teams', 'import'])
        ]);
    }


    public function randomizeButtonWarning(Gradeable $gradeable) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/RandomizeButtonWarning.twig", [
            "gradeable_id" => $gradeable->getId(),
            "randomize_team_rotating_sections_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'teams', 'randomize_rotating'])
        ]);
    }

    //The student not in section variable indicates that an full access grader is viewing a student that is not in their
    //assigned section. canViewWholeGradeable determines whether hidden testcases can be viewed.
    public function hwGradingPage(Gradeable $gradeable, GradedGradeable $graded_gradeable, int $display_version, float $progress, bool $show_hidden_cases, bool $can_inquiry, bool $can_verify, bool $show_verify_all, bool $show_silent_edit, string $late_status, $rollbackSubmission, $sort, $direction, $from, array $solution_ta_notes, array $submitter_itempool_map, $showNewInterface) {

        $this->core->getOutput()->addInternalCss('admin-gradeable.css');
        $isPeerPanel = false;
        $isStudentInfoPanel = true;
        $isDiscussionPanel = false;
        $isRegradePanel = false;
        // WIP: Replace this logic when there is a definitive way to get my peer-ness
        // If this is a peer gradeable but I am not allowed to view the peer panel, then I must be a peer.
        if ($gradeable->isPeerGrading()) {
            if ($this->core->getUser()->getGroup() !== 4) {
                $isPeerPanel = true;
                $isStudentInfoPanel = true;
            }
            else {
                $isPeerPanel = false;
                $isStudentInfoPanel = false;
            }
        }
        if ($graded_gradeable->getGradeable()->isDiscussionBased()) {
            $isDiscussionPanel = true;
        }
        if ($this->core->getConfig()->isRegradeEnabled()) {
            $isRegradePanel = true;
        }
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('mermaid', 'mermaid.min.js'));
        $this->core->getOutput()->enableMobileViewport();

        $display_version_instance = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($display_version);

        $return = "";
        $is_notebook = $gradeable->getAutogradingConfig()->isNotebookGradeable();
        if ($showNewInterface) {
            $this->core->getOutput()->addInternalJs("resizable-panels.js");

            $return .= <<<HTML
        		<div class="content" id="electronic-gradeable-container">
        		    <div class="content-items-container">
                    <div class="content-item content-item-right">
HTML;
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderNavigationBar', $graded_gradeable, $progress, $gradeable->isPeerGrading(), $sort, $direction, $from, $showNewInterface);
            $return .= $this->core->getOutput()->renderTemplate(
                ['grading', 'ElectronicGrader'],
                'renderGradingPanelHeader',
                $isPeerPanel,
                $isStudentInfoPanel,
                $isDiscussionPanel,
                $isRegradePanel,
                $gradeable->getAutogradingConfig()->isNotebookGradeable()
            );

            $return .= <<<HTML
                <div class="panels-container">
                    <div class="two-panel-cont">
                         <div class="two-panel-item two-panel-left active">
                            <div class="panel-item-section left-top"></div>
                            <div class="panel-item-section-drag-bar panel-item-left-drag"></div>
                            <div class="panel-item-section left-bottom"></div>
                         </div>
                         <div class="two-panel-drag-bar active">
                         </div>
                         <div class="two-panel-item two-panel-right">
                            <div class="panel-item-section right-top"></div>
                            <div class="panel-item-section-drag-bar panel-item-right-drag"></div>
                            <div class="panel-item-section right-bottom"></div>
                         </div>   
                    </div>
HTML;
        }
        else {
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderNavigationBar', $graded_gradeable, $progress, $gradeable->isPeerGrading(), $sort, $direction, $from, $showNewInterface);
        }

        $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderAutogradingPanel', $display_version_instance, $show_hidden_cases, $showNewInterface);
        $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderSubmissionPanel', $graded_gradeable, $display_version, $showNewInterface);
        //If TA grading isn't enabled, the rubric won't actually show up, but the template should be rendered anyway to prevent errors, as the code references the rubric panel
        $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderRubricPanel', $graded_gradeable, $display_version, $can_verify, $show_verify_all, $show_silent_edit, $showNewInterface);
        $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderSolutionTaNotesPanel', $gradeable, $solution_ta_notes, $submitter_itempool_map, $showNewInterface);

        if ($isPeerPanel) {
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderPeerPanel', $graded_gradeable, $display_version, $showNewInterface);
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderPeerEditMarksPanel', $graded_gradeable);
        }
        if ($isDiscussionPanel) {
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderDiscussionForum', json_decode($graded_gradeable->getGradeable()->getDiscussionThreadId(), true), $graded_gradeable->getSubmitter(), $graded_gradeable->getGradeable()->isTeamAssignment(), $showNewInterface);
        }

        if ($is_notebook) {
            $this->core->getOutput()->addInternalJs('gradeable-notebook.js');
            $this->core->getOutput()->addInternalCss('gradeable-notebook.css');
            $this->core->getOutput()->addInternalCss('submitbox.css');
            /*Prevents notebook from throwing errors since it depends
            * on file upload to be initialized but might not be good
            * to import the entire drag-and-drop js file into grading
            */
            $this->core->getOutput()->addInternalJs('drag-and-drop.js');

            $notebook_model = $gradeable->getAutogradingConfig()->getUserSpecificNotebook(
                $graded_gradeable->getSubmitter()->getId(),
                $gradeable->getId()
            );

            $notebook = $notebook_model->getNotebook();
            $image_data = $notebook_model->getImagePaths();
            $testcase_messages = $display_version_instance !== null ? $display_version_instance->getTestcaseMessages() : [];
            $highest_version = $graded_gradeable->getAutoGradedGradeable()->getHighestVersion();

            $notebook_data = $notebook_model->getMostRecentNotebookSubmissions(
                $highest_version,
                $notebook,
                $graded_gradeable->getSubmitter()->getId()
            );

            $old_files = [];
            if ($display_version_instance !== null) {
                for ($i = 1; $i <= $notebook_model->getNumParts(); $i++) {
                    foreach ($display_version_instance->getPartFiles($i)['submissions'] as $file) {
                        $old_files[] = [
                            'name' => str_replace('\'', '\\\'', $file['name']),
                            'size' => number_format($file['size'] / 1024, 2),
                            'part' => $i
                        ];
                    }
                }
            }

            $return .= $this->core->getOutput()->renderTemplate(
                ['grading', 'ElectronicGrader'],
                'renderNotebookPanel',
                $notebook_data,
                $testcase_messages,
                $image_data,
                $gradeable->getId(),
                $highest_version,
                $old_files,
                $graded_gradeable->getSubmitter()->getId()
            );
        }

        CodeMirrorUtils::loadDefaultDependencies($this->core);

        if ($isStudentInfoPanel) {
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderInformationPanel', $graded_gradeable, $display_version_instance, $showNewInterface);
        }
        if ($this->core->getConfig()->isRegradeEnabled() && $this->core->getUser()->getGroup() < 4) {
            $return .= $this->core->getOutput()->renderTemplate(['grading', 'ElectronicGrader'], 'renderRegradePanel', $graded_gradeable, $can_inquiry, $showNewInterface);
        }

        if ($graded_gradeable->hasOverriddenGrades()) {
            $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                "color" => "var(--standard-vibrant-yellow)", // canary yellow
                "message" => "Overridden grades"
            ]);
        }
        elseif ($graded_gradeable->getAutoGradedGradeable()->getActiveVersion() === 0) {
            if ($graded_gradeable->getAutoGradedGradeable()->hasSubmission()) {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                    "color" => "var(--standard-creamsicle-orange)", // mango orange
                    "message" => "Cancelled Submission"
                ]);
            }
            else {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                    "color" => "var(--standard-light-pink)", // lipstick pink (purple)
                    "message" => "No Submission"
                ]);
            }
        }
        elseif ($rollbackSubmission != -1) {
            $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                "color" => "var(--standard-creamsicle-orange)", // fire engine red
                "message" => "Late Submission (Rollback to on-time submission - " . $rollbackSubmission . ")"
            ]);
        }
        elseif ($late_status != LateDayInfo::STATUS_GOOD && $late_status != LateDayInfo::STATUS_LATE) {
            $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/ErrorMessage.twig", [
                "color" => "var(--standard-red-orange)", // fire engine red
                "message" => "Late Submission (No on time submission available)"
            ]);
        }
        elseif ($graded_gradeable->getAutoGradedGradeable()->hasSubmission() && count($display_version_instance->getFiles()["submissions"]) > 1 && $graded_gradeable->getGradeable()->isScannedExam()) {
            $pattern1 = "upload.pdf";
            $pattern2 = "/upload_page_\d+/";
            $pattern3 = "/upload_version_\d+_page\d+/";
            $pattern4 = ".submit.timestamp";
            $pattern5 = "bulk_upload_data.json";

            $pattern_match_flag = false;
            foreach ($display_version_instance->getFiles()["submissions"] as $key => $value) {
                if ($pattern1 != $key && !preg_match($pattern2, $key) && !preg_match($pattern3, $key) && $pattern4 != $key && $pattern5 != $key) {
                    $pattern_match_flag = true;
                }
            }

            // This would be more dynamic if $display_version_instance included an expected number, requires more database changes
            if ($pattern_match_flag == true) {
                $return .= $this->core->getOutput()->renderTwigTemplate("grading/electronic/InformationMessage.twig", [
                    "message" => "Multiple files within submissions"
                ]);
            }
        }
        if ($showNewInterface) {
            $return .= <<<HTML
                    </div>
                </div>
            </div>
		</div>
HTML;
        }
        return $return;
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param float $progress
     * @param string $prev_id
     * @param string $next_id
     * @param bool $peer
     * @param string $sort
     * @param string $direction
     * @return string
     */
    public function renderNavigationBar(GradedGradeable $graded_gradeable, float $progress, bool $peer, $sort, $direction, $from, $showNewInterface) {
        $home_url = $this->core->buildCourseUrl(['gradeable', $graded_gradeable->getGradeableId(), 'grading', 'details']) . '?' . http_build_query(['sort' => $sort, 'direction' => $direction, 'view' => (count($this->core->getUser()->getGradingRegistrationSections()) == 0) ? 'all' : null ]);

        $studentBaseUrl = $this->core->buildCourseUrl(['gradeable', $graded_gradeable->getGradeableId(), 'grading', 'grade']);
        $templateFile = "grading/electronic/NavigationBar.twig";
        if ($showNewInterface) {
            $studentBaseUrl = $this->core->buildCourseUrl(['gradeable', $graded_gradeable->getGradeableId(), 'grading', 'grade', 'beta']);
            $templateFile = "grading/electronic/NavigationBarV2.twig";
        }
        // Setup urls for prev and next students
        $prev_student_url = $studentBaseUrl . '?' . http_build_query(['sort' => $sort, 'direction' => $direction, 'from' => $from, 'to' => 'prev', 'to_ungraded' => 'false' ]);
        $next_student_url = $studentBaseUrl . '?' . http_build_query(['sort' => $sort, 'direction' => $direction, 'from' => $from, 'to' => 'next', 'to_ungraded' => 'false' ]);

        // Setup urls for prev and next ungraded students
        $prev_ungraded_student_url = $studentBaseUrl . '?' . http_build_query(['sort' => $sort, 'direction' => $direction, 'from' => $from, 'to' => 'prev', 'to_ungraded' => 'true']);
        $next_ungraded_student_url =  $studentBaseUrl . '?' . http_build_query(['sort' => $sort, 'direction' => $direction, 'from' => $from, 'to' => 'next', 'to_ungraded' => 'true']);

        $i_am_a_peer = false;
        if ($peer && $this->core->getUser()->getGroup() == 4) {
            $i_am_a_peer = true;
        }

        return $this->core->getOutput()->renderTwigTemplate($templateFile, [
            "progress" => $progress,
            "peer_gradeable" => $peer,
            // WIP. Replace this with a better function call once there is a definitive way to determine my peer-ness.
            // For now, I am a peer if I cannot access the peer panel.
            "i_am_a_peer" => $i_am_a_peer,
            "prev_student_url" => $prev_student_url,
            "prev_ungraded_student_url" => $prev_ungraded_student_url,
            "next_student_url" => $next_student_url,
            "next_ungraded_student_url" => $next_ungraded_student_url,
            "home_url" => $home_url,
            'regrade_panel_available' => $this->core->getConfig()->isRegradeEnabled() && $this->core->getUser()->getGroup() < 4,
            'grade_inquiry_pending' => $graded_gradeable->hasActiveRegradeRequest(),
            'discussion_based' => $graded_gradeable->getGradeable()->isDiscussionBased()
        ]);
    }

    public function renderGradingPanelHeader(bool $isPeerPanel, bool $isStudentInfoPanel, bool $isDiscussionPanel, bool $isRegradePanel, bool $is_notebook): string {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/GradingPanelHeader.twig", [
            'isPeerPanel' => $isPeerPanel,
            'isStudentInfoPanel' => $isStudentInfoPanel,
            'isDiscussionPanel' => $isDiscussionPanel,
            'isRegradePanel' => $isRegradePanel,
            'is_notebook' => $is_notebook
        ]);
    }

    /**
     * Render the Autograding Testcases panel
     * @param AutoGradedVersion $version_instance
     * @param bool $show_hidden_cases
     * @param bool $showNewInterface
     * @return string
     */
    public function renderAutogradingPanel($version_instance, bool $show_hidden_cases, bool $showNewInterface) {
        $this->core->getOutput()->addInternalJs('submission-page.js');
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/AutogradingPanel.twig", [
            "showNewInterface" => $showNewInterface,
            "version_instance" => $version_instance,
            "show_hidden_cases" => $show_hidden_cases,
        ]);
    }

    public function renderDiscussionForum($threadIds, $submitter, $isTeam = false, $showNewInterface = false) {
        $posts_view = <<<HTML
            <span class="col grading_label">Discussion Posts</span>
HTML;

        $currentCourse = $this->core->getConfig()->getCourse();

        //Empty thread input
        if ($threadIds === "{}") {
            $threadIds = [];
        }
        $id = '';
        $submitters = [];
        if ($isTeam) {
            $submitters = explode(", ", $submitter->getTeam()->getMemberList());
            $id = $submitter->getTeam()->getId();
        }
        else {
            $id = $submitter->getId();
            $submitters = [$id];
        }
        foreach ($threadIds as $threadId) {
            $posts = [];
            foreach ($submitters as $s_id) {
                $posts = array_merge($posts, $this->core->getQueries()->getPostsForThread($this->core->getUser()->getId(), $threadId, false, 'time', $s_id));
            }
            if (count($posts) > 0) {
                $posts_view .= $this->core->getOutput()->renderTemplate('forum\ForumThread', 'generatePostList', $threadId, $posts, [], $currentCourse, false, true, $id);
            }
            else {
                $posts_view .= <<<HTML
                    <h3 style="text-align: center;">No posts for thread id: {$threadId}</h3> <br/>
HTML;
            }

            $posts_view .= <<<HTML
                    <a href="{$this->core->buildCourseUrl(['forum', 'threads', $threadId])}" target="_blank" rel="noopener nofollow" class="btn btn-default btn-sm" style="margin-top:15px; text-decoration: none;" onClick=""> Go to thread</a>
                    <hr style="border-top:1px solid #999;margin-bottom: 5px;" /> <br/>
HTML;
        }

        if (empty($threadIds)) {
            $posts_view .= <<<HTML
                <h3 style="text-align: center;">No thread id specified.</h3> <br/>
HTML;
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/DiscussionForumPanel.twig", [
            "showNewInterface" => $showNewInterface,
            "discussion_forum_content" => $posts_view
        ]);
    }

    /**
     * Replace the userId with the corresponding anon_id in the given file_path
     * @param string $file_path
     * @return string $anon_path
     */
    public function setAnonPath($file_path) {
        $file_path_parts = explode("/", $file_path);
        $anon_path = "";
        for ($index = 1; $index < count($file_path_parts); $index++) {
            if ($index == 9) {
                $user_id[] = $file_path_parts[$index];
                $anon_id = $this->core->getQueries()->getUsersOrTeamsById($user_id)[$user_id[0]]->getAnonId();
                $anon_path = $anon_path . "/" . $anon_id;
            }
            else {
                $anon_path = $anon_path . "/" . $file_path_parts[$index];
            }
        }
        return $anon_path;
    }

    /**
     * Render the Submissions and Results Browser panel
     * @param GradedGradeable $graded_gradeable
     * @param int $display_version
     * @param bool $showNewInterface
     * @return string by reference
     */
    public function renderSubmissionPanel(GradedGradeable $graded_gradeable, int $display_version, bool $showNewInterface) {
        $add_files = function (&$files, $new_files, $start_dir_name) {
            $files[$start_dir_name] = [];
            if ($new_files) {
                foreach ($new_files as $file) {
                    if ($start_dir_name == "submissions") {
                        $file["path"] = $this->setAnonPath($file["path"]);
                    }
                    $path = explode('/', $file['relative_name']);
                    array_pop($path);
                    $working_dir = &$files[$start_dir_name];
                    foreach ($path as $dir) {
                        if (!isset($working_dir[$dir])) {
                            $working_dir[$dir] = [];
                        }
                        $working_dir = &$working_dir[$dir];
                    }
                    $working_dir[$file['name']] = $file['path'];
                }
            }
        };
        $submissions = [];
        $results = [];
        $results_public = [];
        $checkout = [];

        // NOTE TO FUTURE DEVS: There is code around line 830 (ctrl-f openAll) which depends on these names,
        // if you change here, then change there as well
        // order of these statements matter I believe
        $display_version_instance = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($display_version);
        $isVcs = $graded_gradeable->getGradeable()->isVcs();
        if ($display_version_instance !==  null) {
            $meta_files = $display_version_instance->getMetaFiles();
            $files = $display_version_instance->getFiles();

            $add_files($submissions, array_merge($meta_files['submissions'], $files['submissions']), 'submissions');
            $add_files($checkout, array_merge($meta_files['checkout'], $files['checkout']), 'checkout');
            $add_files($results, $display_version_instance->getResultsFiles(), 'results');
            $add_files($results_public, $display_version_instance->getResultsPublicFiles(), 'results_public');
        }
        $student_grader = false;
        if ($this->core->getUser()->getGroup() == User::GROUP_STUDENT) {
            $student_grader = true;
        }
        $submitter_id = $graded_gradeable->getSubmitter()->getId();
        $anon_submitter_id = $graded_gradeable->getSubmitter()->getAnonId();
        $user_ids[$anon_submitter_id] = $submitter_id;
        $toolbar_css = $this->core->getOutput()->timestampResource(FileUtils::joinPaths('pdf', 'toolbar_embedded.css'), 'css');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdfjs', 'pdf.min.js'), 'vendor');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdfjs', 'pdf_viewer.js'), 'vendor');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdfjs', 'pdf.worker.min.js'), 'vendor');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdf-annotate.js', 'pdf-annotate.min.js'), 'vendor');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdf', 'PDFAnnotateEmbedded.js'), 'js');
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/SubmissionPanel.twig", [
            "showNewInterface" => $showNewInterface,
            "gradeable_id" => $graded_gradeable->getGradeableId(),
            "submitter_id" => $submitter_id,
            "student_grader" => $student_grader,
            "anon_submitter_id" => $anon_submitter_id,
            "has_vcs_files" => $isVcs,
            "user_ids" => $user_ids,
            "submissions" => $submissions,
            "checkout" => $checkout,
            "results" => $results,
            "results_public" => $results_public,
            "active_version" => $display_version,
            'toolbar_css' => $toolbar_css,
            "display_file_url" => $this->core->buildCourseUrl(['display_file'])
        ]);
    }

    /**
     * @param GradedGradeable $graded_gradeable
     * @param AutoGradedVersion|null $display_version_instance
     * @param bool $showNewInterface
     * @return string
     */
    public function renderInformationPanel(GradedGradeable $graded_gradeable, $display_version_instance, bool $showNewInterface) {
        $gradeable = $graded_gradeable->getGradeable();
        $version_change_url = $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'grading', 'grade']) . '?'
            . http_build_query(['who_id' => $graded_gradeable->getSubmitter()->getId()]) . '&gradeable_version=';
        $onChange = "versionChange('{$version_change_url}', this)";

        $tables = [];

        //Late day calculation
        if ($gradeable->isTeamAssignment()) {
            foreach ($graded_gradeable->getSubmitter()->getTeam()->getMemberUsers() as $team_member) {
                $tables[] = LateDaysTableController::renderLateTable($this->core, $team_member, $gradeable->getId());
            }
        }
        else {
            $tables[] = LateDaysTableController::renderLateTable($this->core, $graded_gradeable->getSubmitter()->getUser(), $gradeable->getId());
        }

        if ($display_version_instance === null) {
            $display_version = 0;
            $submission_time = null;
        }
        else {
            $display_version = $display_version_instance->getVersion();
            $submission_time = $display_version_instance->getSubmissionTime();
        }

        // TODO: this is duplicated in Homework View
        $version_data = array_map(function (AutoGradedVersion $version) use ($gradeable) {
            return [
                'points' => $version->getNonHiddenPoints(),
                'days_late' => $gradeable->isStudentSubmit() && $gradeable->hasDueDate() ? $version->getDaysLate() : 0
            ];
        }, $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersions());

        //sort array by version number after values have been mapped
        ksort($version_data);

        $submitter_id = $graded_gradeable->getSubmitter()->getId();
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $new_version = $display_version === $active_version ? 0 : $display_version;

        $this->core->getOutput()->addInternalCss('table.css');

        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/StudentInformationPanel.twig", [
            "showNewInterface" => $showNewInterface,
            "gradeable_id" => $gradeable->getId(),
            "submission_time" => $submission_time,
            "submitter_id" => $submitter_id,
            "submitter" => $graded_gradeable->getSubmitter(),
            "team_assignment" => $gradeable->isTeamAssignment(),
            "display_version" => $display_version,
            "highest_version" => $graded_gradeable->getAutoGradedGradeable()->getHighestVersion(),
            "active_version" => $active_version,
            "on_change" => $onChange,
            "tables" => $tables,
            "versions" => $version_data,
            'total_points' => $gradeable->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit(),
            "csrf_token" => $this->core->getCsrfToken(),
            "update_version_url" => $this->core->buildCourseUrl(['gradeable', $gradeable->getId(), 'version', $new_version])
                . '?' . http_build_query(['ta' => 'true', 'who' => $submitter_id])
        ]);
    }

    /**
     * Render the Grading Rubric panel
     * @param GradedGradeable $graded_gradeable
     * @param int $display_version
     * @param bool $can_verify
     * @param bool $show_verify_all
     * @param bool $show_silent_edit
     * @param bool $showNewInterface
     * @return string
     */
    public function renderRubricPanel(GradedGradeable $graded_gradeable, int $display_version, bool $can_verify, bool $show_verify_all, bool $show_silent_edit, bool $showNewInterface) {
        $return = "";
        $student_anon_ids = [];
        $gradeable = $graded_gradeable->getGradeable();
        if ($gradeable->isTeamAssignment()) {
            $team = $this->core->getQueries()->getTeamById($graded_gradeable->getSubmitter()->getId());
            foreach ($team->getMemberUsers() as $user) {
                $student_anon_ids[] = $user->getAnonId();
            }
        }
        else {
            $student_anon_ids[] = $graded_gradeable->getSubmitter()->getAnonId();
        }
        // Disable grading if the requested version isn't the active one
        $grading_disabled = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() == 0
            || $display_version != $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();

        $version_conflict = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() !== $display_version;
        $has_active_version = $graded_gradeable->getAutoGradedGradeable()->hasActiveVersion();
        $has_submission = $graded_gradeable->getAutoGradedGradeable()->hasSubmission();
        $has_overridden_grades = $graded_gradeable->hasOverriddenGrades();

        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('twigjs', 'twig.min.js'));
        $this->core->getOutput()->addInternalJs('ta-grading-keymap.js');
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdf', 'PDFAnnotateEmbedded.js'));
        $this->core->getOutput()->addInternalJs(FileUtils::joinPaths('pdf', 'PDFInitToolbar.js'));
        $this->core->getOutput()->addInternalJs('ta-grading-rubric-conflict.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');
        $this->core->getOutput()->addInternalJs('ta-grading-rubric.js');

        if ($showNewInterface) {
            $this->core->getOutput()->addInternalJs('ta-grading-v2.js');
            $this->core->getOutput()->addInternalJs('panel-selector-modal.js');
        }
        else {
            $this->core->getOutput()->addInternalJs('ta-grading.js');
        }

        return $return . $this->core->getOutput()->renderTwigTemplate("grading/electronic/RubricPanel.twig", [
                "showNewInterface" => $showNewInterface,
                "gradeable" => $gradeable,
                "student_anon_ids" => $student_anon_ids,
                "anon_id" => $graded_gradeable->getSubmitter()->getAnonId(),
                "gradeable_id" => $gradeable->getId(),
                "is_ta_grading" => $gradeable->isTaGrading(),
                "show_verify_all" => $show_verify_all,
                "can_verify" => $can_verify,
                "grading_disabled" => $grading_disabled,
                "has_submission" => $has_submission,
                "has_overridden_grades" => $has_overridden_grades,
                "has_active_version" => $has_active_version,
                "version_conflict" => $version_conflict,
                "show_silent_edit" => $show_silent_edit,
                "grader_id" => $this->core->getUser()->getId(),
                "display_version" => $display_version,
            ]);
    }

    /**
     * @param Gradeable $gradeable
     * @param array $solution_array
     * @param array $submitter_itempool_map
     * @param bool $showNewInterface
     * @return string
     */
    public function renderSolutionTaNotesPanel($gradeable, $solution_array, $submitter_itempool_map, $showNewInterface) {
        if (!$showNewInterface) {
            return '';
        }
        $this->core->getOutput()->addInternalJs('solution-ta-notes.js');

        $r_components = $gradeable->getComponents();
        $solution_components = [];
        foreach ($r_components as $key => $value) {
            $id = $value->getId();
            $solution_components[] = [
                'id' => $id,
                'title' => $value->getTitle(),
                'is_first_edit' => !isset($solution_array[$id]),
                'author' => isset($solution_array[$id]) ? $solution_array[$id]['author'] : '',
                'solution_notes' => isset($solution_array[$id]) ? $solution_array[$id]['solution_notes'] : '',
                'edited_at' => isset($solution_array[$id])
                    ? DateUtils::convertTimeStamp(
                        $this->core->getUser(),
                        $solution_array[$id]['edited_at'],
                        $this->core->getConfig()->getDateTimeFormat()->getFormat('solution_ta_notes')
                    ) : null,
                'is_itempool_linked' => $value->getIsItempoolLinked(),
                'itempool_item' => $value->getItempool() === "" ? "" : $submitter_itempool_map[$value->getItempool()]
            ];
        }
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/SolutionTaNotesPanel.twig", [
            'gradeable_id' => $gradeable->getId(),
            'solution_components' => $solution_components,
            'current_user_id' => $this->core->getUser()->getId(),
        ]);
    }

    /**
     * Render the Grading Rubric panel
     * @param GradedGradeable $graded_gradeable
     * @param int $display_version
     * @param bool $showNewInterface
     * @return string
     */
    public function renderPeerPanel(GradedGradeable $graded_gradeable, int $display_version, bool $showNewInterface) {
        $return = "";
        $gradeable = $graded_gradeable->getGradeable();

        $grading_disabled = true;

        $version_conflict = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion() !== $display_version;
        $has_active_version = $graded_gradeable->getAutoGradedGradeable()->hasActiveVersion();
        $has_submission = $graded_gradeable->getAutoGradedGradeable()->hasSubmission();
        $has_overridden_grades = $graded_gradeable->hasOverriddenGrades();

        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('twigjs', 'twig.min.js'));
        $this->core->getOutput()->addInternalJs('ta-grading-keymap.js');
        $this->core->getOutput()->addInternalJs('ta-grading-rubric-conflict.js');
        $this->core->getOutput()->addInternalJs('ta-grading-rubric.js');
        $this->core->getOutput()->addInternalJs('gradeable.js');
        $this->core->getOutput()->addInternalCss('table.css');

        if ($showNewInterface) {
            $this->core->getOutput()->addInternalJs('ta-grading-v2.js');
            $this->core->getOutput()->addInternalJs('panel-selector-modal.js');
        }
        else {
            $this->core->getOutput()->addInternalJs('ta-grading.js');
        }

        return $return . $this->core->getOutput()->renderTwigTemplate("grading/electronic/PeerPanel.twig", [
                "showNewInterface" => $showNewInterface,
                "gradeable_id" => $gradeable->getId(),
                "is_ta_grading" => $gradeable->isTaGrading(),
                "anon_id" => $graded_gradeable->getSubmitter()->getAnonId(),
                "grading_disabled" => $grading_disabled,
                "has_submission" => $has_submission,
                "has_overridden_grades" => $has_overridden_grades,
                "has_active_version" => $has_active_version,
                "version_conflict" => $version_conflict,
                "grader_id" => $this->core->getUser()->getId(),
                "display_version" => $display_version
            ]);
    }

    /**
     * Render the Grade Inquiry panel
     * @param GradedGradeable $graded_gradeable
     * @return string
     */
    public function renderPeerEditMarksPanel(GradedGradeable $graded_gradeable) {
        $gradeable = $graded_gradeable->getGradeable();
        $submitter = $graded_gradeable->getSubmitter()->getId();
        $peers_to_list = $this->core->getQueries()->getPeerGradingAssignmentForSubmitter($gradeable->getId(), $submitter);
        if ($gradeable->isTeamAssignment()) {
            foreach ($this->core->getQueries()->getTeamById($submitter)->getMemberUserIds() as $student_id) {
                $peers_to_list = array_merge($peers_to_list, $this->core->getQueries()->getPeerGradingAssignmentForSubmitter($gradeable->getId(), $student_id));
            }
        }
        $components = $gradeable->getComponents();
        $components_details_array = [];
        $peer_details = [];
        $component_scores = [];
        $peer_details["graders"] = [];
        $marks = [];
        foreach ($components as $component) {
            if ($component->isPeer()) {
                foreach ($peers_to_list as $peer) {
                    $graded_component = $graded_gradeable->getOrCreateTaGradedGradeable()->getGradedComponent($component, $this->core->getQueries()->getUsersById([$peer])[$peer]);
                    if ($graded_component !== null) {
                        $peer_details["graders"][$component->getId()][] = $peer;
                        $peer_details["marks_assigned"][$component->getId()][$peer] = $graded_component->getMarkIds();
                        $component_scores[$component->getId()][$peer] = $graded_component->getTotalScore();
                    }
                }
                $component_details["title"] = $component->getTitle();
                $component_details["marks"] = [];
                $component_details["max"] = $component->getMaxValue();
                $component_details["id"] = strval($component->getId());
                foreach ($component->getMarks() as $mark) {
                    $component_details["marks"][] = $mark->getId();
                    $marks[$mark->getId()]["title"] = $mark->getTitle();
                    $marks[$mark->getId()]["points"] = $mark->getPoints();
                }
                $components_details_array[] = $component_details;
            }
        }
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/EditPeerComponentsForm.twig", [
            "gradeable_id" => $gradeable->getId(),
            "peers" => $peers_to_list,
            "submitter_id" => $submitter,
            "peer_details" => $peer_details,
            "components" => $components_details_array,
            "csrf_token" => $this->core->getCsrfToken(),
            "component_scores" => $component_scores,
            "marks" => $marks
        ]);
    }

    /**
     * Render the Grade Inquiry panel
     * @param GradedGradeable $graded_gradeable
     * @param bool $can_inquiry
     * @param bool $showNewInterface
     * @return string
     */
    public function renderRegradePanel(GradedGradeable $graded_gradeable, bool $can_inquiry, bool $showNewInterface) {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/RegradePanel.twig", [
            "showNewInterface" => $showNewInterface,
            "graded_gradeable" => $graded_gradeable,
            "can_inquiry" => $can_inquiry
        ]);
    }

    public function popupStudents() {
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/ReceivedMarkForm.twig");
    }

    public function popupMarkConflicts() {
        return $this->core->getOutput()->renderTwigTemplate('grading/electronic/MarkConflictPopup.twig');
    }

    public function popupSettings() {
        return $this->core->getOutput()->renderTwigTemplate("grading/SettingsForm.twig");
    }


    public function renderNotebookPanel(array $notebook, array $testcase_messages, array $image_data, string $gradeable_id, int $highest_version, array $old_files, string $student_id): string {
        return $this->core->getOutput()->renderTwigTemplate(
            "grading/electronic/NotebookPanel.twig",
            [
            "notebook" => $notebook,
            "testcase_messages" => $testcase_messages,
            "image_data" => $image_data,
            'numberUtils' => new class () {
                //needed to show student multiple choices in random order
                public function getRandomIndices(int $array_length, string $student_id, string $gradeable_id): array {
                    return NumberUtils::getRandomIndices($array_length, '' . $student_id . $gradeable_id);
                }
            },
            "student_id" => $student_id,
            "gradeable_id" => $gradeable_id,
            "highest_version" => $highest_version,
            'max_file_size' => Utils::returnBytes(ini_get('upload_max_filesize')),
            "old_files" => $old_files,
            "is_grader_view" => true
            ]
        );
    }
}
