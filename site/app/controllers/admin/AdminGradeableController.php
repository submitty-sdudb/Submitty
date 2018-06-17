<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use \lib\Database;
use \lib\Functions;
use \app\libraries\GradeableType;
use app\models\AdminGradeable;
use app\models\Gradeable;
use app\models\GradeableComponent;
use app\models\GradeableComponentMark;
use \DateTime;
use app\libraries\FileUtils;

class AdminGradeableController extends AbstractController
{
    public function run()
    {
        switch ($_REQUEST['action']) {
            case 'view_gradeable_page':
                $this->viewPage();
                break;
            case 'upload_new_gradeable':
                $this->createGradeableRequest();
                break;
            case 'edit_gradeable_page':
                $this->editPage(array_key_exists('nav_tab', $_REQUEST) ? $_REQUEST['nav_tab'] : 0);
                break;
            case 'update_gradeable':
                $this->updateGradeableRequest();
                break;
            case 'update_gradeable_rubric':
                // Other updates are happening real time,
                //  but the rubric and the grader assignment need
                //  to be update manually
                $this->updateRubricRequest();
                break;
            case 'update_gradeable_graders':
                $this->updateGradersRequest();
                break;
            case 'upload_new_template':
                $this->uploadNewTemplate();
                break;
            case 'quick_link':
                $this->quickLink();
                break;
            case 'delete_gradeable':
                $this->deleteGradeable();
                break; 
            case 'rebuild_assignement':
                $this->rebuildAssignmentRequest();
                break;           
            default:
                $this->viewPage();
                break;
        }
    }

    //Pulls the data from an existing gradeable and just prints it on the page
    private function uploadNewTemplate()
    {
        if ($_REQUEST['template_id'] === "--None--") {
            $this->viewPage();
            return;
        }
        $admin_gradeable = new AdminGradeable($this->core);
        $this->loadAdminGradeable($admin_gradeable);
        $this->core->getQueries()->getGradeableInfo($_REQUEST['template_id'], $admin_gradeable, true);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add_template", $admin_gradeable);
    }

    //view the page with no data from previous gradeables
    private function viewPage()
    {
        $admin_gradeable = new AdminGradeable($this->core);
        $this->loadAdminGradeable($admin_gradeable);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "add", $admin_gradeable);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage($nav_tab = 0)
    {
        $admin_gradeable = $this->getAdminGradeable($_REQUEST['id']);
        $this->loadAdminGradeable($admin_gradeable);
        $this->core->getOutput()->renderOutput(array('admin', 'AdminGradeable'), 'show_add_gradeable', "edit", $admin_gradeable, $nav_tab);
    }

    // Constructs the non-model data for the gradeable
    private function loadAdminGradeable(AdminGradeable $admin_gradeable)
    {
        $admin_gradeable->setRotatingGradeables($this->core->getQueries()->getRotatingSectionsGradeableIDS());
        $admin_gradeable->setGradeableSectionHistory($this->core->getQueries()->getGradeablesPastAndSection());
        $admin_gradeable->setNumSections($this->core->getQueries()->getNumberRotatingSections());
        $admin_gradeable->setGradersAllSection($this->core->getQueries()->getGradersForAllRotatingSections($admin_gradeable->g_id));
        $graders_from_usertype1 = $this->core->getQueries()->getGradersFromUserType(1);
        $graders_from_usertype2 = $this->core->getQueries()->getGradersFromUserType(2);
        $graders_from_usertype3 = $this->core->getQueries()->getGradersFromUserType(3);

        // Be sure to have this array start at 1 since instructor's permission level is 1
        $graders_from_usertypes = array(1 => $graders_from_usertype1, 2 => $graders_from_usertype2, 3 => $graders_from_usertype3);
        $admin_gradeable->setGradersFromUsertypes($graders_from_usertypes);
        $admin_gradeable->setTemplateList($this->core->getQueries()->getAllGradeablesIdsAndTitles());
        // $admin_gradeable->setInheritTeamsList($this->core->getQueries()->getAllElectronicGradeablesWithBaseTeams());
    }

    private function getAdminGradeable($gradeable_id)
    {
        // Make sure the gradeable already exists
        if (!$this->core->getQueries()->existsGradeable($gradeable_id)) {
            http_response_code(404); // NOT FOUND
            $this->core->getOutput()->renderJson(['errors' => 'Gradeable with provided id does not exist!']);
            return null;
        }

        // Get existing gradeable
        $admin_gradeable = new AdminGradeable($this->core);
        $this->core->getQueries()->getGradeableInfo($gradeable_id, $admin_gradeable, false);

        // Generate marks array if we're getting an electronic gradeable
        if ($admin_gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $old_components = $admin_gradeable->getOldComponents();
            foreach ($old_components as $old_component) {
                $old_component->setMarks($this->core->getQueries()->getGradeableComponentsMarks($old_component->getId()));
            }
        }
        return $admin_gradeable;
    }

