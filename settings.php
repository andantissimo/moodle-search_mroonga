<?php
/**
 * Mroonga search engine
 */

use search_mroonga as mrn;

defined('MOODLE_INTERNAL') || die;

/* @var $ADMIN admin_root */
/* @var $settings admin_settingpage */

if ($ADMIN->fulltree) {
    if (!during_initial_install()) {
        if (!mrn\engine::is_supported()) {
            $settings->add(
                new admin_setting_heading('search_mroonga_settings',
                    '', new lang_string('notsupported', 'search_mroonga', mrn\engine::REQUIRED_VERSION))
                );
        } else {
            $settings->add(
                new mrn\admin_setting_configparameter('tokenizer')
                );
            $settings->add(
                new mrn\admin_setting_configparameter('normalizer')
                );
        }
    }
}
