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

// Vérifier si une tâche de désinscription est en attente.
$pendingtasks = $DB->get_records('task_adhoc', [
    'classname' => '\\local_unenrolcategorycourses\\task\\unenrol_category_task'
]);

if (!empty($pendingtasks)) {
    if (count($pendingtasks) === 1) {
        $msg = 'Une tâche de désinscription est déjà planifiée.';
        $msg .= '<ul>';
    } else if (count($pendingtasks) > 1) {
        $msg = 'Plusieurs tâches de désinscription sont déjà planifiées.<br>';
        $msg .= '<ul>';
    }
    foreach ($pendingtasks as $task) {
        $data = unserialize($task->customdata);
        $data = @unserialize($task->customdata);
        if ($data === false) {
            $data = json_decode($task->customdata);
        }

        if (!empty($data->categoryid)) {
            // Infos catégorie
            $catname = $DB->get_field('course_categories', 'name', ['id' => $data->categoryid]);
            $subcats = core_course_category::get($data->categoryid)->get_all_children_ids(true);
            $allcats = array_merge([$data->categoryid], $subcats);
            list($insql, $params) = $DB->get_in_or_equal($allcats, SQL_PARAMS_NAMED);
            $courses = $DB->get_records_select('course', "category $insql", $params);
            $rolename = $DB->get_field('role', 'name', ['id' => $data->roleid]);
            if (!$rolename) {
                $rolename = 'Rôle inconnu (ID ' . $data->roleid . ')';
            }

            $msg .= '<li><b>Catégorie :</b> ' . format_string($catname) . ' ';
            $msg .= '(' . count($courses) . ' cours concerné(s)) - <b>Méthode : </b>' . $data->method .' - <b>Rôle : </b>' . $rolename;
            $msg .= '</li>';
        } else {
            // Cas où on n'arrive pas à lire les données
            echo $OUTPUT->notification(
                '<li>Une tâche de désinscription est déjà planifiée (détails non disponibles).<li>',
                'notifywarning'
            );
        }
    }
    $msg .= '</ul>';
    echo $OUTPUT->notification($msg, 'notifywarning');
}

// Prévisualisation
if (!$confirm && $data = $mform->get_data()) {
    $categoryid = $data->categoryid;
    $roleid = $data->roleid;
    $method = $data->method;

    $subcats = core_course_category::get($categoryid)->get_all_children_ids(true);
    $allcats = array_merge([$categoryid], $subcats);
    list($insql, $params) = $DB->get_in_or_equal($allcats, SQL_PARAMS_NAMED);
    $courses = $DB->get_records_select('course', "category $insql", $params);

    echo $OUTPUT->heading(get_string('confirmtitle', 'local_unenrolcategorycourses'));
    echo html_writer::tag('p', 'Catégorie : ' . format_string($DB->get_field('course_categories','name',['id'=>$categoryid])));
    echo html_writer::tag('p', 'Rôle : ' . role_get_name($DB->get_record('role',['id'=>$roleid])));
    echo html_writer::tag('p', 'Méthode : ' . $method);
    echo html_writer::tag('p', 'Nombre de cours concernés : ' . count($courses));

    echo html_writer::start_tag('ul');
    foreach ($courses as $c) {
        echo html_writer::tag('li', format_string($c->fullname));
    }
    echo html_writer::end_tag('ul');

    $confirmurl = new moodle_url('/local/unenrolcategorycourses/index.php', [
        'confirm' => 1,
        'sesskey' => sesskey(),
        'categoryid' => $categoryid,
        'roleid' => $roleid,
        'method' => $method
    ]);
    $cancelurl = new moodle_url('/local/unenrolcategorycourses/index.php');

    echo $OUTPUT->confirm(get_string('confirmquestion', 'local_unenrolcategorycourses'), $confirmurl, $cancelurl);

// Planification tâche ad hoc
} else if ($confirm && $categoryid && $roleid && $method) {
    require_sesskey();

    $task = new \local_unenrolcategorycourses\task\unenrol_category_task();
    $task->set_custom_data([
        'categoryid' => $categoryid,
        'roleid' => $roleid,
        'method' => $method
    ]);
    \core\task\manager::queue_adhoc_task($task);

    echo $OUTPUT->notification('La désinscription a été planifiée. Elle sera exécutée par le cron.', 'notifysuccess');
    echo $OUTPUT->continue_button(new moodle_url('/local/unenrolcategorycourses/index.php'));

} else {
    $mform->display();
}

echo $OUTPUT->footer();
