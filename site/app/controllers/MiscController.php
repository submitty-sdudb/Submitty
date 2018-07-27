<?php

namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;

class MiscController extends AbstractController {
    public function run() {
        foreach (array('path', 'file') as $key) {
            if (isset($_REQUEST[$key])) {
                $_REQUEST[$key] = htmlspecialchars_decode(urldecode($_REQUEST[$key]));
            }
        }

        switch($_REQUEST['page']) {
            case 'display_file':
                $this->displayFile();
                break;
            case 'download_file':
                $this->downloadFile();
                break;
            case 'download_file_with_any_role':
                $this->downloadFile(true);
                break;
            case 'delete_course_material_file':
                $this->deleteCourseMaterialFile();
                break;
            case 'delete_course_material_folder':
                $this->deleteCourseMaterialFolder();
                break;
            case 'download_zip':
                $this->downloadZip();
                break;
            case 'download_all_assigned':
                $this->downloadAssignedZips();
                break;
            case 'modify_course_materials_file_permission':
                $this->modifyCourseMaterialsFilePermission();
                break;
            case 'modify_course_materials_file_time_stamp':
                $this->modifyCourseMaterialsFileTimeStamp();
                break;
        }
    }

    // function to check that this is a valid access request
    private function checkValidAccess($is_zip, &$error_string, $download_with_any_role = false, $gradeable = null) {
        $error_string="";
        // only allow zip if it's a grader
        if ($is_zip) {
            $error_string="only graders may access zip files of other students";
            $can_download = (
                $gradeable !== null
                && !$gradeable->useVcsCheckout()
                && $gradeable->getStudentDownload()
                && ($gradeable->getCurrentVersionNumber() === $gradeable->getActiveVersion() || $gradeable->getStudentAnyVersion())
                && $this->core->getUser()->getId() === $_REQUEST['user_id']
            );
            return ($this->core->getUser()->accessGrading() || $can_download);
        }
        // from this point on, is not a zip
        // do path and permissions checking

        $dir = $_GET['dir'];
        $path = $_GET['path'];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part == ".." || $part == ".") {
                 $error_string=".. or . in path";
                 return false;
            }
        }

        if (!FileUtils::isValidFileName($path)) {
            $error_string="not valid filename";
            return false;
        }


        // TEMPORARY HACK PUT THIS HERE
        // INSTRUCTORS ARE UNABLE TO VIEW VCS CHECKOUT FILES WITHOUT THIS
        // if instructor or grader, then it's okay
        if ($this->core->getUser()->accessGrading()) {
            return true;
        }
        // END HACK


        $possible_directories = array("config_upload", "uploads", "submissions", "results", "checkout", "forum_attachments", "uploads/course_materials");
        if (!in_array($dir, $possible_directories)) {
            $error_string="not in possible directories list";
            return false;
        }

        $course_path = $this->core->getConfig()->getCoursePath();
        $check = FileUtils::joinPaths($course_path, $dir);
        if (!Utils::startsWith($path, $check)) {
            $error_string= "does not start with course path";
            return false;
        }
        if (!file_exists($path)) {
            $error_string="path does not exist";
            return false;
        }

        if ($dir === "config_upload" || $dir === "uploads") {
            $error_string="only admin can access uploads";
            return ($this->core->getUser()->accessAdmin());
        } else if($dir === "forum_attachments"){
            //Might need to be revisted but for now fixes the problem where students can't view attachments...
            return true;
        } else if ($dir === "submissions" || $dir === "results" || $dir === "checkout") {
            // if instructor or grader, then it's okay
            if ($this->core->getUser()->accessGrading()) {
                return true;
            }

            // FIXME: need to make this work for peer grading

            $current_user_id = $this->core->getUser()->getId();
            // get the information from the path
            $path_folder = FileUtils::joinPaths($course_path, $dir);
            $path_rest = substr($path, strlen($path_folder)+1);
            $path_gradeable_id = substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR));
            $path_rest = substr($path_rest, strlen($path_gradeable_id)+1);
            $path_user_id = substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR));
            $path_rest = substr($path_rest, strlen($path_user_id)+1);
            $path_version = intval(substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR)));

            // gradeable to get temporary info from
            // if team, get one of the user ids via the team id
            $current_gradeable = $this->core->getQueries()->getGradeable($path_gradeable_id, $current_user_id);
            if($current_gradeable->getPeerGrading()) {
                $peer_grade_set = $this->core->getQueries()->getPeerAssignment($path_gradeable_id, $current_user_id);
                if(in_array($path_user_id, $peer_grade_set)) {
                    return true;
                }
            }
            if ($current_gradeable->isTeamAssignment()) {
                $path_team_id = $path_user_id;
                $path_team_members = $this->core->getQueries()->getTeamById($path_team_id)->getMembers();
                if (count($path_team_members) == 0) {
                     $error_string="this team currently has no members";
                     return false;
                }
                $path_user_id = $path_team_members[0];
            }

            // use the current user id to get the gradeable specified in the path
            $path_gradeable = $this->core->getQueries()->getGradeable($path_gradeable_id, $path_user_id);
            if ($path_gradeable === null) {
                 $error_string="something wrong with gradeable path";
                 return false;
            }

            // if gradeable student view or download false, don't allow anything
            if ($dir == "submissions" && (!$path_gradeable->getStudentView() || !$path_gradeable->getStudentDownload())) {
                 $error_string="students can't view / download submissions for this gradeable";
                 return false;
            }

            // make sure that version is active version if student any version is false
            if (!$path_gradeable->getStudentAnyVersion() && $path_version !== $path_gradeable->getActiveVersion()) {
                 $error_string="you are only allowed only view the active submission version";
                 return false;
            }

            // if team assignment, check that team id matches the team of the current user
            if ($path_gradeable->isTeamAssignment()) {
                $current_team = $this->core->getQueries()->getTeamByGradeableAndUser($path_gradeable_id,$current_user_id);
                if ($current_team === null) {
                     $error_string="this is an invalid team and/or user";
                     return false;
                }
                $current_team_id = $current_team->getId();
                if ($path_team_id != $current_team_id) {
                     $error_string="user is not a member of this team for this gradeable";
                     return false;
                }
            }
            // else, just check that the user ids match
            else {
                if ($current_user_id != $path_user_id) {
                     $error_string="user does not match";
                     return false;
                }
            }
            return true;
        }
        else if ($download_with_any_role === true)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function displayFile() {
        // security check
        $error_string="";
        if (!$this->checkValidAccess(false,$error_string)) {
            $this->core->getOutput()->showError("You do not have access to this file ".$error_string);
            return false;
        }

        $corrected_name = pathinfo($_REQUEST['path'], PATHINFO_DIRNAME) . "/" .  basename(rawurldecode(htmlspecialchars_decode($_GET['path'])));
        $mime_type = FileUtils::getMimeType($corrected_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: ".$mime_type);
            header('Content-Disposition: inline; filename="' . basename(rawurldecode(htmlspecialchars_decode($_GET['path']))) . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($_REQUEST['path']);
        }
        else {
            $contents = file_get_contents($corrected_name);
            if (array_key_exists('ta_grading', $_REQUEST) && $_REQUEST['ta_grading'] === "true") {
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $mime_type, $corrected_name, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }

    private function deleteCourseMaterialFile() {
        // security check
        $error_string="";
        if (!$this->checkValidAccess(false,$error_string)) {
            $message = "You do not have access to that page. ".$error_string;
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                                    'page' => 'course_materials',
                                                    'action' => 'view_course_materials_page')));
        }

        // delete the file from upload/course_materials
        $filename = (pathinfo($_REQUEST['path'], PATHINFO_DIRNAME) . "/" . basename(rawurldecode(htmlspecialchars_decode($_GET['path']))));
        if ( unlink($filename) )
        {
            $this->core->addSuccessMessage(basename($filename) . " has been successfully removed.");
        }
        else{
            $this->core->addErrorMessage("Failed to remove " . basename($filename));
        }

        // remove entry from json file
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $json = FileUtils::readJsonFile($fp);
        if ($json != false)
        {
            unset($json[$filename]);

            if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
                return "Failed to write to file {$fp}";
            }
        }

        //refresh course materials page
        $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                                    'page' => 'course_materials',
                                                    'action' => 'view_course_materials_page')));
    }

    private function deleteCourseMaterialFolder() {
        // security check


        $error_string="";
        if (!$this->checkValidAccess(false,$error_string)) {
            $message = "You do not have access to that page. ".$error_string;
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                                    'page' => 'course_materials',
                                                    'action' => 'view_course_materials_page')));
        }


        $path = $_GET['path'];

        // remove entry from json file
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        if ($json != false)
        {
            $all_files = FileUtils::getAllFiles($path);
            foreach($all_files as $file){
                $filename = $file['path'];
                unset($json[$filename]);
            }

            file_put_contents($fp, FileUtils::encodeJson($json));
        }

        if ( FileUtils::recursiveRmdir($path) )
        {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else{
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        //refresh course materials page
        $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                                    'page' => 'course_materials',
                                                    'action' => 'view_course_materials_page')));
    }

    private function downloadFile($download_with_any_role = false) {
        // security check
        $error_string="";
        if (!$this->checkValidAccess(false,$error_string, $download_with_any_role)) {
            $message = "You do not have access to that page. ".$error_string;
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        $filename = rawurldecode(htmlspecialchars_decode($_REQUEST['file']));
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        readfile(pathinfo($_REQUEST['path'], PATHINFO_DIRNAME) . "/" . basename(rawurldecode(htmlspecialchars_decode($_GET['path']))));
    }

    private function downloadZip() {
        $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id'], $_REQUEST['user_id']);
        // security check
        $error_string="";
        if (!$this->checkValidAccess(true, $error_string, false, $gradeable)) {
            $message = "You do not have access to that page. ".$error_string;
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $zip_file_name = $_REQUEST['gradeable_id'] . "_" . $_REQUEST['user_id'] . "_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $gradeable->getActiveVersion();
        $version = isset($_REQUEST['version']) ? $_REQUEST['version'] : $active_version;
        $folder_names = array();
        $folder_names[] = "submissions";
        $folder_names[] = "results";
        $folder_names[] = "checkout";
        $submissions_path = FileUtils::joinPaths($gradeable_path, $folder_names[0], $gradeable->getId(), $gradeable->getUser()->getId(), $version);
        $results_path = FileUtils::joinPaths($gradeable_path, $folder_names[1], $gradeable->getId(), $gradeable->getUser()->getId(), $version);
        $checkout_path = FileUtils::joinPaths($gradeable_path, $folder_names[2], $gradeable->getId(), $gradeable->getUser()->getId(), $version);
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $paths = array();
        $paths[] = $submissions_path;
        if($this->core->getUser()->accessGrading()) {
            $paths[] = $results_path;
            $paths[] = $checkout_path;
        }
        for ($x = 0; $x < count($paths); $x++) {
            if (is_dir($paths[$x])) {
                    $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($paths[$x]),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $zip -> addEmptyDir($folder_names[$x]);
                foreach ($files as $name => $file)
                {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir())
                    {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($paths[$x]) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $folder_names[$x] . "/" . $relativePath);
                    }
                }
            }
        }

        $zip->close();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }

    private function downloadAssignedZips() {
        // security check
        if (!($this->core->getUser()->accessGrading())) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $zip_file_name = $_REQUEST['gradeable_id'] . "_section_students_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if(isset($_REQUEST['type']) && $_REQUEST['type'] === "All") {
            $type = "all";
            $zip_file_name = $_REQUEST['gradeable_id'] . "_all_students_" . date("m-d-Y") . ".zip";
            if (!($this->core->getUser()->accessFullGrading())) {
                $message = "You do not have access to that page.";
                $this->core->addErrorMessage($message);
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }
        else
        {
            $type = "";
        }

        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id']);
        $paths = ['submissions'];
        if ($gradeable->useVcsCheckout()) {
            //VCS submissions are stored in the checkout directory
            $paths[] = 'checkout';
        }
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($paths as $path) {
            $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $path,
                $gradeable->getId());
            if($type === "all") {
                $zip->addEmptyDir($path);
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($gradeable_path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file)
                {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir())
                    {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($gradeable_path) + 1);
                        // Add current file to archive
                        $zip->addFile($filePath, $path . "/" . $relativePath);
                    }
                }
           } else {
               //gets the students that are part of the sections
               if ($gradeable->isGradeByRegistration()) {
                   $section_key = "registration_section";
                   $sections = $this->core->getUser()->getGradingRegistrationSections();
                   $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
               }
               else {
                   $section_key = "rotating_section";
                   $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                       $this->core->getUser()->getId());
                   $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
               }
               $students_array = array();
               foreach($students as $student) {
                   $students_array[] = $student->getId();
               }
               $files = scandir($gradeable_path);
               $arr_length = count($students_array);
               foreach($files as $file) {
                   for ($x = 0; $x < $arr_length; $x++) {
                       if ($students_array[$x] === $file) {
                           $temp_path = $gradeable_path . "/" . $file;
                           $files_in_folder = new \RecursiveIteratorIterator(
                               new \RecursiveDirectoryIterator($temp_path),
                               \RecursiveIteratorIterator::LEAVES_ONLY
                           );

                           //makes a new directory in the zip to add the files in
                           $zip -> addEmptyDir($file);

                           foreach ($files_in_folder as $name => $file_in_folder)
                           {
                               // Skip directories (they would be added automatically)
                               if (!$file_in_folder->isDir())
                               {
                                   // Get real and relative path for current file
                                   $filePath = $file_in_folder->getRealPath();
                                   $relativePath = substr($filePath, strlen($temp_path) + 1);
                                   // Add current file to archive
                                   $zip->addFile($filePath, $file . "/" . $relativePath);
                               }
                           }
                           $x = $arr_length; //cuts the for loop early when found
                        }
                    }
                }
            }
        }
        // Zip archive will be created only after closing object
        $zip->close();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }


	public function modifyCourseMaterialsFilePermission() {

        // security check
        if($this->core->getUser()->getGroup() !== 1) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                            'page' => 'course_materials',
                                            'action' => 'view_course_materials_page')));
            return;
        }

        if (!isset($_GET['filename']) ||
            !isset($_GET['checked'])) {
            return;
        }

        $file_name = htmlspecialchars($_GET['filename']);
        $checked =  $_GET['checked'];

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $release_datetime = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        $json = FileUtils::readJsonFile($fp);
        if ($json != false) {
            $release_datetime  = $json[$file_name]['release_datetime'];
        }

        if (!isset($release_datetime))
        {
            $release_datetime = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        }

        $json[$file_name] = array('checked' => $checked, 'release_datetime' => $release_datetime);

        if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
            return "Failed to write to file {$fp}";
		}
    }

	public function modifyCourseMaterialsFileTimeStamp() {

        if($this->core->getUser()->getGroup() !== 1) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                            'page' => 'course_materials',
                                            'action' => 'view_course_materials_page')));
           return;
        }

        if (!isset($_GET['filename']) ||
            !isset($_GET['newdatatime'])) {
            return;
        }

        $file_name = htmlspecialchars($_GET['filename']);
        $new_data_time = htmlspecialchars($_GET['newdatatime']);

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $checked = '0';
        $json = FileUtils::readJsonFile($fp);
        if ($json != false) {
            $checked  = $json[$file_name]['checked'];
        }

        $json[$file_name] = array('checked' => $checked, 'release_datetime' => $new_data_time);

        if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
            return "Failed to write to file {$fp}";
		}
    }
}
