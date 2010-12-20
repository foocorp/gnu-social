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
 * A class encapsulating the structure of a column in a table.
 *
 * @category Database
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ColumnDef
{
    /** name of the column. */
    public $name;
    /** type of column, e.g. 'int', 'varchar' */
    public $type;
    /** size of the column. */
    public $size;
    /** boolean flag; can it be null? */
    public $nullable;
    /**
     * type of key: null = no key; 'PRI' => primary;
     * 'UNI' => unique key; 'MUL' => multiple values.
     */
    public $key;
    /** default value if any. */
    public $default;
    /** 'extra' stuff. Returned by MySQL, largely
     * unused. */
    public $extra;
    /** auto increment this field if no value is specific for it during an insert **/
    public $auto_increment;

    /**
     * Constructor.
     *
     * @param string  $name     name of the column
     * @param string  $type     type of the column
     * @param int     $size     size of the column
     * @param boolean $nullable can this be null?
     * @param string  $key      type of key
     * @param value   $default  default value
     * @param value   $extra    unused
     * @param boolean $auto_increment
     */
    function __construct($name=null, $type=null, $size=null,
                         $nullable=true, $key=null, $default=null,
                         $extra=null, $auto_increment=false)
    {
        $this->name     = strtolower($name);
        $this->type     = strtolower($type);
        $this->size     = $size+0;
        $this->nullable = $nullable;
        $this->key      = $key;
        $this->default  = $default;
        $this->extra    = $extra;
        $this->auto_increment = $auto_increment;
    }

    /**
     * Compares this columndef with another to see
     * if they're functionally equivalent.
     *
     * @param ColumnDef $other column to compare
     *
     * @return boolean true if equivalent, otherwise false.
     */
    function equals($other)
    {
        return ($this->name == $other->name &&
                $this->_typeMatch($other) &&
                $this->_defaultMatch($other) &&
                $this->_nullMatch($other) &&
                $this->key == $other->key &&
                $this->auto_increment == $other->auto_increment);
    }

    /**
     * Does the type of this column match the
     * type of the other column?
     *
     * Checks the type and size of a column. Tries
     * to ignore differences between synonymous
     * data types, like 'integer' and 'int'.
     *
     * @param ColumnDef $other other column to check
     *
     * @return boolean true if they're about equivalent
     */
    private function _typeMatch($other)
    {
        switch ($this->type) {
        case 'integer':
        case 'int':
            return ($other->type == 'integer' ||
                    $other->type == 'int');
            break;
        default:
            return ($this->type == $other->type &&
                    $this->size == $other->size);
        }
    }

    /**
     * Does the default behaviour of this column match
     * the other?
     *
     * @param ColumnDef $other other column to check
     *
     * @return boolean true if defaults are effectively the same.
     */
    private function _defaultMatch($other)
    {
        return ((is_null($this->default) && is_null($other->default)) ||
                ($this->default == $other->default));
    }

    /**
     * Does the null behaviour of this column match
     * the other?
     *
     * @param ColumnDef $other other column to check
     *
     * @return boolean true if these columns 'null' the same.
     */
    private function _nullMatch($other)
    {
        return ((!is_null($this->default) && !is_null($other->default) &&
                 $this->default == $other->default) ||
                ($this->nullable == $other->nullable));
    }
}
