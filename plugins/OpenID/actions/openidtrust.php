<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

class OpenidtrustAction extends Action
{
    var $trust_root;
    var $allowUrl;
    var $denyUrl;
    var $user;

    /**
     * Is this a read-only action?
     *
     * @return boolean false
     */
    function isReadOnly($args)
    {
        return false;
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */
    function title()
    {
        // TRANS: Title for identity verification page.
        return _m('OpenID Identity Verification');
    }

    function prepare($args)
    {
        parent::prepare($args);
        common_ensure_session();
        $this->user = common_current_user();
        if(empty($this->user)){
            /* Go log in, and then come back. */
            common_set_returnto($_SERVER['REQUEST_URI']);
            common_redirect(common_local_url('login'));
        }
        $this->trust_root = $_SESSION['openid_trust_root'];
        $this->allowUrl = $_SESSION['openid_allow_url'];
        $this->denyUrl = $_SESSION['openid_deny_url'];
        if(empty($this->trust_root) || empty($this->allowUrl) || empty($this->denyUrl)){
            // TRANS: Client error when visiting page directly.
            $this->clientError(_m('This page should only be reached during OpenID processing, not directly.'));
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            $this->handleSubmit();
        }else{
            $this->showPage();
        }
    }

    function handleSubmit()
    {
        global $_PEAR;

        unset($_SESSION['openid_trust_root']);
        unset($_SESSION['openid_allow_url']);
        unset($_SESSION['openid_deny_url']);
        if($this->arg('allow'))
        {
            //save to database
            $user_openid_trustroot = new User_openid_trustroot();
            $user_openid_trustroot->user_id = $this->user->id;
            $user_openid_trustroot->trustroot = $this->trust_root;
            $user_openid_trustroot->created = common_sql_now();
            if (!$user_openid_trustroot->insert()) {
                $err = &$_PEAR->getStaticProperty('DB_DataObject','lastError');
            }
            common_redirect($this->allowUrl, $code=302);
        }else{
            common_redirect($this->denyUrl, $code=302);
        }
    }

    /**
     * Show page notice
     *
     * Display a notice for how to use the page, or the
     * error if it exists.
     *
     * @return void
     */
    function showPageNotice()
    {
        // TRANS: Page notice. %s is a trustroot name.
        $this->element('p',null,sprintf(_m('%s has asked to verify your identity. Click Continue to verify your identity and login without creating a new password.'),$this->trust_root));
    }

    /**
     * Core of the display code
     *
     * Shows the login form.
     *
     * @return void
     */
    function showContent()
    {
        $this->elementStart('form', array('method' => 'post',
                                   'id' => 'form_openidtrust',
                                   'class' => 'form_settings',
                                   'action' => common_local_url('openidtrust')));
        $this->elementStart('fieldset');
        // TRANS: Button text to continue OpenID identity verification.
        $this->submit('allow', _m('BUTTON','Continue'));
        // TRANS: Button text to cancel OpenID identity verification.
        $this->submit('deny', _m('BUTTON','Cancel'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }
}
