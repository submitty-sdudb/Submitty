<?php 

namespace app\models;
use app\libraries\FileUtils; 
use app\libraries\Core;
use \app\models\User;

class LateDaysCalculation extends AbstractModel {
    /** @var Core */
    protected $core;
    /** @var array: Late day usage for all students queried for all assignments queried. The
    *   outer array is indexed by user_id and the second array is indexed by gradeable id (g_id).
    */
    protected $all_latedays;
    /** @var array */
    protected $students;

    function __construct(Core $core, $user_id = null) {
        parent::__construct($core);
        $this->students = $this->parseStudents($user_id);
        //Calculate lateday usages for all students for all assignments queried
        $this->all_latedays = $this->calculateStudentLatedayUsage($user_id);
    }
    
    private function parseStudents($user_id) {
        $students = array();

        //For each submission ensure that an entry exists for that user and append the submission to their list of
        //submissions.
        $submissions = $this->core->getQueries()->getLateDayInformation($user_id);
        $count = count($submissions);
        for ($i = 0; $i < $count; $i++) {
            $curr_student = $submissions[$i]['user_id'];
            if (!isset($students[$curr_student])) {
                $students[$curr_student] = array(
                    'user_id' => $curr_student,
                    'submissions' => array(),
                    'latedays' => array()
                );
            }

            $students[$curr_student]['submissions'][] = $submissions[$i];
            unset($submissions[$i]);
        }

        //For each lateDayUpdate append the lateDayUpdate to the appropriate user.
        $latedays = $this->core->getQueries()->getLateDayUpdates($user_id);
        $count = count($latedays);
        for ($i = 0; $i < $count; $i++) {
            $curr_student = $latedays[$i]['user_id'];
            if (isset($students[$curr_student])) {
                $students[$curr_student]['latedays'][] = $latedays[$i];
            }
            //Else student got a late day exception but never turned in any assignments.
            unset($latedays[$i]);
        }
        return $students;
    }
    
    private function calculateStudentLatedayUsage($user_id) {
        $all_latedays = array();

        //For each student for each submission calculate late day usage
        foreach ($this->students as $student) {
            //Base allowed late days and remaining late days
            $curr_allowed_term = $this->core->getConfig()->getDefaultStudentLateDays();
            $curr_remaining_late = $this->core->getConfig()->getDefaultStudentLateDays();
            $total_late_used = 0;
            $status = "Good";

            $submissions = $student['submissions'];

            //Sort submissions by due date before calculating late day usage.
            usort($submissions, function($a, $b) { return $a['eg_submission_due_date'] > $b['eg_submission_due_date']; });

            $latedays = $student['latedays'];

            $late_day_usage = array();

            //Calculate per gradeable late day usage. Assumes submissions are in sorted order.
            for ($i = 0; $i < count($submissions); $i++) {

                $submission_latedays = array();
        
                //Sort latedays by since_timestamp before calculating late day usage.
                usort($latedays, function($a, $b) { return $a['since_timestamp'] > $b['since_timestamp']; });

                //Find all late day updates before this submission due date.
                foreach($latedays as $ld){
                    if($ld['since_timestamp'] <= $submissions[$i]['eg_submission_due_date'] &&
                    $curr_allowed_term < $ld['allowed_late_days']){
                        $curr_allowed_term = $ld['allowed_late_days'];
                    }
                }

                $curr_bad_modifier = "";
                $curr_late_used = $submissions[$i]['days_late'];
                // if($submissions[$i]['user_id']== 'student' && $submissions[$i]['g_title'] == 'c_failure_messages'){
                //     echo($curr_late_used);
                // }
                $curr_status = $status;
                $curr_late_charged = 0;

                $late_flag = false;

                //If late days used - extensions applied > 0 then status is "Late"
                if ($curr_late_used - $submissions[$i]['extensions'] > 0) {
                    $curr_status = "Late";
                    $late_flag = true;
                }
                //If late days used - extensions applied > allowed per assignment then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] > $submissions[$i]['assignment_allowed']) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used for this assignment";
                    $late_flag = false;
                }
                //If late days used - extensions applied > allowed per term then status is "Bad..."
                if ($curr_late_used - $submissions[$i]['extensions'] >  $curr_allowed_term - $total_late_used) {
                    $curr_status = "Bad";
                    $curr_bad_modifier = " too many used this term";
                    $late_flag = false;
                }
                //A submission cannot be late and bad simultaneously. If it's late calculate late days charged. Cannot
                //be less than 0 in cases of excess extensions. Decrement remaining late days.
                if ($late_flag) {
                    $curr_late_charged = $curr_late_used - $submissions[$i]['extensions'];
                    $curr_late_charged = ($curr_late_charged < 0) ? 0 : $curr_late_charged;
                    $total_late_used += $curr_late_charged;
                }

                $curr_remaining_late = $curr_allowed_term - $total_late_used;
                $curr_remaining_late = ($curr_remaining_late < 0) ? 0 : $curr_remaining_late;

                $submission_latedays['user_id'] = $submissions[$i]['user_id'];
                $submission_latedays['g_title'] = $submissions[$i]['g_title'];
                $submission_latedays['allowed_per_term'] = $curr_allowed_term;
                $submission_latedays['allowed_per_assignment'] = $submissions[$i]['assignment_allowed'];
                $submission_latedays['late_days_used'] = $curr_late_used;
                $submission_latedays['extensions'] = $submissions[$i]['extensions'];
                $submission_latedays['status'] = $curr_status.$curr_bad_modifier;
                $submission_latedays['late_days_charged'] = $curr_late_charged;
                $submission_latedays['remaining_days'] = $curr_remaining_late;
                $submission_latedays['total_late_used'] = $total_late_used;
                $submission_latedays['eg_submission_due_date'] = $submissions[$i]['eg_submission_due_date'];
                $late_day_usage[$submissions[$i]['g_id']] = $submission_latedays;
            }

            $all_latedays[$student['user_id']] = $late_day_usage;

        }

        return $all_latedays;
    }
    
     /**
     * For the given user id generate the late day usage HTML table.
     * @param $user_id String. The user id of the user whose table you want.
     * @return string. The string representation of the HTML table.
     */
    public function generateTableForUserDate($current_hw, $user_id, $endDate){
        //table header row.
        $table = <<<HTML
                <h3>Overall Late Day Usage</h3><br/>
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
        //If user exists in list build their table. If user does not exist empty table is returned.
        if(array_key_exists($user_id, $this->students)) {

            $student = $this->all_latedays[$user_id];

            //For each submission build a table row.
            foreach ($student as $submission) {
                    $class = "";
                    if($submission['g_title'] == $current_hw){
                        $class = "class='yellow-background'";
                    }
                    $table .= <<<HTML
                <tr>
                    <th $class style="padding:5px; border:thin solid black">{$submission['g_title']}</th>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['allowed_per_term']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['allowed_per_assignment']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['late_days_used']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['extensions']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['status']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['late_days_charged']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['total_late_used']}</td>
                    <td $class align="center" style="padding:5px; border:thin solid black">{$submission['remaining_days']}</td>
                </tr>
HTML;
            }
        }

        //Close HTML tags for table.
        $table .= <<<HTML
                </tbody>
            </table>
HTML;
        return $table;
    }
    
    public function getGradeable($user_id, $g_id) {
        if(array_key_exists($user_id, $this->all_latedays) && array_key_exists($g_id, $this->all_latedays[$user_id])){
            return $this->all_latedays[$user_id][$g_id];
        }
        return array();
    }
}


?>
