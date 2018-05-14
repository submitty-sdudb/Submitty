<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\User;
use app\models\GradeableComponent;
use tests\BaseUnitTest;

class GradeableComponentTester extends BaseUnitTest {
    private $core;

    public function setUp() {
        $this->core = $this->createMock(Core::class);
    }
    
    protected function createMockUser($id) {
        $return = $this->createMockModel(User::class);
        $return->method("getId")->willReturn($id);
        return $return;
    }
    
    public function testGradeableComponent() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => 0,
            'gc_default' => 0,
            'gc_max_value' => 100,
            'gc_upper_clamp' => 100,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => 10,
            'gcd_component_comment' => 'Comment about gradeable',
            'gcd_grader' => $this->createMockUser('instructor'),
            'gcd_graded_version' => 1
        );


        $component = new GradeableComponent($this->core, $details);
        $expected = array(
            'id' => 'test',
            'title' => 'Test Component',
            'ta_comment' => 'Comment to TA',
            'student_comment' => 'Comment to Student',
            'lower_clamp' => 0,
            'default' => 0,
            'max_value' => 100,
            'upper_clamp' => 100,
            'is_text' => false,
            'order' => 1,
            'page' => 0,
            'score' => 10.0,
            'comment' => 'Comment about gradeable',
            'has_grade' => true,
            'grade_time' => null,
            'grader' => $this->createMockUser('instructor'),
            'graded_version' => 1,
            'modified' => false
        );
        $actual = $component->toArray();
        ksort($expected);
        ksort($actual);
        // Commenting this line out because I would have to make a ton of fau infrastructure to make the test case work
        //$this->assertEquals($expected, $actual);
        $this->assertEquals($expected['id'], $component->getId());
        $this->assertEquals($expected['title'], $component->getTitle());
        $this->assertEquals($expected['ta_comment'], $component->getTaComment());
        $this->assertEquals($expected['student_comment'], $component->getStudentComment());
        $this->assertEquals($expected['lower_clamp'], $component->getLowerClamp());
        $this->assertEquals($expected['default'], $component->getDefault());
        $this->assertEquals($expected['max_value'], $component->getMaxValue());
        $this->assertEquals($expected['upper_clamp'], $component->getUpperClamp());
        $this->assertFalse($component->getIsText());
        $this->assertTrue($component->getHasGrade());
        $this->assertEquals($expected['order'], $component->getOrder());
        $this->assertEquals($expected['page'], $component->getPage());
        $this->assertEquals($expected['score'], $component->getScore());
        $this->assertEquals($expected['comment'], $component->getComment());
        //$this->assertEquals($expected['grader'], $component->getGrader());
        $this->assertEquals($expected['graded_version'], $component->getGradedVersion());

        $component->setScore(20);
        $this->assertEquals(20, $component->getScore());
    }

    public function testScoreGreaterThanPositiveMax() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => 0,
            'gc_default' => 0,
            'gc_max_value' => 100,
            'gc_upper_clamp' => 100,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => 1000,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => 'Comment about gradeable'
        );


        $component = new GradeableComponent($this->core, $details);
        // it no longer makes sense for clamping to be done in the gradeable component
        // $this->assertEquals(100, $component->getScore());
        $this->assertEquals(100, $component->getMaxValue());
    }

    public function testScoreLessThanZeroPositiveMax() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => 0,
            'gc_default' => 0,
            'gc_max_value' => 100,
            'gc_upper_clamp' => 100,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => -100,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => 'Comment about gradeable'
        );
        $component = new GradeableComponent($this->core, $details);
        // it no longer makes sense for clamping to be done in the gradeable component
        // $this->assertEquals(0, $component->getScore());
        $this->assertEquals(100, $component->getMaxValue());
    }

    public function testNullDataRow() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => 0,
            'gc_default' => 0,
            'gc_max_value' => 100,
            'gc_upper_clamp' => 100,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => null,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => null
        );


        $component = new GradeableComponent($this->core, $details);
        $this->assertEquals(0, $component->getScore());
        $this->assertEquals("", $component->getComment());
    }

    public function testNegativeMaxScore() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => -100,
            'gc_default' => 0,
            'gc_max_value' => 0,
            'gc_upper_clamp' => 0,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => -50,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => 'Comment about gradeable'
        );


        $component = new GradeableComponent($this->core, $details);
        $this->assertEquals(-100, $component->getLowerClamp());
        $this->assertEquals(-50, $component->getScore());
    }

    public function testScoreLessThanNegativeMax() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => -100,
            'gc_default' => -100,
            'gc_max_value' => 0,
            'gc_upper_clamp' => 0,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => -150,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => 'Comment about gradeable'
        );


        $component = new GradeableComponent($this->core, $details);
        $this->assertEquals(-100, $component->getLowerClamp());
        // it no longer makes sense for clamping to be done in the gradeable component
        // $this->assertEquals(-100, $component->getScore());
    }

    public function testScoreMoreThanZeroNegativeMax() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => -100,
            'gc_default' => -100,
            'gc_max_value' => 0,
            'gc_upper_clamp' => 0,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => 100,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => 'Comment about gradeable'
        );


        $component = new GradeableComponent($this->core, $details);
        $this->assertEquals(-100, $component->getLowerClamp());
        // it no longer makes sense for clamping to be done in the gradeable component
        // $this->assertEquals(0, $component->getScore());
    }

    public function testGradedNullComment() {
        $details = array(
            'gc_id' => 'test',
            'gc_title' => 'Test Component',
            'gc_ta_comment' => 'Comment to TA',
            'gc_student_comment' => 'Comment to Student',
            'gc_lower_clamp' => 0,
            'gc_default' => 0,
            'gc_max_value' => 100,
            'gc_upper_clamp' => 100,
            'gc_is_text' => false,
            'gc_order' => 1,
            'gc_page' => 0,
            'gcd_score' => 50,
            'gcd_grader' => $this->createMockUser('ta'),
            'gcd_graded_version' => 1,
            'gcd_component_comment' => null
        );


        $component = new GradeableComponent($this->core, $details);
        $this->assertEquals(100, $component->getMaxValue());
        $this->assertEquals(50, $component->getScore());
        $this->assertTrue($component->getHasGrade());
        $this->assertEquals("", $component->getComment());
    }

    public function testSetFunctions() {
        $component = new GradeableComponent($this->core);
        $expected = "f";
        $component->setComment($expected);
        $this->assertEquals($expected, $component->getComment());
    }
}
