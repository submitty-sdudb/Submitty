<?php

namespace app\controllers\student;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\GradeableList;
use app\models\LateDaysCalculation;
use app\controllers\grading\ElectronicGraderController;



class SubmissionController extends AbstractController {

    /**
     * @var GradeableList
     */
    private $gradeables_list;
    
    private $upload_details = array('version' => -1, 'version_path' => null, 'user_path' => null,
                                    'assignment_settings' => false);

    public function __construct(Core $core) {
        parent::__construct($core);
        $this->gradeables_list = $this->core->loadModel(GradeableList::class);

    }

    public function run() {
        switch($_REQUEST['action']) {
            case 'upload':
                return $this->ajaxUploadSubmission();
                break;
            case 'update':
                return $this->updateSubmissionVersion();
                break;
            case 'check_refresh':
                return $this->checkRefresh();
                break;
            case 'pop_up':
                return $this->popUp();
                break;
            case 'bulk':
                return $this->ajaxBulkUpload();
                break;
            case 'upload_split':
                return $this->ajaxUploadSplitItem();
                break;
            case 'delete_split':
                return $this->ajaxDeleteSplitItem();
                break;
            case 'verify':
                return $this->ajaxValidGradeable();
                break;
            case 'display':
            default:
                return $this->showHomeworkPage();
                break;
        }
    }

