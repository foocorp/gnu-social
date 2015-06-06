<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to show reCaptcha when a user registers
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Eric Helgeson <erichelgeson@gmail.com>
 * @copyright 2009
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/plugins/Recaptcha/recaptchalib.php');

class RecaptchaPlugin extends Plugin
{
    var $private_key;
    var $public_key;
    var $display_errors;
    var $failed;
    var $ssl;

    function onInitializePlugin()
    {
        if(!isset($this->private_key)) {
            common_log(LOG_ERR, 'Recaptcha: Must specify private_key in config.php');
        }
        if(!isset($this->public_key)) {
            common_log(LOG_ERR, 'Recaptcha: Must specify public_key in config.php');
        }
    }

    function onEndRegistrationFormData($action)
    {
        $action->elementStart('li');
        // TRANS: Field label.
        $action->raw('<label for="recaptcha">'._m('Captcha').'</label>');

        // AJAX API will fill this div out.
        // We're calling that instead of the regular one so we stay compatible
        // with application/xml+xhtml output as for mobile.
        $action->element('div', array('id' => 'recaptcha'));
        $action->elementEnd('li');

        $action->recaptchaPluginNeedsOutput = true;
        return true;
    }

    function onEndShowScripts($action)
    {
        if (isset($action->recaptchaPluginNeedsOutput) && $action->recaptchaPluginNeedsOutput) {
            // Load the AJAX API
            if (GNUsocial::isHTTPS()) {
                $url = "https://www.google.com/recaptcha/api/js/recaptcha_ajax.js";
            } else {
                $url = "http://www.google.com/recaptcha/api/js/recaptcha_ajax.js";
            }
            $action->script($url);

            // And when we're ready, fill out the captcha!
            $key = json_encode($this->public_key);
            $action->inlinescript("\$(function(){Recaptcha.create($key, 'recaptcha');});");
        }
        return true;
    }

    function onStartRegistrationTry($action)
    {
        $resp = recaptcha_check_answer ($this->private_key,
                                        $_SERVER["REMOTE_ADDR"],
                                        $action->trimmed('recaptcha_challenge_field'),
                                        $action->trimmed('recaptcha_response_field'));

        if (!$resp->is_valid) {
            if($this->display_errors) {
                // TRANS: Error message displayed if there is in error communicating with the
                // TRANS: reCAPTCHA server. %s is the error.
                $action->showForm(sprintf(_m('(reCAPTCHA error: %s)', $resp->error)));
            }
            // TRANS: Error message displayed if a provided captcha response does not match.
            $action->showForm(_m('Captcha does not match!'));
            return false;
        }
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Recaptcha',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Eric Helgeson',
                            'homepage' => 'http://status.net/wiki/Plugin:Recaptcha',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Uses <a href="http://recaptcha.org/">Recaptcha</a> service to add a  '.
                               'captcha to the registration page.'));
        return true;
    }
}
