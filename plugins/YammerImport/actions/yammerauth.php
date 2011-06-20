<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Yammer import administration panel
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class YammerauthAction extends AdminPanelAction
{
    /**
     * Show the Yammer admin panel form
     *
     * @return void
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->verify_token = $this->trim('verify_token');
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
        if ($this->verify_token) {
            $runner->saveAuthToken($this->verify_token);
            $form = new YammerAuthProgressForm();
        } else {
            $url = $runner->requestAuth();
            $form = new YammerAuthVerifyForm($this, $url);
        }

        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Page title for Yammer administration panel.
        $this->element('title', null, _m('Connect to Yammer'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $form->show();
        $this->elementEnd('body');
        $this->elementEnd('html');
    }
}
