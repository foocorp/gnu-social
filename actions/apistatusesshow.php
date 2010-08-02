<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a notice (as a Twitter-style status)
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
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Tom Blankenship <mac65@mac65.com>
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiprivateauth.php';

/**
 * Returns the notice specified by id as a Twitter-style status and inline user
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Tom Blankenship <mac65@mac65.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiStatusesShowAction extends ApiPrivateAuthAction
{

    var $notice_id = null;
    var $notice    = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        // 'id' is an undocumented parameter in Twitter's API. Several
        // clients make use of it, so we support it too.

        // show.json?id=12345 takes precedence over /show/12345.json

        $this->notice_id = (int)$this->trimmed('id');

        if (empty($notice_id)) {
            $this->notice_id = (int)$this->arg('id');
        }

        $this->notice = Notice::staticGet((int)$this->notice_id);

        return true;
    }

    /**
     * Handle the request
     *
     * Check the format and show the notice
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(_('API method not found.'), $code = 404);
            return;
        }

        $this->showNotice();
    }

    /**
     * Show the notice
     *
     * @return void
     */

    function showNotice()
    {
        if (!empty($this->notice)) {
            if ($this->format == 'xml') {
                $this->showSingleXmlStatus($this->notice);
            } elseif ($this->format == 'json') {
                $this->show_single_json_status($this->notice);
            }
        } else {

            // XXX: Twitter just sets a 404 header and doens't bother
            // to return an err msg

            $deleted = Deleted_notice::staticGet($this->notice_id);

            if (!empty($deleted)) {
                $this->clientError(
                    _('Status deleted.'),
                    410,
                    $this->format
                );
            } else {
                $this->clientError(
                    _('No status with that ID found.'),
                    404,
                    $this->format
                );
            }
        }
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this notice last modified?
     *
     * @return string datestamp of the latest notice in the stream
     */

    function lastModified()
    {
        if (!empty($this->notice)) {
            return strtotime($this->notice->created);
        }

        return null;
    }

    /**
     * An entity tag for this notice
     *
     * Returns an Etag based on the action name, language, and
     * timestamps of the notice
     *
     * @return string etag
     */

    function etag()
    {
        if (!empty($this->notice)) {

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->notice->id,
                      strtotime($this->notice->created))
            )
            . '"';
        }

        return null;
    }

}
