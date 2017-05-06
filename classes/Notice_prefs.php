<?php
/**
 * GNU social
 *
 * Data class for Notice preferences
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
 * @category  Data
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

class Notice_prefs extends Managed_DataObject
{
    public $__table = 'notice_prefs';       // table name
    public $notice_id;                      // int(4)  primary_key not_null
    public $namespace;                       // varchar(191)  not_null
    public $topic;                           // varchar(191)  not_null
    public $data;                            // text
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'user'),
                'namespace' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'namespace, like pluginname or category'),
                'topic' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'preference key, i.e. description, age...'),
                'data' => array('type' => 'blob', 'description' => 'topic data, may be anything'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('notice_id', 'namespace', 'topic'),
            'foreign keys' => array(
                'notice_prefs_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'notice_prefs_notice_id_idx' => array('notice_id'),
            ),
        );
    }

    static function getNamespacePrefs(Notice $notice, $namespace, array $topic=array())
    {
        if (empty($topic)) {
            $prefs = new Notice_prefs();
            $prefs->notice_id = $notice->getID();
            $prefs->namespace  = $namespace;
            $prefs->find();
        } else {
            $prefs = self::pivotGet('notice_id', $notice->getID(), array('namespace'=>$namespace, 'topic'=>$topic));
        }

        if (empty($prefs->N)) {
            throw new NoResultException($prefs);
        }

        return $prefs;
    }

    static function getNamespace(Notice $notice, $namespace, array $topic=array())
    {
        $prefs = self::getNamespacePrefs($notice, $namespace, $topic);
        return $prefs->fetchAll();
    }

    static function getAll(Notice $notice)
    {
        try {
            $prefs = self::listFind('notice_id', array($notice->getID()));
        } catch (NoResultException $e) {
            return array();
        }

        $list = array();
        while ($prefs->fetch()) {
            if (!isset($list[$prefs->namespace])) {
                $list[$prefs->namespace] = array();
            }
            $list[$prefs->namespace][$prefs->topic] = $prefs->data;
        }
        return $list;
    }

    static function getTopic(Notice $notice, $namespace, $topic) {
        return self::getByPK(array('notice_id' => $notice->getID(),
                                            'namespace'  => $namespace,
                                            'topic'      => $topic));
    }

    static function getData(Notice $notice, $namespace, $topic, $def=null) {
        try {
            $pref = self::getTopic($notice, $namespace, $topic);
        } catch (NoResultException $e) {
            if ($def === null) {
                // If no default value was set, continue the exception.
                throw $e;
            }
            // If there was a default value, return that.
            return $def;
        }
        return $pref->data;
    }

    static function getConfigData(Notice $notice, $namespace, $topic) {
        try {
            $data = self::getData($notice, $namespace, $topic);
        } catch (NoResultException $e) {
            $data = common_config($namespace, $topic);
        }
        return $data;
    }

    /*
     * Sets a notice preference based on Notice, namespace and topic
     *
     * @param  Notice  $notice    Which notice this is for
     * @param  string  $namespace Under which namespace (pluginname etc.)
     * @param  string  $topic     Preference name (think key in key-val store)
     * @param  string  $data      Data to be put into preference storage, null means delete
     *
     * @return true if changes are made, false if no action taken
     * @throws ServerException if preference could not be saved
     */
    static function setData(Notice $notice, $namespace, $topic, $data=null) {
        try {
            $pref = self::getTopic($notice, $namespace, $topic);
            if (is_null($data)) {
                $pref->delete();
            } else {
                $orig = clone($pref);
                $pref->data = $data;
                $pref->update($orig);
            }
            return true;
        } catch (NoResultException $e) {
            if (is_null($data)) {
                return false; // No action taken
            }
        }

        $pref = new Notice_prefs();
        $pref->notice_id  = $notice->getID();
        $pref->namespace  = $namespace;
        $pref->topic      = $topic;
        $pref->data       = $data;
        $pref->created    = common_sql_now();
        
        if ($pref->insert() === false) {
            throw new ServerException('Could not save notice preference.');
        }
        return true;
    }
}
