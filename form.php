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

require_once("$CFG->libdir/formslib.php");

class local_unenrolcategorycourses_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $options = core_course_category::make_categories_list();
        $mform->addElement('select', 'categoryid', get_string('category'), $options);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required');

        $roles = role_fix_names(get_all_roles(), context_system::instance());
        $roleoptions = [];
        foreach ($roles as $role) {
            $roleoptions[$role->id] = $role->localname;
        }
        $mform->addElement('select', 'roleid', get_string('role'), $roleoptions);
        $mform->setType('roleid', PARAM_INT);
        $mform->addRule('roleid', null, 'required');

        $mform->addElement('select', 'method', 'Méthode', [
            'manual' => 'Inscription manuelle',
            'self'   => 'Auto-inscription'
        ]);
        $mform->setType('method', PARAM_ALPHA);
        $mform->addRule('method', null, 'required');

        $this->add_action_buttons(true, 'Planifier la désinscription');
    }
}
