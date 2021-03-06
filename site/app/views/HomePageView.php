<?php

namespace app\views;

use app\libraries\DateUtils;
use app\models\User;

class HomePageView extends AbstractView {
    /**
     * @param User $user
     * @param array $unarchived_courses
     * @param array $archived_courses
     */
    public function showHomePage(
        User $user,
        array $unarchived_courses,
        array $archived_courses
    ) {
        $statuses = [];
        $course_types = [$unarchived_courses, $archived_courses];
        $rank_titles = [
            User::GROUP_INSTRUCTOR              => "老师",
            User::GROUP_FULL_ACCESS_GRADER      => "助教",
            User::GROUP_LIMITED_ACCESS_GRADER   => "阅卷人",
            User::GROUP_STUDENT                 => "学生"
        ];

        foreach ($course_types as $course_type) {
            $ranks = [];

            //Create rank lists
            for ($i = 1; $i < 5; $i++) {
                $ranks[$i] = [
                    'title' => $rank_titles[$i],
                    'courses' => [],
                ];
            }

            //Assemble courses into rank lists
            foreach ($course_type as $course) {
                $ranks[$course['user_group']]['courses'][] = $course;
            }

            //Filter any ranks with no courses
            $ranks = array_filter($ranks, function ($rank) {
                return count($rank["courses"]) > 0;
            });
            $statuses[] = $ranks;
        }

        $this->output->addInternalCss('homepage.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->output->setPageName('Homepage');
        return $this->output->renderTwigTemplate('HomePage.twig', [
            "user" => $user,
            "statuses" => $statuses,
        ]);
    }

    public function showCourseCreationPage($faculty, $head_instructor, $semesters, bool $is_superuser, string $csrf_token) {
        $this->output->addBreadcrumb("新建课程");
        return $this->output->renderTwigTemplate('CreateCourseForm.twig', [
            "csrf_token" => $csrf_token,
            "head_instructor" => $head_instructor,
            "faculty" => $faculty,
            "is_superuser" => $is_superuser,
            "semesters" => $semesters,
            "course_creation_url" => $this->output->buildUrl(['home', 'courses', 'new']),
            "course_code_requirements" => $this->core->getConfig()->getCourseCodeRequirements(),
            "add_term_url" => $this->output->buildUrl(['term', 'new'])
        ]);
    }

    public function showSystemUpdatePage(string $csrf_token): string {
        $this->output->addBreadcrumb("系统更新");
        return $this->output->renderTwigTemplate('admin/SystemUpdate.twig', [
            "csrf_token" => $csrf_token,
            "latest_tag" => $this->core->getConfig()->getLatestTag()
        ]);
    }
}
