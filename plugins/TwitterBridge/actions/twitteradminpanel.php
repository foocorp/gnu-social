<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Twitter bridge administration panel
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

/**
 * Administer global Twitter bridge settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TwitteradminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for Twitter administration panel.
        return _m('TITLE','Twitter');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for Twitter bridge administration page.
        return _m('Twitter bridge settings');
    }

    /**
     * Show the Twitter admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new TwitterAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save settings from the form
     *
     * @return void
     */
    function saveSettings()
    {
        static $settings = array(
            'twitter'     => array('consumer_key', 'consumer_secret'),
            'integration' => array('source')
        );

        static $booleans = array(
            'twitter'       => array('signin')
        );
        if (Event::handle('TwitterBridgeAdminImportControl')) {
            $booleans['twitterimport'] = array('enabled');
        }

        $values = array();

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting]
                    = $this->trimmed($setting);
            }
        }

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting]
                    = ($this->boolean($setting)) ? 1 : 0;
            }
        }

        // This throws an exception on validation errors

        $this->validate($values);

        // assert(all values are valid);

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        $config->query('COMMIT');

        // Flush the router cache: we may have enabled/disabled bridging,
        // which will add or remove some actions.
        $cache = Cache::instance();
        $cache->delete(Router::cacheKey());

        return;
    }

    function validate(&$values)
    {
        // Validate consumer key and secret (can't be too long)

        if (mb_strlen($values['twitter']['consumer_key']) > 255) {
            $this->clientError(
                // TRANS: Client error displayed when a consumer key is invalid because it is too long.
                _m('Invalid consumer key. Maximum length is 255 characters.')
            );
        }

        if (mb_strlen($values['twitter']['consumer_secret']) > 255) {
            $this->clientError(
                // TRANS: Client error displayed when a consumer secret is invalid because it is too long.
                _m('Invalid consumer secret. Maximum length is 255 characters.')
            );
        }
    }

    function isImportEnabled()
    {
        // Since daemon setup isn't automated yet...
        // @todo: if merged into main queues, detect presence of daemon config
        return true;
    }
}

class TwitterAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'twitteradminpanel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('twitteradminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart(
            'fieldset',
            array('id' => 'settings_twitter-application')
        );
        // TRANS: Fieldset legend for Twitter application settings.
        $this->out->element('legend', null, _m('Twitter application settings'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input(
            'consumer_key',
            // TRANS: Field label for Twitter assigned consumer key.
            _m('Consumer key'),
            // TRANS: Field title for Twitter assigned consumer key.
            _m('The consumer key assigned by Twitter.'),
            'twitter'
        );
        $this->unli();

        $this->li();
        $this->input(
            'consumer_secret',
            // TRANS: Field label for Twitter assigned consumer secret.
            _m('Consumer secret'),
            // TRANS: Field title for Twitter assigned consumer secret.
            _m('The consumer secret assigned by Twitter.'),
            'twitter'
        );
        $this->unli();

        $globalConsumerKey = common_config('twitter', 'global_consumer_key');
        $globalConsumerSec = common_config('twitter', 'global_consumer_secret');

        if (!empty($globalConsumerKey) && !empty($globalConsumerSec)) {
            $this->li();
            // TRANS: Form guide displayed when two required fields have already been provided.
            $this->out->element('p', 'form_guide', _m('Note: A global consumer key and secret are set.'));
            $this->unli();
        }

        $this->li();
        $this->input(
            'source',
            // TRANS: Field label for Twitter application name.
            _m('Integration source'),
            // TRANS: Field title for Twitter application name.
            _m('The name of your Twitter application.'),
            'integration'
        );
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart(
            'fieldset',
            array('id' => 'settings_twitter-options')
        );
        // TRANS: Fieldset legend for Twitter integration options.
        $this->out->element('legend', null, _m('Options'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();

        $this->out->checkbox(
            // TRANS: Checkbox label for global setting.
            'signin', _m('Enable "Sign-in with Twitter"'),
            (bool) $this->value('signin', 'twitter'),
            // TRANS: Checkbox title.
            _m('This allow users to login with their Twitter credentials.')
        );
        $this->unli();

        if (Event::handle('TwitterBridgeAdminImportControl')) {
            $this->li();
            $this->out->checkbox(
                // TRANS: Checkbox label for global setting.
                'enabled', _m('Enable Twitter import'),
                (bool) $this->value('enabled', 'twitterimport'),
                // TRANS: Checkbox title for global setting.
                _m('Allow users to import their Twitter friends\' timelines. Requires daemons to be manually configured.')
            );
            $this->unli();
        }

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text for saving the administrative Twitter bridge settings.
        $this->out->submit('submit', _m('BUTTON','Save'), 'submit', null,
        // TRANS: Button title for saving the administrative Twitter bridge settings.
        _m('Save the Twitter bridge settings.'));
    }
}
