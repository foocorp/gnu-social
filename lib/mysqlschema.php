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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class representing the database schema
 *
 * A class representing the database schema. Can be used to
 * manipulate the schema -- especially for plugins and upgrade
 * utilities.
 *
 * @category Database
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class MysqlSchema extends Schema
{
    static $_single = null;
    protected $conn = null;


    /**
     * Main public entry point. Use this to get
     * the singleton object.
     *
     * @return Schema the (single) Schema object
     */

    static function get()
    {
        if (empty(self::$_single)) {
            self::$_single = new Schema();
        }
        return self::$_single;
    }

    /**
     * Returns a TableDef object for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $name Name of the table to get
     *
     * @return TableDef tabledef for that table.
     * @throws SchemaTableMissingException
     */

    public function getTableDef($name)
    {
        $query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS " .
                 "WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s'";
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, $schema, $name);
        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }
        if ($res->numRows() == 0) {
            $res->free();
            throw new SchemaTableMissingException("No such table: $name");
        }

        $td = new TableDef();

        $td->name    = $name;
        $td->columns = array();

        $row = array();

        while ($res->fetchInto($row, DB_FETCHMODE_ASSOC)) {

            $cd = new ColumnDef();

            $cd->name = $row['COLUMN_NAME'];

            $packed = $row['COLUMN_TYPE'];

            if (preg_match('/^(\w+)\((\d+)\)$/', $packed, $match)) {
                $cd->type = $match[1];
                $cd->size = $match[2];
            } else {
                $cd->type = $packed;
            }

            $cd->nullable = ($row['IS_NULLABLE'] == 'YES') ? true : false;
            $cd->key      = $row['COLUMN_KEY'];
            $cd->default  = $row['COLUMN_DEFAULT'];
            $cd->extra    = $row['EXTRA'];

            // Autoincrement is stuck into the extra column.
            // Pull it out so we don't accidentally mod it every time...
            $extra = preg_replace('/(^|\s)auto_increment(\s|$)/i', '$1$2', $cd->extra);
            if ($extra != $cd->extra) {
                $cd->extra = trim($extra);
                $cd->auto_increment = true;
            }

            // mysql extensions -- not (yet) used by base class
            $cd->charset  = $row['CHARACTER_SET_NAME'];
            $cd->collate  = $row['COLLATION_NAME'];

            $td->columns[] = $cd;
        }
        $res->free();

        return $td;
    }

    /**
     * Pull the given table properties from INFORMATION_SCHEMA.
     * Most of the good stuff is MySQL extensions.
     *
     * @return array
     * @throws Exception if table info can't be looked up
     */

    function getTableProperties($table, $props)
    {
        $query = "SELECT %s FROM INFORMATION_SCHEMA.TABLES " .
                 "WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s'";
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, implode(',', $props), $schema, $table);
        $res = $this->conn->query($sql);

        $row = array();
        $ok = $res->fetchInto($row, DB_FETCHMODE_ASSOC);
        $res->free();
        
        if ($ok) {
            return $row;
        } else {
            throw new SchemaTableMissingException("No such table: $table");
        }
    }

    /**
     * Gets a ColumnDef object for a single column.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $table  name of the table
     * @param string $column name of the column
     *
     * @return ColumnDef definition of the column or null
     *                   if not found.
     */

    public function getColumnDef($table, $column)
    {
        $td = $this->getTableDef($table);

        foreach ($td->columns as $cd) {
            if ($cd->name == $column) {
                return $cd;
            }
        }

        return null;
    }

    /**
     * Creates a table with the given names and columns.
     *
     * @param string $name    Name of the table
     * @param array  $columns Array of ColumnDef objects
     *                        for new table.
     *
     * @return boolean success flag
     */

    public function createTable($name, $columns)
    {
        $uniques = array();
        $primary = array();
        $indices = array();

        $sql = "CREATE TABLE $name (\n";

        for ($i = 0; $i < count($columns); $i++) {

            $cd =& $columns[$i];

            if ($i > 0) {
                $sql .= ",\n";
            }

            $sql .= $this->_columnSql($cd);
        }

        $idx = $this->_indexList($columns);

        if ($idx['primary']) {
            $sql .= ",\nconstraint primary key (" . implode(',', $idx['primary']) . ")";
        }

        foreach ($idx['uniques'] as $u) {
            $key = $this->_uniqueKey($name, $u);
            $sql .= ",\nunique index $key ($u)";
        }

        foreach ($idx['indices'] as $i) {
            $key = $this->_key($name, $i);
            $sql .= ",\nindex $key ($i)";
        }

        $sql .= ") ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin; ";

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Look over a list of column definitions and list up which
     * indices will be present
     */
    private function _indexList(array $columns)
    {
        $list = array('uniques' => array(),
                      'primary' => array(),
                      'indices' => array());
        foreach ($columns as $cd) {
            switch ($cd->key) {
            case 'UNI':
                $list['uniques'][] = $cd->name;
                break;
            case 'PRI':
                $list['primary'][] = $cd->name;
                break;
            case 'MUL':
                $list['indices'][] = $cd->name;
                break;
            }
        }
        return $list;
    }

    /**
     * Get the unique index key name for a given column on this table
     */
    function _uniqueKey($tableName, $columnName)
    {
        return $this->_key($tableName, $columnName);
    }

    /**
     * Get the index key name for a given column on this table
     */
    function _key($tableName, $columnName)
    {
        return "{$tableName}_{$columnName}_idx";
    }

    /**
     * Drops a table from the schema
     *
     * Throws an exception if the table is not found.
     *
     * @param string $name Name of the table to drop
     *
     * @return boolean success flag
     */

    public function dropTable($name)
    {
        $res = $this->conn->query("DROP TABLE $name");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Adds an index to a table.
     *
     * If no name is provided, a name will be made up based
     * on the table name and column names.
     *
     * Throws an exception on database error, esp. if the table
     * does not exist.
     *
     * @param string $table       Name of the table
     * @param array  $columnNames Name of columns to index
     * @param string $name        (Optional) name of the index
     *
     * @return boolean success flag
     */

    public function createIndex($table, $columnNames, $name=null)
    {
        if (!is_array($columnNames)) {
            $columnNames = array($columnNames);
        }

        if (empty($name)) {
            $name = "{$table}_".implode("_", $columnNames)."_idx";
        }

        $res = $this->conn->query("ALTER TABLE $table ".
                                   "ADD INDEX $name (".
                                   implode(",", $columnNames).")");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Drops a named index from a table.
     *
     * @param string $table name of the table the index is on.
     * @param string $name  name of the index
     *
     * @return boolean success flag
     */

    public function dropIndex($table, $name)
    {
        $res = $this->conn->query("ALTER TABLE $table DROP INDEX $name");

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Adds a column to a table
     *
     * @param string    $table     name of the table
     * @param ColumnDef $columndef Definition of the new
     *                             column.
     *
     * @return boolean success flag
     */

    public function addColumn($table, $columndef)
    {
        $sql = "ALTER TABLE $table ADD COLUMN " . $this->_columnSql($columndef);

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Modifies a column in the schema.
     *
     * The name must match an existing column and table.
     *
     * @param string    $table     name of the table
     * @param ColumnDef $columndef new definition of the column.
     *
     * @return boolean success flag
     */

    public function modifyColumn($table, $columndef)
    {
        $sql = "ALTER TABLE $table MODIFY COLUMN " .
          $this->_columnSql($columndef);

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Drops a column from a table
     *
     * The name must match an existing column.
     *
     * @param string $table      name of the table
     * @param string $columnName name of the column to drop
     *
     * @return boolean success flag
     */

    public function dropColumn($table, $columnName)
    {
        $sql = "ALTER TABLE $table DROP COLUMN $columnName";

        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Ensures that a table exists with the given
     * name and the given column definitions.
     *
     * If the table does not yet exist, it will
     * create the table. If it does exist, it will
     * alter the table to match the column definitions.
     *
     * @param string $tableName name of the table
     * @param array  $columns   array of ColumnDef
     *                          objects for the table
     *
     * @return boolean success flag
     */

    public function ensureTable($tableName, $columns)
    {
        // XXX: DB engine portability -> toilet

        try {
            $td = $this->getTableDef($tableName);
        } catch (SchemaTableMissingException $e) {
            return $this->createTable($tableName, $columns);
        }

        $cur = $this->_names($td->columns);
        $new = $this->_names($columns);

        $dropIndex  = array();
        $toadd      = array_diff($new, $cur);
        $todrop     = array_diff($cur, $new);
        $same       = array_intersect($new, $cur);
        $tomod      = array();
        $addIndex   = array();
        $tableProps = array();

        foreach ($same as $m) {
            $curCol = $this->_byName($td->columns, $m);
            $newCol = $this->_byName($columns, $m);

            if (!$newCol->equals($curCol)) {
                $tomod[] = $newCol->name;
                continue;
            }

            // Earlier versions may have accidentally left tables at default
            // charsets which might be latin1 or other freakish things.
            if ($this->_isString($curCol)) {
                if ($curCol->charset != 'utf8') {
                    $tomod[] = $newCol->name;
                    continue;
                }
            }
        }

        // Find any indices we have to change...
        $curIdx = $this->_indexList($td->columns);
        $newIdx = $this->_indexList($columns);

        if ($curIdx['primary'] != $newIdx['primary']) {
            if ($curIdx['primary']) {
                $dropIndex[] = 'drop primary key';
            }
            if ($newIdx['primary']) {
                $keys = implode(',', $newIdx['primary']);
                $addIndex[] = "add constraint primary key ($keys)";
            }
        }

        $dropUnique = array_diff($curIdx['uniques'], $newIdx['uniques']);
        $addUnique = array_diff($newIdx['uniques'], $curIdx['uniques']);
        foreach ($dropUnique as $columnName) {
            $dropIndex[] = 'drop key ' . $this->_uniqueKey($tableName, $columnName);
        }
        foreach ($addUnique as $columnName) {
            $addIndex[] = 'add constraint unique key ' . $this->_uniqueKey($tableName, $columnName) . " ($columnName)";;
        }

        $dropMultiple = array_diff($curIdx['indices'], $newIdx['indices']);
        $addMultiple = array_diff($newIdx['indices'], $curIdx['indices']);
        foreach ($dropMultiple as $columnName) {
            $dropIndex[] = 'drop key ' . $this->_key($tableName, $columnName);
        }
        foreach ($addMultiple as $columnName) {
            $addIndex[] = 'add key ' . $this->_key($tableName, $columnName) . " ($columnName)";
        }

        // Check for table properties: make sure we're using a sane
        // engine type and charset/collation.
        // @fixme make the default engine configurable?
        $oldProps = $this->getTableProperties($tableName, array('ENGINE', 'TABLE_COLLATION'));
        if (strtolower($oldProps['ENGINE']) != 'innodb') {
            $tableProps['ENGINE'] = 'InnoDB';
        }
        if (strtolower($oldProps['TABLE_COLLATION']) != 'utf8_bin') {
            $tableProps['DEFAULT CHARSET'] = 'utf8';
            $tableProps['COLLATE'] = 'utf8_bin';
        }

        if (count($dropIndex) + count($toadd) + count($todrop) + count($tomod) + count($addIndex) + count($tableProps) == 0) {
            // nothing to do
            return true;
        }

        // For efficiency, we want this all in one
        // query, instead of using our methods.

        $phrase = array();

        foreach ($dropIndex as $indexSql) {
            $phrase[] = $indexSql;
        }

        foreach ($toadd as $columnName) {
            $cd = $this->_byName($columns, $columnName);

            $phrase[] = 'ADD COLUMN ' . $this->_columnSql($cd);
        }

        foreach ($todrop as $columnName) {
            $phrase[] = 'DROP COLUMN ' . $columnName;
        }

        foreach ($tomod as $columnName) {
            $cd = $this->_byName($columns, $columnName);

            $phrase[] = 'MODIFY COLUMN ' . $this->_columnSql($cd);
        }

        foreach ($addIndex as $indexSql) {
            $phrase[] = $indexSql;
        }

        foreach ($tableProps as $key => $val) {
            $phrase[] = "$key=$val";
        }

        $sql = 'ALTER TABLE ' . $tableName . ' ' . implode(', ', $phrase);

        common_log(LOG_DEBUG, __METHOD__ . ': ' . $sql);
        $res = $this->conn->query($sql);

        if (PEAR::isError($res)) {
            throw new Exception($res->getMessage());
        }

        return true;
    }

    /**
     * Returns the array of names from an array of
     * ColumnDef objects.
     *
     * @param array $cds array of ColumnDef objects
     *
     * @return array strings for name values
     */

    private function _names($cds)
    {
        $names = array();

        foreach ($cds as $cd) {
            $names[] = $cd->name;
        }

        return $names;
    }

    /**
     * Get a ColumnDef from an array matching
     * name.
     *
     * @param array  $cds  Array of ColumnDef objects
     * @param string $name Name of the column
     *
     * @return ColumnDef matching item or null if no match.
     */

    private function _byName($cds, $name)
    {
        foreach ($cds as $cd) {
            if ($cd->name == $name) {
                return $cd;
            }
        }

        return null;
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param ColumnDef $cd column to create
     *
     * @return string correct SQL for that column
     */

    private function _columnSql($cd)
    {
        $sql = "{$cd->name} ";

        if (!empty($cd->size)) {
            $sql .= "{$cd->type}({$cd->size}) ";
        } else {
            $sql .= "{$cd->type} ";
        }

        if ($this->_isString($cd)) {
            $sql .= " CHARACTER SET utf8 ";
        }

        if (!empty($cd->default)) {
            $sql .= "default {$cd->default} ";
        } else {
            $sql .= ($cd->nullable) ? "null " : "not null ";
        }
        
        if (!empty($cd->auto_increment)) {
            $sql .= " auto_increment ";
        }

        if (!empty($cd->extra)) {
            $sql .= "{$cd->extra} ";
        }

        return $sql;
    }

    /**
     * Is this column a string type?
     */
    private function _isString(ColumnDef $cd)
    {
        $strings = array('char', 'varchar', 'text');
        return in_array(strtolower($cd->type), $strings);
    }
}
