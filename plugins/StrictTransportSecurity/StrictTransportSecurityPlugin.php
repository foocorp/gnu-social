<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable Strict Transport Security headers
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

if (!defined('GNUSOCIAL')) { exit(1); }

class StrictTransportSecurityPlugin extends Plugin
{
    public $max_age = 15552000;
    public $includeSubDomains = false;
    public $preloadToken = false;

    function __construct()
    {
        parent::__construct();
    }

    function onArgsInitialize($args)
    {
        $path = common_config('site', 'path');
        if(common_config('site', 'ssl') == 'always' && ($path == '/' || ! $path )) {
            header('Strict-Transport-Security: max-age=' . $this->max_age
                    . ($this->includeSubDomains ? '; includeSubDomains' : '')
                    . ($this->preloadToken ? '; preload' : ''));
        }
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'StrictTransportSecurity',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:StrictTransportSecurity',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The Strict Transport Security plugin implements the Strict Transport Security header, improving the security of HTTPS only sites.'));
        return true;
    }
}
