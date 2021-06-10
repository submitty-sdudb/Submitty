<?php

namespace app\views;

use app\models\User;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\PollModel;

class PollView extends AbstractView {

    public function showPollsInstructor($todays_polls, $older_polls, $future_polls, $dropdown_states) {
        $this->core->getOutput()->addBreadcrumb("投票");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls-dropdown.js');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageInstructor.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'future_polls' => $future_polls,
            'dropdown_states' => $dropdown_states,
            'semester' => $this->core->getConfig()->getSemester(),
            'course' => $this->core->getConfig()->getCourse()
          ]);
    }

    public function showPollsStudent($todays_polls, $older_polls) {
        $this->core->getOutput()->addBreadcrumb("投票");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addInternalJs('polls-dropdown.js');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/AllPollsPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'todays_polls' => $todays_polls,
            'older_polls' => $older_polls,
            'user_id' => $this->core->getUser()->getId(),
          ]);
    }

    public function showNewPollPage() {
        $this->core->getOutput()->addBreadcrumb("投票", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("新建投票");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'date_format' => $this->core->getConfig()->getDateTimeFormat()->getFormat('poll'),
            'max_size' => Utils::returnBytes(ini_get('upload_max_filesize'))
          ]);
    }

    public function showPollStudent($poll) {
        $this->core->getOutput()->addBreadcrumb("投票", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("查看投票");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->enableMobileViewport();
        $image_path = $poll->getImagePath();
        $file_data = null;
        if ($image_path !== null) {
            $file_data = base64_encode(file_get_contents($image_path));
            $file_data = 'data: ' . mime_content_type($image_path) . ';charset=utf-8;base64,' . $file_data;
        }
        return $this->core->getOutput()->renderTwigTemplate("polls/PollPageStudent.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'user_id' => $this->core->getUser()->getId(),
            'file_data' => $file_data
          ]);
    }

    public function editPoll(PollModel $poll) {
        $this->core->getOutput()->addBreadcrumb("投票", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("编辑投票");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/NewPollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'max_size' => Utils::returnBytes(ini_get('upload_max_filesize'))
          ]);
    }

    public function viewResults($poll, $results) {
        $this->core->getOutput()->addBreadcrumb("投票", $this->core->buildCourseUrl(["polls"]));
        $this->core->getOutput()->addBreadcrumb("查看结果");
        $this->core->getOutput()->addInternalCss('polls.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('plotly', 'plotly.js'));
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("polls/ViewPollResults.twig", [
            'csrf_token' => $this->core->getCsrfToken(),
            'base_url' => $this->core->buildCourseUrl() . '/polls',
            'poll' => $poll,
            'results' => $results
          ]);
    }
}
