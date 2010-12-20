<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/Facebook/FacebookPlugin.php';

class FBConnectLoginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            $this->clientError(_m('Already logged in.'));
        }

        $this->showPage();
    }

    function getInstructions()
    {
        // TRANS: Instructions.
        return _m('Login with your Facebook Account');
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Facebook Login');
    }

    function showContent() {

        $this->elementStart('fieldset');
        $this->element('fb:login-button', array('onlogin' => 'goto_login()',
            'length' => 'long'));

        $this->elementEnd('fieldset');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
