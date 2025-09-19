<?php
namespace local_unenrolcategorycourses\task;

defined('MOODLE_INTERNAL') || die();

class unenrol_category_task extends \core\task\adhoc_task {
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $categoryid = $data->categoryid;
        $roleid     = $data->roleid;
        $method     = $data->method;

        mtrace("=== Démarrage de la désinscription par catégorie ===");
        mtrace("Catégorie ID: {$categoryid}, Rôle ID: {$roleid}, Méthode: {$method}");

        $subcats = \core_course_category::get($categoryid)->get_all_children_ids(true);
        $allcats = array_merge([$categoryid], $subcats);
        list($insql, $params) = $DB->get_in_or_equal($allcats, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select('course', "category $insql", $params);

        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $instances = enrol_get_instances($course->id, true);
            $totalunenrolled = 0;

            foreach ($instances as $instance) {
                if ($instance->enrol !== $method) {
                    continue;
                }
                $plugin = enrol_get_plugin($instance->enrol);
                if (!$plugin) {
                    continue;
                }

                $users = get_role_users($roleid, $context, false, 'u.id');
                foreach ($users as $user) {
                    if ($DB->record_exists('user_enrolments', ['userid' => $user->id, 'enrolid' => $instance->id])) {
                        $plugin->unenrol_user($instance, $user->id);
                        $totalunenrolled++;
                    }
                }
            }

            if ($totalunenrolled > 0) {
                mtrace("Cours '{$course->fullname}' (ID {$course->id}) : {$totalunenrolled} utilisateur(s) désinscrit(s).");
            } else {
                mtrace("Cours '{$course->fullname}' (ID {$course->id}) : aucun utilisateur désinscrit.");
            }
        }
        mtrace("=== Fin de la tâche de désinscription ===");
    }
}
