<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Repeat a notice through the API
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
 * @category  API
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Repeat a notice through the API
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiStatusesRetweetAction extends ApiAuthAction
{
    protected $needPost = true;

    var $original = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        $this->original = Notice::getKV('id', $id);

        if (!$this->original instanceof Notice) {
            // TRANS: Client error displayed trying to repeat a non-existing notice through the API.
            $this->clientError(_('No such notice.'), 400, $this->format);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Make a new notice for the update, save it, and show it
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        $repeat = $this->original->repeat($this->scoped, $this->source);

        $this->showNotice($repeat);
    }

    /**
     * Show the resulting notice
     *
     * @return void
     */
    function showNotice($notice)
    {
        if (!empty($notice)) {
            if ($this->format == 'xml') {
                $this->showSingleXmlStatus($notice);
            } elseif ($this->format == 'json') {
                $this->show_single_json_status($notice);
            }
        }
    }
}
