<?php
/**
 * @package   search_mroonga
 * @copyright 2018 MALU {@link https://github.com/andantissimo}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace search_mroonga;

defined('MOODLE_INTERNAL') || die;

class document extends \core_search\document {
    /**
     * Formats the timestamp according to the search engine needs.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_time_for_engine($timestamp) {
        return (string)$timestamp;
    }

    /**
     * Formats the string according to the search engine needs.
     *
     * @param string $string
     * @return string
     */
    public static function format_string_for_engine($string) {
        return $string;
    }

    /**
     * Returns a timestamp from the value stored in the search engine.
     *
     * @param string $time
     * @return int
     */
    public static function import_time_from_engine($time) {
        return (int)$time;
    }

    /**
     * Overwritten to use markdown format as we use markdown for solr highlighting.
     *
     * @return int
     */
    protected function get_text_format() {
        return FORMAT_HTML;
    }

    /**
     * Formats a text string coming from the search engine.
     *
     * @param  string $text Text to format
     * @return string HTML text to be renderer
     */
    protected function format_text($text) {
        return $text;
    }

    /**
     * Apply any defaults to unset fields before export. Called after document building, but before export.
     *
     * Sub-classes of this should make sure to call parent::apply_defaults().
     */
    protected function apply_defaults() {
        parent::apply_defaults();
    }
}
