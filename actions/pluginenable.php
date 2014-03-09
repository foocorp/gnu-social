<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Plugin enable action.
 *
 * This program is free software: you can redistribute it and/or modify
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
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin enable action.
 *
 * (Re)-enables a plugin from the default plugins list.
 *
 * Takes parameters:
 *
 *    - plugin: plugin name
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class PluginEnableAction extends Action
{
    var $user;
    var $plugin;

    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        // @fixme these are pretty common, should a parent class factor these out?

        // Only allow POST requests

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            // TRANS: Client error displayed when trying to use another method than POST.
            // TRANS: Do not translate POST.
            $this->clientError(_('This action only accepts POST requests.'));
        }

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
        }

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
        }

        if (!AdminPanelAction::canAdmin('plugins')) {
            // TRANS: Client error displayed when trying to enable or disable a plugin without access rights.
            $this->clientError(_('You cannot administer plugins.'));
        }

        $this->plugin = $this->arg('plugin');
        $defaultPlugins = common_config('plugins', 'default');
        if (!array_key_exists($this->plugin, $defaultPlugins)) {
            // TRANS: Client error displayed when trying to enable or disable a non-existing plugin.
            $this->clientError(_('No such plugin.'));
        }

        return true;
    }

    /**
     * Handle request
     *
     * Does the subscription and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        $key = 'disable-' . $this->plugin;
        Config::save('plugins', $key, $this->overrideValue());

        // @fixme this is a pretty common pattern and should be refactored down
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, $this->successShortTitle());
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = $this->successNextForm();
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            $url = common_local_url('pluginsadminpanel');
            common_redirect($url, 303);
        }
    }

    /**
     * Value to save into $config['plugins']['disable-<name>']
     */
    protected function overrideValue()
    {
        return 0;
    }

    protected function successShortTitle()
    {
        // TRANS: Page title for AJAX form return when enabling a plugin.
        return _m('plugin', 'Enabled');
    }

    protected function successNextForm()
    {
        return new DisablePluginForm($this, $this->plugin);
    }
}
