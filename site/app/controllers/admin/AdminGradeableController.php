<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\exceptions\ValidationException;
use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\Component;
use app\models\gradeable\Mark;
use app\libraries\FileUtils;

class AdminGradeableController extends AbstractController {
    public function run() {
        switch ($_GET['action']) {
            case 'view_gradeable_page':
                $this->newPage();
                break;
            case 'upload_new_gradeable':
                $this->createGradeableRequest();
                break;
            case 'edit_gradeable_page':
                $this->editGradeableRequest();
                break;
            case 'update_gradeable':
                $this->updateGradeableRequest();
                break;
            case 'update_gradeable_rubric':
                // Other updates are happening real time,
                //  but the rubric and the grader assignment need
                //  to be updated separately
                $this->updateRubricRequest();
                break;
            case 'update_gradeable_graders':
                $this->updateGradersRequest();
                break;
            case 'upload_new_template':
                $this->uploadNewTemplateRequest();
                break;
            case 'quick_link':
                $this->quickLink();
                break;
            case 'delete_gradeable':
                $this->deleteGradeable();
                break;
            case 'rebuild_assignment':
                $this->rebuildAssignmentRequest();
                break;
            default:
                $this->newPage();
                break;
        }
    }

    /* Page load methods */

    private function uploadNewTemplateRequest() {
        $this->uploadNewTemplate($_GET['template_id']);
    }

