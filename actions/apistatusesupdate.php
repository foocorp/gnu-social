<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Post a notice (update your status) through the API
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
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/* External API usage documentation. Please update when you change how this method works. */

/*! @page statusesupdate statuses/update

    @section Description
    Updates the authenticating user's status. Requires the status parameter specified below.
    Request must be a POST.

    @par URL pattern
    /api/statuses/update.:format

    @par Formats (:format)
    xml, json

    @par HTTP Method(s)
    POST

    @par Requires Authentication
    Yes

    @param status (Required) The URL-encoded text of the status update.
    @param source (Optional) The source of the status.
    @param in_reply_to_status_id (Optional) The ID of an existing status that the update is in reply to.
    @param lat (Optional) The latitude the status refers to.
    @param long (Optional) The longitude the status refers to.
    @param media (Optional) a media upload, such as an image or movie file.

    @sa @ref authentication
    @sa @ref apiroot

    @subsection usagenotes Usage notes

    @li The URL pattern is relative to the @ref apiroot.
    @li If the @e source parameter is not supplied the source of the status will default to 'api'.
    @li The XML response uses <a href="http://georss.org/Main_Page">GeoRSS</a>
    to encode the latitude and longitude (see example response below <georss:point>).
    @li Data uploaded via the @e media parameter should be multipart/form-data encoded.

    @subsection exampleusage Example usage

    @verbatim
    curl -u username:password http://example.com/api/statuses/update.xml -d status='Howdy!' -d lat='30.468' -d long='-94.743'
    @endverbatim

    @subsection exampleresponse Example response

    @verbatim
    <?xml version="1.0" encoding="UTF-8"?>
    <status>
      <text>Howdy!</text>
      <truncated>false</truncated>
      <created_at>Tue Mar 30 23:28:05 +0000 2010</created_at>
      <in_reply_to_status_id/>
      <source>api</source>
      <id>26668724</id>
      <in_reply_to_user_id/>
      <in_reply_to_screen_name/>
      <geo xmlns:georss="http://www.georss.org/georss">
        <georss:point>30.468 -94.743</georss:point>
      </geo>
      <favorited>false</favorited>
      <user>
        <id>25803</id>
        <name>Jed Sanders</name>
        <screen_name>jedsanders</screen_name>
        <location>Hoop and Holler, Texas</location>
        <description>I like to think of myself as America's Favorite.</description>
        <profile_image_url>http://avatar.example.com/25803-48-20080924200604.png</profile_image_url>
        <url>http://jedsanders.net</url>
        <protected>false</protected>
        <followers_count>5</followers_count>
        <profile_background_color/>
        <profile_text_color/>
        <profile_link_color/>
        <profile_sidebar_fill_color/>
        <profile_sidebar_border_color/>
        <friends_count>2</friends_count>
        <created_at>Wed Sep 24 20:04:00 +0000 2008</created_at>
        <favourites_count>0</favourites_count>
        <utc_offset>0</utc_offset>
        <time_zone>UTC</time_zone>
        <profile_background_image_url/>
        <profile_background_tile>false</profile_background_tile>
        <statuses_count>70</statuses_count>
        <following>true</following>
        <notifications>true</notifications>
      </user>
    </status>
    @endverbatim
*/

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';
require_once INSTALLDIR . '/lib/mediafile.php';

/**
 * Updates the authenticating user's status (posts a notice).
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

class ApiStatusesUpdateAction extends ApiAuthAction
{
    var $source                = null;
    var $status                = null;
    var $in_reply_to_status_id = null;
    var $lat                   = null;
    var $lon                   = null;

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

        $this->status = $this->trimmed('status');
        $this->lat    = $this->trimmed('lat');
        $this->lon    = $this->trimmed('long');

        $this->in_reply_to_status_id
            = intval($this->trimmed('in_reply_to_status_id'));

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

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                _('This method requires a POST.'),
                400,
                $this->format
            );
            return;
        }

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
             $msg = _('The server was unable to handle that much POST ' .
                    'data (%s bytes) due to its current configuration.');

            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        if (empty($this->status)) {
            $this->clientError(
                _('Client must provide a \'status\' parameter with a value.'),
                400,
                $this->format
            );
            return;
        }

        if (empty($this->auth_user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        $status_shortened = common_shorten_links($this->status);

        if (Notice::contentTooLong($status_shortened)) {

            // Note: Twitter truncates anything over 140, flags the status
            // as "truncated."

            $this->clientError(
                sprintf(
                    _('That\'s too long. Max notice size is %d chars.'),
                    Notice::maxContent()
                ),
                406,
                $this->format
            );

            return;
        }

        // Check for commands

        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($this->auth_user, $status_shortened);

        if ($cmd) {

            if ($this->supported($cmd)) {
                $cmd->execute(new Channel());
            }

            // Cmd not supported?  Twitter just returns your latest status.
            // And, it returns your last status whether the cmd was successful
            // or not!

            $this->notice = $this->auth_user->getCurrentNotice();

        } else {

            $reply_to = null;

            if (!empty($this->in_reply_to_status_id)) {

                // Check whether notice actually exists

                $reply = Notice::staticGet($this->in_reply_to_status_id);

                if ($reply) {
                    $reply_to = $this->in_reply_to_status_id;
                } else {
                    $this->clientError(
                        _('Not found.'),
                        $code = 404,
                        $this->format
                    );
                    return;
                }
            }

            $upload = null;

            try {
                $upload = MediaFile::fromUpload('media', $this->auth_user);
            } catch (Exception $e) {
                $this->clientError($e->getMessage(), $e->getCode(), $this->format);
                return;
            }

            if (isset($upload)) {
                $status_shortened .= ' ' . $upload->shortUrl();

                if (Notice::contentTooLong($status_shortened)) {
                    $upload->delete();
                    $msg = _(
                        'Max notice size is %d chars, ' .
                        'including attachment URL.'
                    );
                    $this->clientError(
                        sprintf($msg, Notice::maxContent()),
                        400,
                        $this->format
                    );
                }
            }

            $content = html_entity_decode($status_shortened, ENT_NOQUOTES, 'UTF-8');

            $options = array('reply_to' => $reply_to);

            if ($this->auth_user->shareLocation()) {

                $locOptions = Notice::locationOptions($this->lat,
                                                      $this->lon,
                                                      null,
                                                      null,
                                                      $this->auth_user->getProfile());

                $options = array_merge($options, $locOptions);
            }

            try {
                $this->notice = Notice::saveNew(
                    $this->auth_user->id,
                    $content,
                    $this->source,
                    $options
                );
            } catch (Exception $e) {
                $this->clientError($e->getMessage(), $e->getCode(), $this->format);
                return;
            }

            if (isset($upload)) {
                $upload->attachToNotice($this->notice);
            }

        }

        $this->showNotice();
    }

    /**
     * Show the resulting notice
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
        }
    }

    /**
     * Is this command supported when doing an update from the API?
     *
     * @param string $cmd the command to check for
     *
     * @return boolean true or false
     */

    function supported($cmd)
    {
        static $cmdlist = array('MessageCommand', 'SubCommand', 'UnsubCommand',
            'FavCommand', 'OnCommand', 'OffCommand');

        if (in_array(get_class($cmd), $cmdlist)) {
            return true;
        }

        return false;
    }

}
