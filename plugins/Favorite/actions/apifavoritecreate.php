<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Add a notice to a user's list of favorite notices via the API
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://gnu.io/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Favorites the status specified in the ID parameter as the authenticating user.
 * Returns the favorite status when successful.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://gnu.io/
 */
class ApiFavoriteCreateAction extends ApiAuthAction
{
    var $notice = null;

    protected $needPost = true;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->notice = Notice::getKV($this->arg('id'));
        if (!empty($this->notice->repeat_of)) {
                common_log(LOG_DEBUG, 'Trying to Fave '.$this->notice->id.', repeat of '.$this->notice->repeat_of);
                common_log(LOG_DEBUG, 'Will Fave '.$this->notice->repeat_of.' instead');
                $real_notice_id = $this->notice->repeat_of;
                $this->notice = Notice::getKV($real_notice_id);
        }

        return true;
    }

    protected function handle()
    {
        parent::handle();

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed when coming across a non-supported API method.
                _('API method not found.'),
                404,
                $this->format
            );
        }

        if (empty($this->notice)) {
            $this->clientError(
                // TRANS: Client error displayed when requesting a status with a non-existing ID.
                _('No status found with that ID.'),
                404,
                $this->format
            );
        }

        try {
            $stored = Fave::addNew($this->scoped, $this->notice);
        } catch (AlreadyFulfilledException $e) {
            // Note: Twitter lets you fave things repeatedly via API.
            $this->clientError($e->getMessage(), 403);
        }

        if ($this->format == 'xml') {
            $this->showSingleXmlStatus($this->notice);
        } elseif ($this->format == 'json') {
            $this->show_single_json_status($this->notice);
        }
    }
}
