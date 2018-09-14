<?php
/**
 * @package   search_mroonga
 * @copyright 2018 MALU {@link https://github.com/andantissimo}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace search_mroonga;

defined('MOODLE_INTERNAL') || die;

class admin_setting_configparser extends \admin_setting_configselect {
    /**
     * @var string[]
     */
    private static $default = [
        'tokenizer'  => engine::DEFAULT_TOKENIZER,
        'normalizer' => engine::DEFAULT_NORMALIZER,
    ];

    /**
     * @var string[]
     */
    private static $supported = null;

    /**
     * @var string[]
     */
    private static $current = null;

    /**
     * @var string[]
     */
    private static $writing = null;

    /**
     * Constructor
     *
     * @param string $name
     */
    public function __construct($name) {
        if (self::$supported === null)
            self::$supported = engine::get_supported_parsers();
        if (self::$current === null)
            self::$current = engine::get_current_parser();
        if (self::$writing === null)
            self::$writing = [ 'tokenizer' => null, 'normalizer' => null ];
        parent::__construct("search_mroonga/$name",
            new \lang_string("{$name}", 'search_mroonga'),
            new \lang_string("{$name}_desc", 'search_mroonga'),
            self::$default[$name],
            self::$supported[$name]);
    }

    /**
     * Returns the config if possible
     *
     * @return mixed returns config if successful else null
     */
    public function config_read($name) {
        return self::$current[$name];
    }

    /**
     * Used to set a config pair and log change
     *
     * @param string $name
     * @param mixed $value Gets converted to string if not null
     * @return bool Write setting to config table
     */
    public function config_write($name, $value) {
        // rejects unsupported value
        if (!isset(self::$supported[$name][$value]))
            return false;

        self::$writing[$name] = $value;

        if (in_array(null, self::$writing, true))
            return true;

        if (self::$writing != self::$current) {
            if (!engine::table_exists()) {
                engine::create_table(self::$writing['tokenizer'], self::$writing['normalizer']);
            } else {
                // altering index may take a long time if many records exist.
                \core_php_time_limit::raise();
                engine::alter_table(self::$writing['tokenizer'], self::$writing['normalizer']);
            }
        }

        self::$current = self::$writing;

        return true;
    }
}
