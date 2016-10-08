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
     * @global \mysqli_native_moodle_database $DB
     * @param string $name
     */
    public function __construct($name) {
        global $DB;
        if (self::$supported === null) {
            self::$supported = [ 'tokenizer' => [], 'normalizer' => [] ];
            $list = $DB->get_record_sql(
                "SELECT mroonga_command('tokenizer_list')  AS tokenizers,
                        mroonga_command('normalizer_list') AS normalizers");
            foreach (array_column(json_decode($list->tokenizers, true), 'name') as $value) {
                if (in_array($value, [ 'TokenDelimit', 'TokenDelimitNull', 'TokenRegexp' ]))
                    continue;
                self::$supported['tokenizer'][$value] = preg_replace('/^Token/', '', $value);
            }
            foreach (array_column(json_decode($list->normalizers, true), 'name') as $value) {
                self::$supported['normalizer'][$value] = preg_replace('/^Normalizer/', '', $value);
            }
        }
        if (self::$current === null) {
            self::$current = [ 'tokenizer' => null, 'normalizer' => null ];
            $comment = $DB->get_field_sql(
                "SELECT index_comment
                   FROM information_schema.statistics
                  WHERE table_schema = DATABASE()
                    AND table_name   = '{search_mroonga}'
                    AND column_name  = 'content'");
            $matches = [];
            if (preg_match('/tokenizer\s+"([^"]*)"/i', $comment, $matches))
                self::$current['tokenizer'] = $matches[1];
            if (preg_match('/normalizer\s+"([^"]*)"/i', $comment, $matches))
                self::$current['normalizer'] = $matches[1];
        }
        parent::__construct("search_mroonga/$name",
            new \lang_string("{$name}", 'search_mroonga'),
            new \lang_string("{$name}_desc", 'search_mroonga'),
            self::$defaults[$name],
            self::$supported[$name]);
    }

    /**
     * Returns the config if possible
     *
     * @global
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
        global $DB;

        // rejects unsupported value
        if (!isset(self::$supported[$name][$value]))
            return false;

        if (self::$written === null) {
            self::$written = [ 'tokenizer' => null, 'normalizer' => null ];
        }
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
            $DB->execute(
                "ALTER TABLE {search_mroonga}
                  DROP INDEX ft,
                ADD FULLTEXT ft (title, content, description1, description2)
                     COMMENT 'tokenizer \"$tokenizer\", normalizer \"$normalizer\"'");
        }

        // purge cache
        self::$current = null;

        return true;
    }
}
