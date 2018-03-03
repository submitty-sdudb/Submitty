<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\models\User;
use app\models\HWReport;
use \app\libraries\GradeableType;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use app\libraries\FileUtils;

class ElectronicGraderController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'details':
                $this->showDetails();
                break;
            case 'submit_team_form':
                $this->adminTeamSubmit();
                break;
            case 'grade':
                $this->showGrading();
                break;
            case 'save_one_component':
                $this->saveSingleComponent();
                break;
            case 'save_gradeable_comment':
                $this->saveGradeableComment();
                break;
            case 'get_mark_data':
                $this->getMarkDetails();
                break;
            case 'get_gradeable_comment':
                $this->getGradeableComment();
                break;
            case 'get_marked_users':
                $this->getUsersThatGotTheMark();
                break;
            case 'add_one_new_mark':
                $this->addOneMark();
            default:
                $this->showStatus();
                break;
        }
    }

    /**
     * Shows statistics for the grading status of a given electronic submission. This is shown to all full access
     * graders. Limited access graders will only see statistics for the sections they are assigned to.
     */
    public function showStatus() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);
        
        $peer = false;
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if ($gradeable->getPeerGrading() && ($this->core->getUser()->getGroup() == 4)) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }

        /*
         * we need number of students per section
         */

        $no_team_users = array();
        $graded_components = array();
        $graders = array();
        $average_scores = array();
        $sections = array();
        $total_users = array();
        $component_averages = array();
        $autograded_average = array();
        $overall_average = array();
        $num_submitted = array();
        if ($peer) {
            $peer_grade_set = $gradeable->getPeerGradeSet();
            $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section');
            $num_components = $gradeable->getNumPeerComponents();
            $graded_components = $this->core->getQueries()->getGradedPeerComponentsByRegistrationSection($gradeable_id, $sections);
            $my_grading = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId());
            $component_averages = array();
            $autograded_average = array();
            $overall_average = array();
            $section_key='registration_section';
        }
        else if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            $section_key='registration_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
            $num_components = $gradeable->getNumTAComponents();
        }
        //grading by rotating section
        else {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            $section_key='rotating_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }

         $num_submitted = $this->core->getQueries()->getTotalSubmittedUserCountByGradingSections($gradeable->getId(), $sections, $section_key);

        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key);
                $component_averages = $this->core->getQueries()->getAverageComponentScores($gradeable_id, $section_key);
                $autograded_average = $this->core->getQueries()->getAverageAutogradedScores($gradeable_id, $section_key);
                $overall_average = $this->core->getQueries()->getAverageForGradeable($gradeable_id, $section_key);
            }
            $num_components = $gradeable->getNumTAComponents();
        }
        $sections = array();
        $total_students = 0;
        if (count($total_users) > 0) {
            // Get total number of students (submitted and unsubmitted)
            foreach ($total_users as $key => $value) {
                if ($key == 'NULL') continue;
                $total_students += $value;
            }
            
            if ($peer) {
                $sections['stu_grad'] = array(
                    'total_components' => $num_components * $peer_grade_set,
                    'graded_components' => $my_grading,
                    'graders' => array()
                );
                $sections['all'] = array(
                    'total_components' => 0,
                    'graded_components' => 0,
                    'graders' => array()
                );
                foreach($total_users as $key => $value) {
                    if($key == 'NULL') continue;
                    $sections['all']['total_components'] += $value *$num_components*$peer_grade_set;
                    $sections['all']['graded_components'] += isset($graded_components[$key]) ? $graded_components[$key] : 0;
                }
                $sections['all']['total_components'] -= $peer_grade_set*$num_components;
                $sections['all']['graded_components'] -= $my_grading;
            }
            else {
                foreach ($num_submitted as $key => $value) {
                    $sections[$key] = array(
                        'total_components' => $value * $num_components,
                        'graded_components' => 0,
                        'graders' => array()
                    );
                    if ($gradeable->isTeamAssignment()) {
                        $sections[$key]['no_team'] = $no_team_users[$key];
                    }
                    if (isset($graded_components[$key])) {
                        // Clamp to total components if unsubmitted assigment is graded for whatever reason
                        $sections[$key]['graded_components'] = min(intval($graded_components[$key]), $sections[$key]['total_components']);
                    }
                    if (isset($graders[$key])) {
                        $sections[$key]['graders'] = $graders[$key];
                    }
                }
            }
        }

        $registered_but_not_rotating = count($this->core->getQueries()->getRegisteredUsersWithNoRotatingSection());
        $rotating_but_not_registered = count($this->core->getQueries()->getUnregisteredStudentsWithRotatingSection());

        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'statusPage', $gradeable, $sections, $component_averages, $autograded_average, $overall_average, $total_students, $registered_but_not_rotating, $rotating_but_not_registered, $section_key);
    }

    /**
     * This loads a gradeable and
     */
    public function showDetails() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);

        $this->core->getOutput()->addBreadcrumb('Student Index');

        $peer = false;
        if ($gradeable === null) {
            $this->core->getOutput()->renderOutput('Error', 'noGradeable', $gradeable_id);
            return;
        }
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }

        $students = array();
        //If we are peer grading, load in all students to be graded by this peer.
        if ($peer) {
            $student_ids = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $graders = array();
            $section_key = "registration_section";
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                $this->core->getUser()->getId());
            if (!isset($_GET['view']) || $_GET['view'] !== "all") {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable->getId(), $sections);
        }
        if ((isset($_GET['view']) && $_GET['view'] === "all") || ($this->core->getUser()->accessAdmin() && count($sections) === 0)) {
            //Checks to see if the Grader has access to all users in the course,
            //Will only show the sections that they are graders for if not TA or Instructor
            if($this->core->getUser()->getGroup() < 3) {
                $students = $this->core->getQueries()->getAllUsers($section_key);
            } else {
                $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
        }

        if(!$peer) {
            $student_ids = array_map(function(User $student) { return $student->getId(); }, $students);
        }

        $empty_teams = array();
        if ($gradeable->isTeamAssignment()) {
            // Only give getGradeables one User ID per team
            $all_teams = $this->core->getQueries()->getTeamsByGradeableId($gradeable_id);
            foreach($all_teams as $team) {
                $student_ids = array_diff($student_ids, $team->getMembers());
                $team_section = $gradeable->isGradeByRegistration() ? $team->getRegistrationSection() : $team->getRotatingSection();
                if ($team->getSize() > 0 && (in_array($team_section, $sections) ||
                                            (isset($_GET['view']) && $_GET['view'] === "all") ||
                                            (count($sections) === 0 && $this->core->getUser()->accessAdmin()))) {
                    $student_ids[] = $team->getMembers()[0];
                }
                if ($team->getSize() === 0 && $this->core->getUser()->accessAdmin()) {
                    $empty_teams[] = $team;
                }
            }
        }

        $rows = $this->core->getQueries()->getGradeables($gradeable_id, $student_ids, $section_key);
        if ($gradeable->isTeamAssignment()) {
            // Rearrange gradeables arrray into form (sec 1 teams, sec 1 individuals, sec 2 teams, sec 2 individuals, etc...)
            $sections = array();
            $individual_rows = array();
            $team_rows = array();
            foreach($rows as $row) {
                if ($gradeable->isGradeByRegistration()) {
                    $section = $row->getTeam() === null ? strval($row->getUser()->getRegistrationSection()) : strval($row->getTeam()->getRegistrationSection());
                }
                else {
                    $section = $row->getTeam() === null ? strval($row->getUser()->getRotatingSection()) : strval($row->getTeam()->getRotatingSection());
                }

                if ($section != null && !in_array($section, $sections)) {
                    $sections[] = $section;
                }

                if ($row->getTeam() === null) {
                    if (!isset($individual_rows[$section])) {
                        $individual_rows[$section] = array();
                    }
                    $individual_rows[$section][] = $row;
                }
                else {
                    if (!isset($team_rows[$section])) {
                        $team_rows[$section] = array();
                    }
                    $team_rows[$section][] = $row;
                }
            }

            asort($sections);
            $rows = array();
            foreach($sections as $section) {
                if (isset($team_rows[$section])) {
                    $rows = array_merge($rows, $team_rows[$section]);
                }
                if (isset($individual_rows[$section])) {
                    $rows = array_merge($rows, $individual_rows[$section]);
                }
            }
            // Put null section at end of array
            if (isset($team_rows[""])) {
                $rows = array_merge($rows, $team_rows[""]);
            }
            if (isset($individual_rows[""])) {
                $rows = array_merge($rows, $individual_rows[""]);
            }
        }
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'detailsPage', $gradeable, $rows, $graders, $empty_teams);

        if ($gradeable->isTeamAssignment() && $this->core->getUser()->accessAdmin()) {
            if ($gradeable->isGradeByRegistration()) {
                $all_sections = $this->core->getQueries()->getRegistrationSections();
                $key = 'sections_registration_id';
            }
            else {
                $all_sections = $this->core->getQueries()->getRotatingSections();
                $key = 'sections_rotating_id';
            }
            foreach ($all_sections as $i => $section) {
                $all_sections[$i] = $section[$key];
            }
            $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'adminTeamForm', $gradeable, $all_sections);
        }
    }

    public function adminTeamSubmit() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $this->core->addErrorMessage("Invalid CSRF Token");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage("Only admins can edit teams");
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);

        $return_url = $this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details','gradeable_id'=>$gradeable_id));
        if (isset($_POST['view'])) $return_url .= "&view={$_POST['view']}";

        if (!$gradeable->isTeamAssignment()) {
            $this->core->addErrorMessage("{$gradeable->getName()} is not a team assignment");
            $this->core->redirect($return_url);
        }

        $num_users = intval($_POST['num_users']);
        $user_ids = array();
        for ($i = 0; $i < $num_users; $i++) {
            $id = trim(htmlentities($_POST["user_id_{$i}"]));
            if (($id !== "") && !in_array($id, $user_ids)) {
                if ($this->core->getQueries()->getUserById($id) === null) {
                    $this->core->addErrorMessage("ERROR: {$id} is not a valid User ID");
                    $this->core->redirect($return_url);
                }
                $user_ids[] = $id;
            }
        }
        $new_team = $_POST['new_team'] === 'true' ? true : false;

        if ($new_team) {
            $leader = $_POST['new_team_user_id'];
            ElectronicGraderController::CreateTeamWithLeaderAndUsers($this->core, $gradeable, $leader, $user_ids);
        }
        else {
            $team_id = $_POST['edit_team_team_id'];
            $team = $this->core->getQueries()->getTeamById($team_id);
            if ($team === null) {
                $this->core->addErrorMessage("ERROR: {$team_id} is not a valid Team ID");
                $this->core->redirect($return_url);
            }
            $team_members = $team->getMembers();
            $add_user_ids = array();
            foreach($user_ids as $id) {
                if (!in_array($id, $team_members)) {
                    if ($this->core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                        $this->core->addErrorMessage("ERROR: {$id} is already on a team");
                        $this->core->redirect($return_url);
                    }
                    $add_user_ids[] = $id;
                }
            }
            $remove_user_ids = array();
            foreach($team_members as $id) {
                if (!in_array($id, $user_ids)) {
                    $remove_user_ids[] = $id;
                }
            }

            $section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
            if ($gradeable->isGradeByRegistration()) {
                $this->core->getQueries()->updateTeamRegistrationSection($team_id, $section);
            }
            else {
                $this->core->getQueries()->updateTeamRotatingSection($team_id, $section);
            }
            foreach($add_user_ids as $id) {
                $this->core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
                $this->core->getQueries()->acceptTeamInvitation($team_id, $id);
            }
            foreach($remove_user_ids as $id) {
                $this->core->getQueries()->leaveTeam($team_id, $id);
            }
            $this->core->addSuccessMessage("Updated Team {$team_id}");

            $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$this->core->getConfig()->getTimezone()->getName();
            $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions", $gradeable_id, $team_id, "user_assignment_settings.json");
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                $this->core->addErrorMEssage("Failed to open settings file");
                $this->core->redirect($return_url);
            }
            foreach($add_user_ids as $id) {
                $json["team_history"][] = array("action" => "admin_add_user", "time" => $current_time,
                                                    "admin_user" => $this->core->getUser()->getId(), "added_user" => $id);
            }
            foreach($remove_user_ids as $id) {
                $json["team_history"][] = array("action" => "admin_remove_user", "time" => $current_time,
                                                    "admin_user" => $this->core->getUser()->getId(), "removed_user" => $id);
            }
            if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
                $this->core->addErrorMEssage("Failed to write to team history to settings file");
            }
        }   
        
        $this->core->redirect($return_url);
    }

    static public function createTeamWithLeaderAndUsers($core, $gradeable, $leader, $user_ids){
        $team_leader_id = null;
        $gradeable_id = $gradeable->getId();
        foreach($user_ids as $id) {
            if($id === "undefined" || $id === "")
            {
                continue;
            }
            if ($core->getQueries()->getTeamByGradeableAndUser($gradeable_id, $id) !== null) {
                $core->addErrorMessage("ERROR: {$id} is already on a team");
                return;
            }
            if ($id === $leader) {
                $team_leader_id = $id;
            }
        }
        if ($team_leader_id === null) {
            $core->addErrorMessage("ERROR: {$team_leader_id} must be on the team");
            return;
        }

        $registration_section = $core->getQueries()->getUserById($team_leader_id)->getRegistrationSection();
        $rotating_section = $core->getQueries()->getUserById($team_leader_id)->getRotatingSection();

        //overwrite sections if they are available in the post
        if(isset($_POST['section']) && $_POST['section'] !== "NULL"){
            if ($gradeable->isGradeByRegistration()) {
                $registration_section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
            }
            else {
                $rotating_section = $_POST['section'] === "NULL" ? null : intval($_POST['section']);
            }
        }

        $team_id = $core->getQueries()->createTeam($gradeable_id, $team_leader_id, $registration_section, $rotating_section);
        foreach($user_ids as $id) {
            if($id === "undefined" or $id === ""){
                continue;
            }
            $core->getQueries()->declineAllTeamInvitations($gradeable_id, $id);
            if ($id !== $team_leader_id) $core->getQueries()->acceptTeamInvitation($team_id, $id);
        }
        $core->addSuccessMessage("Created New Team {$team_id}");

        $gradeable_path = FileUtils::joinPaths($core->getConfig()->getCoursePath(), "submissions", $gradeable_id);
        if (!FileUtils::createDir($gradeable_path)) {
            $core->addErrorMEssage("Failed to make folder for this assignment");
            return;
        }

        $user_path = FileUtils::joinPaths($gradeable_path, $team_id);
        if (!FileUtils::createDir($user_path)) {
            $core->addErrorMEssage("Failed to make folder for this assignment for the team");
            return;
        }

        $current_time = (new \DateTime('now', $core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO")." ".$core->getConfig()->getTimezone()->getName();
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        $json = array("team_history" => array(array("action" => "admin_create", "time" => $current_time,
                                                    "admin_user" => $core->getUser()->getId(), "first_user" => $team_leader_id)));
        foreach($user_ids as $id) {
            if ($id !== $team_leader_id) {
                $json["team_history"][] = array("action" => "admin_add_user", "time" => $current_time,
                                                "admin_user" => $core->getUser()->getId(), "added_user" => $id);
            }
        }
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $this->core->addErrorMEssage("Failed to write to team history to settings file");
        }
    }

    public function showGrading() {
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $peer = false;
        if ($this->core->getUser()->getGroup() > $gradeable->getMinimumGradingGroup()) {
            if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup()==4) {
                $peer = true;
            }
            else {
                $this->core->addErrorMessage("You do not have permission to grade {$gradeable->getName()}");
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }

        $gradeableUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb("{$gradeable->getName()} Grading", $gradeableUrl);
        $indexUrl = $this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'details', 'gradeable_id' => $gradeable_id));
        $this->core->getOutput()->addBreadcrumb('Student Index', $indexUrl);

        $graded = 0;
        $total = 0;
        if($peer) {
            $section_key = 'registration_section';
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            $total = $gradeable->getPeerGradeSet();
            $graded = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId()) / $gradeable->getNumPeerComponents();
        }
        else if ($gradeable->isGradeByRegistration()) {
            $section_key = "registration_section";
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRegistrationSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_registration_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections,$orderBy="registration_section,user_id;");
            $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'registration_section'));
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'registration_section'));
        }
        else {
            $section_key = "rotating_section";
            $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            if ($this->core->getUser()->accessAdmin() && $sections == null) {
                $sections = $this->core->getQueries()->getRotatingSections();
                for ($i = 0; $i < count($sections); $i++) {
                    $sections[$i] = $sections[$i]['sections_rotating_id'];
                }
            }
            $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections,$orderBy="rotating_section,user_id;");
            $total = array_sum($this->core->getQueries()->getTotalUserCountByGradingSections($sections, 'rotating_section'));
            $graded = array_sum($this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, 'rotating_section'));
        }

        //multiplies users and the number of components a gradeable has together
        $total = $total * count($gradeable->getComponents());
        if($total == 0) {
            $progress = 100;
        }
        else {
            $progress = round(($graded / $total) * 100, 1);
        }
        if(!$peer) {
            $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
        }
        //$gradeables_to_grade = $this->core->getQueries()->getGradeables($gradeable_id, $user_ids_to_grade, $section_key);

        $who_id = isset($_REQUEST['who_id']) ? $_REQUEST['who_id'] : "";
        //$who_id = isset($who_id[$_REQUEST['who_id']]) ? $who_id[$_REQUEST['who_id']] : "";
        if (($who_id !== "") && ($this->core->getUser()->getGroup() === 3) && !in_array($who_id, $user_ids_to_grade)) {
            $this->core->addErrorMessage("You do not have permission to grade {$who_id}");
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }
        if($peer && !in_array($who_id, $user_ids_to_grade)) {
            $_SESSION['messages']['error'][] = "You do not have permission to grade this student.";
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $prev_id = "";
        $next_id = "";
        $break_next = false;
        if($who_id === ""){
            $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details', 
                'gradeable_id' => $gradeable_id)));
        }
        
        $index = array_search($who_id, $user_ids_to_grade);
        $not_in_my_section = false;
        //If the student isn't in our list of students to grade.
        if($index === false){
          //If we are a full access grader, let us access the student anyway (but don't set next and previous)
          if($this->core->getUser()->accessFullGrading()){
            $prev_id = "";
            $next_id = "";
            $not_in_my_section = true;
          }
          else{
             //If we are not a full access grader and the student isn't in our list, send us back to the index page.
             $this->core->addErrorMessage("ERROR: You do not have access to grade the requested student.");
             $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
          }
        }
        else{
          //If the student is in our list of students to grade, set next and previous index appropriately
          if($index > 0){
            $prev_id = $user_ids_to_grade[$index-1];
          }
          if($index < count($user_ids_to_grade)-1){
              $next_id = $user_ids_to_grade[$index+1];
          }
        }

        

        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $who_id);
        if ($gradeable === NULL){
          //This will trigger if a full access grader attempts to specifically access a non-existant student.
          $this->core->addErrorMessage("ERROR: The requested student does not exist.");
          $this->core->redirect($this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'gradeable_id' => $gradeable_id)));
        }

        $gradeable->loadResultDetails();
        $individual = $_REQUEST['individual'];

        $anon_ids = $this->core->getQueries()->getAnonId(array($prev_id, $next_id));

        $nameBreadCrumb = '';


        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() !== null) {
            foreach ($gradeable->getTeam()->getMembers() as $team_member) {
                $team_member = $this->core->getQueries()->getUserById($team_member);
                $nameBreadCrumb .= $team_member->getId() . ', ';
            }
            $nameBreadCrumb = rtrim($nameBreadCrumb, ', ');
        } else {
            $nameBreadCrumb .= $gradeable->getUser()->getId();
        }       

        $this->core->getOutput()->addCSS($this->core->getConfig()->getBaseUrl()."/css/ta-grading.css");
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'hwGradingPage', $gradeable, $progress, $prev_id, $next_id, $individual, $not_in_my_section);
        $this->core->getOutput()->renderOutput(array('grading', 'ElectronicGrader'), 'popupStudents');
    }

    public function saveSingleComponent() {
        $grader_id = $this->core->getUser()->getId();
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $overwrite = $_POST['overwrite'];
        $version_updated = "false"; //if the version is updated

        //checks if user has permission
        if ($this->core->getUser()->getGroup() === 4) {
            if(!$gradeable->getPeerGrading()) {
                $this->core->addErrorMessage("You do not have permission to grade this");
                return;
            }
            else {
                $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
                if(!in_array($user_id, $user_ids_to_grade)) {
                    $this->core->addErrorMessage("You do not have permission to grade this student");
                    return;
                }
            }
        }
        else if ($this->core->getUser()->getGroup() === 3) {
            if ($gradeable->isGradeByRegistration()) {
                $sections = $this->core->getUser()->getGradingRegistrationSections();
                $users_to_grade = $this->core->getQueries()->getUsersByRegistrationSections($sections);
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
                $users_to_grade = $this->core->getQueries()->getUsersByRotatingSections($sections);
            }
            $user_ids_to_grade = array_map(function(User $user) { return $user->getId(); }, $users_to_grade);
            if (!in_array($user_id, $user_ids_to_grade)) {
                $this->core->addErrorMessage("You do not have permission to grade {$user_id}");
                return;
            }
        }

        //save the component
        foreach ($gradeable->getComponents() as $component) {
            if(is_array($component)) {
                if($component[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
                $found = false;
                foreach($component as $peer) {
                    if($peer->getGrader() === null) {
                        $component = $peer;
                        $found = true;
                        break;
                    }
                    if($peer->getGrader()->getId() == $grader_id) {
                        $component = $peer;
                        $found = true;
                        break;
                    }
                }
                if(!$found){
                    $component = $this->core->getQueries()->getGradeableComponents($gradeable->getId())[$component[0]->getId()];
                    $marks = $this->core->getQueries()->getGradeableComponentsMarks($component->getId());
                    $component->setMarks($marks); //I think this does nothing
                }
            }
            else if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            }
            //checks if a component has changed, i.e. a mark has been selected or unselected since last time
            //also checks if all the marks are false
            $index = 0;
            $temp_mark_selected = false;
            $all_false = true;
            $debug = "";
            $mark_modified = false;
            foreach ($component->getMarks() as $mark) {
                if (isset($_POST['num_existing_marks'])) {
                    if ($index >= $_POST['num_existing_marks']) {
                        break;
                    }   
                }
                $temp_mark_selected = ($_POST['marks'][$index]['selected'] == 'true') ? true : false;
                if($all_false === true && $temp_mark_selected === true) {
                    $all_false = false;
                }
                if($temp_mark_selected !== $mark->getHasMark()) {
                    $mark_modified = true;
                }
                $index++;
            }
            for ($i = $index; $i < $_POST['num_mark']; $i++) {
                if ($_POST['marks'][$i]['selected'] == 'true') {
                    $all_false = false;
                    $mark_modified = true;
                    break;
                }
            }

            if($all_false === true) {
                if($_POST['custom_message'] != "" || floatval($_POST['custom_points']) != 0) {
                    $all_false = false;
                }
            }

            if($mark_modified === false) {
                if ($component->getComment() != $_POST['custom_message']) {
                    $mark_modified = true;
                }
                if ($component->getScore() != $_POST['custom_points']) {
                    $mark_modified = true;
                }
            }
            //if no gradeable id exists adds one to the gradeable data
            if($gradeable->getGdId() == null) {
                $gradeable->saveGradeableData();
            }
            if($all_false === true) {
                $component->deleteData($gradeable->getGdId());
                $debug = 'delete';
            } else {
                //only change the component information is the mark was modified or componet and its gradeable are out of sync.
                if($mark_modified === true || ($component->getGradedVersion() !== $gradeable->getActiveVersion())) {
                    if ($component->getGrader() === null || $overwrite === "true") {
                        $component->setGrader($this->core->getUser());
                    }

                    $version_updated = "true";
                    $component->setGradedVersion($_POST['active_version']);
                    $component->setGradeTime(new \DateTime('now', $this->core->getConfig()->getTimezone()));
                    $component->setComment($_POST['custom_message']);
                    $component->setScore($_POST['custom_points']);
                    $debug = $component->saveGradeableComponentData($gradeable->getGdId());
                }
            }

            $index = 0;
            // save existing marks
            foreach ($component->getMarks() as $mark) {
                if (isset($_POST['num_existing_marks'])) {
                    if ($index >= $_POST['num_existing_marks']) {
                        break;
                    }   
                }
                $mark->setPoints($_POST['marks'][$index]['points']);
                $mark->setNote($_POST['marks'][$index]['note']);
                $mark->setOrder($_POST['marks'][$index]['order']);
                $mark->save();
                $_POST['marks'][$index]['selected'] == 'true' ? $mark->setHasMark(true) : $mark->setHasMark(false);
                if($all_false === false) {
                    $mark->saveGradeableComponentMarkData($gradeable->getGdId(), $component->getId(), $component->getGrader()->getId());
                }
                $index++;
            }
            // create new marks
            /*
            $order_counter = $this->core->getQueries()->getGreatestGradeableComponentMarkOrder($component);
            $order_counter++;
            for ($i = $index; $i < $_POST['num_mark']; $i++) {
                $mark = new GradeableComponentMark($this->core);
                $mark->setGcId($component->getId());
                $mark->setPoints($_POST['marks'][$i]['points']);
                $mark->setNote($_POST['marks'][$i]['note']);
                $mark->setOrder($order_counter);
                $mark_id = $mark->save();
                $mark->setId($mark_id);
                $_POST['marks'][$i]['selected'] == 'true' ? $mark->setHasMark(true) : $mark->setHasMark(false);
                if($all_false === false) {
                    $mark->saveGradeableComponentMarkData($gradeable->getGdId(), $component->getId(), $component->getGrader()->getId());
                }
                $order_counter++;
            }
            */
        }
        //generates the HW Report each time a mark is saved
        $hwReport = new HWReport($this->core);
        $hwReport->generateSingleReport($user_id, $gradeable_id);

        if($this->core->getUser()->getGroup() == 4) {
            $hwReport->generateSingleReport($this->core->getUser()->getId(), $gradeable_id);
        }

        $response = array('status' => 'success', 'modified' => $mark_modified, 'all_false' => $all_false, 'database' => $debug, 'overwrite' => $overwrite, 'version_updated' => $version_updated);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function addOneMark() {

        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        foreach ($gradeable->getComponents() as $component) {
            if(is_array($component)) {
                if($component[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
            } else if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            }
            $order_counter = $this->core->getQueries()->getGreatestGradeableComponentMarkOrder($component);
            $order_counter++;
            $mark = new GradeableComponentMark($this->core);
            $mark->setGcId($component->getId());
            $mark->setPoints(0);
            $mark->setNote("");
            $mark->setOrder($order_counter);
            $mark_id = $mark->save();
            $mark->setId($mark_id);
            $_POST['marks'][$i]['selected'] == 'true' ? $mark->setHasMark(true) : $mark->setHasMark(false);
            if($all_false === false) {
                $mark->saveGradeableComponentMarkData($gradeable->getGdId(), $component->getId(), $component->getGrader()->getId());
            }
        }
    }

    public function saveGradeableComment() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $gradeable->setOverallComment($_POST['gradeable_comment']);
        $gradeable->saveGradeableData();
        $hwReport = new HWReport($this->core);
        $hwReport->generateSingleReport($user_id, $gradeable_id);
    }

    public function getMarkDetails() {
        //gets all the details from the database of a mark to readd it to the view
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        foreach ($gradeable->getComponents() as $question) {
            if(is_array($question)) {
                if($question[0]->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
                foreach($question as $cmpt) {
                    if($cmpt->getGrader() == null) {
                        $component = $cmpt;
                        break;
                    }
                    if($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                        $component = $cmpt;
                        break;
                    }
                }
            }
            else {
                $component = $question;
                if($component->getId() != $_POST['gradeable_component_id']) {
                    continue;
                }
            }
            $return_data = array();
            foreach ($component->getMarks() as $mark) {
                $temp_array = array();
                $temp_array['score'] = $mark->getPoints();
                $temp_array['note'] = $mark->getNote();
                $temp_array['has_mark'] = $mark->getHasMark();
                $return_data[] = $temp_array;
            }
            $temp_array = array();
            $temp_array['custom_score'] = $component->getScore();
            $temp_array['custom_note'] = $component->getComment();
            $return_data[] = $temp_array;
        }

        $response = array('status' => 'success', 'data' => $return_data);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function getGradeableComment() {
        $gradeable_id = $_POST['gradeable_id'];
        $user_id = $this->core->getQueries()->getUserFromAnon($_POST['anon_id'])[$_POST['anon_id']];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        $response = array('status' => 'success', 'data' => $gradeable->getOverallComment());
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function getUsersThatGotTheMark() {
        $gradeable_id = $_POST['gradeable_id'];
        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id);
        $gcm_order = $_POST['order_num'];
        $return_data;
        $name_info;
        foreach ($gradeable->getComponents() as $component) {
            if ($component->getId() != $_POST['gradeable_component_id']) {
                continue;
            } else {
                foreach ($component->getMarks() as $mark) {
                    if ($mark->getOrder() == intval($gcm_order)) {
                        $return_data = $this->core->getQueries()->getDataFromGCMD($component->getId(), $mark);
                        $name_info['question_name'] = $component->getTitle();
                        $name_info['mark_note'] = $mark->getNote();
                    }
                }
            }
        }

        $sections = array();
        $this->getStats($gradeable, $sections);

        $response = array('status' => 'success', 'data' => $return_data, 'sections' => $sections, 'name_info' => $name_info);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    private function getStats($gradeable, &$sections, $graders=array(), $total_users=array(), $no_team_users=array(), $graded_components=array()) {
        $gradeable_id = $gradeable->getId();
        if ($gradeable->isGradeByRegistration()) {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getUser()->getGradingRegistrationSections();
            }
            else {
                $sections = $this->core->getQueries()->getRegistrationSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_registration_id'];
                }
            }
            $section_key='registration_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRegistrationSections($sections);
            }
        }
        else {
            if(!$this->core->getUser()->accessFullGrading()){
                $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id, $this->core->getUser()->getId());
            }
            else {
                $sections = $this->core->getQueries()->getRotatingSections();
                foreach ($sections as $i => $section) {
                    $sections[$i] = $section['sections_rotating_id'];
                }
            }
            $section_key='rotating_section';
            if (count($sections) > 0) {
                $graders = $this->core->getQueries()->getGradersForRotatingSections($gradeable_id, $sections);
            }
        }

        if (count($sections) > 0) {
            if ($gradeable->isTeamAssignment()) {
                $total_users = $this->core->getQueries()->getTotalTeamCountByGradingSections($gradeable_id, $sections, $section_key);
                $no_team_users = $this->core->getQueries()->getUsersWithoutTeamByGradingSections($gradeable_id, $sections, $section_key);
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByTeamGradingSections($gradeable_id, $sections, $section_key);
            }
            else {
                $total_users = $this->core->getQueries()->getTotalUserCountByGradingSections($sections, $section_key);
                $no_team_users = array();
                $graded_components = $this->core->getQueries()->getGradedComponentsCountByGradingSections($gradeable_id, $sections, $section_key);
            }
        }

        $num_components = $this->core->getQueries()->getTotalComponentCount($gradeable_id);
        $sections = array();
        if (count($total_users) > 0) {
            foreach ($total_users as $key => $value) {
                $sections[$key] = array(
                    'total_components' => $value * $num_components,
                    'graded_components' => 0,
                    'graders' => array()
                );
                if ($gradeable->isTeamAssignment()) {
                    $sections[$key]['no_team'] = $no_team_users[$key];
                }
                if (isset($graded_components[$key])) {
                    $sections[$key]['graded_components'] = intval($graded_components[$key]);
                }
                if (isset($graders[$key])) {
                    $sections[$key]['graders'] = $graders[$key];
                }
            }
        }
    }
}