    // Generates a blank first component for a gradeable
    private function genBlankComponent(AdminGradeable $gradeable)
    {
        // Make a new gradeable component with good default values
        $gradeable_component = new GradeableComponent($this->core);
        if ($gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            // Not required
        } else if ($gradeable->g_gradeable_type === GradeableType::CHECKPOINTS) {
            $gradeable_component->setTitle('Checkpoint 1');
            $gradeable_component->setMaxValue(1);
            $gradeable_component->setUpperClamp(1);
        } else if ($gradeable->g_gradeable_type === GradeableType::NUMERIC_TEXT) {
            // Not required
        } else {
            return false;
        }

        // Add it to the database
        $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $gradeable->g_id);

        // Add a new mark to the db if its electronic
        if ($gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $this->core->getQueries()->getGradeableInfo($gradeable->g_id, $gradeable);
            $components = $gradeable->getOldComponents();

            // Get the first (and only) component
            $comp = $components[0];

            $mark = new GradeableComponentMark($this->core);
            $mark->setGcId($comp->getId());
            $mark->setOrder(0); // must set order since it defaults to 1
            $this->core->getQueries()->createGradeableComponentMark($mark);
        }
        return true;
    }

    /**
     * Asserts that the provided date is a \DateTime object and converts it to one
     *  if its a string, returning any error in parsing.
     *
     * @param $date DateTime|string A reference to the date object to assert.  Set to null if failed.
     * @return null|string The error message or null
     */
    private function assertDate(&$date)
    {
        if (gettype($date) === 'string') {
            try {
                $date = new \DateTime($date, $this->core->getConfig()->getTimezone());
            } catch (\Exception $e) {
                $date = null;
                return 'Invalid Format!';
            }
        }
        return null;
    }

    private static function anyErrors($errors)
    {
        foreach ($errors as $prop => $error) {
            if ($error[0] === 0) {
                return true;
            }
        }
        return false;
    }

    private static function error($data)
    {
        return [0, $data];
    }

    private static function warning($data)
    {
        return [1, $data];
    }

    /**
     * Checks if a gradeable is valid
     *
     * @param $admin_gradeable AdminGradeable the gradeable to validate
     *
     * @return array error messages
     */
    private function validateGradeable(AdminGradeable $admin_gradeable)
    {
        // For now, only check that the dates are valid, but here's a list of checks:
        //  -Non-blank Name
        //  -force boolean values to be boolean
        //  -non-blank autograding config (for electronic submission)
        //  -maybe some warnings about the rubric
        //  -Dates
        //  -Late days must be >= 0

        // Messages array that holds warning/error messages for
        //  any AdminGradeable Properties that have issues
        $errors = array();

        if ($admin_gradeable->g_title === '') {
            $errors['g_title'] = self::error('Title cannot be blank!');
        }

        // Boolean values are false unless 'true'
        $boolean_properties = [
            'g_grade_by_registration',
            'eg_is_repository',
            'eg_team_assignment',
            'eg_use_ta_grading',
            'eg_student_view',
            'eg_student_submit',
            'eg_student_download',
            'eg_student_any_version',
            'eg_peer_grading',
            'eg_pdf_page',
            'eg_pdf_page_student'
        ];
        foreach ($boolean_properties as $property) {
            if (gettype($admin_gradeable->$property) !== 'boolean') {
                $admin_gradeable->$property = $admin_gradeable->$property === 'true';
            }
        }

        // Make sure autograding config isn't blank
        if ($admin_gradeable->g_gradeable_type == GradeableType::ELECTRONIC_FILE) {
            if ($admin_gradeable->eg_config_path === '') {
                $errors['eg_config_path'] = self::error('Config Path Cannot be Blank!');
            }
        }

        // Make sure that all of the provided dates are in a valid format.
        //  At the same time, massage the date-times with a time zone to prep
        //      for database update
        $dates = [
            'g_ta_view_start_date',
            'eg_submission_open_date',
            'eg_submission_due_date',
            'g_grade_start_date',
            'g_grade_released_date',
            'eg_team_lock_date'
        ];
        foreach ($dates as $date) {
            $result = $this->assertDate($admin_gradeable->$date);
            if ($result !== null) {
                $errors[$date] = self::error($result);
            }
        }

        $late_interval = null;
        try {
            $admin_gradeable->eg_late_days = (int)$admin_gradeable->eg_late_days;
            if ($admin_gradeable->eg_late_days < 0) {
                $errors['eg_late_days'] = self::error('Late day count must be >= 0!');
            } else {
                $late_interval = new \DateInterval('P' . strval($admin_gradeable->eg_late_days) . 'D');
            }
        } catch (\Exception $e) {
            $errors['eg_late_days'] = self::error('Invalid Format!');
        }

        // Some alias' for easier time comparison
        $ta_view = $admin_gradeable->g_ta_view_start_date;
        $open = $admin_gradeable->eg_submission_open_date;
        $due = $admin_gradeable->eg_submission_due_date;
        $grade = $admin_gradeable->g_grade_start_date;
        $release = $admin_gradeable->g_grade_released_date;
        $max_due = $due;
        if (!($due === null || $late_interval === null)) {
            $max_due = (clone $due)->add($late_interval);
        }

        if ($admin_gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            if (!($ta_view === null || $open === null) && $ta_view > $open) {
                $errors['g_ta_view_start_date'] = self::error('TA Beta Testing Date must not be later than Submission Open Date');
            }
            if (!($open === null || $due === null) && $open > $due) {
                $errors['eg_submission_open_date'] = self::error('Submission Open Date must not be later than Submission Due Date');
            }

            if ($admin_gradeable->eg_use_ta_grading) {

                if (!($due === null || $grade === null) && $due > $grade) {
                    $errors['g_grade_start_date'] = self::error('Manual Grading Open Date must be no earlier than Due Date');
                } else if (!($due === null || $grade === null) && $max_due > $grade) {
                    $errors['g_grade_start_date'] = self::warning('Manual Grading Open Date should be no earlier than Due Date');
                }

                if (!($grade === null || $release === null) && $grade > $release) {
                    $errors['g_grade_released_date'] = self::error('Grades Released Date must be later than the Manual Grading Open Date');
                }
            } else {

                // No TA grading, but we must set this start date so the database
                //  doesn't complain when we update it
                $admin_gradeable->g_grade_start_date = $release;

                if (!($max_due === null || $release === null) && $max_due > $release) {
                    $errors['g_grade_released_date'] = self::error('Grades Released Date must be later than the Due Date + Max Late Days');
                }
            }
        } else {
            // The only check if its not an electronic gradeable
            if (!($ta_view === null || $release === null) && $ta_view > $release) {
                $errors['g_grade_released_date'] = self::error('Grades Released Date must be later than the TA Beta Testing Date');
            }
        }

        return $errors;
    }

    // check whether radio button's value is 'true'
    private static function isRadioButtonTrue($name)
    {
        return isset($_POST[$name]) && $_POST[$name] === 'true';
    }

    private function updateRubricRequest()
    {
        // Assume something will go wrong
        http_response_code(500);

        $gradeable = $this->getAdminGradeable($_REQUEST['id']);
        if ($gradeable === null) {
            http_response_code(404);
            return;
        }
        $result = $this->updateRubric($gradeable, $_POST);

        $response_data = [];

        if (count($result) === 0) {
            http_response_code(204); // NO CONTENT
        } else {
            $response_data['errors'] = $result;
            http_response_code(400);
        }

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    // Parses the checkpoint details from the user form into a Component.  NOTE: order is not set here
    private static function parseCheckpoint(GradeableComponent $component, $details)
    {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        if (!isset($details['extra_credit'])) {
            $details['extra_credit'] = 'false';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setLowerClamp(0);
        $component->setDefault(0);
        // if it is extra credit then it would be out of 0 points otherwise 1
        $component->setMaxValue($details['extra_credit'] === 'true' ? 0 : 1);
        $component->setUpperClamp(1);
        $component->setIsText(false);
        $component->setIsPeer(false);
        $component->setPage(0);
    }

    private static function parseNumeric(GradeableComponent $component, $details)
    {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        if (!isset($details['max_score'])) {
            $details['max_score'] = 0;
        }
        if (!isset($details['extra_credit'])) {
            $details['extra_credit'] = 'false';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setLowerClamp(0);
        $component->setDefault(0);
        $component->setMaxValue($details['extra_credit'] === 'true' ? 0 : $details['max_score']);
        $component->setUpperClamp($details['max_score']);
        $component->setIsText(false);
        $component->setIsPeer(false);
        $component->setPage(0);
    }

    private static function parseText(GradeableComponent $component, $details)
    {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setLowerClamp(0);
        $component->setDefault(0);
        $component->setMaxValue(0);
        $component->setUpperClamp(0);
        $component->setIsText(true);
        $component->setIsPeer(false);
        $component->setPage(0);
    }

    private static function parseEgComponent(GradeableComponent $component, $details, $x)
    {
        $component->setTitle($details['comment_title_' . strval($x + 1)]);
        $component->setTaComment($details['ta_comment_' . strval($x + 1)]);
        $component->setStudentComment($details['student_comment_' . strval($x + 1)]);
        $is_penalty = (isset($details['rad_penalty-' . strval($x + 1)]) && $details['rad_penalty-' . strval($x + 1)] == 'yes') ? true : false;
        $lower_clamp = ($is_penalty === true) ? floatval($details['lower_' . strval($x + 1)]) : 0;
        $component->setLowerClamp($lower_clamp);
        $is_deduction = (isset($details['grade_by-' . strval($x + 1)]) && $details['grade_by-' . strval($x + 1)] == 'count_down') ? true : false;
        $temp_num = ($is_deduction === true) ? floatval($details['points_' . strval($x + 1)]) : 0;
        $component->setDefault($temp_num);
        $component->setMaxValue($details['points_' . strval($x + 1)]);
        $is_extra = (isset($details['rad_extra_credit-' . strval($x + 1)]) && $details['rad_extra_credit-' . strval($x + 1)] == 'yes') ? true : false;
        $upper_clamp = ($is_extra === true) ? (floatval($details['points_' . strval($x + 1)]) + floatval($details['upper_' . strval($x + 1)])) : floatval($details['points_' . strval($x + 1)]);
        $component->setUpperClamp($upper_clamp);
        $component->setIsText(false);
        $peer_grading_component = (isset($details['peer_component_' . strval($x + 1)]) && $details['peer_component_' . strval($x + 1)] == 'on') ? true : false;
        $component->setIsPeer($peer_grading_component);
        if (self::isRadioButtonTrue('eg_pdf_page_student')) {
            $page_component = -1;
        } else if (self::isRadioButtonTrue('eg_pdf_page')) {
            $page_component = ($details['page_component_' . strval($x + 1)]);
        } else {
            $page_component = 0;
        }
        $component->setPage($page_component);
    }

    private function updateRubric(AdminGradeable $admin_gradeable, $details)
    {
        // Add the rubric information using the old method for now.
        $edit_gradeable = 1;
        $peer_grading_complete_score = 0;

        $old_components = $admin_gradeable->getOldComponents();
        $num_old_components = count($old_components);
        $start_index = $num_old_components;

        // The electronic file mode is the least touched of them all since it will be replaced
        //  with a unified interface with TA grading and share a separate "rubric" controller for it.
        if ($admin_gradeable->g_gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $make_peer_assignments = false;
            if ($admin_gradeable->eg_peer_grading) {
                $old_peer_grading_assignments = $this->core->getQueries()->getPeerGradingAssignNumber($admin_gradeable->g_id);
                $make_peer_assignments = ($old_peer_grading_assignments !== $admin_gradeable->eg_peer_grade_set);
                if ($make_peer_assignments) {
                    $this->core->getQueries()->clearPeerGradingAssignments($admin_gradeable->g_id);
                }
            }
            if ($make_peer_assignments && $admin_gradeable->eg_peer_grading) {
                $users = $this->core->getQueries()->getAllUsers();
                $user_ids = array();
                $grading = array();
                $peer_grade_set = $admin_gradeable->eg_peer_grade_set;
                foreach ($users as $key => $user) {
                    // Need to remove non-student users, or users in the NULL section
                    if ($user->getRegistrationSection() == null) {
                        unset($users[$key]);
                    } else {
                        $user_ids[] = $user->getId();
                        $grading[$user->getId()] = array();
                    }
                }
                $user_number = count($user_ids);
                shuffle($user_ids);
                for ($i = 0; $i < $user_number; $i++) {
                    for ($j = 1; $j <= $peer_grade_set; $j++) {
                        $grading[$user_ids[$i]][] = $user_ids[($i + $j) % $user_number];
                    }
                }

                foreach ($grading as $grader => $assignment) {
                    foreach ($assignment as $student) {
                        $this->core->getQueries()->insertPeerGradingAssignment($grader, $student, $admin_gradeable->g_id);
                    }
                }
            }

            // Count the number of components (questions)
            $num_questions = 0;
            foreach ($details as $k => $v) {
                if (strpos($k, 'comment_title_') !== false) {
                    ++$num_questions;
                }
            }

            $x = 0;
            // Iterate through each existing component and update them in the database,
            //  removing any extras
            foreach ($old_components as $old_component) {
                if (is_array($old_component)) {
                    $old_component = $old_component[0];
                }
                if ($x < $num_questions && $x < $num_old_components) {
                    if ($old_component->getTitle() === "Grading Complete" || $old_component->getOrder() == -1) {
                        if ($peer_grading_complete_score == 0) {
                            $this->core->getQueries()->deleteGradeableComponent($old_component);
                        } else if ($old_component->getMaxValue() != $peer_grading_complete_score) {
                            $old_component->setMaxValue($peer_grading_complete_score);
                            $old_component->setUpperClamp($peer_grading_complete_score);
                            $this->core->getQueries()->updateGradeableComponent($old_component);
                        }
                        continue;
                    }
                    self::parseEgComponent($old_component, $details, $x);
                    $old_component->setOrder($x);
                    $this->core->getQueries()->updateGradeableComponent($old_component);
                } else if ($num_old_components > $num_questions) {
                    $this->core->getQueries()->deleteGradeableComponent($old_component);
                }
                $x++;
            }

            for ($x = $start_index; $x < $num_questions; $x++) {
                if ($x == 0 && $peer_grading_complete_score != 0) {
                    $gradeable_component = new GradeableComponent($this->core);
                    $gradeable_component->setMaxValue($peer_grading_complete_score);
                    $gradeable_component->setUpperClamp($peer_grading_complete_score);
                    $gradeable_component->setOrder($x - 1);
                    $gradeable_component->setTitle("Grading Complete");
                    $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $admin_gradeable->g_id);
                }
                $gradeable_component = new GradeableComponent($this->core);
                self::parseEgComponent($gradeable_component, $details, $x);
                $gradeable_component->setOrder($x);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $admin_gradeable->g_id);
            }


            //remake the gradeable to update all the data
            $admin_gradeable = $this->getAdminGradeable($admin_gradeable->g_id);
            $components = $admin_gradeable->getOldComponents();

            //Adds/Edits/Deletes the Marks
            $index = 1;
            foreach ($components as $comp) {
                $marks = $comp->getMarks();
                if (is_array($comp)) {
                    $comp = $comp[0];
                }
                if ($comp->getOrder() == -1) {
                    continue;
                }

                $num_marks = 0;
                foreach ($details as $k => $v) {
                    if (strpos($k, 'mark_points_' . $index) !== false) {
                        $num_marks++;
                    }
                }

                for ($y = 0; $y < $num_marks; $y++) {
                    //adds the mark if it is new
                    if ($details['mark_gcmid_' . $index . '_' . $y] == "NEW") {
                        $mark = new GradeableComponentMark($this->core);
                        $mark->setGcId($comp->getId());
                        $mark->setPoints(floatval($details['mark_points_' . $index . '_' . $y]));
                        $mark->setNote($details['mark_text_' . $index . '_' . $y]);
                        if (isset($details['mark_publish_' . $index . '_' . $y])) {
                            $mark->setPublish(true);
                        } else {
                            $mark->setPublish(false);
                        }
                        $mark->setOrder($y);
                        $this->core->getQueries()->createGradeableComponentMark($mark);
                    } else { //edits existing marks
                        foreach ($marks as $mark) {
                            if ($details['mark_gcmid_' . $index . '_' . $y] == $mark->getId()) {
                                $mark->setGcId($comp->getId());
                                $mark->setPoints(floatval($details['mark_points_' . $index . '_' . $y]));
                                $mark->setNote($details['mark_text_' . $index . '_' . $y]);
                                $mark->setOrder($y);
                                if (isset($details['mark_publish_' . $index . '_' . $y])) {
                                    $mark->setPublish(true);
                                } else {
                                    $mark->setPublish(false);
                                }
                                $this->core->getQueries()->updateGradeableComponentMark($mark);
                            }
                        }
                    }

                    //delete marks marked for deletion
                    $is_there_deleted = false;
                    $gcm_ids_deletes = explode(",", $details['component_deleted_marks_' . $index]);
                    foreach ($gcm_ids_deletes as $gcm_id_to_delete) {
                        foreach ($marks as $mark) {
                            if ($gcm_id_to_delete == $mark->getId()) {
                                $this->core->getQueries()->deleteGradeableComponentMark($mark);
                                $is_there_deleted = true;
                            }
                        }
                    }

                    //since we delete some marks, we must now reorder them. Also it is important to note that 
                    //$marks is sorted by gcm_order in increasing order
                    if ($is_there_deleted === true) {
                        $temp_order = 0;
                        foreach ($marks as $mark) {
                            //if the mark's id is a deleted id, skip it
                            $is_deleted = false;
                            foreach ($gcm_ids_deletes as $gcm_id_to_delete) {
                                if ($gcm_id_to_delete == $mark->getId()) {
                                    $is_deleted = true;
                                    break;
                                }
                            }
                            if (!$is_deleted) {
                                $mark->setOrder($temp_order);
                                $this->core->getQueries()->updateGradeableComponentMark($mark);
                                $temp_order++;
                            }
                        }
                    }
                }
                $index++;
            }
        } else if ($admin_gradeable->g_gradeable_type === GradeableType::CHECKPOINTS) {
            if (!isset($details['checkpoints'])) {
                $details['checkpoints'] = [];
            }

            $num_checkpoints = count($details['checkpoints']);

            // Iterate through each existing component and update them in the database,
            //  removing any extras
            $x = 0;
            foreach ($old_components as $old_component) {
                if ($x < $num_checkpoints && $x < $num_old_components) {
                    self::parseCheckpoint($old_component, $details['checkpoints'][$x]);
                    $old_component->setOrder($x);
                    $this->core->getQueries()->updateGradeableComponent($old_component);
                } else if ($num_old_components > $num_checkpoints) {
                    $this->core->getQueries()->deleteGradeableComponent($old_component);
                }
                $x++;
            }

            // iterate through each new checkpoint, adding them to the database
            for ($x = $start_index; $x < $num_checkpoints; $x++) {
                $gradeable_component = new GradeableComponent($this->core);
                self::parseCheckpoint($gradeable_component, $details['checkpoints'][$x]);
                $gradeable_component->setOrder($x);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $admin_gradeable->g_id);
            }
        } else if ($admin_gradeable->g_gradeable_type === GradeableType::NUMERIC_TEXT) {
            if (!isset($details['numeric'])) {
                $details['numeric'] = [];
            }
            if (!isset($details['text'])) {
                $details['text'] = [];
            }

            $num_numeric = count($details['numeric']);
            $num_text = count($details['text']);

            $start_index_numeric = 0;
            $start_index_text = 0;

            // Load all of the old numeric/text elements into two arrays
            $old_numerics = array();
            $num_old_numerics = 0;
            $old_texts = array();
            $num_old_texts = 0;
            foreach ($old_components as $old_component) {
                if ($old_component->getIsText() === true) {
                    $old_texts[] = $old_component;
                    $num_old_texts++;
                } else {
                    $old_numerics[] = $old_component;
                    $num_old_numerics++;
                }
            }

            $x = 0;
            // Iterate through each existing numeric component and update them in the database,
            //  removing any extras
            foreach ($old_numerics as $old_numeric) {
                if ($x < $num_numeric && $x < $num_old_numerics) {
                    self::parseNumeric($old_numeric, $details['numeric'][$x]);
                    $old_numeric->setOrder($x);
                    $this->core->getQueries()->updateGradeableComponent($old_numeric);
                    $start_index_numeric++;
                } else if ($num_old_numerics > $num_numeric) {
                    $this->core->getQueries()->deleteGradeableComponent($old_numeric);
                }
                $x++;
            }

            for ($x = $start_index_numeric; $x < $num_numeric; $x++) {
                $gradeable_component = new GradeableComponent($this->core);
                self::parseNumeric($gradeable_component, $details['numeric'][$x]);
                $gradeable_component->setOrder($x);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $admin_gradeable->g_id);
            }

            $z = $x;
            $x = 0;
            // Iterate through each existing text component and update them in the database,
            //  removing any extras
            if ($edit_gradeable === 1) {
                foreach ($old_texts as $old_text) {
                    if ($x < $num_text && $x < $num_old_texts) {
                        self::parseText($old_text, $details['text'][$x]);
                        $old_text->setOrder($z + $x);
                        $this->core->getQueries()->updateGradeableComponent($old_text);
                        $start_index_text++;
                    } else if ($num_old_texts > $num_text) {
                        $this->core->getQueries()->deleteGradeableComponent($old_text);
                    }
                    $x++;
                }
            }

            for ($y = $start_index_text; $y < $num_text; $y++) {
                $gradeable_component = new GradeableComponent($this->core);
                self::parseText($gradeable_component, $details['text'][$x]);
                $gradeable_component->setOrder($y + $z);
                $this->core->getQueries()->createNewGradeableComponent($gradeable_component, $admin_gradeable->g_id);
            }
        } else {
            throw new \InvalidArgumentException("Error.");
        }

        return [];
    }

    private function updateGradersRequest()
    {
        $gradeable = $this->getAdminGradeable($_REQUEST['id']);
        if ($gradeable === null) {
            http_response_code(404);
            return;
        }
        $this->loadAdminGradeable($gradeable);
        $result = $this->updateGraders($gradeable, $_POST);

        $response_data = [];

        if (!self::anyErrors($result)) {
            http_response_code(204); // NO CONTENT
        } else {
            http_response_code(400);
        }
        $response_data['errors'] = $result;

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    private function updateGraders(AdminGradeable $gradeable, $details)
    {
        // Assert the format/data is correct
        $errors = [];
        if (!isset($details['graders'])) {
            return ['graders', self::error('Blank Submission!')];
        }
        $valid_graders = [];
        foreach($gradeable->getGradersFromUsertypes() as $level=>$graders) {
            foreach($graders as $grader) {
                $valid_graders[] = $grader['user_id'];
            }
        }

        foreach ($details['graders'] as $name => $sections) {

            if (!in_array($name, $valid_graders)) {
                $errors[$name] = self::error('Invalid grader id for this gradeable!');
                continue;
            }
            foreach ($sections as $section) {
                if (!is_numeric($section)) {
                    $errors[$name] = self::error('Sections must be integers!');
                    break;
                }
                $i_val = (int)$section;
                if ($i_val < 1) {
                    $errors[$name] = self::error('Sections must be 1 or higher!');
                    break;
                }
                if ($i_val > $gradeable->getNumSections()) {
                    $errors[$name] = self::error('Sections must not exceed section count');
                    break;
                }
            }
        }
        if (self::anyErrors($errors))
            return $errors;
        if ($gradeable->g_grade_by_registration === false) {
            try {
                $this->core->getQueries()->setupRotatingSections($details['graders'], $gradeable->g_id);
            } catch (\Exception $e) {
                $errors['db'] = self::error("Query Failed: {$e}");
            }
        }

        return $errors;
    }

    private function createGradeableRequest()
    {
        $gradeable_id = $_POST['g_id'];
        $result = $this->createGradeable($gradeable_id, $_POST, $_POST['gradeable_template']);

        if ($result === null) {
            // Finally, redirect to the edit page
            $this->redirectToEdit($gradeable_id);
        } else {
            if ($result[0] == 1) { // Request Error
                http_response_code(400);
            } else if ($result[0] == 2) { // Server Error
                http_response_code(500);
            }
            // TODO: good way to handle these errors
            die($result[1]);
        }
    }

    private function createGradeable($gradeable_id, $details)
    {
        // First assert that the gradeable ID is valid
        preg_match('/^[a-zA-Z0-9_-]*$/', $gradeable_id, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) === 0) {
            return [1, 'Invalid Gradeable Id!'];
        }

        // Make sure the gradeable doesn't already exist
        if ($this->core->getQueries()->existsGradeable($gradeable_id)) {
            return [1, 'Gradeable Already Exists!'];
        }

        // Make sure the template exists if we're using one
        $template_gradeable = null;
        if ($details['gradeable_template'] !== '--None--') {
            $template_id = $details['gradeable_template'];
            $template_gradeable = $this->getAdminGradeable($template_id);
            if ($template_gradeable === null) {
                return [1, 'Template Id does not exist!'];
            }
        }

        // Create the gradeable with good default information
        //
        $admin_gradeable = new AdminGradeable($this->core);
        $admin_gradeable->g_id = $gradeable_id;
        $gradeable_type = $details['g_gradeable_type'];
        if ($gradeable_type === "Electronic File") {
            $admin_gradeable->g_gradeable_type = GradeableType::ELECTRONIC_FILE;

            // Setup the default path on create
            $admin_gradeable->eg_config_path = '/usr/local/submitty/more_autograding_examples/python_simple_homework/config';
        } else if ($gradeable_type === "Checkpoints") {
            $admin_gradeable->g_gradeable_type = GradeableType::CHECKPOINTS;
        } else if ($gradeable_type === "Numeric") {
            $admin_gradeable->g_gradeable_type = GradeableType::NUMERIC_TEXT;
        } else {
            return [1, 'Invalid gradeable type!'];
        }
        $admin_gradeable->eg_team_assignment = $details['eg_team_assignment'] === 'true';
        $admin_gradeable->eg_is_repository = $details['eg_is_repository'] === 'true';
        try {
            $this->core->getQueries()->createNewGradeable($admin_gradeable);

            // Generate a blank component to make the rubric UI work properly
            $this->genBlankComponent($admin_gradeable);
        } catch (\Exception $e) {
            return [2, 'Database call failed: ' . $e];
        }

        // Mutable first-page properties
        $front_page_property_names = [
            'g_title',
            'g_instructions_url',
            'eg_use_ta_grading',
            'eg_max_team_size',
            'eg_subdirectory',
            'g_syllabus_bucket'
        ];

        // Call updates with the front page properties
        $front_page_properties = array();
        foreach ($front_page_property_names as $prop) {
            $front_page_properties[$prop] = $details[$prop];
        }
        $result = $this->updateGradeable($admin_gradeable, $front_page_properties);
        if ($result === null) {
            return [2, 'Gradeable was not created!'];
        } else if (self::anyErrors($result)) {
            return [2, 'Merged template data failed to validate!'];
        }

        // Assert that the provided first page information and template information is valid
        //  This is delegated to the 'updateGradeable' method (this should never fail)
        if ($template_gradeable !== null) {
            $template_properties = array();

            // The Gradeable properties that should be copied from the template on creation
            $template_property_names = [
                'g_min_grading_group',
                'g_grade_by_registration',
                'g_overall_ta_instructions',
                'eg_config_path',
                'eg_student_view',
                'eg_student_submit',
                'eg_student_download',
                'eg_student_any_version',
                'eg_late_days',
                'eg_precision',
                'eg_pdf_page',
                'eg_pdf_page_student'
            ];

            //get a subset of the properties we want to copy
            foreach ($template_property_names as $prop) {
                $template_properties[$prop] = $template_gradeable->$prop;
            }

            // request this update to the gradeable
            $result = $this->updateGradeableById($gradeable_id, $template_properties);
            if ($result === null) {
                // This should almost never happen
                return [2, 'Gradeable was not created!'];
            } else if (self::anyErrors($result)) {
                return [2, 'Merged template data failed to validate!'];
            }
        } else {
            // No template, so just start the build here
            $result = $this->enqueueBuild($admin_gradeable);
            if ($result !== null) {
                // TODO: what key should this get?
                return [2, 'Build queue entry failed!'];
            }
        }
    }

    private function updateGradeableRequest()
    {
        $result = $this->updateGradeableById($_REQUEST['id'], $_POST);
        if ($result === null) {
            http_response_code(404);
            return;
        }

        $response_data = [];

        if (!self::anyErrors($result)) {
            http_response_code(204); // NO CONTENT
        } else {
            http_response_code(400);
        }
        $response_data['errors'] = $result;

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    private function updateGradeableById($gradeable_id, $details)
    {
        $admin_gradeable = $this->getAdminGradeable($gradeable_id);
        if ($admin_gradeable === null) {
            return null;
        }
        return $this->updateGradeable($admin_gradeable, $details);
    }

    private function updateGradeable(AdminGradeable $admin_gradeable, $details)
    {
        // A few fields that cannot be changed
        $blacklist = [
            'g_id' => 'Gradeable Id',
            'g_gradeable_type' => 'Gradeable Type',
            'eg_team_assignment' => 'Teamness',
            'eg_is_repository' => 'Upload Method'
        ];
        // A few fields that need sanitation
        $sanitize = [
            'g_title', 'g_instructions_url'
        ];
        $errors = array();

        // Apply new values for all properties submitted
        foreach ($details as $prop => $post_val) {

            // small blacklist (values that can't change)
            if (key_exists($prop, $blacklist)) {
                $errors[$prop] = self::error('Cannot Change ' . $blacklist[$prop] . ' once created');
                continue;
            }

            // Try to set the property
            try {
                if (in_array($prop, $sanitize)) {
                    $admin_gradeable->$prop = filter_var($post_val, FILTER_SANITIZE_SPECIAL_CHARS);
                } else if (property_exists($admin_gradeable, $prop)) {
                    $admin_gradeable->$prop = $post_val;
                } else {
                    $errors[$prop] = self::error('Not Found!');
                }
            } catch (\Exception $e) {
                $errors[$prop] = self::error($e);
            }
        }

        // Trigger a rebuild if the config changes
        if (key_exists('eg_config_path', $details)) {
            $result = $this->enqueueBuild($admin_gradeable);
            if ($result !== null) {
                // TODO: what key should this get?
                $errors['server'] = self::error($result);
            }
        }

        // If the post array is 0, that means that the name of the element was blank
        if (count($details) === 0) {
            $errors['general'] = self::error('Request contained no properties, perhaps the name was blank?');
        }

        $errors = array_merge($errors, self::validateGradeable($admin_gradeable));

        // Be strict.  Only apply database changes if there were no errors. (allow warnings)
        if (!self::anyErrors($errors)) {
            try {
                $this->core->getQueries()->updateGradeable($admin_gradeable);
            } catch (\Exception $e) {
                $errors['db'] = self::error($e);
            }
        }
        return $errors;
    }

    private function deleteGradeable()
    {
        $g_id = $_REQUEST['id'];

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] != $this->core->getCsrfToken()) {
            die("Invalid CSRF Token");
        }
        if (!$this->core->getUser()->accessAdmin()) {
            die("Only admins can delete gradeable");
        }
        $this->core->getQueries()->deleteGradeable($g_id);

        $course_path = $this->core->getConfig()->getCoursePath();
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        $file = FileUtils::joinPaths($course_path, "config", "form", "form_" . $g_id . ".json");
        if ((file_exists($file)) && (!unlink($file))) {
            die("Cannot delete form_{$g_id}.json");
        }

        $config_build_file = "/var/local/submitty/to_be_built/" . $semester . "__" . $course . "__" . $g_id . ".json";
        $config_build_data = array("semester" => $semester,
            "course" => $course,
            "no_build" => true);

        if (file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false) {
            die("Failed to write file {$config_build_file}");
        }

        $this->returnToNav();
    }

    private function writeFormConfig(AdminGradeable $gradeable)
    {
        if ($gradeable->g_gradeable_type !== GradeableType::ELECTRONIC_FILE)
            return null;

        // Refresh the configuration file with updated information
        // See 'make_assignments_txt_file.py' and grade_item.py for where these properties are used
        // Note: These property names must match the 'setup_sample_courses.py' names
        $jsonProperties = [
            'gradeable_id' => $gradeable->g_id,
            'config_path' => $gradeable->eg_config_path,
            'date_due' => $gradeable->eg_submission_due_date,
            'upload_type' => $gradeable->eg_is_repository ? "Repository" : "Upload File"
        ];

        $fp = $this->core->getConfig()->getCoursePath() . '/config/form/form_' . $gradeable->g_id . '.json';
        if (file_put_contents($fp, json_encode($jsonProperties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            return "Failed to write to file {$fp}";
        }
        return null;
    }

    private function enqueueBuildFile($g_id)
    {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        // FIXME:  should use a variable intead of hardcoded top level path
        $config_build_file = "/var/local/submitty/to_be_built/" . $semester . "__" . $course . "__" . $g_id . ".json";

        $config_build_data = [
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $g_id
        ];

        if (file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false) {
            return "Failed to write to file {$config_build_file}";
        }
        return null;
    }

    private function enqueueBuild(AdminGradeable $gradeable)
    {
        // If write form config fails, it will return non-null and end execution, but
        //  if it does return null, we want to run 'enqueueBuildFile'.  This coalescing can
        //  be chained so long as 'null' is the success condition.
        return $this->writeFormConfig($gradeable) ?? $this->enqueueBuildFile($gradeable->g_id);
    }

    private function rebuildAssignmentRequest()
    {
        $g_id = $_REQUEST['id'];
        $result = $this->enqueueBuildFile($g_id);
        if ($result !== null) {
            die($result);
        }
        $this->returnToNav();
    }

    private function quickLink()
    {
        $g_id = $_REQUEST['id'];
        $action = $_REQUEST['quick_link_action'];
        $gradeable = $this->core->getQueries()->getGradeable($g_id);
        if ($action === "release_grades_now") { //what happens on the quick link depends on the action
            $gradeable->setGradeReleasedDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_ta_now") {
            $gradeable->setTaViewDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_grading_now") {
            $gradeable->setGradeStartDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        } else if ($action === "open_students_now") {
            $gradeable->setOpenDate(new \DateTime('now', $this->core->getConfig()->getTimezone()));
        }
        $gradeable->updateGradeable();
        $this->returnToNav();
    }

    //return to the navigation page
    private function returnToNav()
    {
        $url = $this->core->buildUrl(array());
        header('Location: ' . $url);
    }

    private function redirectToEdit($gradeable_id)
    {
        $url = $this->core->buildUrl([
            'component' => 'admin',
            'page' => 'admin_gradeable',
            'action' => 'edit_gradeable_page',
            'id' => $gradeable_id,
            'nav_tab' => '-1']);
        header('Location: ' . $url);
    }
}
