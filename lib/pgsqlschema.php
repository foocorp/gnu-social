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
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PgsqlSchema extends Schema
{

    /**
     * Returns a table definition array for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $table Name of the table to get
     *
     * @return array tabledef for that table.
     */

    public function getTableDef($table)
    {
        $def = array();
        $hasKeys = false;

        // Pull column data from INFORMATION_SCHEMA
        $columns = $this->fetchMetaInfo($table, 'columns', 'ordinal_position');
        if (count($columns) == 0) {
            throw new SchemaTableMissingException("No such table: $table");
        }

        // We'll need to match up fields by ordinal reference
        $orderedFields = array();

        foreach ($columns as $row) {

            $name = $row['column_name'];
            $orderedFields[$row['ordinal_position']] = $name;

            $field = array();
            $field['type'] = $row['udt_name'];

            if ($type == 'char' || $type == 'varchar') {
                if ($row['character_maximum_length'] !== null) {
                    $field['length'] = intval($row['character_maximum_length']);
                }
            }
            if ($type == 'numeric') {
                // Other int types may report these values, but they're irrelevant.
                // Just ignore them!
                if ($row['numeric_precision'] !== null) {
                    $field['precision'] = intval($row['numeric_precision']);
                }
                if ($row['numeric_scale'] !== null) {
                    $field['scale'] = intval($row['numeric_scale']);
                }
            }
            if ($row['is_nullable'] == 'NO') {
                $field['not null'] = true;
            }
            if ($row['column_default'] !== null) {
                $field['default'] = $row['column_default'];
                if ($this->isNumericType($type)) {
                    $field['default'] = intval($field['default']);
                }
            }

            $def['fields'][$name] = $field;
        }

        // Pulling index info from pg_class & pg_index
        // This can give us primary & unique key info, but not foreign key constraints
        // so we exclude them and pick them up later.
        $indexInfo = $this->getIndexInfo($table);
        foreach ($indexInfo as $row) {
            $keyName = $row['key_name'];

            // Dig the column references out!
            //
            // These are inconvenient arrays with partial references to the
            // pg_att table, but since we've already fetched up the column
            // info on the current table, we can look those up locally.
            $cols = array();
            $colPositions = explode(' ', $row['indkey']);
            foreach ($colPositions as $ord) {
                if ($ord == 0) {
                    $cols[] = 'FUNCTION'; // @fixme
                } else {
                    $cols[] = $orderedFields[$ord];
                }
            }

            $def['indexes'][$keyName] = $cols;
        }

        // Pull constraint data from INFORMATION_SCHEMA:
        // Primary key, unique keys, foreign keys
        $keyColumns = $this->fetchMetaInfo($table, 'key_column_usage', 'constraint_name,ordinal_position');
        $keys = array();

        foreach ($keyColumns as $row) {
            $keyName = $row['constraint_name'];
            $keyCol = $row['column_name'];
            if (!isset($keys[$keyName])) {
                $keys[$keyName] = array();
            }
            $keys[$keyName][] = $keyCol;
        }

        foreach ($keys as $keyName => $cols) {
            // name hack -- is this reliable?
            if ($keyName == "{$table}_pkey") {
                $def['primary key'] = $cols;
            } else if (preg_match("/^{$table}_(.*)_fkey$/", $keyName, $matches)) {
                $fkey = $this->getForeignKeyInfo($table, $keyName);
                $colMap = array_combine($cols, $fkey['col_names']);
                $def['foreign keys'][$keyName] = array($fkey['table_name'], $colMap);
            } else {
                $def['unique keys'][$keyName] = $cols;
            }
        }
        return $def;
    }

    /**
     * Pull some INFORMATION.SCHEMA data for the given table.
     *
     * @param string $table
     * @return array of arrays
     */
    function fetchMetaInfo($table, $infoTable, $orderBy=null)
    {
        $query = "SELECT * FROM information_schema.%s " .
                 "WHERE table_name='%s'";
        $sql = sprintf($query, $infoTable, $table);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return $this->fetchQueryData($sql);
    }

    /**
     * Pull some PG-specific index info
     * @param string $table
     * @return array of arrays
     */
    function getIndexInfo($table)
    {
        $query = 'SELECT ' .
                 '(SELECT relname FROM pg_class WHERE oid=indexrelid) AS key_name, ' .
                 '* FROM pg_index ' .
                 'WHERE indrelid=(SELECT oid FROM pg_class WHERE relname=\'%s\') ' .
                 'AND indisprimary=\'f\' AND indisunique=\'f\' ' .
                 'ORDER BY indrelid, indexrelid';
        $sql = sprintf($query, $table);
        return $this->fetchQueryData($sql);
    }

    /**
     * Column names from the foreign table can be resolved with a call to getTableColumnNames()
     * @param <type> $table
     * @return array array of rows with keys: fkey_name, table_name, table_id, col_names (array of strings)
     */
    function getForeignKeyInfo($table, $constraint_name)
    {
        // In a sane world, it'd be easier to query the column names directly.
        // But it's pretty hard to work with arrays such as col_indexes in direct SQL here.
        $query = 'SELECT ' .
                 '(SELECT relname FROM pg_class WHERE oid=confrelid) AS table_name, ' .
                 'confrelid AS table_id, ' .
                 '(SELECT indkey FROM pg_index WHERE indexrelid=conindid) AS col_indexes ' .
                 'FROM pg_constraint ' .
                 'WHERE conrelid=(SELECT oid FROM pg_class WHERE relname=\'%s\') ' .
                 'AND conname=\'%s\' ' .
                 'AND contype=\'f\'';
        $sql = sprintf($query, $table, $constraint_name);
        $data = $this->fetchQueryData($sql);
        if (count($data) < 1) {
            throw new Exception("Could not find foreign key " . $constraint_name . " on table " . $table);
        }

        $row = $data[0];
        return array(
            'table_name' => $row['table_name'],
            'col_names' => $this->getTableColumnNames($row['table_id'], $row['col_indexes'])
        );
    }

    /**
     *
     * @param int $table_id
     * @param array $col_indexes
     * @return array of strings
     */
    function getTableColumnNames($table_id, $col_indexes)
    {
        $indexes = array_map('intval', explode(' ', $col_indexes));
        $query = 'SELECT attnum AS col_index, attname AS col_name ' .
                 'FROM pg_attribute where attrelid=%d ' .
                 'AND attnum IN (%s)';
        $sql = sprintf($query, $table_id, implode(',', $indexes));
        $data = $this->fetchQueryData($sql);

        $byId = array();
        foreach ($data as $row) {
            $byId[$row['col_index']] = $row['col_name'];
        }

        $out = array();
        foreach ($indexes as $id) {
            $out[] = $byId[$id];
        }
        return $out;
    }

    /**
     * Translate the (mostly) mysql-ish column types into somethings more standard
     * @param string column type
     *
     * @return string postgres happy column type
     */
    private function _columnTypeTranslation($type) {
      $map = array(
      'datetime' => 'timestamp',
      );
      if(!empty($map[$type])) {
        return $map[$type];
      }
      return $type;
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param array $cd column to create
     *
     * @return string correct SQL for that column
     */

    function columnSql(array $cd)
    {
        $line = array();
        $line[] = parent::columnSql($cd);

        /*
        if ($table['foreign keys'][$name]) {
            foreach ($table['foreign keys'][$name] as $foreignTable => $foreignColumn) {
                $line[] = 'references';
                $line[] = $this->quoteIdentifier($foreignTable);
                $line[] = '(' . $this->quoteIdentifier($foreignColumn) . ')';
            }
        }
        */

        return implode(' ', $line);
    }

    /**
     * Append phrase(s) to an array of partial ALTER TABLE chunks in order
     * to alter the given column from its old state to a new one.
     *
     * @param array $phrase
     * @param string $columnName
     * @param array $old previous column definition as found in DB
     * @param array $cd current column definition
     */
    function appendAlterModifyColumn(array &$phrase, $columnName, array $old, array $cd)
    {
        $prefix = 'ALTER COLUMN ' . $this->quoteIdentifier($columnName) . ' ';

        $oldType = $this->mapType($old);
        $newType = $this->mapType($cd);
        if ($oldType != $newType) {
            $phrase[] = $prefix . 'TYPE ' . $newType;
        }

        if (!empty($old['not null']) && empty($cd['not null'])) {
            $phrase[] = $prefix . 'DROP NOT NULL';
        } else if (empty($old['not null']) && !empty($cd['not null'])) {
            $phrase[] = $prefix . 'SET NOT NULL';
        }

        if (isset($old['default']) && !isset($cd['default'])) {
            $phrase[] = $prefix . 'DROP DEFAULT';
        } else if (!isset($old['default']) && isset($cd['default'])) {
            $phrase[] = $prefix . 'SET DEFAULT ' . $this->quoteDefaultValue($cd);
        }
    }

    /**
     * Append an SQL statement to drop an index from a table.
     * Note that in PostgreSQL, index names are DB-unique.
     *
     * @param array $statements
     * @param string $table
     * @param string $name
     * @param array $def
     */
    function appendDropIndex(array &$statements, $table, $name)
    {
        $statements[] = "DROP INDEX $name";
    }

    /**
     * Quote a db/table/column identifier if necessary.
     *
     * @param string $name
     * @return string
     */
    function quoteIdentifier($name)
    {
        return $this->conn->quoteIdentifier($name);
    }

    function mapType($column)
    {
        $map = array('serial' => 'bigserial', // FIXME: creates the wrong name for the sequence for some internal sequence-lookup function, so better fix this to do the real 'create sequence' dance.
                     'numeric' => 'decimal',
                     'datetime' => 'timestamp',
                     'blob' => 'bytea');

        $type = $column['type'];
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if ($type == 'int') {
            if (!empty($column['size'])) {
                $size = $column['size'];
                if ($size == 'small') {
                    return 'int2';
                } else if ($size == 'big') {
                    return 'int8';
                }
            }
            return 'int4';
        }

        return $type;
    }

    // @fixme need name... :P
    function typeAndSize($column)
    {
        if ($column['type'] == 'enum') {
            $vals = array_map(array($this, 'quote'), $column['enum']);
            return "text check ($name in " . implode(',', $vals) . ')';
        } else {
            return parent::typeAndSize($column);
        }
    }

    /**
     * Filter the given table definition array to match features available
     * in this database.
     *
     * This lets us strip out unsupported things like comments, foreign keys,
     * or type variants that we wouldn't get back from getTableDef().
     *
     * @param array $tableDef
     */
    function filterDef(array $tableDef)
    {
        foreach ($tableDef['fields'] as $name => &$col) {
            // No convenient support for field descriptions
            unset($col['description']);

            /*
            if (isset($col['size'])) {
                // Don't distinguish between tinyint and int.
                if ($col['size'] == 'tiny' && $col['type'] == 'int') {
                    unset($col['size']);
                }
            }
             */
            $col['type'] = $this->mapType($col);
            unset($col['size']);
        }
        if (!empty($tableDef['primary key'])) {
            $tableDef['primary key'] = $this->filterKeyDef($tableDef['primary key']);
        }
        if (!empty($tableDef['unique keys'])) {
            foreach ($tableDef['unique keys'] as $i => $def) {
                $tableDef['unique keys'][$i] = $this->filterKeyDef($def);
            }
        }
        return $tableDef;
    }

    /**
     * Filter the given key/index definition to match features available
     * in this database.
     *
     * @param array $def
     * @return array
     */
    function filterKeyDef(array $def)
    {
        // PostgreSQL doesn't like prefix lengths specified on keys...?
        foreach ($def as $i => $item)
        {
            if (is_array($item)) {
                $def[$i] = $item[0];
            }
        }
        return $def;
    }
}
