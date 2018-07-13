<?php

namespace app\models\gradeable;

use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\exceptions\ValidationException;
use app\exceptions\NotImplementedException;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\libraries\Core;
use app\models\AbstractModel;
use app\models\Team;

/**
 * All data describing the configuration of a gradeable
 *  Note: All per-student data is in the GradedGradeable class
 *
 *  Note: there is no guarantee of the values of properties not relevant to the gradeable type
 *
 *  Missing validation: student permissions (i.e. view/submit) - low priority
 *
 * @method string getId()
 * @method string getTitle()
 * @method string getInstructionsUrl()
 * @method void setInstructionsUrl($url)
 * @method int getType()
 * @method bool isGradeByRegistration()
 * @method void setGradeByRegistration($grade_by_reg)
 * @method \DateTime getTaViewStartDate()
 * @method \DateTime getGradeStartDate()
 * @method \DateTime getGradeReleasedDate()
 * @method \DateTime getGradeLockedDate()
 * @method \DateTime getMinGradingGroup()
 * @method string getSyllabusBucket()
 * @method void setSyllabusBucket($bucket)
 * @method string getTaInstructions()
 * @method void setTaInstructions($instructions)
 * @method string getAutogradingConfigPath()
 * @method bool isVcs()
 * @method void setVcs($use_vcs)
 * @method string getVcsSubdirectory()
 * @method void setVcsSubdirectory($subdirectory)
 * @method bool isTeamAssignment()
 * @method int getTeamSizeMax()
 * @method \DateTime getTeamLockDate()
 * @method bool isTaGrading()
 * @method void setTaGrading($use_ta_grading)
 * @method bool isStudentView()
 * @method void setStudentView($can_student_view)
 * @method bool isStudentSubmit()
 * @method void setStudentSubmit($can_student_submit)
 * @method bool isStudentDownload()
 * @method void setStudentDownload($can_student_download)
 * @method bool isStudentDownloadAnyVersion()
 * @method void setStudentDownloadAnyVersion($student_download_any_version)
 * @method bool isPeerGrading()
 * @method void setPeerGrading($use_peer_grading)
 * @method int getPeerGradeSet()
 * @method void setPeerGradeSet($grade_set)
 * @method \DateTime getSubmissionOpenDate()
 * @method \DateTime getSubmissionDueDate()
 * @method int getLateDays()
 * @method bool isLateSubmissionAllowed()
 * @method void setLateSubmissionAllowed($allow_late_submission)
 * @method float getPrecision()
 * @method void setPrecision($grading_precision)
 * @method Component[] getComponents()
 */
class Gradeable extends AbstractModel {
    /* Properties for all types of gradeables */

    /** @property @var string The course-wide unique gradeable id */
    protected $id = "";
    /** @property @var string The gradeable's title */
    protected $title = "";
    /** @property @var string The instructions url to give to students */
    protected $instructions_url = "";
    /** @property @var int The type of gradeable */
    protected $type = GradeableType::ELECTRONIC_FILE;
    /** @property @var bool If the gradeable should be graded per registration section (true) or rotating sections(false) */
    protected $grade_by_registration = true;
    /** @property @var int The minimum user group that can grade this gradeable (1=instructor) */
    protected $min_grading_group = 1;
    /** @property @var string The syllabus classification of this gradeable */
    protected $syllabus_bucket = "homework";
    /** @property @var Component[] An array of all of this gradeable's components */
    protected $components = [];

    /* (private) Lazy-loaded Properties */

    /** @property @var bool If any manual grades have been entered for this gradeable */
    private $any_manual_grades = null;
    /** @property @var bool If any submissions exist */
    private $any_submissions = null;
    /** @property @var Team[] Any teams that have been formed */
    private $teams = null;
    /** @property @var string[][] Which graders are assigned to which rotating sections (empty if $grade_by_registration is true)
     *                          Array (indexed by grader id) of arrays of rotating section numbers
     */
    private $rotating_grader_sections = null;
    private $rotating_grader_sections_modified = false;
    /** @property @var AutogradingConfig The object that contains the autograding config data */
    private $autograding_config = null;

