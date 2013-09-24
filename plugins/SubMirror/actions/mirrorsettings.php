<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @category  Plugins
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class MirrorSettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */
    function title()
    {
        // TRANS: Page title.
        return _m('Feed mirror settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */

    function getInstructions()
    {
        // TRANS: Page instructions.
        return _m('You can mirror updates from many RSS and Atom feeds ' .
                  'into your StatusNet timeline!');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */
    function showContent()
    {
        $user = common_current_user();
        $provider = $this->trimmed('provider');
        if ($provider) {
            $this->showAddFeedForm($provider);
        } else {
            $this->elementStart('div', array('id' => 'add-mirror'));
            $this->showAddWizard();
            $this->elementEnd('div');

            $mirror = new SubMirror();
            $mirror->subscriber = $user->id;
            if ($mirror->find()) {
                while ($mirror->fetch()) {
                    $this->showFeedForm($mirror);
                }
            }
        }
    }

    function showAddWizard()
    {
        $form = new AddMirrorWizard($this);
        $form->show();
    }

    function showFeedForm($mirror)
    {
        $profile = Profile::getKV('id', $mirror->subscribed);
        if ($profile) {
            $form = new EditMirrorForm($this, $profile);
            $form->show();
        }
    }

    function showAddFeedForm()
    {
        switch ($this->arg('provider')) {
        case 'statusnet':
            break;
        case 'twitter':
            $form = new AddTwitterMirrorForm($this);
            break;
        case 'wordpress':
            break;
        case 'linkedin':
            break;
        case 'feed':
        default:
            $form = new AddMirrorForm($this);
        }
        $form->show();
    }

    /**
     *
     * @param array $args
     *
     * @todo move the ajax display handling to common code
     */
    function handle($args)
    {
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title for page with form to add a mirror feed provider on.
            $this->element('title', null, _m('Provider add'));
            $this->elementEnd('head');
            $this->elementStart('body');

            $this->showAddFeedForm();

            $this->elementEnd('body');
            $this->endHTML();
        } else {
            return parent::handle($args);
        }
    }
    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
     *
     * @return void
     */
    function handlePost()
    {
    }

    /**
     * Show the local navigation menu
     *
     * This is the same for all settings, so we show it here.
     *
     * @return void
     */
    function showLocalNav()
    {
        $menu = new SettingsNav($this);
        $menu->show();
    }

    function showScripts()
    {
        parent::showScripts();
        $this->script('plugins/SubMirror/js/mirrorsettings.js');
    }

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('plugins/SubMirror/css/mirrorsettings.css');
    }
}
