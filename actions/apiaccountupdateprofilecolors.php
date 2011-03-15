<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update a user's design colors
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Sets one or more hex values that control the color scheme of the
 * authenticating user's design
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAccountUpdateProfileColorsAction extends ApiAuthAction
{
    var $profile_background_color     = null;
    var $profile_text_color           = null;
    var $profile_link_color           = null;
    var $profile_sidebar_fill_color   = null;
    var $profile_sidebar_border_color = null;

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

        $this->user   = $this->auth_user;

        $this->profile_background_color
            = $this->trimmed('profile_background_color');
        $this->profile_text_color
            = $this->trimmed('profile_text_color');
        $this->profile_link_color
            = $this->trimmed('profile_link_color');
        $this->profile_sidebar_fill_color
            = $this->trimmed('profile_sidebar_fill_color');

        // XXX: we don't support changing the sidebar border color
        // in our designs.

        $this->profile_sidebar_border_color
            = $this->trimmed('profile_sidebar_border_color');

        // XXX: Unlike Twitter, we do allow people to change the 'content color'

        $this->profile_content_color = $this->trimmed('profile_content_color');

        return true;
    }

    /**
     * Handle the request
     *
     * Try to save the user's colors in her design. Create a new design
     * if the user doesn't already have one.
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
                // TRANS: Client error. POST is a HTTP command. It should not be translated.
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(
                // TRANS: Client error displayed trying to execute an unknown API method updating profile colours.
                _('API method not found.'),
                404,
                $this->format
            );
            return;
        }

        $design = $this->user->getDesign();

        if (!empty($design)) {
            $original = clone($design);

            try {
                $this->setColors($design);
            } catch (WebColorException $e) {
                $this->clientError($e->getMessage(), 400, $this->format);
                return false;
            }

            $result = $design->update($original);

            if ($result === false) {
                common_log_db_error($design, 'UPDATE', __FILE__);
                // TRANS: Client error displayed when a database error occurs updating profile colours.
                $this->clientError(_('Could not update your design.'));
                return;
            }
        } else {
            $this->user->query('BEGIN');

            // save new design
            $design = new Design();

            try {
                $this->setColors($design);
            } catch (WebColorException $e) {
                $this->clientError($e->getMessage(), 400, $this->format);
                return false;
            }

            $id = $design->insert();

            if (empty($id)) {
                common_log_db_error($id, 'INSERT', __FILE__);
                // TRANS: Client error displayed when a database error occurs inserting profile colours.
                $this->clientError(_('Unable to save your design settings.'));
                return;
            }

            $original              = clone($this->user);
            $this->user->design_id = $id;
            $result                = $this->user->update($original);

            if (empty($result)) {
                common_log_db_error($original, 'UPDATE', __FILE__);
                // TRANS: Client error displayed when a database error occurs updating profile colours.
                $this->clientError(_('Unable to save your design settings.'));
                $this->user->query('ROLLBACK');
                return;
            }

            $this->user->query('COMMIT');
        }

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            // TRANS: Client error displayed a user has no profile updating profile colours.
            $this->clientError(_('User has no profile.'));
            return;
        }

        $twitter_user = $this->twitterUserArray($profile, true);

        if ($this->format == 'xml') {
            $this->initDocument('xml');
            $this->showTwitterXmlUser($twitter_user, 'user', true);
            $this->endDocument('xml');
        } elseif ($this->format == 'json') {
            $this->initDocument('json');
            $this->showJsonObjects($twitter_user);
            $this->endDocument('json');
        }
    }

    /**
     * Sets the user's design colors based on the request parameters
     *
     * @param Design $design the user's Design
     *
     * @return void
     */
    function setColors($design)
    {
        $bgcolor = empty($this->profile_background_color) ?
            null : new WebColor($this->profile_background_color);
        $tcolor  = empty($this->profile_text_color) ?
            null : new WebColor($this->profile_text_color);
        $sbcolor = empty($this->profile_sidebar_fill_color) ?
            null : new WebColor($this->profile_sidebar_fill_color);
        $lcolor  = empty($this->profile_link_color) ?
            null : new WebColor($this->profile_link_color);
        $ccolor  = empty($this->profile_content_color) ?
            null : new WebColor($this->profile_content_color);

        if (!empty($bgcolor)) {
            $design->backgroundcolor = $bgcolor->intValue();
        }

        if (!empty($ccolor)) {
            $design->contentcolor = $ccolor->intValue();
        }

        if (!empty($sbcolor)) {
            $design->sidebarcolor = $sbcolor->intValue();
        }

        if (!empty($tcolor)) {
            $design->textcolor = $tcolor->intValue();
        }

        if (!empty($lcolor)) {
            $design->linkcolor = $lcolor->intValue();
        }

        return true;
    }
}