    private function popUp() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                           'showPopUp', $gradeable);
    }

    private function showHomeworkPage() {
        $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($gradeable_id, GradeableType::ELECTRONIC_FILE);
        if ($gradeable !== null) {
            $error = false;
            $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

            // ORIGINAL
            //if ($gradeable->getOpenDate() > $now && !$this->core->getUser()->accessAdmin()) {

            // TEMPORARY - ALLOW LIMITED & FULL ACCESS GRADERS TO PRACTICE ALL FUTURE HOMEWORKS
            if ($gradeable->getOpenDate() > $now && !$this->core->getUser()->accessGrading()) {
                $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'noGradeable', $gradeable_id);
                return array('error' => true, 'message' => 'No gradeable with that id.');
            }
            else if ($gradeable->isTeamAssignment() && $gradeable->getTeam() === null && !$this->core->getUser()->accessAdmin()) {
                $this->core->addErrorMessage('Must be on a team to access submission');
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
                return array('error' => true, 'message' => 'Must be on a team to access submission.');                
            }
            else {
                $loc = array('component' => 'student',
                             'gradeable_id' => $gradeable->getId());
                $this->core->getOutput()->addBreadcrumb($gradeable->getName(), $this->core->buildUrl($loc));
                $this->core->getOutput()->disableBuffer();
                if (!$gradeable->hasConfig()) {
                    $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                           'showGradeableError', $gradeable);
                    $error = true;
                }
                else {
                    $gradeable->loadResultDetails();
                    $ldu = $this->core->loadModel(LateDaysCalculation::class, $gradeable->getUser()->getId());
                    $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $gradeable_id);
                    if(empty($late_days)) {
                        $extensions = 0;
                    }
                    else{
                        $extensions = $late_days['extensions'];
                    }
                    $days_late = DateUtils::calculateDayDiff($gradeable->getDueDate());
                    $late_days_use = max(0, $days_late - $extensions);
                    if ($gradeable->beenTAgraded() && $gradeable->hasGradeFile()) {
                        $gradeable->updateUserViewedDate();
                    }
                    $this->core->getOutput()->renderOutput(array('submission', 'Homework'),
                                                           'showGradeable', $gradeable, $late_days_use, $extensions);
                }
            }
            return array('id' => $gradeable_id, 'error' => $error);
        }
        else {
            $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'noGradeable', $gradeable_id);
            return array('error' => true, 'message' => 'No gradeable with that id.');
        }
    }

    /**
    * Function for verification that a given RCS ID is valid and has a corresponding user and gradeable.
    * This should be called via AJAX, saving the result to the json_buffer of the Output object. 
    * If failure, also returns message explaining what happened.
    * If success, also returns highest version of the student gradeable.
    */
    private function ajaxValidGradeable() {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            $msg = "Invalid CSRF token. Refresh the page and try again.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        if (!isset($_POST['user_id'])) {
            $msg = "Did not pass in user_id.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();
        
        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        //usernames come in comma delimited. We split on the commas, then filter out blanks.
        $user_ids = explode (",", $_POST['user_id']);
        $user_ids = array_filter($user_ids);

        //If no user id's were submitted, give a graceful error.
        if (count($user_ids) === 0) {
            $msg = "No valid user ids were found.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        //For every userid, we have to check that its real.
        foreach($user_ids as $id){
            $user = $this->core->getQueries()->getUserById($id);
            if ($user === null) {
                $msg = "Invalid user id '{$id}'";
                $return = array('success' => false, 'message' => $msg);
                $this->core->getOutput()->renderJson($return);
                return $return;
            }
            if (!$user->isLoaded()) {
                $msg = "Invalid user id '{$id}'";
                $return = array('success' => false, 'message' => $msg);
                $this->core->getOutput()->renderJson($return);
                return $return;
            }
        }
        //This grabs the first user in the list. If there is more than one user
        //in the list, we will use this user as the team leader.
        $user_id = reset($user_ids);

        $user = $this->core->getQueries()->getUserById($user_id);

        $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);

        if($gradeable === null){
            $msg = "Gradeable not found.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        //If this is a team assignment, we need to check that all users are on the same (or no) team.
        //To do this, we just compare the leader's teamid to the team id of every other user.
        if($gradeable->isTeamAssignment()){
            $leader_team_id = "";
            $leader_team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $user_id);
            if($leader_team !== null){
                $leader_team_id = $leader_team->getId();
            }
            foreach($user_ids as $id){
                $user_team_id = "";
                $user_team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $id);
                if($user_team !== null){
                    $user_team_id = $user_team->getId();
                }
                if($user_team_id !== $leader_team_id){
                    $msg = "Inconsistent teams. One or more users are on different teams.";
                    $return = array('success' => false, 'message' => $msg);
                    $this->core->getOutput()->renderJson($return);
                    return $return;
                }
            }
        }


        $gradeable->loadResultDetails();

        $highest_version = $gradeable->getHighestVersion();
        $previous_submission = false;
        //If there has been a previous submission, we tag it so that we can pop up a warning.
        if($highest_version > 0){
            $previous_submission = true;
        }
        $return = array('success' => true, 'highest_version' => $highest_version, 'previous_submission' => $previous_submission);
        $this->core->getOutput()->renderJson($return);

        return $return;
    }

    /**
    * Function that uploads a bulk PDF to the uploads/bulk_pdf folder. Splits it into PDFs of the page
    * size entered and places in the uploads/split_pdf folder.
    * Its error checking has overlap with ajaxUploadSubmission.
    */
    private function ajaxBulkUpload() {
        if (!isset($_POST['num_pages'])) {
            $msg = "Did not pass in number of pages or files were too large.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }

        // make sure is admin
        if (!$this->core->getUser()->accessAdmin()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        $num_pages = $_POST['num_pages'];
        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $gradeable_list[$gradeable_id];
        $gradeable->loadResultDetails();

        // making sure files have been uploaded

        if (isset($_FILES["files1"])) {
            $uploaded_file = $_FILES["files1"];
        }
            
        $errors = array();
        if (isset($uploaded_file)) {
            $count = count($uploaded_file["name"]);
            for ($j = 0; $j < $count; $j++) {
                if (!isset($uploaded_file["tmp_name"][$j]) || $uploaded_file["tmp_name"][$j] === "") {
                    $error_message = $uploaded_file["name"][$j]." failed to upload. ";
                    if (isset($uploaded_file["error"][$j])) {
                        $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_file["error"][$j]). ".";
                    }
                    $errors[] = $error_message;
                }
            }
        }
            
        if (count($errors) > 0) {
            $error_text = implode("\n", $errors);
            return $this->uploadResult("Upload Failed: ".$error_text, false);
        }

        $max_size = $gradeable->getMaxSize();
	if ($max_size < 10000000) {
	    $max_size = 10000000;
	}
        // Error checking of file name
        $file_size = 0;
        if (isset($uploaded_file)) {
            for ($j = 0; $j < $count; $j++) {
                if(FileUtils::isValidFileName($uploaded_file["name"][$j]) === false) {
                    return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_file["name"][$j].".", false);
                }
                if(substr($uploaded_file["name"][$j],-3) != "pdf") {
                    return $this->uploadResult($uploaded_file["name"][$j]." is not a PDF!", false);
                }
                $file_size += $uploaded_file["size"][$j];
            }
        }
            
        if ($file_size > $max_size) {
            return $this->uploadResult("File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.", false);
        }

        // creating uploads/bulk_pdf/gradeable_id directory

        $pdf_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "bulk_pdf", $gradeable->getId());
        if (!FileUtils::createDir($pdf_path)) {
            return $this->uploadResult("Failed to make gradeable path.", false);
        }

        // creating directory under gradeable_id with the timestamp

        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("m-d-Y_H:i:sO");
        $version_path = FileUtils::joinPaths($pdf_path, $current_time);
        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make gradeable path.", false);
        }

        // save the pdf in that directory
        // delete the temporary file
        if (isset($uploaded_file)) {
            for ($j = 0; $j < $count; $j++) {
                if ($this->core->isTesting() || is_uploaded_file($uploaded_file["tmp_name"][$j])) {
                    $dst = FileUtils::joinPaths($version_path, $uploaded_file["name"][$j]);
                    if (!@copy($uploaded_file["tmp_name"][$j], $dst)) {
                        return $this->uploadResult("Failed to copy uploaded file {$uploaded_file["name"][$j]} to current submission.", false);
                    }
                }
                else {
                    return $this->uploadResult("The tmp file '{$uploaded_file['name'][$j]}' was not properly uploaded.", false);
                }
                // Is this really an error we should fail on?
                if (!@unlink($uploaded_file["tmp_name"][$j])) {
                    return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file["name"][$j]} from temporary storage.", false);
                }
            }
        }

        // use pdf_check.cgi to check that # of pages is valid and split
        // also get the cover image and name for each pdf appropriately

        // Open a cURL connection
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->core->getConfig()->getCgiUrl()."pdf_check.cgi?&num={$num_pages}&sem={$semester}&course={$course}&g_id={$gradeable_id}&ver={$current_time}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);

        if ($output === false) {
            return $this->uploadResult(curl_error($ch),false);
        }

        $output = json_decode($output, true);
        curl_close($ch);

        if ($output === null) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult("Error JSON response for pdf split: ".json_last_error_msg(),false);
        }
        else if (!isset($output['valid'])) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult("Missing response in JSON for pdf split",false);
        }
        else if ($output['valid'] !== true) {
            FileUtils::recursiveRmdir($version_path);
            return $this->uploadResult($output['message'],false);
        }

        $gradeable->loadResultDetails();

        $return = array('success' => true);
        $this->core->getOutput()->renderJson($return);
        return $return;
    }

    /**
     * Function for uploading a split item that already exists to the server. 
     * The file already exists in uploads/split_pdf/gradeable_id/timestamp folder. This should be called via AJAX, saving the result
     * to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     * Has overlap with ajaxUploadSubmission
     *
     * @return boolean
     */
    private function ajaxUploadSplitItem() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }
    
        $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();
        
        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }
        if (!isset($_POST['user_id'])) {
            return $this->uploadResult("Invalid user id.", false);
        }
        if (!isset($_POST['path'])) {
            return $this->uploadResult("Invalid path.", false);
        }

        // make sure is admin
        if (!$this->core->getUser()->accessAdmin()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $original_user_id = $this->core->getUser()->getId();

        $gradeable_id = $_REQUEST['gradeable_id'];
        //user ids come in as a comma delimited list. we explode that list, then filter out empty values.
        $user_ids = explode (",", $_POST['user_id']);
        $user_ids = array_filter($user_ids);
        //This grabs the first user in the list. If this is a team assignment, they will be the team leader.
        $user_id = reset($user_ids);

        $path = $_POST['path'];
        if ($user_id === $original_user_id) {
            $gradeable = $gradeable_list[$gradeable_id];
        }
        else {
            $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        }

        if($gradeable === null){
            $msg = "Gradeable not found.";
            $return = array('success' => false, 'message' => $msg);
            $this->core->getOutput()->renderJson($return);
            return $return;
        }

        $gradeable->loadResultDetails();
        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId());

        /*
         * Perform checks on the following folders (and whether or not they exist):
         * 1) the assignment folder in the submissions directory
         * 2) the student's folder in the assignment folder
         * 3) the version folder in the student folder
         * 4) the uploads folder from the specified path
         */
        if (!FileUtils::createDir($gradeable_path)) {
            return $this->uploadResult("Failed to make folder for this assignment.", false);
        }
        
        $who_id = $user_id;
        $team_id = "";
        if ($gradeable->isTeamAssignment()) {
            $leader = $user_id;
            $team =  $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $leader);
            if ($team !== null) {
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
            //if the student isn't on a team, build the team.
            else{
                //If the team doesn't exist yet, we need to build a new one. (Note, we have already checked in ajaxvalidgradeable
                //that all users are either on the same team or no team).
                ElectronicGraderController::CreateTeamWithLeaderAndUsers($this->core, $gradeable, $leader, $user_ids);
                $team =  $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $leader);
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
        }
        
        $user_path = FileUtils::joinPaths($gradeable_path, $who_id);
        $this->upload_details['user_path'] = $user_path;
        if (!FileUtils::createDir($user_path)) {
                return $this->uploadResult("Failed to make folder for this assignment for the user.", false);
        }
    
        $new_version = $gradeable->getHighestVersion() + 1;
        $version_path = FileUtils::joinPaths($user_path, $new_version);
        
        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make folder for the current version.", false);
        }
    
        $this->upload_details['version_path'] = $version_path;
        $this->upload_details['version'] = $new_version;
        
        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $max_size = $gradeable->getMaxSize();

        $path = rawurldecode(htmlspecialchars_decode($path));

        $uploaded_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $path);

        $uploaded_file = rawurldecode(htmlspecialchars_decode($uploaded_file));

        // copy over the uploaded file
        if (isset($uploaded_file)) {
            if (!@copy($uploaded_file, FileUtils::joinPaths($version_path,"upload.pdf"))) {
                return $this->uploadResult("Failed to copy uploaded file {$uploaded_file} to current submission.", false);
            }
            if (!@unlink($uploaded_file)) {
                return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
            }
            if (!@unlink(str_replace(".pdf", "_cover.pdf", $uploaded_file))) {
                return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
            }
        }

        // if split_pdf/gradeable_id/timestamp directory is now empty, delete that directory
        $timestamp = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR));
        $timestamp_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $timestamp);
        $files = FileUtils::getAllFiles($timestamp_path);
        if (count($files) == 0) {
            if (!FileUtils::recursiveRmdir($timestamp_path)) {
                return $this->uploadResult("Failed to remove the empty timestamp directory {$timestamp} from the split_pdf directory.", false);
            }
        }
        
    
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        if (!file_exists($settings_file)) {
            $json = array("active_version" => $new_version,
                          "history" => array(array("version" => $new_version,
                                                   "time" => $current_time_string_tz,
                                                   "who" => $original_user_id,
                                                   "type" => "upload")));
        }
        else {
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                return $this->uploadResult("Failed to open settings file.", false);
            }
            $json["active_version"] = $new_version;
            $json["history"][] = array("version"=> $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "upload");
        }
    
        // TODO: If any of these fail, should we "cancel" (delete) the entire submission attempt or just leave it?
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            return $this->uploadResult("Failed to write to settings file.", false);
        }
        
        $this->upload_details['assignment_settings'] = true;

        if (!@file_put_contents(FileUtils::joinPaths($version_path, ".submit.timestamp"), $current_time_string_tz."\n")) {
            return $this->uploadResult("Failed to save timestamp file for this submission.", false);
        }

        $queue_file = array($this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse(),
            $gradeable->getId(), $who_id, $new_version);
        $queue_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "to_be_graded_queue",
            implode("__", $queue_file));

        // create json file...
        if ($gradeable->isTeamAssignment()) {
            $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
                                "course" => $this->core->getConfig()->getCourse(),
                                "gradeable" =>  $gradeable->getId(),
                                "required_capabilities" => $gradeable->getRequiredCapabilities(),
                                "max_possible_grading_time" => $gradeable->getMaxPossibleGradingTime(),
                                "queue_time" => $current_time,
                                "user" => $user_id,
                                "team" => $team_id,
                                "who" => $who_id,
                                "is_team" => True,
                                "version" => $new_version);
        }
        else {
            $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
                                "course" => $this->core->getConfig()->getCourse(),
                                "gradeable" =>  $gradeable->getId(),
                                "required_capabilities" => $gradeable->getRequiredCapabilities(),
                                "max_possible_grading_time" => $gradeable->getMaxPossibleGradingTime(),
                                "queue_time" => $current_time,
                                "user" => $user_id,
                                "team" => $team_id,
                                "who" => $who_id,
                                "is_team" => False,
                                "version" => $new_version);
        }

        if (@file_put_contents($queue_file, FileUtils::encodeJson($queue_data), LOCK_EX) === false) {
            return $this->uploadResult("Failed to create file for grading queue.", false);
        }

        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), null, $team_id, $new_version, $current_time);
        }
        else {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), $user_id, null, $new_version, $current_time);
        }

        return $this->uploadResult("Successfully uploaded version {$new_version} for {$gradeable->getName()} for {$who_id}");
    }

    /**
     * Function for deleting a split item from the uploads/split_pdf/gradeable_id/timestamp folder. This should be called via AJAX, 
     * saving the result to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     *
     * @return boolean
     */
    private function ajaxDeleteSplitItem() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }
        
        $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();

        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }
        if (!isset($_POST['path'])) {
            return $this->uploadResult("Invalid path.", false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $gradeable = $gradeable_list[$gradeable_id];
        $gradeable->loadResultDetails();
        $path = rawurldecode(htmlspecialchars_decode($_POST['path']));

        $uploaded_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $path);

        $uploaded_file = rawurldecode(htmlspecialchars_decode($uploaded_file));

        if (!@unlink($uploaded_file)) {
            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
        }

        if (!@unlink(str_replace(".pdf", "_cover.pdf", $uploaded_file))) {
            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_file} from temporary storage.", false);
        }

        // delete timestamp folder if empty
        $timestamp = substr($path, 0, strpos($path, DIRECTORY_SEPARATOR));
        $timestamp_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf",
            $gradeable->getId(), $timestamp);
        $files = FileUtils::getAllFiles($timestamp_path);
        if (count($files) == 0) {
            if (!FileUtils::recursiveRmdir($timestamp_path)) {
                return $this->uploadResult("Failed to remove the empty timestamp directory {$timestamp} from the split_pdf directory.", false);
            }
        }

        return $this->uploadResult("Successfully deleted this PDF.");
    }

    /**
     * Function for uploading a submission to the server. This should be called via AJAX, saving the result
     * to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     *
     * @return array
     */
    private function ajaxUploadSubmission() {
        if (empty($_POST)) {
            $max_size = ini_get('post_max_size');
            return $this->uploadResult("Empty POST request. This may mean that the sum size of your files are greater than {$max_size}.", false);
        }
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token.", false);
        }

        $vcs_checkout = isset($_REQUEST['vcs_checkout']) ? $_REQUEST['vcs_checkout'] === "true" : false;
        if ($vcs_checkout && !isset($_POST['repo_id'])) {
            return $this->uploadResult("Invalid repo id.", false);
        }

        $student_page = isset($_REQUEST['student_page']) ? $_REQUEST['student_page'] === "true" : false;
        if ($student_page && !isset($_POST['pages'])) {
            return $this->uploadResult("Invalid pages.", false);
        }
    
        $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();
        
        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }

        if (!isset($_POST['user_id'])) {
            return $this->uploadResult("Invalid user id.", false);
        }

        $gradeable_id = $_REQUEST['gradeable_id'];
        $original_user_id = $this->core->getUser()->getId();
        $user_id = $_POST['user_id'];
        // repo_id for VCS use
        $repo_id = $_POST['repo_id'];

        // make sure is admin if the two ids do not match
        if ($original_user_id !== $user_id && !$this->core->getUser()->accessAdmin()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        if ($user_id == $original_user_id) {
            $gradeable = $gradeable_list[$gradeable_id];
        }
        else {
            $gradeable = $this->core->getQueries()->getGradeable($gradeable_id, $user_id);
        }
        $gradeable->loadResultDetails();

        // if student submission, make sure that gradeable allows submissions
        if (!$this->core->getUser()->accessGrading() && $original_user_id == $user_id && !$gradeable->getStudentSubmit()) {
            $msg = "You do not have access to that page.";
            $this->core->addErrorMessage($msg);
            return $this->uploadResult($msg, false);
        }

        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId());
        
        /*
         * Perform checks on the following folders (and whether or not they exist):
         * 1) the assignment folder in the submissions directory
         * 2) the student's folder in the assignment folder
         * 3) the version folder in the student folder
         * 4) the part folders in the version folder in the version folder
         */
        if (!FileUtils::createDir($gradeable_path)) {
            return $this->uploadResult("Failed to make folder for this assignment.", false);
        }
        
        $who_id = $user_id;
        $team_id = "";
        if ($gradeable->isTeamAssignment()) {
            $team = $gradeable->getTeam();
            if ($team !== null) {
                $team_id = $team->getId();
                $who_id = $team_id;
                $user_id = "";
            }
            else {
                return $this->uploadResult("Must be on a team to access submission.", false);
            }
        }
        
        $user_path = FileUtils::joinPaths($gradeable_path, $who_id);
        $this->upload_details['user_path'] = $user_path;
        if (!FileUtils::createDir($user_path)) {
                return $this->uploadResult("Failed to make folder for this assignment for the user.", false);
        }
    
        $new_version = $gradeable->getHighestVersion() + 1;
        $version_path = FileUtils::joinPaths($user_path, $new_version);
        
        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make folder for the current version.", false);
        }
    
        $this->upload_details['version_path'] = $version_path;
        $this->upload_details['version'] = $new_version;
    
        $part_path = array();
        // We upload the assignment such that if it's multiple parts, we put it in folders "part#" otherwise
        // put all files in the root folder
        if ($gradeable->getNumParts() > 1) {
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                $part_path[$i] = FileUtils::joinPaths($version_path, "part".$i);
                if (!FileUtils::createDir($part_path[$i])) {
                    return $this->uploadResult("Failed to make the folder for part {$i}.", false);
                }
            }
        }
        else {
            $part_path[1] = $version_path;
        }
        
        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $max_size = $gradeable->getMaxSize();
        
        if ($vcs_checkout === false) {
            $uploaded_files = array();
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++){
                if (isset($_FILES["files{$i}"])) {
                    $uploaded_files[$i] = $_FILES["files{$i}"];
                }
            }
            
            $errors = array();
            $count = array();
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if (isset($uploaded_files[$i])) {
                    $count[$i] = count($uploaded_files[$i]["name"]);
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (!isset($uploaded_files[$i]["tmp_name"][$j]) || $uploaded_files[$i]["tmp_name"][$j] === "") {
                            $error_message = $uploaded_files[$i]["name"][$j]." failed to upload. ";
                            if (isset($uploaded_files[$i]["error"][$j])) {
                                $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_files[$i]["error"][$j]). ".";
                            }
                            $errors[] = $error_message;
                        }
                    }
                }
            }
            
            if (count($errors) > 0) {
                $error_text = implode("\n", $errors);
                return $this->uploadResult("Upload Failed: ".$error_text, false);
            }

            // save the contents of the text boxes to files
            $empty_textboxes = true;
            if (isset($_POST['textbox_answers'])) {
                $textbox_answer_array = json_decode($_POST['textbox_answers']);
                for ($i = 0; $i < $gradeable->getNumTextBoxes(); $i++) {
                    $textbox_answer_val = $textbox_answer_array[$i];
                    if ($textbox_answer_val != "") $empty_textboxes = false;
                    $filename = $gradeable->getTextboxes()[$i]['filename'];
                    $dst = FileUtils::joinPaths($version_path, $filename);
                    // FIXME: add error checking
                    $file = fopen($dst, "w");
                    fwrite($file, $textbox_answer_val);
                    fclose($file);
                }
            }
    
            $previous_files = array();
            $previous_part_path = array();
            $tmp = json_decode($_POST['previous_files']);
            for ($i = 0; $i < $gradeable->getNumParts(); $i++) {
                if (count($tmp[$i]) > 0) {
                    $previous_files[$i + 1] = $tmp[$i];
                }
            }
            
            if (empty($uploaded_files) && empty($previous_files) && $empty_textboxes) {
                return $this->uploadResult("No files to be submitted.", false);
            }
            
            if (count($previous_files) > 0) {
                if ($gradeable->getHighestVersion() === 0) {
                    return $this->uploadResult("No submission found. There should not be any files from a previous submission.", false);
                }
                
                $previous_path = FileUtils::joinPaths($user_path, $gradeable->getHighestVersion());
                if ($gradeable->getNumParts() > 1) {
                    for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                        $previous_part_path[$i] = FileUtils::joinPaths($previous_path, "part".$i);
                    }
                }
                else {
                    $previous_part_path[1] = $previous_path;
                }

                foreach ($previous_part_path as $path) {
                    if (!is_dir($path)) {
                        return $this->uploadResult("Files from previous submission not found. Folder for previous submission does not exist.", false);
                    }
                }
    
                for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                    if (isset($previous_files[$i])) {
                        foreach ($previous_files[$i] as $prev_file) {
                            $filename = FileUtils::joinPaths($previous_part_path[$i], $prev_file);
                            if (!file_exists($filename)) {
                                $name = basename($filename);
                                return $this->uploadResult("File '{$name}' does not exist in previous submission.", false);
                            }
                        }
                    }
                }
            }
            
            // Determine the size of the uploaded files as well as whether or not they're a zip or not.
            // We save that information for later so we know which files need unpacking or not and can save
            // a check to getMimeType()
            $file_size = 0;
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if (isset($uploaded_files[$i])) {
                    $uploaded_files[$i]["is_zip"] = array();
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (FileUtils::getMimeType($uploaded_files[$i]["tmp_name"][$j]) == "application/zip") {
                            if(FileUtils::checkFileInZipName($uploaded_files[$i]["tmp_name"][$j]) === false) {
                                return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside ".$uploaded_files[$i]["name"][$j].".", false);
                            }
                            $uploaded_files[$i]["is_zip"][$j] = true;
                            $file_size += FileUtils::getZipSize($uploaded_files[$i]["tmp_name"][$j]);
                        }
                        else {
                            if(FileUtils::isValidFileName($uploaded_files[$i]["name"][$j]) === false) {
                                return $this->uploadResult("Error: You may not use quotes, backslashes or angle brackets in your file name ".$uploaded_files[$i]["name"][$j].".", false);
                            }
                            $uploaded_files[$i]["is_zip"][$j] = false;
                            $file_size += $uploaded_files[$i]["size"][$j];
                        }
                    }
                }
                if (isset($previous_files[$i]) && isset($previous_part_path[$i])) {
                    foreach ($previous_files[$i] as $prev_file) {
                        $file_size += filesize(FileUtils::joinPaths($previous_part_path[$i], $prev_file));
                    }
                }
            }
            
            if ($file_size > $max_size) {
                return $this->uploadResult("File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.", false);
            }

            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                // copy selected previous submitted files
                if (isset($previous_files[$i])){
                    for ($j=0; $j < count($previous_files[$i]); $j++){
                        $src = FileUtils::joinPaths($previous_part_path[$i], $previous_files[$i][$j]);
                        $dst = FileUtils::joinPaths($part_path[$i], $previous_files[$i][$j]);
                        if (!@copy($src, $dst)) {
                            return $this->uploadResult("Failed to copy previously submitted file {$previous_files[$i][$j]} to current submission.", false);
                        }
                    }
                }

                if (isset($uploaded_files[$i])) {
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if ($uploaded_files[$i]["is_zip"][$j] === true) {
                            $zip = new \ZipArchive();
                            $res = $zip->open($uploaded_files[$i]["tmp_name"][$j]);
                            if ($res === true) {
                                $zip->extractTo($part_path[$i]);
                                $zip->close();
                            }
                            else {
                                // If the zip is an invalid zip (say we remove the last character from the zip file
                                // then trying to get the status code will throw an exception and not give us a string
                                // so we have that string hardcoded, otherwise we can just get the status string as
                                // normal.
                                $error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
                                return $this->uploadResult("Could not properly unpack zip file. Error message: ".$error_message.".", false);
                            }
                        }
                        else {
                            if ($this->core->isTesting() || is_uploaded_file($uploaded_files[$i]["tmp_name"][$j])) {
                                $dst = FileUtils::joinPaths($part_path[$i], $uploaded_files[$i]["name"][$j]);
                                if (!@copy($uploaded_files[$i]["tmp_name"][$j], $dst)) {
                                    return $this->uploadResult("Failed to copy uploaded file {$uploaded_files[$i]["name"][$j]} to current submission.", false);
                                }
                            }
                            else {
                                return $this->uploadResult("The tmp file '{$uploaded_files[$i]['name'][$j]}' was not properly uploaded.", false);
                            }
                        }
                        // Is this really an error we should fail on?
                        if (!@unlink($uploaded_files[$i]["tmp_name"][$j])) {
                            return $this->uploadResult("Failed to delete the uploaded file {$uploaded_files[$i]["name"][$j]} from temporary storage.", false);
                        }
                    }
                }
            }
        }
        else {
            $vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();
            $vcs_path = $gradeable->getSubdirectory();

            // use entirely student input
            if ($vcs_base_url == "" && $vcs_path == "") {
                if ($repo_id == "") {
                    // FIXME: commented out for now to pass Travis.
                    // SubmissionControllerTests needs to be rewriten for proper VCS uploads.
                    // return $this->uploadResult("repository url input cannot be blank.", false);
                }
                $vcs_full_path = $repo_id;
            }
            // use base url + path with variable string replacements
            else {
                if (strpos($vcs_path,"\$repo_id") !== false && $repo_id == "") {
                    return $this->uploadResult("repository id input cannot be blank.", false);
                }
                $vcs_path = str_replace("{\$gradeable_id}",$gradeable_id,$vcs_path);
                $vcs_path = str_replace("{\$user_id}",$who_id,$vcs_path);
                $vcs_path = str_replace("{\$team_id}",$who_id,$vcs_path);
                $vcs_path = str_replace("{\$repo_id}",$repo_id,$vcs_path);
                $vcs_full_path = $vcs_base_url.$vcs_path;
            }

            if (!@touch(FileUtils::joinPaths($version_path, ".submit.VCS_CHECKOUT"))) {
                return $this->uploadResult("Failed to touch file for vcs submission.", false);
            }
        }

        // save the contents of the page number inputs to files
        $empty_pages = true;
        if (isset($_POST['pages'])) {
            $pages_array = json_decode($_POST['pages']);
            $total = count($gradeable->getComponents());
            $filename = "student_pages.json";
            $dst = FileUtils::joinPaths($version_path, $filename);
            $json = array();
            $i = 0;
            foreach ($gradeable->getComponents() as $question) {
                $order = intval($question->getOrder());
                $title = $question->getTitle();
                $page_val = intval($pages_array[$i]);   
                $json[] = array("order" => $order,
                                "title" => $title,
                                "page #" => $page_val);
                $i++;
            }
            if (!@file_put_contents($dst, FileUtils::encodeJson($json))) {
                return $this->uploadResult("Failed to write to pages file.", false);
            }
        }
    
        $settings_file = FileUtils::joinPaths($user_path, "user_assignment_settings.json");
        if (!file_exists($settings_file)) {
            $json = array("active_version" => $new_version,
                          "history" => array(array("version" => $new_version,
                                                   "time" => $current_time_string_tz,
                                                   "who" => $original_user_id,
                                                   "type" => "upload")));
        }
        else {
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                return $this->uploadResult("Failed to open settings file.", false);
            }
            $json["active_version"] = $new_version;
            $json["history"][] = array("version"=> $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "upload");
        }
    
        // TODO: If any of these fail, should we "cancel" (delete) the entire submission attempt or just leave it?
        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            return $this->uploadResult("Failed to write to settings file.", false);
        }
        
        $this->upload_details['assignment_settings'] = true;

        if (!@file_put_contents(FileUtils::joinPaths($version_path, ".submit.timestamp"), $current_time_string_tz."\n")) {
            return $this->uploadResult("Failed to save timestamp file for this submission.", false);
        }

        $queue_file = array($this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse(),
            $gradeable->getId(), $who_id, $new_version);
        $queue_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "to_be_graded_queue",
            implode("__", $queue_file));

        // create json file...
        if ($gradeable->isTeamAssignment()) {
            $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
                                "course" => $this->core->getConfig()->getCourse(),
                                "gradeable" =>  $gradeable->getId(),
                                "required_capabilities" => $gradeable->getRequiredCapabilities(),
                                "max_possible_grading_time" => $gradeable->getMaxPossibleGradingTime(),
                                "queue_time" => $current_time,
                                "user" => $user_id,
                                "team" => $team_id,
                                "who" => $who_id,
                                "is_team" => True,
                                "version" => $new_version);
        }
        else {
            $queue_data = array("semester" => $this->core->getConfig()->getSemester(),
                                "course" => $this->core->getConfig()->getCourse(),
                                "gradeable" =>  $gradeable->getId(),
                                "required_capabilities" => $gradeable->getRequiredCapabilities(),
                                "max_possible_grading_time" => $gradeable->getMaxPossibleGradingTime(),
                                "queue_time" => $current_time,
                                "user" => $user_id,
                                "team" => $team_id,
                                "who" => $who_id,
                                "is_team" => False,
                                "version" => $new_version);
        }
        

        if (@file_put_contents($queue_file, FileUtils::encodeJson($queue_data), LOCK_EX) === false) {
            return $this->uploadResult("Failed to create file for grading queue.", false);
        }

        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), null, $team_id, $new_version, $current_time);
        }
        else {
            $this->core->getQueries()->insertVersionDetails($gradeable->getId(), $user_id, null, $new_version, $current_time);
        }

        if ($user_id == $original_user_id)
            $this->core->addSuccessMessage("Successfully uploaded version {$new_version} for {$gradeable->getName()}");
        else
            $this->core->addSuccessMessage("Successfully uploaded version {$new_version} for {$gradeable->getName()} for {$who_id}");
            

        return $this->uploadResult("Successfully uploaded files");
    }
    
    private function uploadResult($message, $success = true) {
        if (!$success) {
            // we don't want to throw an exception here as that'll mess up our return json payload
            if ($this->upload_details['version_path'] !== null
                && !FileUtils::recursiveRmdir($this->upload_details['version_path'])) {
                // @codeCoverageIgnoreStart
                // Without the filesystem messing up here, we should not be able to hit this error
                Logger::error("Could not clean up folder {$this->upload_details['version_path']}");

            }
            // @codeCoverageIgnoreEnd
            else if ($this->upload_details['assignment_settings'] === true) {
                $settings_file = FileUtils::joinPaths($this->upload_details['user_path'], "user_assignment_settings.json");
                $settings = json_decode(file_get_contents($settings_file), true);
                if (count($settings['history']) == 1) {
                    unlink($settings_file);
                }
                else {
                    array_pop($settings['history']);
                    $last = Utils::getLastArrayElement($settings['history']);
                    $settings['active_version'] = $last['version'];
                    file_put_contents($settings_file, FileUtils::encodeJson($settings));
                }
            }
        }

        $return = array('success' => $success, 'error' => !$success, 'message' => $message);
        
        $this->core->getOutput()->renderJson($return);
        return $return;
    }
    
    private function updateSubmissionVersion() {
        if (isset($_REQUEST['ta'])){
            $ta = $_REQUEST['ta'];
            $who = $_REQUEST['who'];
            $individual = $_REQUEST['individual'];
            $mylist = new GradeableList($this->core, $this->core->getQueries()->getUserById($who));
            $gradeable_list = $mylist->getSubmittableElectronicGradeables();
        }
        else{
            $ta = false;
            $gradeable_list = $this->gradeables_list->getSubmittableElectronicGradeables();
        }
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            $msg = "Invalid gradeable id.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->buildUrl(array('component' => 'student')));
            return array('error' => true, 'message' => $msg);
        }
        
        $gradeable = $gradeable_list[$_REQUEST['gradeable_id']];
        $gradeable->loadResultDetails();
        $url = $this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()));
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $msg = "Invalid CSRF token. Refresh the page and try again.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        if ($gradeable->isTeamAssignment() && $gradeable->getTeam() === null) {
            $msg = 'Must be on a team to access submission.';
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
            return array('error' => true, 'message' => $msg);
        }
    
        $new_version = intval($_REQUEST['new_version']);
        if ($new_version < 0) {
            $msg = "Cannot set the version below 0.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }
        
        if ($new_version > $gradeable->getHighestVersion()) {
            $msg = "Cannot set the version past {$gradeable->getHighestVersion()}.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        if (!$this->core->getUser()->accessGrading() && !$gradeable->getStudentSubmit()) {
            $msg = "Cannot submit for this assignment.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }

        $original_user_id = $this->core->getUser()->getId();
        $user_id = $gradeable->getUser()->getId();
        if ($gradeable->isTeamAssignment()) {
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($gradeable->getId(), $user_id);
            if ($team !== null) {
                $user_id = $team->getId();
            }
        }
    
        $settings_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId(), $user_id, "user_assignment_settings.json");
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $msg = "Failed to open settings file.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($url);
            return array('error' => true, 'message' => $msg);
        }
        $json["active_version"] = $new_version;
        $current_time = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        $current_time_string_tz = $current_time . " " . $this->core->getConfig()->getTimezone()->getName();

        $json["history"][] = array("version" => $new_version, "time" => $current_time_string_tz, "who" => $original_user_id, "type" => "select");

        if (!@file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $msg = "Could not write to settings file.";
            $this->core->addErrorMessage($msg);
            $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                              'gradeable_id' => $gradeable->getId())));
            return array('error' => true, 'message' => $msg);
        }

        $version = ($new_version > 0) ? $new_version : null;

        if($gradeable->isTeamAssignment()) {
            $this->core->getQueries()->updateActiveVersion($gradeable->getId(), null, $user_id, $version);
        }
        else {
            $this->core->getQueries()->updateActiveVersion($gradeable->getId(), $user_id, null, $version);
        }
        

        if ($new_version == 0) {
            $msg = "Cancelled submission for gradeable.";
            $this->core->addSuccessMessage($msg);
        }
        else {
            $msg = "Updated version of gradeable to version #{$new_version}.";
            $this->core->addSuccessMessage($msg);
        }
        if($ta) {
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic',
                                                    'action' => 'grade', 'gradeable_id' => $gradeable->getId(),
                                                    'who_id'=>$who, 'individual'=>$individual,
                                                          'gradeable_version' => $new_version)));
        }
        else {
            $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                          'gradeable_id' => $gradeable->getId(),
                                                          'gradeable_version' => $new_version)));
        }

        return array('error' => false, 'version' => $new_version, 'message' => $msg);
    }
    
    /**
     * Check if the results folder exists for a given gradeable and version results.json
     * in the results/ directory. If the file exists, we output a string that the calling
     * JS checks for to initiate a page refresh (so as to go from "in-grading" to done
     */
    public function checkRefresh() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $version = $_REQUEST['gradeable_version'];
        $g_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
        $gradeable = $this->gradeables_list->getGradeable($g_id, GradeableType::ELECTRONIC_FILE);

        $user_id = $this->core->getUser()->getId();
        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($g_id, $user_id);
            if ($team !== null) {
                $user_id = $team->getId();
            }
        }

        $path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "results", $g_id,
            $user_id, $version);
        if (file_exists($path."/results.json")) {
            $refresh_string = "REFRESH_ME";
            $refresh_bool = true;
        }
        else {
            $refresh_string = "NO_REFRESH";
            $refresh_bool = false;
        }
        $this->core->getOutput()->renderString($refresh_string);
        return array('refresh' => $refresh_bool, 'string' => $refresh_string);
    }
}
