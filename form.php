<?php
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
