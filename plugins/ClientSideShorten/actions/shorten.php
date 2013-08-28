<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List users for autocompletion
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
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Shorten all URLs in a string
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ShortenAction extends Action
{
    private $text;

    function prepare($args)
    {
        parent::prepare($args);
        $this->groups=array();
        $this->users=array();
        $this->text = $this->arg('text');
        if(is_null($this->text)){
            // TRANS: Client exception thrown when a text argument is not present.
            throw new ClientException(_m('"text" argument must be specified.'));
        }
        return true;
    }

    function handle($args=null)
    {
        parent::handle($args);
        header('Content-Type: text/plain');
        $shortened_text = common_shorten_links($this->text);
        print $shortened_text;
    }
}
