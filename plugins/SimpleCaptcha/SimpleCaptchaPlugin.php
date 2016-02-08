<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package     Plugin
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class SimpleCaptchaPlugin extends Plugin
{
    public function initialize()
    {
        // This probably needs some work. For example with IPv6 you can easily generate new IPs...
        $client_ip = common_client_ip();
        $this->client_ip = $client_ip[0] ?: $client_ip[1];   // [0] is proxy, [1] should be the real IP
    }

    public function onEndRegistrationFormData(Action $action)
    {
        $action->elementStart('li');
        // TRANS: Field label.
        $action->input('simplecaptcha', _m('Captcha'), null,
                        // TRANS: The instruction box for our simple captcha plugin
                        sprintf(_m('Copy this to the textbox: "%s"'), $this->getCaptchaText()),
                        // TRANS: Placeholder in the text box
                        /* name=id */ null, /* required */ true, ['placeholder'=>_m('Prove that you are sentient.')]);
        $action->elementEnd('li');
        return true;
    }

    protected function getCaptchaText()
    {
        return common_config('site', 'name');
    }

    public function onStartRegistrationTry(Action $action)
    {
        if ($action->arg('simplecaptcha') !== $this->getCaptchaText()) {
            common_log(LOG_INFO, 'Stopped non-sentient registration of nickname '._ve($action->trimmed('nickname')).' from IP: '._ve($this->client_ip));
            throw new ClientException(_m('Captcha does not match!'));
        }
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Simple Captcha',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/social',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('A simple captcha to get rid of spambots.'));

        return true;
    }
}
