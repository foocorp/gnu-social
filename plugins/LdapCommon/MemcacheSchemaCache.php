<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Cache the LDAP schema in memcache to improve performance
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */
class MemcacheSchemaCache implements Net_LDAP2_SchemaCache
{
    protected $c;
    protected $cacheKey;

    /**
     * Initialize the simple cache
     *
     * Config is as following:
     *  memcache     memcache instance
     *  cachekey  the key in the cache to look at
     *
     * @param array $cfg Config array
     */
    public function MemcacheSchemaCache($cfg)
    {
        $this->c = $cfg['c'];
        $this->cacheKey = $cfg['cacheKey'];
    }

    /**
    * Return the schema object from the cache
    *
    * @return Net_LDAP2_Schema|Net_LDAP2_Error|false
    */
    public function loadSchema()
    {
         return $this->c->get($this->cacheKey);
    }

    /**
     * Store a schema object in the cache
     *
     * This method will be called, if Net_LDAP2 has fetched a fresh
     * schema object from LDAP and wants to init or refresh the cache.
     *
     * To invalidate the cache and cause Net_LDAP2 to refresh the cache,
     * you can call this method with null or false as value.
     * The next call to $ldap->schema() will then refresh the caches object.
     *
     * @param mixed $schema The object that should be cached
     * @return true|Net_LDAP2_Error|false
     */
    public function storeSchema($schema) {
        return $this->c->set($this->cacheKey, $schema);
    }
}
