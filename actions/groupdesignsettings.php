<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Saves a design for a given group.
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
 * @category  Settings
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/designsettings.php';

/**
 * Set a group's design
 *
 * Saves a design for a given group
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupDesignSettingsAction extends DesignSettingsAction
{
    var $group = null;

    /**
     * Sets the right action for the form, and passes request args into
     * the base action
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // TRANS: Client error displayed trying to change group design settings while not logged in.
            $this->clientError(_('You must be logged in to edit a group.'));
            return false;
        }

        $nickname_arg = $this->trimmed('nickname');
        $nickname     = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            common_redirect(common_local_url('groupdesignsettings', $args), 301);
            return false;
        }

        if (!$nickname) {
            // TRANS: Client error displayed trying to change group design settings without providing a group nickname.
            $this->clientError(_('No nickname.'), 404);
            return false;
        }

        $groupid = $this->trimmed('groupid');

        if ($groupid) {
            $this->group = User_group::staticGet('id', $groupid);
        } else {
            $local = Local_group::staticGet('nickname', $nickname);
            if ($local) {
                $this->group = User_group::staticGet('id', $local->group_id);
            }
        }

        if (!$this->group) {
            // TRANS: Client error displayed trying to change group design settings while providing a nickname for a non-existing group.
            $this->clientError(_('No such group.'), 404);
            return false;
        }

        $cur = common_current_user();

        if (!$cur->isAdmin($this->group)) {
            // TRANS: Client error displayed trying to change group design settings without being a (group) admin.
            $this->clientError(_('You must be an admin to edit the group.'), 403);
            return false;
        }

        $this->submitaction = common_local_url('groupdesignsettings',
            array('nickname' => $this->group->nickname));

        return true;
    }

    /**
     * A design for this action
     *
     * if the group attribute has been set, returns that group's
     * design.
     *
     * @return Design a design object to use
     */
    function getDesign()
    {

        if (empty($this->group)) {
            return null;
        }

        return $this->group->getDesign();
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Title group design settings page.
        return _('Group design');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instructions for group design settings page.
        return _('Customize the way your group looks ' .
        'with a background image and a colour palette of your choice.');
    }

    /**
     * Override to show group nav stuff
     *
     * @return nothing
     */
    function showObjectNav()
    {
        $nav = new GroupNav($this, $this->group);
        $nav->show();
    }

    /**
     * Override to show default nav stuff
     *
     * @return nothing
     */
    function showLocalNav()
    {
        Action::showLocalNav();
    }

    /**
     * Get the design we want to edit
     *
     * @return Design
     */
    function getWorkingDesign()
    {
        $design = null;

        if (isset($this->group)) {
            $design = $this->group->getDesign();
        }

        return $design;
    }

    /**
     * Content area of the page
     *
     * Shows a form for changing the design
     *
     * @return void
     */
    function showContent()
    {
        $design = $this->getWorkingDesign();

        if (empty($design)) {
            $design = Design::siteDesign();
        }

        $this->showDesignForm($design);
    }

    /**
     * Save or update the group's design settings
     *
     * @return void
     */
    function saveDesign()
    {
        try {
            $bgcolor = new WebColor($this->trimmed('design_background'));
            $ccolor  = new WebColor($this->trimmed('design_content'));
            $sbcolor = new WebColor($this->trimmed('design_sidebar'));
            $tcolor  = new WebColor($this->trimmed('design_text'));
            $lcolor  = new WebColor($this->trimmed('design_links'));
        } catch (WebColorException $e) {
            $this->showForm($e->getMessage());
            return;
        }

        $onoff = $this->arg('design_background-image_onoff');

        $on   = false;
        $off  = false;
        $tile = false;

        if ($onoff == 'on') {
            $on = true;
        } else {
            $off = true;
        }

        $repeat = $this->boolean('design_background-image_repeat');

        if ($repeat) {
            $tile = true;
        }

        $design = $this->group->getDesign();

        if (!empty($design)) {
            // update design

            $original = clone($design);

            $design->backgroundcolor = $bgcolor->intValue();
            $design->contentcolor    = $ccolor->intValue();
            $design->sidebarcolor    = $sbcolor->intValue();
            $design->textcolor       = $tcolor->intValue();
            $design->linkcolor       = $lcolor->intValue();

            $design->setDisposition($on, $off, $tile);

            $result = $design->update($original);

            if ($result === false) {
                common_log_db_error($design, 'UPDATE', __FILE__);
                // TRANS: Form validation error displayed when group design settings could not be updated because of an application issue.
                $this->showForm(_('Unable to update your design settings.'));
                return;
            }
        } else {
            $this->group->query('BEGIN');

            // save new design

            $design = new Design();

            $design->backgroundcolor = $bgcolor->intValue();
            $design->contentcolor    = $ccolor->intValue();
            $design->sidebarcolor    = $sbcolor->intValue();
            $design->textcolor       = $tcolor->intValue();
            $design->linkcolor       = $lcolor->intValue();

            $design->setDisposition($on, $off, $tile);

            $id = $design->insert();

            if (empty($id)) {
                common_log_db_error($id, 'INSERT', __FILE__);
                // TRANS: Form validation error displayed when group design settings could not be saved because of an application issue.
                $this->showForm(_('Unable to save your design settings.'));
                return;
            }

            $original               = clone($this->group);
            $this->group->design_id = $id;
            $result                 = $this->group->update($original);

            if (empty($result)) {
                common_log_db_error($original, 'UPDATE', __FILE__);
                // TRANS: Form validation error displayed when group design settings could not be saved because of an application issue.
                $this->showForm(_('Unable to save your design settings.'));
                $this->group->query('ROLLBACK');
                return;
            }

            $this->group->query('COMMIT');
        }

        $this->saveBackgroundImage($design);

        // TRANS: Form text to confirm saved group design settings.
        $this->showForm(_('Design preferences saved.'), true);
    }
}
