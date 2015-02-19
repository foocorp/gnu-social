<?php

/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Database schema utilities
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Database
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class SchemaUpdater
{
    public function __construct($schema)
    {
        $this->schema = $schema;
        $this->checksums = $this->getChecksums();
    }

    /**
     * @param string $tableName
     * @param array $tableDef
     */
    public function register($tableName, array $tableDef)
    {
        // Check if the table we're registering is related to a Managed_DataObject
        if (is_a(ucfirst($tableName), 'Managed_DataObject', true)) {
            call_user_func("{$tableName}::beforeSchemaUpdate");
        }

        $this->tables[$tableName] = $tableDef;
    }

    /**
     * Go ping em!
     *
     * @fixme handle tables that belong on different database servers...?
     */
    public function checkSchema()
    {
        $checksums = $this->checksums;
        foreach ($this->tables as $table => $def) {
            $checksum = $this->checksum($def);
            if (empty($checksums[$table])) {
                common_log(LOG_DEBUG, "No previous schema_version for $table: updating to $checksum");
            } else if ($checksums[$table] == $checksum) {
                common_log(LOG_DEBUG, "Last schema_version for $table up to date: $checksum");
                continue;
            } else {
                common_log(LOG_DEBUG, "Last schema_version for $table is {$checksums[$table]}: updating to $checksum");
            }
            //$this->conn->query('BEGIN');
            $this->schema->ensureTable($table, $def);
            $this->saveChecksum($table, $checksum);
            //$this->conn->commit();
        }
    }

    /**
     * Calculate a checksum for this table definition array.
     *
     * @param array $def
     * @return string
     */
    public function checksum(array $def)
    {
        $flat = serialize($def);
        return sha1($flat);
    }

    /**
     * Pull all known table checksums into an array for easy lookup.
     *
     * @return array: associative array of table names to checksum strings
     */
    protected function getChecksums()
    {
        $checksums = array();

        PEAR::pushErrorHandling(PEAR_ERROR_EXCEPTION);
        try {
            $sv = new Schema_version();
            $sv->find();
            while ($sv->fetch()) {
                $checksums[$sv->table_name] = $sv->checksum;
            }

            return $checksums;
        } catch (Exception $e) {
            // no dice!
            common_log(LOG_DEBUG, "Possibly schema_version table doesn't exist yet.");
        }
        PEAR::popErrorHandling();

        return $checksums;
    }

    /**
     * Save or update current available checksums.
     *
     * @param string $table
     * @param string $checksum
     */
    protected function saveChecksum($table, $checksum)
    {
        PEAR::pushErrorHandling(PEAR_ERROR_EXCEPTION);
        try {
            $sv = new Schema_version();
            $sv->table_name = $table;
            $sv->checksum = $checksum;
            $sv->modified = common_sql_now();
            if (isset($this->checksums[$table])) {
                $sv->update();
            } else {
                $sv->insert();
            }
        } catch (Exception $e) {
            // no dice!
            common_log(LOG_DEBUG, "Possibly schema_version table doesn't exist yet.");
        }
        PEAR::popErrorHandling();
        $this->checksums[$table] = $checksum;
    }
}
