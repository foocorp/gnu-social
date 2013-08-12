<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to push RSS/Atom updates to a PubSubHubBub hub
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

if (!defined('STATUSNET')) {
    exit(1);
}

class SimpleUrlPlugin extends UrlShortenerPlugin
{
    public $serviceUrl;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->serviceUrl)){
            // TRANS: Exception thrown when the SimpleUrl plugin has been configured incorrectly.
            throw new Exception(_m('You must specify a serviceUrl.'));
        }
    }

    protected function shorten($url) {
        return $this->http_get(sprintf($this->serviceUrl,urlencode($url)));
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => sprintf('SimpleUrl (%s)', $this->shortenerName),
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:SimpleUrl',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            sprintf(_m('Uses <a href="http://%1$s/">%1$s</a> URL-shortener service.'),
                                    $this->shortenerName));

        return true;
    }
}
