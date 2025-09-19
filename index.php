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
 * Form for categories unenrollment
 *
 * @package     local_unenrolcategorycourses
 * @copyright   2024 Olivier VALENTIN
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();

admin_externalpage_setup('local_unenrolcategorycourses');

$context = context_system::instance();
require_capability('local/unenrolcategorycourses:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/unenrolcategorycourses/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_unenrolcategorycourses'));
$PAGE->set_heading(get_string('pluginname', 'local_unenrolcategorycourses'));

require_once(__DIR__ . '/form.php');
echo $OUTPUT->header();

$mform = new local_unenrolcategorycourses_form();

$categoryid = optional_param('categoryid', 0, PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);
$method     = optional_param('method', '', PARAM_ALPHA);
$confirm    = optional_param('confirm', 0, PARAM_BOOL);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/index.php'));
}

// Check if there are others tasks registered.
$pendingtasks = $DB->get_records('task_adhoc', [
    'classname' => '\\local_unenrolcategorycourses\\task\\unenrol_category_task'
]);

if (!empty($pendingtasks)) {
    // Change message if one or several tasks is/are found.
    if (count($pendingtasks) === 1) {
        $msg = (get_string('pendingtaskfound', 'local_unenrolcategorycourses'));
        $msg .= '<ul>';
    } else if (count($pendingtasks) > 1) {
        $msg = (get_string('pendingtasksfound', 'local_unenrolcategorycourses'));
        $msg .= '<ul>';
    }
    // Analyze each task.
    foreach ($pendingtasks as $task) {
        $data = unserialize($task->customdata);
        $data = @unserialize($task->customdata);
        if ($data === false) {
            $data = json_decode($task->customdata);
        }

        if (!empty($data->categoryid)) {
            // Category information.
            $catname = $DB->get_field('course_categories', 'name', ['id' => $data->categoryid]);
            $subcats = core_course_category::get($data->categoryid)->get_all_children_ids(true);
            $allcats = array_merge([$data->categoryid], $subcats);
            list($insql, $params) = $DB->get_in_or_equal($allcats, SQL_PARAMS_NAMED);
            $courses = $DB->get_records_select('course', "category $insql", $params);

            // Retrieve role name.
            $rolename = $DB->get_field('role', 'name', ['id' => $data->roleid]);
            if (!$rolename) {
                $rolename = get_string('unknownrole', 'local_unenrolcategorycourses').' : '.$data->roleid;
            }

            $msg .= '<li><b>'.get_string('category', 'core').' : </b>'.format_string($catname).' ';
            $msg .= '(' . count($courses).get_string('countcoursesaffected', 'local_unenrolcategorycourses');
            $msg .= ' - <b>'.get_string('enrollmethod', 'local_unenrolcategorycourses').' : </b>' . $data->method;
            $msg .= ' - <b>'.get_string('role', 'core').' : </b>'.$rolename;
            $msg .= '</li>';
        } else {
            // If we can't read task information.
            echo $OUTPUT->notification(
                '<li>'.get_string('notaskinformation', 'local_unenrolcategorycourses').'<li>',
                'notifywarning'
            );
        }
    }
    $msg .= '</ul>';
    echo $OUTPUT->notification($msg, 'notifywarning');
}

// Confirm form.
if (!$confirm && $data = $mform->get_data()) {
    $categoryid = $data->categoryid;
    $roleid = $data->roleid;
    $method = $data->method;

    $subcats = core_course_category::get($categoryid)->get_all_children_ids(true);
    $allcats = array_merge([$categoryid], $subcats);
    list($insql, $params) = $DB->get_in_or_equal($allcats, SQL_PARAMS_NAMED);
    $courses = $DB->get_records_select('course', "category $insql", $params);

    echo $OUTPUT->heading(get_string('confirmtitle', 'local_unenrolcategorycourses'));

    // Display task params.
    $params ='<ul>
        <li><b>'.get_string('category', 'core').' : </b>'.format_string($DB->get_field('course_categories','name',['id'=>$categoryid])).'</li>
        <li><b>'.get_string('role', 'core').' : </b>'.role_get_name($DB->get_record('role',['id'=>$roleid])).'</li>
        <li><b>'.get_string('enrollmethod', 'local_unenrolcategorycourses').' : </b>'.$method.'</li>
        <li><b>'.count($courses).' '.get_string('countcoursesaffected', 'local_unenrolcategorycourses').'</b></li>
    </ul>';
    echo html_writer::div($params);

    // Create Bootstrap menu to see affected courses.
    if ($courses > 0) {
        $courselist = '';
        foreach ($courses as $course) {
            $courselist .= '<li>'.format_string($course->fullname).'</li>';
        }
        $courselistemenu = '
        <p>
            <a class="btn btn-primary" data-toggle="collapse" href="#coursListCollapse" role="button" aria-expanded="false" aria-controls="coursListCollapse">
                '.get_string('showcourseslistbutton', 'local_unenrolcategorycourses').'
            </a>
        </p>
        <div class="collapse" id="coursListCollapse">
            <div class="card card-body">
                <ul>
                    '.$courselist.'
                </ul>
            </div>
        </div>
        ';

    echo html_writer::div($courselistemenu);
    }

    $confirmurl = new moodle_url('/local/unenrolcategorycourses/index.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
        'categoryid' => $categoryid,
        'roleid' => $roleid,
        'method' => $method
    ]);
    $cancelurl = new moodle_url('/local/unenrolcategorycourses/index.php');

    echo $OUTPUT->confirm(get_string('confirmquestion', 'local_unenrolcategorycourses'), $confirmurl, $cancelurl);

// If confirmed, add task.
} else if ($confirm && $categoryid && $roleid && $method) {
    require_sesskey();

    $task = new \local_unenrolcategorycourses\task\unenrol_category_task();
    $task->set_custom_data([
        'categoryid' => $categoryid,
        'roleid' => $roleid,
        'method' => $method
    ]);
    \core\task\manager::queue_adhoc_task($task);

    echo $OUTPUT->notification(get_string('taskscheduled', 'local_unenrolcategorycourses'), 'notifysuccess');
    echo $OUTPUT->continue_button(new moodle_url('/local/unenrolcategorycourses/index.php'));

} else {
    $mform->display();
}

echo $OUTPUT->footer();
