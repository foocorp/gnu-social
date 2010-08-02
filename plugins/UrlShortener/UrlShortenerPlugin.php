<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do URL shortening
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for plugins that do URL shortening
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

abstract class UrlShortenerPlugin extends Plugin
{
    public $shortenerName;
    public $freeService=false;
    //------------Url Shortener plugin should implement some (or all) of these methods------------\\

    /**
    * Short a URL
    * @param url
    * @return string shortened version of the url, or null if URL shortening failed
    */
    protected abstract function shorten($url);

    //------------These methods may help you implement your plugin------------\\
    protected function http_get($url)
    {
        $request = HTTPClient::start();
        $response = $request->get($url);
        return $response->getBody();
    }

    protected function http_post($url,$data)
    {
        $request = HTTPClient::start();
        $response = $request->post($url, null, $data);
        return $response->getBody();
    }

    //------------Below are the methods that connect StatusNet to the implementing Url Shortener plugin------------\\

    function onInitializePlugin(){
        if(!isset($this->shortenerName)){
            throw new Exception("must specify a shortenerName");
        }
    }

    function onGetUrlShorteners(&$shorteners)
    {
        $shorteners[$this->shortenerName]=array('freeService'=>$this->freeService);
    }

    function onStartShortenUrl($url,$shortenerName,&$shortenedUrl)
    {
        if($shortenerName == $this->shortenerName && strlen($url) >= common_config('site', 'shorturllength')){
            $result = $this->shorten($url);
            if(isset($result) && $result != null && $result !== false){
                $shortenedUrl=$result;
                common_log(LOG_INFO, __CLASS__ . ": $this->shortenerName shortened $url to $shortenedUrl");
                return false;
            }
        }
    }
}