    private function editGradeableRequest() {
        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($_REQUEST['id']);
            $this->editPage($gradeable, $_GET['semester'], $_GET['course'], $_GET['nav_tab'] ?? 0);
        } catch(\InvalidArgumentException $e) {
            // If the gradeable can't be found, redirect to new page
            $this->newPage();
        }
    }

    /**
     * Pulls data from an existing gradeable to display the 'new' page with
     * @param string $template_id The id of the gradeable to use as a template
     */
    private function uploadNewTemplate($template_id) {
        try {
            $template_gradeable = $this->core->getQueries()->getGradeableConfig($template_id);
            $this->newPage($template_gradeable);
        } catch(\InvalidArgumentException $e) {
            // If the template gradeable can't be found, redirect to new page
            $this->newPage();
        }
    }

    const syllabus_buckets = [
        'homework', 'assignment', 'problem-set',
        'quiz', 'test', 'exam',
        'exercise', 'lecture-exercise', 'reading', 'lab', 'recitation', 'worksheet',
        'project',
        'participation', 'note',
        'none (for practice only)'];

    /**
     * Displays the 'new' page, populating the first-page properties with the
     *  provided gradeable's data
     * @param Gradeable $gradeable
     */
    private function newPage(Gradeable $gradeable = null) {
        $this->core->getOutput()->addBreadcrumb("Create gradeable");

        $template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();
        $submit_url = $this->core->buildUrl([
            'component' => 'admin',
            'page' => 'admin_gradeable',
            'action' => 'upload_new_gradeable'
        ]);

        $this->core->getOutput()->renderTwigOutput('admin/admin_gradeable/AdminGradeableBase.twig', [
            'submit_url' => $submit_url,
            'gradeable' => $gradeable,
            'action' => $gradeable !== null ? 'template' : 'new',
            'template_list' => $template_list,
            'syllabus_buckets' => self::syllabus_buckets,
        ]);
    }

    //view the page with pulled data from the gradeable to be edited
    private function editPage(Gradeable $gradeable, $semester, $course, $nav_tab = 0) {
        $this->core->getOutput()->addBreadcrumb('Edit Gradeable');

        // Serialize the components for numeric/checkpoint rubrics
        $gradeable_components_enc = array_map(function(Component $c) {return $c->toArray(); }, $gradeable->getComponents());

        // Construct history array, first indexed by user type, then by gradeable id
        $gradeable_section_history = [];
        $graders_from_usertypes = $this->core->getQueries()->getGradersByUserType();
        foreach ($graders_from_usertypes as $usertype) {
            foreach ($usertype as $grader) {
                $gradeable_section_history[$grader] = [];
            }
        }

        // Construct a list of rotating gradeables
        $rotating_gradeables = [];
        foreach ($this->core->getQueries()->getGradeablesPastAndSection() as $row) {
            $gradeable_section_history[$row['user_id']][$row['g_id']] = $row['sections_rotating_id'];

            // Use the keys to remove duplicates
            $rotating_gradeables[$row['g_id']] = 1;
        }
        $rotating_gradeables = array_keys($rotating_gradeables);

        // Get some global configuration data
        $num_rotating_sections = $this->core->getQueries()->getNumberRotatingSections();
        $default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        $vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();

        $saved_config_path = $gradeable->getAutogradingConfigPath();

        // This helps determine which radio button to check when selecting config.
        // Default option, which means the user has to specify the path.
        $config_select_mode = 'manual';

        // These are hard coded default config options.
        $default_config_paths = ['/usr/local/submitty/more_autograding_examples/upload_only/config',
            '/usr/local/submitty/more_autograding_examples/iclicker_upload/config',
            '/usr/local/submitty/more_autograding_examples/left_right_exam_seating/config',
            '/usr/local/submitty/more_autograding_examples/pdf_exam/config',
            '/usr/local/submitty/more_autograding_examples/test_notes_upload/config',
            '/usr/local/submitty/more_autograding_examples/test_notes_upload_3page/config'];
        foreach ($default_config_paths as $path) {
            // If this happens then select the first radio button 'Using Default'
            if ($path === $saved_config_path) {
                $config_select_mode = 'defaults';
                break;
            }
        }

        // Configs uploaded to the 'Upload Gradeable Config' page
        $uploaded_configs_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'config_upload');
        $all_uploaded_configs = FileUtils::getAllFiles($uploaded_configs_dir);
        $all_uploaded_config_paths = array();
        foreach ($all_uploaded_configs as $file) {
            $all_uploaded_config_paths[] = $file['path'];
            // If this happens then select the second radio button 'Using Uploaded'
            if ($file['path'] === $saved_config_path) {
                $config_select_mode = 'uploaded';
            }
        }

        // Configs stored in a private repository (specified in course config)
        $config_repo_name = $this->core->getConfig()->getPrivateRepository();
        $repository_config_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $config_repo_name);
        $all_repository_configs = FileUtils::getAllFiles($repository_config_dir);
        $all_repository_config_paths = array();
        foreach ($all_repository_configs as $file) {
            $all_repository_config_paths[] = $file['path'];
            // If this happens then select the third radio button 'Use Private Repository'
            if ($file['path'] === $saved_config_path) {
                $config_select_mode = 'repo';
            }
        }

        // Load output from cmake build of config file
        $cmake_out_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'build', $gradeable->getId(), 'log_cmake_output.txt');
        $cmake_output = is_file($cmake_out_dir) ? file_get_contents($cmake_out_dir) : null;

        // $this->inherit_teams_list = $this->core->getQueries()->getAllElectronicGradeablesWithBaseTeams();

        $this->core->getOutput()->renderTwigOutput('admin/admin_gradeable/AdminGradeableBase.twig', [
            'gradeable' => $gradeable,
            'action' => 'edit',
            'nav_tab' => $nav_tab,
            'semester' => $semester,
            'course' => $course,
            'date_format' => 'Y-m-d H:i:sO',
            'syllabus_buckets' => self::syllabus_buckets,
            'gradeable_components_enc' => json_encode($gradeable_components_enc),

            // Non-Gradeable-model data
            'gradeable_section_history' => $gradeable_section_history,
            'num_rotating_sections' => $num_rotating_sections,
            'rotating_gradeables' => $rotating_gradeables,
            'graders_from_usertypes' => $graders_from_usertypes,
            //'inherit_teams_list' => $inherit_teams_list
            'default_late_days' => $default_late_days,
            'vcs_base_url' => $vcs_base_url,
            'is_pdf_page' => $gradeable->isPdfUpload(),
            'is_pdf_page_student' => $gradeable->isStudentPdfUpload(),
            'num_numeric' => $gradeable->getNumNumeric(),
            'num_text' => $gradeable->getNumText(),
            'type_string' => GradeableType::typeToString($gradeable->getType()),
            'show_edit_warning' => $gradeable->anyManualGrades(),

            // Config selection data
            'config_repo_name' => $config_repo_name,
            'all_repository_config_paths' => $all_repository_config_paths,
            'all_uploaded_config_paths' => $all_uploaded_config_paths,
            'default_config_paths' => $default_config_paths,
            'config_select_mode' => $config_select_mode,

            //build outputs
            'cmake_output' => htmlentities($cmake_output),

            'upload_config_url' => $this->core->buildUrl([
                'component' => 'admin',
                'page' => 'gradeable',
                'action' => 'upload_config'
            ])
        ]);
    }

    /* Http request methods (i.e. ajax) */

    private function tryGetGradeable($gradeable_id) {
        try {
            return $this->core->getQueries()->getGradeableConfig($gradeable_id);
        } catch (\Exception $exception) {
            http_response_code(404);
            $this->core->getOutput()->renderJson(['errors' => 'Gradeable with provided id does not exist!']);
            return null;
        }
    }

    private function newComponent(Gradeable $gradeable) {
        return new Component($this->core, $gradeable, [
            'id' => 0,
            'title' => '',
            'ta_comment' => '',
            'student_comment' => '',
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => 0,
            'upper_clamp' => 0,
            'text' => false,
            'peer' => false,
            'order' => -1,
            'page' => Component::PDF_PAGE_NONE
        ]);
    }

    private function newMark(Component $component) {
        return new Mark($this->core, $component, [
            'id' => 0,
            'points' => 0,
            'title' => '',
            'order' => 0,
            'publish' => false
        ]);
    }

    // Generates a blank first component for a gradeable
    private function genBlankComponent(Gradeable $gradeable) {
        // Make a new gradeable component with good default values
        $component = $this->newComponent($gradeable);
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            // Add a new mark (count up)
            $mark0 = $this->newMark($component);
            $mark0->setTitle('No Credit');
            $component->setMarks([$mark0]);
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
            $component->setTitle('Checkpoint 1');
            $component->setPoints(['lower_clamp' => 0, 'default' => 0, 'max_value' => 1, 'upper_clamp' => 1]);
        } else if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
            // Add a new mark to the db if its electronic
            $mark = $this->newMark($component);
            $component->setMarks([$mark]);
        } else {
            throw new \InvalidArgumentException('Gradeable type invalid');
        }

        // Add it to the gradeable
        $gradeable->setComponents([$component]);
    }

    // check whether radio button's value is 'true'
    private static function isRadioButtonTrue($name) {
        return isset($_POST[$name]) && $_POST[$name] === 'true';
    }

    private function updateRubricRequest() {
        // Assume something will go wrong
        http_response_code(500);

        $gradeable = $this->tryGetGradeable($_REQUEST['id']);
        if ($gradeable === null) {
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
    private static function parseCheckpoint(Component $component, $details) {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        if (!isset($details['extra_credit'])) {
            $details['extra_credit'] = 'false';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            // if it is extra credit then it would be out of 0 points otherwise 1
            'max_value' => $details['extra_credit'] === 'true' ? 0 : 1,
            'upper_clamp' => 1
        ]);
        $component->setText(false);
        $component->setPeer(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    private static function parseNumeric(Component $component, $details) {
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
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => $details['extra_credit'] === 'true' ? 0 : $details['max_score'],
            'upper_clamp' => $details['max_score']
        ]);
        $component->setText(false);
        $component->setPeer(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    private static function parseText(Component $component, $details) {
        if (!isset($details['label'])) {
            $details['label'] = '';
        }
        $component->setTitle($details['label']);
        $component->setTaComment("");
        $component->setStudentComment("");
        $component->setPoints([
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => 0,
            'upper_clamp' => 0
        ]);
        $component->setText(true);
        $component->setPeer(false);
        $component->setPage(Component::PDF_PAGE_NONE);
    }

    private static function parseEgComponent(Component $component, $details, $x) {
        $component->setTitle($details['comment_title_' . strval($x + 1)]);
        $component->setTaComment($details['ta_comment_' . strval($x + 1)]);
        $component->setStudentComment($details['student_comment_' . strval($x + 1)]);

        $is_penalty = (isset($details['rad_penalty-' . strval($x + 1)]) && $details['rad_penalty-' . strval($x + 1)] == 'yes') ? true : false;
        $lower_clamp = ($is_penalty === true) ? floatval($details['lower_' . strval($x + 1)]) : 0;
        $is_deduction = (isset($details['grade_by-' . strval($x + 1)]) && $details['grade_by-' . strval($x + 1)] == 'count_down') ? true : false;
        $default = ($is_deduction === true) ? floatval($details['points_' . strval($x + 1)]) : 0;
        $max_value = $details['points_' . strval($x + 1)];
        $is_extra = (isset($details['rad_extra_credit-' . strval($x + 1)]) && $details['rad_extra_credit-' . strval($x + 1)] == 'yes') ? true : false;
        $upper_clamp = ($is_extra === true) ? (floatval($details['points_' . strval($x + 1)]) + floatval($details['upper_' . strval($x + 1)])) : floatval($details['points_' . strval($x + 1)]);

        $component->setPoints([
            'lower_clamp' => $lower_clamp,
            'default' => $default,
            'max_value' => $max_value,
            'upper_clamp' => $upper_clamp
        ]);
        $component->setText(false);
        $peer_grading_component = (isset($details['peer_component_' . strval($x + 1)]) && $details['peer_component_' . strval($x + 1)] == 'on') ? true : false;
        $component->setPeer($peer_grading_component);

        if (self::isRadioButtonTrue('pdf_page')) {
            if (self::isRadioButtonTrue('pdf_page_student')) {
                $page_component = -1;
            } else {
                $page_component = ($details['page_component_' . strval($x + 1)]);
            }
        } else {
            $page_component = 0;
        }
        $component->setPage($page_component);
    }

    private function updateRubric(Gradeable $gradeable, $details) {
        // Add the rubric information using the old method for now.
        $peer_grading_complete_score = 0;

        $old_components = $gradeable->getComponents();
        $num_old_components = count($old_components);
        $start_index = $num_old_components;

        /** @var Component[] $new_components */
        $new_components = [];
        /** @var Component[] $delete_components */
        $delete_components = [];

        $update_component_peer = function (Component $component, $peer_grading_complete_score) {
            $component->setPoints([
                'lower_clamp' => $component->getLowerClamp(),
                'default' => $component->getDefault(),
                'max_value' => $peer_grading_complete_score,
                'upper_clamp' => $peer_grading_complete_score
            ]);
        };

        // The electronic file mode is the least touched of them all since it will be replaced
        //  with a unified interface with TA grading and share a separate "rubric" controller for it.
        if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
            $make_peer_assignments = false;
            if ($gradeable->isPeerGrading()) {
                $old_peer_grading_assignments = $this->core->getQueries()->getPeerGradingAssignNumber($gradeable->getId());
                $make_peer_assignments = ($old_peer_grading_assignments !== $gradeable->getPeerGradeSet());
                if ($make_peer_assignments) {
                    $this->core->getQueries()->clearPeerGradingAssignments($gradeable->getId());
                }
            }
            if ($make_peer_assignments && $gradeable->isPeerGrading()) {
                $users = $this->core->getQueries()->getAllUsers();
                $user_ids = array();
                $grading = array();
                $peer_grade_set = $gradeable->getPeerGradeSet();
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
                        $this->core->getQueries()->insertPeerGradingAssignment($grader, $student, $gradeable->getId());
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
                            $update_component_peer($old_component, $peer_grading_complete_score);
                            $new_components[] = $old_component;
                        }
                        continue;
                    }
                    self::parseEgComponent($old_component, $details, $x);
                    $old_component->setOrder($x);
                    $new_components[] = $old_component;
                } else if ($num_old_components > $num_questions) {
                    $delete_components[] = $old_component;
                }
                $x++;
            }

            for ($x = $start_index; $x < $num_questions; $x++) {
                if ($x == 0 && $peer_grading_complete_score != 0) {
                    $component = $this->newComponent($gradeable);
                    $update_component_peer($component, $peer_grading_complete_score);
                    $component->setOrder($x - 1);
                    $component->setTitle("Grading Complete");
                    $new_components[] = $component;
                }
                $component = $this->newComponent($gradeable);
                self::parseEgComponent($component, $details, $x);
                $component->setOrder($x);
                $new_components[] = $component;
            }

            //Adds/Edits/Deletes the Marks
            $index = 1;
            foreach ($new_components as $comp) {
                $marks = $comp->getMarks();
                $new_marks = [];
                $delete_marks = [];

                if ($comp->getOrder() == -1) {
                    continue;
                }

                $num_marks = 0;
                foreach ($details as $k => $v) {
                    if (strpos($k, 'mark_points_' . $index) !== false) {
                        $num_marks++;
                    }
                }

                $update_mark = function (Mark $mark, $index, $y) use ($details) {
                    $mark->setPoints(floatval($details['mark_points_' . $index . '_' . $y]));
                    $mark->setTitle($details['mark_text_' . $index . '_' . $y]);
                    $mark->setOrder($y);
                    $mark->setPublish(isset($details['mark_publish_' . $index . '_' . $y]));
                };

                for ($y = 0; $y < $num_marks; $y++) {
                    //adds the mark if it is new
                    if ($details['mark_gcmid_' . $index . '_' . $y] == "NEW") {
                        $mark = $this->newMark($comp);
                        $update_mark($mark, $index, $y);
                        $new_marks[] = $mark;
                    } else { //edits existing marks
                        foreach ($marks as $mark) {
                            if ($details['mark_gcmid_' . $index . '_' . $y] == $mark->getId()) {
                                $update_mark($mark, $index, $y);
                                $new_marks[] = $mark;
                            }
                        }
                    }

                    //delete marks marked for deletion
                    $gcm_ids_deletes = explode(",", $details['component_deleted_marks_' . $index]);
                    foreach ($gcm_ids_deletes as $gcm_id_to_delete) {
                        foreach ($marks as $mark) {
                            if ($gcm_id_to_delete == $mark->getId()) {
                                $delete_marks[] = $mark;
                            }
                        }
                    }
                }
                $index++;

                // Finally, set the new marks ...
                $comp->setMarks($new_marks);

                // ... And delete the ones marked for deletion
                $this->core->getQueries()->deleteMarks($delete_marks);
            }
        } else if ($gradeable->getType() === GradeableType::CHECKPOINTS) {
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
                    $new_components[] = $old_component;
                } else if ($num_old_components > $num_checkpoints) {
                    $delete_components[] = $old_component;
                }
                $x++;
            }

            // iterate through each new checkpoint, adding them to the database
            for ($x = $start_index; $x < $num_checkpoints; $x++) {
                $component = $this->newComponent($gradeable);
                self::parseCheckpoint($component, $details['checkpoints'][$x]);
                $component->setOrder($x);
                $new_components[] = $component;
            }
        } else if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
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
                if ($old_component->isText() === true) {
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
                    $new_components[] = $old_numeric;
                    $start_index_numeric++;
                } else if ($num_old_numerics > $num_numeric) {
                    $delete_components[] = $old_numeric;
                }
                $x++;
            }

            for ($x = $start_index_numeric; $x < $num_numeric; $x++) {
                $component = $this->newComponent($gradeable);
                self::parseNumeric($component, $details['numeric'][$x]);
                $component->setOrder($x);
                $new_components[] = $component;
            }

            $z = $x;
            $x = 0;
            // Iterate through each existing text component and update them in the database,
            //  removing any extras
            foreach ($old_texts as $old_text) {
                if ($x < $num_text && $x < $num_old_texts) {
                    self::parseText($old_text, $details['text'][$x]);
                    $old_text->setOrder($z + $x);
                    $new_components[] = $old_text;
                    $start_index_text++;
                } else if ($num_old_texts > $num_text) {
                    $delete_components[] = $old_text;
                }
                $x++;
            }

            for ($y = $start_index_text; $y < $num_text; $y++) {
                $component = $this->newComponent($gradeable);
                self::parseText($component, $details['text'][$x]);
                $component->setOrder($y + $z);
                $new_components[] = $component;
            }
        } else {
            throw new \InvalidArgumentException("Error.");
        }

        // Finally, Set the components and update the gradeable
        $gradeable->setComponents($new_components);

        // Delete the old ones
        $this->core->getQueries()->deleteComponents($delete_components);

        // Save to the database
        $this->core->getQueries()->updateGradeable($gradeable);

        return [];
    }

    private function updateGradersRequest() {
        $gradeable = $this->tryGetGradeable($_REQUEST['id']);
        if ($gradeable === null) {
            return;
        }

        $result = $this->updateGraders($gradeable, $_POST);

        $response_data = [];

        if (count($result) === 0) {
            http_response_code(204); // NO CONTENT
        } else {
            http_response_code(400);
        }
        $response_data['errors'] = $result;

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    private function updateGraders(Gradeable $gradeable, $details) {
        if (!isset($details['graders'])) {
            return ['graders' => 'Blank Submission!'];
        }

        try {
            $gradeable->setRotatingGraderSections($details['graders']);
        } catch (\Exception $exception) {
            return ['graders' => $exception];
        }

        $this->core->getQueries()->updateGradeable($gradeable);
        return [];
    }

    private function createGradeableRequest() {
        $gradeable_id = $_POST['id'];
        $result = $this->createGradeable($gradeable_id, $_POST);

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

    private function createGradeable($gradeable_id, $details) {
        // Make sure the gradeable doesn't already exist
        if ($this->core->getQueries()->existsGradeable($gradeable_id)) {
            return [1, 'Gradeable Already Exists!'];
        }

        // Create the gradeable with good default information
        //
        $gradeable_type = GradeableType::stringToType($details['type']);
        $gradeable_create_data = [
            'type' => $gradeable_type,
            'grade_by_registration' => true,
            'min_grading_group' => 1,
        ];

        $template_property_names = [
            'min_grading_group',
            'grade_by_registration',
            'ta_instructions',
            'autograding_config_path',
            'student_view',
            'student_submit',
            'student_download',
            'student_download_any_version',
            'late_days',
            'precision'
        ];
        // Make sure the template exists if we're using one
        $template_gradeable = null;
        if ($details['gradeable_template'] !== '--None--') {
            $template_id = $details['gradeable_template'];
            $template_gradeable = $this->core->getQueries()->getGradeableConfig($template_id);
            if ($template_gradeable === null) {
                return [1, 'Template Id does not exist!'];
            }

            // Setup the create data from the template
            $template_data = $template_gradeable->toArray();
            foreach ($template_property_names as $name) {
                $gradeable_create_data[$name] = $template_data[$name];
            }

        } else {
            $non_template_property_values = [
                'min_grading_group' => 1,
                'grade_by_registration' => true,
                'ta_instructions' => '',
                'autograding_config_path' => '/usr/local/submitty/more_autograding_examples/python_simple_homework/config',
                'student_view' => false,
                'student_submit' => false,
                'student_download' => false,
                'student_download_any_version' => false,
                'late_days' => 0,
                'precision' => 0.5
            ];
            $gradeable_create_data = array_merge($gradeable_create_data, $non_template_property_values);
        }

        // first-page properties
        $front_page_property_names = [
            'id',
            'title',
            'instructions_url',
            'syllabus_bucket'
        ];
        foreach ($front_page_property_names as $prop) {
            $gradeable_create_data[$prop] = $details[$prop];
        }

        // Electronic-only values
        if ($gradeable_type === GradeableType::ELECTRONIC_FILE) {
            $gradeable_create_data = array_merge($gradeable_create_data, [
                'team_assignment' => $details['team_assignment'] === 'true',
                'vcs' => $details['vcs'] === 'true',
                'ta_grading' => $details['ta_grading'] === 'true',
                'team_size_max' => $details['team_size_max'],
                'vcs_subdirectory' => $details['vcs_subdirectory'],

                'autograding_config_path' => '/usr/local/submitty/more_autograding_examples/python_simple_homework/config',

                // TODO: properties that aren't supported yet
                'peer_grading' => false,
                'peer_grade_set' => 0,
                'late_submission_allowed' => true
            ]);
        } else {
            // Values for these electronic-only properties
            $gradeable_create_data = array_merge($gradeable_create_data, [
                'team_assignment' => false,
                'vcs' => false,
                'team_size_max' => 0,
                'vcs_subdirectory' => '',
                'autograding_config_path' => '',
                'peer_grading' => false,
                'peer_grade_set' => 0,
                'late_submission_allowed' => true
            ]);
        }

        // Setup good default dates
        $tonight = new \DateTime();
        $tonight->setTime(23, 59, 59);
        $gradeable_create_data = array_merge($gradeable_create_data, [
            'ta_view_start_date' => (clone $tonight)->sub(new \DateInterval('P1D')),
            'grade_start_date' => (clone $tonight)->add(new \DateInterval('P10D')),
            'grade_released_date' => (clone $tonight)->add(new \DateInterval('P14D')),
            'team_lock_date' => (clone $tonight)->add(new \DateInterval('P7D')),
            'submission_open_date' => (clone $tonight),
            'submission_due_date' => (clone $tonight)->add(new \DateInterval('P7D'))
        ]);

        // Finally, construct the gradeable
        $gradeable = new Gradeable($this->core, $gradeable_create_data);

        // Generate a blank component to make the rubric UI work properly
        $this->genBlankComponent($gradeable);

        // Save the gradeable to the database
        $this->core->getQueries()->createGradeable($gradeable); // creates the gradeable

        // start the build
        $result = $this->enqueueBuild($gradeable);
        if ($result !== null) {
            // TODO: what key should this get?
            return [2, "Build queue entry failed: $result"];
        }

        return null;
    }

    private function updateGradeableRequest() {
        $result = $this->updateGradeableById($_REQUEST['id'], $_POST);
        if ($result === null) {
            http_response_code(404);
            return;
        }

        $response_data = [];

        if (count($result) === 0) {
            http_response_code(204); // NO CONTENT
        } else {
            http_response_code(400);
        }
        $response_data['errors'] = $result;

        // Finally, send the requester back the information
        $this->core->getOutput()->renderJson($response_data);
    }

    private function updateGradeableById($gradeable_id, $details) {
        $gradeable = $this->core->getQueries()->getGradeableConfig($gradeable_id);
        if ($gradeable === null) {
            return null;
        }
        return $this->updateGradeable($gradeable, $details);
    }

    private function updateGradeable(Gradeable $gradeable, $details) {
        $errors = array();

        // If the post array is 0, that means that the name of the element was blank
        if (count($details) === 0) {
            $errors['general'] = 'Request contained no properties, perhaps the name was blank?';
            return $errors;
        }

        // Trigger a rebuild if the config / due date changes
        $trigger_rebuild_props = ['autograding_config_path', 'submission_due_date'];
        $trigger_rebuild = count(array_intersect($trigger_rebuild_props, array_keys($details))) > 0;

        $boolean_properties = [
            'grade_by_registration',
            'ta_grading',
            'student_view',
            'student_submit',
            'student_download',
            'student_download_any_version',
            'peer_grading'
        ];

        // Date properties all need to be set at once
        $dates = $gradeable->getDates();
        $date_set = false;
        foreach (array_merge(Gradeable::date_properties, ['late_days']) as $date_property) {
            if (isset($details[$date_property])) {
                $dates[$date_property] = $details[$date_property];

                // Unset dates so we don't try and use it in the other loop
                unset($details[$date_property]);
                $date_set = true;
            }
        }
        if ($date_set) {
            try {
                $gradeable->setDates($dates);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->getDetails());
            }
        }

        // Apply other new values for all properties submitted
        foreach ($details as $prop => $post_val) {
            // Convert boolean values into booleans
            if (in_array($prop, $boolean_properties)) {
                $post_val = $post_val === 'true';
            }

            // Try to set the property
            try {
                //convert the property name to a setter name
                $setter_name = 'set' . implode('',
                        array_map(function ($val) {
                            return ucfirst($val);
                        }, explode('_', $prop)));
                $gradeable->$setter_name($post_val);
            } catch (\Exception $e) {
                // If something goes wrong, record it so we can tell the user
                $errors[$prop] = $e->getMessage();
            }
        }

        if ($trigger_rebuild) {
            $result = $this->enqueueBuild($gradeable);
            if ($result !== null) {
                // TODO: what key should this get?
                $errors['server'] = $result;
            }
        }

        // Be strict.  Only apply database changes if there were no errors. (allow warnings)
        if (count($errors) === 0) {
            try {
                $this->core->getQueries()->updateGradeable($gradeable);
            } catch (\Exception $e) {
                $errors['db'] = $e;
            }
        }
        return $errors;
    }

    private function deleteGradeable() {
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

        // this will cleanup the build files
        $this->enqueueBuildFile($g_id);

        $this->returnToNav();
    }

    private function writeFormConfig(Gradeable $gradeable) {
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE)
            return null;

        // Refresh the configuration file with updated information
        // See 'make_assignments_txt_file.py' and grade_item.py for where these properties are used
        // Note: These property names must match the 'setup_sample_courses.py' names
        $jsonProperties = [
            'gradeable_id' => $gradeable->getId(),
            'config_path' => $gradeable->getAutogradingConfigPath(),
            'date_due' => DateUtils::dateTimeToString($gradeable->getSubmissionDueDate()),
            'upload_type' => $gradeable->isVcs() ? "repository" : "upload file"
        ];

        $fp = $this->core->getConfig()->getCoursePath() . '/config/form/form_' . $gradeable->getId() . '.json';
        if ((!is_writable($fp) && file_exists($fp))
            || file_put_contents($fp, json_encode($jsonProperties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            return "Failed to write to file {$fp}";
        }
        return null;
    }

    private function enqueueBuildFile($g_id) {
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        // FIXME:  should use a variable intead of hardcoded top level path
        $config_build_file = "/var/local/submitty/daemon_job_queue/" . $semester . "__" . $course . "__" . $g_id . ".json";

        $config_build_data = [
            "job" => "BuildConfig",
            "semester" => $semester,
            "course" => $course,
            "gradeable" => $g_id
        ];

        if ((!is_writable($config_build_file) && file_exists($config_build_file))
            || file_put_contents($config_build_file, json_encode($config_build_data, JSON_PRETTY_PRINT)) === false) {
            return "Failed to write to file {$config_build_file}";
        }
        return null;
    }

    private function enqueueBuild(Gradeable $gradeable) {
        // If write form config fails, it will return non-null and end execution, but
        //  if it does return null, we want to run 'enqueueBuildFile'.  This coalescing can
        //  be chained so long as 'null' is the success condition.
        return $this->writeFormConfig($gradeable) ?? $this->enqueueBuildFile($gradeable->getId());
    }

    private function rebuildAssignmentRequest() {
        $g_id = $_REQUEST['id'];
        $gradeable = $this->core->getQueries()->getGradeableConfig($g_id);
        $result = $this->enqueueBuild($gradeable);
        if ($result !== null) {
            die($result);
        }
        $this->core->addSuccessMessage("Successfully added {$g_id} to the rebuild queue");
        $this->returnToNav();
    }

    private function quickLink() {
        $g_id = $_REQUEST['id'];
        $action = $_REQUEST['quick_link_action'];

        $gradeable = $this->core->getQueries()->getGradeableConfig($g_id);
        $dates = $gradeable->getDates();
        $now = new \DateTime('now', $this->core->getConfig()->getTimezone());
        $message = "";
        $success = null;
        //what happens on the quick link depends on the action
        if ($action === "release_grades_now") {
            if ($dates['grade_released_date'] > $now) {
                $dates['grade_released_date'] = $now;
                $message .= "Released grades for ";
                $success = true;
            } else {
                $message .= "Grades already released for";
                $success = false;
            }
        } else if ($action === "open_ta_now") {
            if ($dates['ta_view_start_date'] > $now) {
                $dates['ta_view_start_date'] = $now;
                $message .= "Opened TA access to ";
                $success = true;
            } else {
                $message .= "TA access already open for ";
                $success = false;
            }
        } else if ($action === "open_grading_now") {
            if ($dates['grade_start_date'] > $now) {
                $dates['grade_start_date'] = $now;
                $message .= "Opened grading for ";
                $success = true;
            } else {
                $message .= "Grading already open for ";
                $success = false;
            }
        } else if ($action === "open_students_now") {
            if ($dates['submission_open_date'] > $now) {
                $dates['submission_open_date'] = $now;
                $message .= "Opened student access to ";
                $success = true;
            } else {
                $message .= "Student access already open for ";
                $success = false;
            }
        }
        $gradeable->setDates($dates);
        $this->core->getQueries()->updateGradeable($gradeable);
        if ($success === true) {
            $this->core->addSuccessMessage($message.$g_id);
        } else if ($success === false) {
            $this->core->addErrorMessage($message.$g_id);
        } else {
            $this->core->addErrorMessage("Failed to update status of ".$g_id);
        }
        $this->returnToNav();

    }

    //return to the navigation page
    private function returnToNav() {
        $this->core->redirect($this->core->buildUrl(array()));
    }

    private function redirectToEdit($gradeable_id) {
        $url = $this->core->buildUrl([
            'component' => 'admin',
            'page' => 'admin_gradeable',
            'action' => 'edit_gradeable_page',
            'id' => $gradeable_id,
            'nav_tab' => '-1']);
        header('Location: ' . $url);
    }
}
