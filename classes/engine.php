<?php
/**
 * Mroonga search engine
 */
namespace search_mroonga;

defined('MOODLE_INTERNAL') || die;

/**
 * Mroonga fulltext search engine.
 */
class engine extends \core_search\engine {
    /**
     * The required version of the Mroonga.
     */
    const REQUIRED_VERSION = 6.03;
    /**
     * The default tokenizer.
     */
    const DEFAULT_TOKENIZER = 'TokenMecab';
    /**
     * The default normalizer.
     */
    const DEFAULT_NORMALIZER = 'NormalizerMySQLUnicodeCIExceptKanaCIKanaWithVoicedSoundMark';

    /**
     * Is the Mroonga storage engine supported.
     *
     * @global \mysqli_native_moodle_database $DB
     * @return bool
     */
    public static function is_supported() {
        global $DB;
        if ($DB->get_dbfamily() !== 'mysql')
            return false;
        $engines = $DB->get_records_sql('SHOW STORAGE ENGINES');
        if (empty($engines['Mroonga']) || $engines['Mroonga']->support !== 'YES')
            return false;
        $version = $DB->get_field_sql('SELECT @@mroonga_version');
        if ($version < self::REQUIRED_VERSION)
            return false;
        return true;
    }

    /**
     * Does the Mroonga search table exist.
     *
     * @global \mysqli_native_moodle_database $DB
     * @return bool
     */
    public static function table_exists() {
        global $DB;
        $engine = $DB->get_field_sql(
            "SELECT engine
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name   = '{search_mroonga}'");
        return $engine === 'Mroonga';
    }

    /**
     * Creates Mroonga search table.
     *
     * @global \mysqli_native_moodle_database $DB
     */
    public static function create_table() {
        global $DB;
        $parameters = sprintf('tokenizer "%s", normalizer "%s"',
            self::DEFAULT_TOKENIZER, self::DEFAULT_NORMALIZER);
        $DB->execute("
            CREATE TABLE `{search_mroonga}` (
                `id`           BIGINT(10)   NOT NULL AUTO_INCREMENT,
                `itemid`       BIGINT(10)   NOT NULL,
                `title`        VARCHAR(255) NOT NULL,
                `content`      LONGTEXT     NOT NULL,
                `contextid`    BIGINT(10)   NOT NULL,
                `areaid`       VARCHAR(255) NOT NULL,
                `type`         SMALLINT(4)  NOT NULL,
                `courseid`     BIGINT(10)   NOT NULL,
                `owneruserid`  BIGINT(10)   NOT NULL,
                `modified`     BIGINT(10)   NOT NULL DEFAULT 0,
                `userid`       BIGINT(10)   DEFAULT NULL,
                `description1` LONGTEXT,
                `description2` LONGTEXT,
                PRIMARY KEY (`id`),
                INDEX `contextid` (`contextid`),
                INDEX `areaid` (`areaid`),
                INDEX `courseid` (`courseid`),
                INDEX `modified` (`modified`),
                INDEX `itemid` (`itemid`),
                FULLTEXT `ft` (`title`, `content`, `description1`, `description2`) COMMENT '$parameters'
            ) ENGINE=Mroonga DEFAULT CHARSET=utf8");
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Does the system satisfy all the requirements.
     *
     * Should be overwritten if the search engine has any system dependencies
     * that needs to be checked.
     *
     * @return bool
     */
    public function is_installed() {
        return self::is_supported();
    }

    /**
     * Returns the total number of documents available for the most recent call to execute_query.
     *
     * This can be an estimate, but should get more accurate the higher the limited passed to execute_query is.
     * To do that, the engine can use (actual result returned count + count of unchecked documents), or
     * (total possible docs - docs that have been checked and rejected).
     *
     * Engine can limit to manager::MAX_RESULTS if there is cost to determining more.
     * If this cannot be computed in a reasonable way, manager::MAX_RESULTS may be returned.
     *
     * @return int
     */
    public function get_query_total_count() {
        return \core_search\manager::MAX_RESULTS;
    }

    /**
     * Return true if file indexing is supported and enabled. False otherwise.
     *
     * @return bool
     */
    public function file_indexing_enabled() {
        return false;
    }

    /**
     * Clears the current query error value.
     *
     * @return void
     */
    public function clear_query_error() {
        $this->queryerror = null;
    }

    /**
     * Is the server ready to use?
     *
     * This should also check that the search engine configuration is ok.
     *
     * @return true|string Returns true if all good or an error string.
     */
    public function is_server_ready() {
        if (!self::table_exists()) try {
            self::create_table();
        } catch (\moodle_exception $ex) {
            return $ex->getMessage();
        }
        return true;
    }

    /**
     * Adds a document to the search engine.
     *
     * @global \moodle_database $DB
     * @param document $document
     * @param bool     $fileindexing True if file indexing is to be used
     * @return bool    False if the file was skipped or failed, true on success
     */
    public function add_document($document, $fileindexing = false) {
        global $DB;
        $new = (object)$document->export_for_engine();
        $conditions = [
            'areaid' => $new->areaid,
            'itemid' => $new->itemid,
        ];
        if ($id = $DB->get_field('search_mroonga', 'id', $conditions)) {
            $new->id = $id;
            return $DB->update_record('search_mroonga', $new);
        } else {
            return $DB->insert_record('search_mroonga', $new);
        }
    }

    /**
     * Executes the query on the engine.
     *
     * Implementations of this function should check user context array to limit the results to contexts where the
     * user have access. They should also limit the owneruserid field to manger::NO_OWNER_ID or the current user's id.
     * Engines must use area->check_access() to confirm user access.
     *
     * Engines should reasonably attempt to fill up to limit with valid results if they are available.
     *
     * @global \moodle_database $DB
     * @global stdClass $USER
     * @param  stdClass $filters Query and filters to apply.
     * @param  array    $usercontexts Contexts where the user has access. True if the user can access all contexts.
     * @param  int      $limit The maximum number of results to return. If empty, limit to manager::MAX_RESULTS.
     * @return \core_search\document[] Results or false if no results
     */
    public function execute_query($filters, $usercontexts, $limit = 0) {
        global $DB, $USER;

        $criteria = [ 'MATCH(title, content, description1, description2) AGAINST(:q IN BOOLEAN MODE)' ];
        $params = [ 'q' => "*D+ {$filters->q}" ];
        if (!empty($filters->title)) {
            $criteria[] = $DB->sql_like('title', ':title', false, false);
            $params['title'] = "%{$filters->title}%";
        }
        if (!empty($filters->areaids)) {
            list ($q, $p) = $DB->get_in_or_equal($filters->areaids, SQL_PARAMS_NAMED, 'areaid');
            $criteria[] = "areaid $q";
            $params += $p;
        }
        if (!empty($filters->courseids)) {
            list ($q, $p) = $DB->get_in_or_equal($filters->courseids, SQL_PARAMS_NAMED, 'courseid');
            $criteria[] = "courseid $q";
            $params += $p;
        }
        if (!empty($filters->timestart)) {
            $criteria[] = ':timestart <= modified';
            $params['timestart'] = $filters->timestart;
        }
        if (!empty($filters->timeend)) {
            $criteria[] = 'modified <= :timeend';
            $params['timeend'] = $filters->timeend;
        }
        if ($usercontexts && is_array($usercontexts)) {
            $allcontexts = [];
            foreach ($usercontexts as $areacontexts)
                foreach ($areacontexts as $contextid)
                    $allcontexts[$contextid] = $contextid;
            if (empty($allcontexts))
                return [];
            list ($q, $p) = $DB->get_in_or_equal($allcontexts, SQL_PARAMS_NAMED, 'contextid');
            $criteria[] = "contextid $q";
            $params += $p;
        }
        $select = implode(' AND ', $criteria);
        $records = $DB->get_records_select('search_mroonga', $select, $params, '', '*', 0, $limit);
        $documents = [];
        foreach ($records as $record) {
            if ($record->owneruserid != \core_search\manager::NO_OWNER_ID && $record->owneruserid != $USER->id)
                continue;
            $searcharea = $this->get_search_area($record->areaid);
            if (!$searcharea)
                continue;
            $access = $searcharea->check_access($record->itemid);
            switch ($access) {
            case \core_search\manager::ACCESS_GRANTED:
                $documents[] = $this->to_document($searcharea, (array)$record);
                break;
            }
        }
        return $documents;
    }

    /**
     * Delete all documents.
     *
     * @global \moodle_database $DB
     * @param string $areaid To filter by area
     * @return void
     */
    public function delete($areaid = null) {
        global $DB;
        if ($areaid) {
            $DB->delete_records('search_mroonga', [ 'areaid' => $areaid ]);
        } else {
            $DB->delete_records('search_mroonga');
        }
    }
}