    /* Properties exclusive to numeric-text/checkpoint gradeables */

    /** @property @var string The overall ta instructions for grading (numeric-text/checkpoint only) */
    protected $ta_instructions = "";

    /* Properties exclusive to electronic gradeables */

    /** @property @var string The location of the autograding configuration file */
    protected $autograding_config_path = "";
    /** @property @var bool If the gradeable is using vcs upload (true) or manual upload (false) */
    protected $vcs = false;
    /** @property @var string The subdirectory within the VCS repository for this gradeable */
    protected $vcs_subdirectory = "";
    /** @property @var bool If the gradeable is a team assignment */
    protected $team_assignment = false;
    /** @property @var int The maximum team size (if the gradeable is a team assignment) */
    protected $team_size_max = 0;
    /** @property @var bool If the gradeable is using any manual grading */
    protected $ta_grading = false;
    /** @property @var bool If students can view submissions */
    protected $student_view = false;
    /** @property @var bool If students can make submissions */
    protected $student_submit = false;
    /** @property @var bool If students can download submitted files */
    protected $student_download = false;
    /** @property @var bool If students can view/download any version of the submitted files, or just the active version */
    protected $student_download_any_version = false;
    /** @property @var bool If the gradeable uses peer grading */
    protected $peer_grading = false;
    /** @property @var int The number of peers each student will be graded by */
    protected $peer_grade_set = 0;
    /** @property @var bool If submission after student's max deadline
     *      (due date + min(late days allowed, late days remaining)) is allowed
     */
    protected $late_submission_allowed = true;
    /** @property @var float The point precision for manual grading */
    protected $precision = 0.0;

    /* Dates for all types of gradeables */

    /** @property @var \DateTime The so-called 'TA Beta-Testing' date.  This is when the gradeable appears for TA's */
    protected $ta_view_start_date = null;
    /** @property @var \DateTime The date that graders may start grading */
    protected $grade_start_date = null;
    /** @property @var \DateTime The date that grades will be released to students */
    protected $grade_released_date = null;
    /** @property @var \DateTime The date after which only instructors may change grades (aka when grades are 'due') */
    protected $grade_locked_date = null;

    /* Dates for electronic gradeables*/

    /** @property @var \DateTime The deadline for joining teams (if the gradeable is a team assignment) */
    protected $team_lock_date = null;
    /** @property @var \DateTime The date students can start making submissions */
    protected $submission_open_date = null;
    /** @property @var \DateTime The date, before which all students must make a submissions (or be marked late) */
    protected $submission_due_date = null;
    /** @property @var int The number of late days allowed */
    protected $late_days = 0;

    /**
     * Gradeable constructor.
     * @param Core $core
     * @param array $details
     * @throws \InvalidArgumentException if any of the details were not found or invalid
     * @throws ValidationException If any of the dates are incompatible or invalid
     */
    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        $this->setIdInternal($details['id']);
        $this->setTitle($details['title']);
        $this->setInstructionsUrl($details['instructions_url']);
        $this->setTypeInternal($details['type']);
        $this->setGradeByRegistration($details['grade_by_registration']);
        $this->setMinGradingGroup($details['min_grading_group']);
        $this->setSyllabusBucket($details['syllabus_bucket']);
        $this->setTaInstructions($details['ta_instructions']);

        if ($this->getType() === GradeableType::ELECTRONIC_FILE) {
            $this->setAutogradingConfigPath($details['autograding_config_path']);
            $this->setVcs($details['vcs']);
            $this->setVcsSubdirectory($details['vcs_subdirectory']);
            $this->setTeamAssignmentInternal($details['team_assignment']);
            $this->setTeamSizeMax($details['team_size_max']);
            $this->setTaGrading($details['ta_grading']);
            $this->setStudentView($details['student_view']);
            $this->setStudentSubmit($details['student_submit']);
            $this->setStudentDownload($details['student_download']);
            $this->setStudentDownloadAnyVersion($details['student_download_any_version']);
            $this->setPeerGrading($details['peer_grading']);
            $this->setPeerGradeSet($details['peer_grade_set']);
            $this->setLateSubmissionAllowed($details['late_submission_allowed']);
            $this->setPrecision($details['precision']);
        }

