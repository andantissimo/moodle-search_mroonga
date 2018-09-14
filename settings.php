<?php
/**
 * @package   search_mroonga
 * @copyright 2018 MALU {@link https://github.com/andantissimo}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use search_mroonga as mrn;

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    if (!during_initial_install()) {
        if (!mrn\engine::is_supported()) {
            $settings->add(
                new admin_setting_heading('search_mroonga_settings',
                    '', new lang_string('notsupported', 'search_mroonga', mrn\engine::REQUIRED_VERSION))
                );
        } else {
            $settings->add(new mrn\admin_setting_configparser('tokenizer'));
            $settings->add(new mrn\admin_setting_configparser('normalizer'));
        }
    }
}
