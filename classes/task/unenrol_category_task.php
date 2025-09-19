<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Task for categories unenrollment
 *
 * @package     local_unenrolcategorycourses
 * @copyright   2024 Olivier VALENTIN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unenrolcategorycourses\task;

defined('MOODLE_INTERNAL') || die();

class unenrol_category_task extends \core\task\adhoc_task {
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $categoryid = $data->categoryid;
        $roleid     = $data->roleid;
        $method     = $data->method;

        mtrace("start task - unenroll users from selected category ...");
        mtrace("category ID: {$categoryid}, role ID: {$roleid}, method: {$method}");

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
                mtrace("course '{$course->fullname}' (ID {$course->id}) : {$totalunenrolled} user(s) unenrolled.");
            } else {
                mtrace("course '{$course->fullname}' (ID {$course->id}) : no users unenrolled.");
            }
        }
        mtrace("end task - unenroll users from selected category ...");
    }
}
