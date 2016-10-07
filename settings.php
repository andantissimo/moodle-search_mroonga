<?php

defined('MOODLE_INTERNAL') || die;

/* @var $DB mysqli_native_moodle_database */
/* @var $PAGE moodle_page */
/* @var $ADMIN admin_root */
/* @var $settings admin_settingpage */

if ($ADMIN->fulltree) {
    if (!during_initial_install()) {
        if (!search_mroonga\engine::is_supported()) {
            $settings->add(
                new admin_setting_heading('search_mroonga_settings',
                    '', new lang_string('notsupported', 'search_mroonga'))
                );
        } else {
            $settings->add(
                new search_mroonga\admin_setting_configparameter('tokenizer')
                );
            $settings->add(
                new search_mroonga\admin_setting_configparameter('normalizer')
                );
        }
    }
}
