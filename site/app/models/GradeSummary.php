<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\GradeableType;
//use app\libraries\Logger;

class GradeSummary extends AbstractModel {

    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     * @param $student_output_json
     * @param $buckets
     * @param Gradeable[] $summary_data
     */
    public function generateSummariesFromQueryResults(&$student_output_json, &$buckets, $summary_data) {
        /* Array of Students, indexed by user_id
            Each index contains an array indexed by syllabus
            bucket which contain all assignments in the respective syllabus bucket
        */
        foreach ($summary_data as $gradeable) {
            $student_id = $gradeable->getUser()->getId();
            if(!isset($buckets[ucwords($gradeable->getSyllabusBucket())])) {
                $buckets[ucwords($gradeable->getSyllabusBucket())] = true;
            }
            if(!array_key_exists($student_id, $student_output_json)) {
                $student_output_json[$student_id] = array();

                // CREATE HEADER FOR JSON
                $student_output_json[$student_id]["user_id"] = $student_id;
                $student_output_json[$student_id]["legal_first_name"] = $gradeable->getUser()->getLegalFirstName();
                $student_output_json[$student_id]["preferred_first_name"] = $gradeable->getUser()->getPreferredFirstName();
                $student_output_json[$student_id]["last_name"] = $gradeable->getUser()->getLegalLastName();
                $student_output_json[$student_id]["preferred_last_name"] = $gradeable->getUser()->getPreferredLastName();
                $student_output_json[$student_id]["registration_section"] = $gradeable->getUser()->getRegistrationSection();

                $student_output_json[$student_id]["default_allowed_late_days"] = $this->core->getConfig()->getDefaultStudentLateDays();
                //$student_output_json["allowed_late_days"] = $late_days_allowed;

                $student_output_json[$student_id]["last_update"] = date("l, F j, Y");
                foreach($buckets as $category => $bucket) {
                    $student_output_json[$student_id][ucwords($category)] = array();
                }
            }
            if(!isset($student_output_json[$student_id][ucwords($gradeable->getSyllabusBucket())])) {
                $student_output_json[$student_id][ucwords($gradeable->getSyllabusBucket())] = array();
            }
            $student = $student_output_json[$student_id];
            $total_late_used = 0;
            $student_output_json[$student_id] = $this->generateSummary($gradeable, $student, $total_late_used);
        }
    }

    /**
     * @param Gradeable $gradeable
     * @param $student
     *
     * @return mixed
     */
    private function generateSummary($gradeable, $student, &$total_late_used) {
        $this_g = array();

        $autograding_score = $gradeable->getGradedAutoGraderPoints();
        $ta_grading_score = $gradeable->getGradedTAPoints();

        $this_g['id'] = $gradeable->getId();
        $this_g['name'] = $gradeable->getName();
        $this_g['grade_released_date'] = $gradeable->getGradeReleasedDate();

        if($gradeable->validateVersions() || !$gradeable->useTAGrading()){
            $this_g['score'] = max(0,floatval($autograding_score)+floatval($ta_grading_score));
        }
        else{
            $this_g['score'] = 0;
            if($gradeable->validateVersions(-1)) {
                $this_g['note'] = 'This has not been graded yet.';
            }
            else if($gradeable->getActiveVersion() !== 0) {
                $this_g['note'] = 'Score is set to 0 because there are version conflicts.';
            }
        }

        switch ($gradeable->getType()) {
            case GradeableType::ELECTRONIC_FILE:
                $this->addLateDays($this_g, $gradeable);
                $this->addText($this_g, $gradeable);
                break;
            case GradeableType::NUMERIC_TEXT:
                $this->addText($this_g, $gradeable);
                $this->addProblemScores($this_g, $gradeable);
                break;
            case GradeableType::CHECKPOINTS:
                $this->addProblemScores($this_g, $gradeable);
                break;
        }
        array_push($student[ucwords($gradeable->getSyllabusBucket())], $this_g);

        return $student;
    }

    /**
     * @param $this_g
     * @param Gradeable $gradeable
     */
    private function addLateDays(&$this_g, $gradeable, &$total_late_used) {
        $gradeable->calculateLateDays($total_late_used);

        if(substr($gradeable->getLateStatus(), 0, 3) == 'Bad') {
            $this_g["score"] = 0;
        }
        $this_g['status'] = $gradeable->getLateStatus();

        if ($gradeable->getCurrLateCharged() != 0 && $total_late_used > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $this_g['days_late'] = $gradeable->getCurrLateCharged();

            // REPLACED BY:
            $this_g['days_after_deadline'] = $total_late_used;
            $this_g['extensions'] = $gradeable->getLateDayExceptions();
            $this_g['days_charged'] = $gradeable->getCurrLateCharged();

        }
        else {
            $this_g['days_late'] = 0;
        }
    }

    /**
     * @param $this_g
     * @param Gradeable $gradeable
     */
    private function addText(&$this_g, $gradeable) {
        $text_items = array();
        foreach($gradeable->getComponents() as $component) {
            array_push($text_items, array($component->getTitle() => $component->getComment()));
        }

        if(count($text_items) > 0){
            $this_g["text"] = $text_items;
        }
    }

    /**
     * @param $this_g
     * @param Gradeable $gradeable
     */
    private function addProblemScores(&$this_g, $gradeable) {
        $component_scores = array();
        foreach($gradeable->getComponents() as $component) {
            array_push($component_scores, array($component->getTitle() => $component->getScore()));
        }
        $this_g["component_scores"] = $component_scores;
    }

    public function generateAllSummaries() {
        $users = $this->core->getQueries()->getAllUsers();
        $user_ids = array_map(function($user) {return $user->getId();}, $users);
        //XXX: This grabs the gradeables in alphabetical order
        $gradeable_ids = $this->core->getQueries()->getAllGradeablesIds();
        $gradeable_ids = array_map(function($g_id) { return $g_id["g_id"];}, $gradeable_ids);

        /* This is the default behavior at the moment, grab all users for a gradeable chunk.
         * There isn't any memory advantage right now to doing smaller chunks of users, but
         * that isn't to say that there might not be a benefit in the future to user chunking.
         */
        $size_of_user_id_chunks = 75; //ceil(count($user_ids) / 2);
        $size_of_gradeable_id_chunks = 5;

        $student_output_json = array();
        $buckets = array();
        $gradeable_id_chunks = array_chunk($gradeable_ids,$size_of_gradeable_id_chunks);
        $user_id_chunks = array_chunk($user_ids,$size_of_user_id_chunks);
        foreach($user_id_chunks as $user_id_chunk) {
            foreach ($gradeable_id_chunks as $gradeable_id_chunk) {
                $summary_data = $this->core->getQueries()->getGradeables($gradeable_id_chunk, $user_id_chunk);
                //Logger::debug("Got gradeables " . implode(",", $gradeable_id_chunk) . " for users " . implode(",",$user_id_chunk));
                $this->generateSummariesFromQueryResults($student_output_json, $buckets, $summary_data);
                //Logger::debug("Current memory usage: " . memory_get_usage(false) . " True memory usage: " . memory_get_usage(true));
            }
        }

        // WRITE THE JSON FILE
        foreach($student_output_json as $student) {
            $student_id = $student['user_id'];
            $student_output_json_name = $student_id . "_summary.json";
            file_put_contents(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports","all_grades", $student_output_json_name)), json_encode($student,JSON_PRETTY_PRINT));

        }

    }
}

