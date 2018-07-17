<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

class CourseMaterialsController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'view_course_materials_page':
            		$this->viewCourseMaterialsPage();
                break;
            case 'delete_course_materials_file':
                $this->deleteCourseMaterialsFile();
                break;
            default:
                $this->viewCourseMaterialsPage();
                break;
        }
    }

    public function viewCourseMaterialsPage() {
        $user_group = $this->core->getUser()->getGroup();
        $instructor_permission = ($user_group === 1);
        $this->core->getOutput()->renderOutput(array('course', 'CourseMaterials'), 'listCourseMaterials', $instructor_permission);  //todo
    }

    public function deleteCourseMaterialsFile() {
        if($this->core->getUser()->getGroup() !== 1) {
           return $this->uploadResult("You have no permission to delete this file", false);
        }

        if (!isset($_POST['filename'])) {
            return $this->uploadResult("Empty file.", false);
        }

        $file_name = $_POST['filename'];

        $course_materials_file_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials", $filename);

        if (!@unlink($course_materials_file_path)) {
            return $this->uploadResult("Failed to delete the file {$filename} from the server.", false);
        }


        return $this->uploadResult("Successfully deleted!", true);
      }
}
