<?php
/**
 * Mroonga search engine
 */
namespace search_mroonga;

defined('MOODLE_INTERNAL') || die;

/**
 * Select one parameter from Mroonga index options.
 */
class admin_setting_configparameter extends \admin_setting_configselect {
    /**
     * @var string[]
     */
    private static $defaults = [
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
    private static $written = null;

    /**
     * Constructor
     *
     * @param string $name
     */
    public function __construct($name) {
        if (self::$supported === null)
            self::$supported = engine::get_supported_parameters();
        if (self::$current === null)
            self::$current = engine::get_current_parameters();
        parent::__construct("search_mroonga/$name",
            new \lang_string("{$name}", 'search_mroonga'),
            new \lang_string("{$name}_desc", 'search_mroonga'),
            self::$defaults[$name],
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

        if (self::$written === null)
            self::$written = [ 'tokenizer' => null, 'normalizer' => null ];
        self::$written[$name] = $value;

        $tokenizer  = self::$written['tokenizer'];
        $normalizer = self::$written['normalizer'];
        if ($tokenizer === null || $normalizer === null)
            return true;

        // alter is not necessary.
        if ($tokenizer  === self::$current['tokenizer'] &&
            $normalizer === self::$current['normalizer']) {
            return true;
        }

        if (!engine::table_exists()) {
            engine::create_table($tokenizer, $normalizer);
        } else {
            // altering index may take a long time if many records exist.
            \core_php_time_limit::raise();
            engine::alter_table($tokenizer, $normalizer);
        }

        // purge cache
        self::$current = null;

        return true;
    }
}
