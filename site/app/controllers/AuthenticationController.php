<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\Utils;
use app\libraries\Logger;
use app\libraries\response\MultiResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthenticationController
 *
 * Controller to deal with user authentication and de-authentication. The actual lifting is done through Core
 * and the associated registered IAuthentication interface returning whether or not we were able to actually
 * authenticate the user or not, and then the controller redirects on that answer.
 */
class AuthenticationController extends AbstractController {
    /**
     * @var bool Is the user logged in or not. We use this to prevent the user going to the login controller
     *           and trying to login again.
     */
    private $logged_in;

    /**
     * AuthenticationController constructor.
     *
     * @param Core $core
     * @param bool $logged_in
     */
    public function __construct(Core $core, $logged_in = false) {
        parent::__construct($core);
        $this->logged_in = $logged_in;
    }

    /**
     * Logs out the current user from the system. This is done by both deleting the current going
     * session from the database as well as invalidating the session id saved in the cookie. The latter
     * is not strictly necessary, but still good to tidy up.
     *
     * @Route("/authentication/logout")
     * @return MultiResponse
     */
    public function logout() {
        if ($this->core->removeCurrentSession()) {
            Logger::logAccess($this->core->getUser()->getId(), $_COOKIE['submitty_token'], "logout");
        }

        Utils::setCookie('submitty_session', '', time() - 3600);
        // Remove all history for checkpoint gradeables
        foreach (array_keys($_COOKIE) as $cookie) {
            if (strpos($cookie, "_history") == strlen($cookie) - 8) { // '_history' is len 8
                Utils::setCookie($cookie, '', time() - 3600);
            }
        }


        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildUrl(['authentication', 'login']))
        );
    }

    /**
     * Display the login form to the user
     *
     * @Route("/authentication/login")
     *
     * @var string $old the url to redirect to after login
     * @return MultiResponse
     */
    public function loginForm($old = null) {
        if (!is_null($old) && !Utils::startsWith(urldecode($old), $this->core->getConfig()->getBaseUrl())) {
            $old = null;
        }
        return MultiResponse::webOnlyResponse(
            new WebResponse('Authentication', 'loginForm', $old)
        );
    }

    /**
     * Checks the submitted login form via the configured "authentication" setting. Additionally, on successful
     * login, we want to redirect the user $_REQUEST the page they were attempting to goto before being sent to the
     * login form (this being saved in the $_POST['old'] array). However, on failure to login, we want to continue
     * to maintain that old request data passing it back into the login form.
     *
     * @Route("/authentication/check_login")
     *
     * @var string $old the url to redirect to after login
     * @return MultiResponse
     */
    public function checkLogin($old = null) {
        if (!is_null($old) && !Utils::startsWith(urldecode($old), $this->core->getConfig()->getBaseUrl())) {
            $old = null;
        }
        if (isset($old)) {
            $old = urldecode($old);
        }
        if ($this->logged_in) {
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($old)
            );
        }
        $_POST['stay_logged_in'] = (isset($_POST['stay_logged_in']));
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = '请输入用户名及密码';

            $this->core->addErrorMessage($msg);
            return new MultiResponse(
                JsonResponse::getFailResponse($msg),
                null,
                new RedirectResponse($old)
            );
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->authenticate($_POST['stay_logged_in']) === true) {
            Logger::logAccess($_POST['user_id'], $_COOKIE['submitty_token'], "login");
            $msg = "欢迎登入：" . htmlentities($_POST['user_id']);

            $this->core->addSuccessMessage($msg);
            return new MultiResponse(
                JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]),
                null,
                new RedirectResponse($old)
            );
        }
        else {
            $msg = "用户名或密码错误";

            $this->core->addErrorMessage($msg);
            $this->core->redirect($old);
            return new MultiResponse(
                JsonResponse::getFailResponse($msg),
                null,
                new RedirectResponse($old)
            );
        }
    }

    /**
     * @Route("/api/token", methods={"POST"})
     *
     * @return MultiResponse
     */
    public function getToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = '请输入用户名及密码';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $token = $this->core->authenticateJwt();
        if ($token) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['token' => $token]));
        }
        else {
            $msg = "用户名或密码错误";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }

    /**
     * @Route("/api/token/invalidate", methods={"POST"})
     *
     * @return MultiResponse
     */
    public function invalidateToken() {
        if (!isset($_POST['user_id']) || !isset($_POST['password'])) {
            $msg = '请输入用户名及密码';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        $success = $this->core->invalidateJwt();
        if ($success) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
        }
        else {
            $msg = "用户名或密码错误";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
    }

    /**
     * Handle stateless authentication for the VCS endpoints.
     *
     * This endpoint is unique from the other authentication methods in
     * that this requires a specific course so that we can check a user's
     * status, as well as potentially information about a particular
     * gradeable in that course.
     *
     * @Route("{_semester}/{_course}/authentication/vcs_login")
     * @return MultiResponse
     */
    public function vcsLogin() {
        if (
            empty($_POST['user_id'])
            || empty($_POST['password'])
            || empty($_POST['gradeable_id'])
            || empty($_POST['id'])
            || !$this->core->getConfig()->isCourseLoaded()
        ) {
            $msg = '校验不完整';
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        $this->core->getAuthentication()->setUserId($_POST['user_id']);
        $this->core->getAuthentication()->setPassword($_POST['password']);
        if ($this->core->getAuthentication()->authenticate() !== true) {
            $msg = "用户名或密码错误";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }

        $user = $this->core->getQueries()->getUserById($_POST['user_id']);
        if ($user === null) {
            $msg = "未找到对应用户";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }
        elseif ($user->accessFullGrading()) {
            $msg = "欢迎登入：{$_POST['user_id']}";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]));
        }

        try {
            $gradeable = $this->core->getQueries()->getGradeableConfig($_POST['gradeable_id']);
        }
        catch (\InvalidArgumentException $exc) {
            $gradeable = null;
        }

        if ($gradeable !== null && $gradeable->isTeamAssignment()) {
            if (!$this->core->getQueries()->getTeamById($_POST['id'])->hasMember($_POST['user_id'])) {
                $msg = "用户不在组织内";
                return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
            }
        }
        elseif ($_POST['user_id'] !== $_POST['id']) {
            $msg = "用户无权访问该项目";
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse($msg));
        }

        $msg = "欢迎登入：{$_POST['user_id']}";
        return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse(['message' => $msg, 'authenticated' => true]));
    }
}
