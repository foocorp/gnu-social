<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show up to 100 repeats of a notice
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

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';
require_once INSTALLDIR . '/lib/mediafile.php';

/**
 * Show up to 100 repeats of a notice
 *
 * @category API
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiStatusesRetweetsAction extends ApiAuthAction
{
    const MAXCOUNT = 100;

    var $original = null;
    var $cnt      = self::MAXCOUNT;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        $this->original = Notice::getKV('id', $id);

        if (empty($this->original)) {
            // TRANS: Client error displayed trying to display redents of a non-exiting notice.
            $this->clientError(_('No such notice.'),
                               400, $this->format);
            return false;
        }

        $cnt = $this->trimmed('count');

        if (empty($cnt) || !is_integer($cnt)) {
            $cnt = 100;
        } else {
            $this->cnt = min((int)$cnt, self::MAXCOUNT);
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
    function handle($args)
    {
        parent::handle($args);

        $strm = $this->original->repeatStream($this->cnt);

        switch ($this->format) {
        case 'xml':
            $this->showXmlTimeline($strm);
            break;
        case 'json':
            $this->showJsonTimeline($strm);
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), $code = 404);
            break;
        }
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return true;
    }
}
