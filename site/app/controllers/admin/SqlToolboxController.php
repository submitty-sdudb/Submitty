<?php

declare(strict_types=1);

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\exceptions\DatabaseException;
use app\libraries\database\QueryIdentifier;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\views\admin\SqlToolboxView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UsersController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */

class SqlToolboxController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/sql_toolbox", methods={"GET"})
     */
    public function showToolbox(): WebResponse {
        $organizedTables = [];
        //Loop through and create a 2d array that holds all columns for each table name.
        foreach ($this->core->getQueries()->getCourseSchemaTables() as $table) {
            $organizedTables[$table['table_name']][] = [
                'name' => $table['column_name'],
                'type' => $table['data_type']
            ];
        }
        return new WebResponse(SqlToolboxView::class, 'showToolbox', $organizedTables);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/sql_toolbox", methods={"POST"})
     */
    public function runQuery(): JsonResponse {
        $query = trim($_POST['sql']);

        if (QueryIdentifier::identify($query) !== QueryIdentifier::SELECT) {
            return JsonResponse::getFailResponse('Invalid query, can only run SELECT queries.');
        }

        $semiColonCount = substr_count($query, ';');
        if ($semiColonCount > 1 || ($semiColonCount === 1 && substr($query, -1) !== ';')) {
            return JsonResponse::getFailResponse('Detected multiple queries, not running.');
        }

        try {
            $this->core->getCourseDB()->beginTransaction();
            $this->core->getCourseDB()->query($query);
            return JsonResponse::getSuccessResponse($this->core->getCourseDB()->rows());
        }
        catch (DatabaseException $exc) {
            return JsonResponse::getFailResponse("Error running query: " . $exc->getMessage());
        }
        finally {
            $this->core->getCourseDB()->rollback();
        }
    }
}