        // Set dates last
        $this->setDates($details);
        $this->modified = false;
    }

    const date_properties = [
        'ta_view_start_date',
        'grade_start_date',
        'grade_released_date',
        'team_lock_date',
        'submission_open_date',
        'submission_due_date',
        'grade_locked_date'
    ];

    public function toArray() {
        // Use the default behavior for the most part, but convert the dates
        $return = parent::toArray();

        foreach (self::date_properties as $date) {
            $return[$date] = $this->$date !== null ? DateUtils::dateTimeToString($this->$date) : null;
        }

        // Serialize important Lazy-loaded values
        $return['rotating_grader_sections'] = parent::parseObject($this->getRotatingGraderSections());
        $return['autograding_config'] = parent::parseObject($this->getAutogradingConfig());

        return $return;
    }

    /**
     * Gets the component object with the provided component id
     * @param int $component_id
     * @return Component|null The Component with the provided id, or null if not found
     */
    public function getComponent($component_id) {
        foreach ($this->getComponents() as $component) {
            if ($component->getId() === $component_id) {
                return $component;
            }
        }
        throw new \InvalidArgumentException('Component id did not exist in gradeable');
    }

    /**
     * Loads the autograding config file at $this->autograding_config into an array, or null if error/not found
     * @return AutogradingConfig|null
     */
    private function loadAutogradingConfig() {
        $course_path = $this->core->getConfig()->getCoursePath();

        try {
            $details = FileUtils::readJsonFile(FileUtils::joinPaths($course_path, 'config', 'build',
                "build_{$this->id}.json"));

            // If the file could not be found, the result will be false, so don't
            //  create the config if the file can't be found
            if ($details !== false) {
                return new AutogradingConfig($this->core, $details);
            }
            return null;
        } catch (\Exception $e) {
            // Don't throw an error, just don't make any data
            return null;
        }
    }

    /**
     * Parses array of the date properties to set to force them into a valid format
     * @param array $dates An array containing a combination of \DateTime and string objects indexed by date property name
     * @return \DateTime[] A full array of \DateTime objects (one element for each gradeable date property or null if not provided / bad format)
     *                      with a 'late_days' integer element
     */
    private function parseDates(array $dates) {
        $parsedDates = [];
        foreach (self::date_properties as $date) {
            if (isset($dates[$date]) && $dates[$date] !== null) {
                try {
                    $parsedDates[$date] = DateUtils::parseDateTime($dates[$date], $this->core->getConfig()->getTimezone());
                } catch (\Exception $e) {
                    $parsedDates[$date] = null;
                }
            } else {
                $parsedDates[$date] = null;
            }
        }

        // Assume that if no late days provided that there should be zero of them;
        $parsedDates['late_days'] = intval($dates['late_days'] ?? 0);
        return $parsedDates;
    }

    /**
     * Asserts that a set of dates are valid, see docs for `setDates` for the specification
     * @param array $dates A complete array of property-name-indexed \DateTime objects (or int for 'late_days')
     * @throws ValidationException With all messages for each invalid property
     */
    private function assertDates(array $dates) {
        $errors = [];

        // A message to set if the date is null, which happens when: the provided date is null,
        //  or the parsing failed.  In either case, this is an appropriate message
        $invalid_format_message = 'Invalid date-time value!';

        //
        // NOTE: The function `Utils::compareNullableGt(a,b)` in this context is called so that
        //          it returns TRUE if the two values being compared are incompatible.  If the function
        //          returns FALSE then either the condition a>b is false, or one of the values are null.
        //          THIS NULL CASE MUST BE HANDLED IN SOME OTHER WAY.  As you can see, this is achieved by
        //          null checks for each date before the comparisons are made.
        //
        //    i.e. in the expression 'Utils::compareNullableGt($ta_view_start_date, $submission_open_date)'
        //      if 'ta_view_start_date' > 'submission_open_date', then the function will return TRUE and
        //          we set an error for 'ta_view_start_date', but
        //      if 'ta_view_start_date' <= 'submission_open_date', then the two values are compatible: no error
        //      In the case that either value is null, the function will return FALSE.  The comparison becomes
        //      irrelevant and the conditions:
        //          '$ta_view_start_date === null' and/or '$submission_open_date === null' will set errors
        //          for those values appropriately
        //

        $ta_view_start_date = $dates['ta_view_start_date'];
        $grade_start_date = $dates['grade_start_date'];
        $grade_released_date = $dates['grade_released_date'];
        $team_lock_date = $dates['team_lock_date'];
        $submission_open_date = $dates['submission_open_date'];
        $submission_due_date = $dates['submission_due_date'];
        $late_days = $dates['late_days'];

        $late_interval = null;
        if ($late_days < 0) {
            $errors['late_days'] = 'Late day count must be a non-negative integer!';
        } else {
            try {
                $late_interval = new \DateInterval('P' . strval($late_days) . 'D');
            } catch (\Exception $e) {
                // This is for development debugging. In reality, we should never hit this line
                $errors['late_days'] = "Error parsing late days: {$e}";
            }
        }

        $max_due = $submission_due_date;
        if (!($submission_due_date === null || $late_interval === null)) {
            $max_due = (clone $submission_due_date)->add($late_interval);
        }

        if ($ta_view_start_date === null) {
            $errors['ta_view_start_date'] = $invalid_format_message;
        }
        if ($grade_released_date === null) {
            $errors['grade_released_date'] = $invalid_format_message;
        }

        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            if ($submission_open_date === null) {
                $errors['submission_open_date'] = $invalid_format_message;
            }
            if ($submission_due_date === null) {
                $errors['submission_due_date'] = $invalid_format_message;
            }

            if (Utils::compareNullableGt($ta_view_start_date, $submission_open_date)) {
                $errors['ta_view_start_date'] = 'TA Beta Testing Date must not be later than Submission Open Date';
            }
            if (Utils::compareNullableGt($submission_open_date, $submission_due_date)) {
                $errors['submission_open_date'] = 'Submission Open Date must not be later than Submission Due Date';
            }
            if ($this->ta_grading) {
                if ($grade_start_date === null) {
                    $errors['grade_start_date'] = $invalid_format_message;
                }
//                if ($grade_locked_date === null) {
//                    $errors['grade_locked_date'] = $invalid_format_message;
//                }
                if (Utils::compareNullableGt($submission_due_date, $grade_start_date)) {
                    $errors['grade_start_date'] = 'Manual Grading Open Date must be no earlier than Due Date';
                }
                if (Utils::compareNullableGt($grade_start_date, $grade_released_date)) {
                    $errors['grade_released_date'] = 'Grades Released Date must be later than the Manual Grading Open Date';
                }
            } else {
                if (Utils::compareNullableGt($max_due, $grade_released_date)) {
                    $errors['grade_released_date'] = 'Grades Released Date must be later than the Due Date + Max Late Days';
                }
            }
            if ($this->team_assignment) {
                if ($team_lock_date === null) {
                    $errors['team_lock_date'] = $invalid_format_message;
                }
            }
        } else {

            // TA beta testing date <= manual grading open date <= grades released
            if (Utils::compareNullableGt($ta_view_start_date, $grade_start_date)) {
                $errors['ta_view_start_date'] = 'TA Beta Testing date must be before the Grading Open Date';
            }
            if (Utils::compareNullableGt($grade_start_date, $grade_released_date)) {
                $errors['grade_released_date'] = 'Grades Released Date must be later than the Grading Open Date';
            }
        }

        if (count($errors) !== 0) {
            throw new ValidationException('Date validation failed', $errors);
        }
    }

    /**
     * Sets the all of the dates of this gradeable
     * Validation: All parenthetical values are only relevant for electronic submission and drop out of this expression
     *  ta_view_start_date <= (submission_open_date) <= (submission_due_date) <= (grade_start_date) <= grade_released_date
     *      AND
     *  (submission_due_date + late days) <= grade_released_date
     *
     * @param $dates string[]|\DateTime[] An array of dates/date strings indexed by property name
     */
    public function setDates(array $dates) {
        // Wrangle the input so we have a fully populated array of \DateTime's (or nulls)
        $dates = $this->parseDates($dates);

        // Asserts that this date information is valid
        $this->assertDates($dates);

        // Manually set each property (instead of iterating over self::date_properties) so the user
        //  can't set dates irrelevant to the gradeable settings

        $this->ta_view_start_date = $dates['ta_view_start_date'];
        $this->grade_start_date = $dates['grade_start_date'];
        $this->grade_released_date = $dates['grade_released_date'];
        $this->grade_locked_date = $dates['grade_locked_date'];

        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            if (!$this->ta_grading) {
                // No TA grading, but we must set this start date so the database
                //  doesn't complain when we update it
                $this->grade_start_date = $dates['grade_released_date'];
            }

            // Set team lock date even if not team assignment because it is NOT NULL in the db
            $this->team_lock_date = $dates['team_lock_date'];
            $this->submission_open_date = $dates['submission_open_date'];
            $this->submission_due_date = $dates['submission_due_date'];
            $this->late_days = $dates['late_days'];
        }
        $this->modified = true;
    }

    /**
     * Gets all of the gradeable's date values indexed by property name
     * @return \DateTime[]
     */
    public function getDates() {
        $dates = [];
        foreach(self::date_properties as $property) {
            $dates[$property] = $this->$property;
        }
        return $dates;
    }

    /**
     * Gets the rotating section grader assignment
     * @return array An array (indexed by user id) of arrays of section ids
     */
    public function getRotatingGraderSections() {
        if($this->rotating_grader_sections === null) {
            $this->setRotatingGraderSections($this->core->getQueries()->getRotatingSectionsByGrader($this->id));
            $this->rotating_grader_sections_modified = false;
        }
        return $this->rotating_grader_sections;
    }

    /**
     * Gets the autograding configuration object
     * @return AutogradingConfig
     */
    public function getAutogradingConfig() {
        if($this->autograding_config === null) {
            $this->autograding_config = $this->loadAutogradingConfig();
        }
        return $this->autograding_config;
    }

    /**
     * Gets whether this gradeable has an autograding config
     * @return bool True if it has an autograding config
     */
    public function hasAutogradingConfig() {
        return $this->getAutogradingConfig() !== null;
    }

    /** @internal */
    public function setTaViewStartDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setGradeStartDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setGradeReleasedDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setGradeLockedDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setTeamLockDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setSubmissionOpenDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setSubmissionDueDate($date) {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    /** @internal */
    public function setAutogradingConfig() {
        throw new \BadFunctionCallException('Cannot set the autograding config data');
    }

    /**
     * Sets the gradeable Id.  Must match the regular expression:  ^[a-zA-Z0-9_-]*$
     * @param string $id The gradeable id to set
     */
    private function setIdInternal($id) {
        preg_match('/^[a-zA-Z0-9_-]*$/', $id, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) === 0) {
            throw new \InvalidArgumentException('Gradeable id must be alpha-numeric/hyphen/underscore only');
        }
        $this->id = $id;
    }

    /** @internal */
    public function setId($id) {
        throw new \BadFunctionCallException('Cannot change Id of gradeable');
    }

    /**
     * Sets the gradeable Title
     * @param string $title Must not be blank.
     */
    public function setTitle($title) {
        if ($title === '') {
            throw new \InvalidArgumentException('Gradeable title must not be blank');
        }
        $this->title = strval($title);
        $this->modified = true;
    }

    /**
     * Sets the gradeable type
     * @param GradeableType $type Must be a valid GradeableType
     */
    private function setTypeInternal($type) {
        // Call this to make an exception if the type is invalid
        GradeableType::typeToString($type);
        $this->type = $type;
    }

    /** @internal */
    public function setType($type) {
        throw new \BadFunctionCallException('Cannot change gradeable type');
    }

    /**
     * Sets the minimum user level that can grade an assignment.
     * @param int $group Must be at least 1 and no more than 4
     */
    public function setMinGradingGroup($group) {
        // Disallow the 0 group (this may catch some potential bugs with instructors not being able to edit gradeables)
        if ((is_int($group) || ctype_digit($group)) && intval($group) > 0 && intval($group) <= 4) {
            $this->min_grading_group = $group;
        } else {
            throw new \InvalidArgumentException('Grading group must be an integer larger than 0');
        }
        $this->modified = true;
    }

    /**
     * Sets the maximum team size
     * @param int $max_team_size Must be at least 0
     */
    public function setTeamSizeMax($max_team_size) {
        if ((is_int($max_team_size) || ctype_digit($max_team_size)) && intval($max_team_size) >= 0) {
            $this->team_size_max = intval($max_team_size);
        } else {
            throw new \InvalidArgumentException('Max team size must be a non-negative integer!');
        }
        $this->modified = true;
    }

    /**
     * Sets the peer grading set
     * @param int $peer_grading_set Must be at least 0
     */
    public function setPeerGradingSet($peer_grading_set) {
        if ((is_int($peer_grading_set) || ctype_digit($peer_grading_set)) && intval($peer_grading_set) >= 0) {
            $this->peer_grade_set = intval($peer_grading_set);
        } else {
            throw new \InvalidArgumentException('Peer grade set must be a non-negative integer!');
        }
        $this->modified = true;
    }

    /**
     * Sets the array of components
     * @param Component[] $components Must be an array of only Component
     */
    public function setComponents(array $components) {
        foreach ($components as $component) {
            if (!($component instanceof Component)) {
                throw new \InvalidArgumentException('Object in components array wasn\'t a component');
            }
        }
        $this->components = array_values($components);

        // sort by order
        usort($this->components, function(Component $a, Component $b) {
            return $a->getOrder() - $b->getOrder();
        });
    }

    /**
     * Sets the path to the autograding config
     * @param string $path Must not be blank
     */
    public function setAutogradingConfigPath($path) {
        if ($path === '') {
            throw new \InvalidArgumentException('Autograding configuration file path cannot be blank');
        }
        $this->autograding_config_path = strval($path);
        $this->modified = true;
    }

    /**
     * Sets whether the gradeable is a team gradeable
     * @param bool $use_teams
     */
    private function setTeamAssignmentInternal($use_teams) {
        $this->team_assignment = $use_teams === true;
    }
    
    /** @internal */
    public function setTeamAssignment($use_teams) {
        throw new \BadFunctionCallException('Cannot change teamness of gradeable');
    }

    /**
     * Sets the rotating grader sections for this gradeable
     * @param array $rotating_grader_sections An array (indexed by grader id) of arrays of section numbers
     */
    public function setRotatingGraderSections($rotating_grader_sections) {
        // Number of total rotating sections
        $num_sections = $this->core->getQueries()->getNumberRotatingSections();

        $parsed_graders_sections = [];
        foreach($rotating_grader_sections as $user=>$grader_sections) {
            if($grader_sections !== null) {
                if(!is_array($grader_sections)) {
                    throw new \InvalidArgumentException('Rotating grader section for grader was not array');
                }
                // Parse each section array into strings
                $parsed_sections = [];
                foreach($grader_sections as $section) {
                    if ((is_int($section) || ctype_digit($section)) && intval($section) > 0 && intval($section) <= $num_sections) {
                        $parsed_sections[] = intval($section);
                    } else {
                        throw new \InvalidArgumentException('Grading section must be a positive integer no more than the number of rotating sections!');
                    }
                }
                $parsed_graders_sections[$user] = $parsed_sections;
            }
        }
        $this->rotating_grader_sections = $parsed_graders_sections;
        $this->rotating_grader_sections_modified = true;
    }

    /**
     * Rounds a provided point value to the nearest multiple of $precision
     *
     * @param $points float|string The number to round
     * @return float The rounded result
     */
    public function roundPointValue($points) {
        // Note that changing the gradeable precision does not trigger
        //  all of the component/mark point values to update.  This is intended.

        // No precision, no rounding
        if ($this->precision === 0.0) {
            return $points;
        }

        $points = floatval($points);
        $q = (int)($points / $this->precision);
        $r = fmod($points, $this->precision);

        // If the remainder is more than half the precision away from zero, then add one
        //  times the direction from zero to the quotient.  Multiply by precision
        return ($q + (abs($r) > $this->precision / 2 ? ($r > 0 ? 1 : -1) : 0)) * $this->precision;
    }



    /**
     * Gets if this gradeable has any manual grades (any GradedGradeables exist)
     * @return bool True if any manual grades exist
     */
    public function anyManualGrades() {
        if($this->any_manual_grades === null) {
            $this->any_manual_grades = $this->core->getQueries()->getGradeableHasGrades($this->getId());
        }
        return $this->any_manual_grades;
    }

    /**
     * Gets if this gradeable has any submissions yet
     * @return bool
     */
    public function anySubmissions() {
        if($this->any_submissions === null) {
            // Until we find a submission, assume there are none
            $this->any_submissions = false;
            if ($this->type === GradeableType::ELECTRONIC_FILE) {
                $semester = $this->core->getConfig()->getSemester();
                $course = $this->core->getConfig()->getCourse();
                $submission_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/" . "submissions/" . $this->getId();
                if (is_dir($submission_path)) {
                    $this->any_submissions = false;
                }
            }
        }
        return $this->any_submissions;
    }

    /**
     * Gets all of the teams formed for this gradeable
     * @return Team[]
     */
    public function getTeams() {
        if($this->teams === null) {
            $this->teams = $this->core->getQueries()->getTeamsByGradeableId($this->getId());
        }
        return $this->teams;
    }

    /**
     * Gets if this gradeable has any teams formed yet
     * @return bool
     */
    public function anyTeams() {
        return !empty($this->getTeams());
    }

    /**
     * Used to decide whether a gradeable can be deleted or not.
     * This means: No submissions, No manual grades entered, and No teams formed
     * @return bool True if the gradeable can be deleted
     */
    public function canDelete() {
        return !$this->anySubmissions() && !$this->anyManualGrades() && !$this->anyTeams();
    }

    /**
     * Gets whether the rotating grader sections were modified
     * @return bool
     */
    public function isRotatingGraderSectionsModified() {
        return $this->rotating_grader_sections_modified;
    }

    /**
     * Gets if this gradeable is pdf-upload
     * @return bool
     */
    public function isPdfUpload() {
        foreach ($this->components as $component) {
            if ($component->getPage() !== Component::PDF_PAGE_NONE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets if the students assign pages to components
     * @return bool
     */
    public function isStudentPdfUpload() {
        foreach ($this->components as $component) {
            if ($component->getPage() === Component::PDF_PAGE_STUDENT) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets the number of numeric components if type is GradeableType::NUMERIC_TEXT
     * @return int
     */
    public function getNumNumeric() {
        if ($this->type !== GradeableType::NUMERIC_TEXT) {
            return 0;
        }
        $count = 0;
        foreach ($this->components as $component) {
            if (!$component->isText()) {
                ++$count;
            }
        }
        return $count;
    }

    /**
     * Gets the number of text components if type is GradeableType::NUMERIC_TEXT
     * @return int
     */
    public function getNumText() {
        if ($this->type !== GradeableType::NUMERIC_TEXT) {
            return 0;
        }
        $count = 0;
        foreach ($this->components as $component) {
            if ($component->isText()) {
                ++$count;
            }
        }
        return $count;
    }
}
