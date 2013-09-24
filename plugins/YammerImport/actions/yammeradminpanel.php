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

class YammeradminpanelAction extends AdminPanelAction
{
    private $runner;

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for Yammer import administration panel.
        return _m('Yammer Import');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for Yammer import administration panel.
        return _m('This Yammer import tool is still undergoing testing, ' .
                  'and is incomplete in some areas. ' .
                'Currently user subscriptions and group memberships are not ' .
                'transferred; in the future this may be supported for ' .
                'imports done by verified administrators on the Yammer side.');
    }

    function prepare($args)
    {
        $ok = parent::prepare($args);

        $this->subaction = $this->trimmed('subaction');
        $this->runner = YammerRunner::init();

        return $ok;
    }

    function handle($args)
    {
        // @fixme move this to saveSettings and friends?
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            StatusNet::setApi(true); // short error pages :P
            $this->checkSessionToken();
            if ($this->subaction == 'change-apikey') {
                $form = new YammerApiKeyForm($this);
            } else if ($this->subaction == 'apikey') {
                if ($this->saveKeys()) {
                    $form = new YammerAuthInitForm($this, $this->runner);
                } else {
                    $form = new YammerApiKeyForm($this);
                }
            } else if ($this->subaction == 'authinit') {
                // hack
                if ($this->arg('change-apikey')) {
                    $form = new YammerApiKeyForm($this);
                } else {
                    $url = $this->runner->requestAuth();
                    $form = new YammerAuthVerifyForm($this, $this->runner);
                }
            } else if ($this->subaction == 'authverify') {
                $this->runner->saveAuthToken($this->trimmed('verify_token'));

                // Haho! Now we can make THE FUN HAPPEN
                $this->runner->startBackgroundImport();

                $form = new YammerProgressForm($this, $this->runner);
            } else if ($this->subaction == 'pause-import') {
                // TRANS: Error message about an import job being paused from the admin panel.
                $this->runner->recordError(_m('Paused from admin panel.'));
                $form = $this->statusForm();
            } else if ($this->subaction == 'continue-import') {
                $this->runner->clearError();
                $this->runner->startBackgroundImport();
                $form = $this->statusForm();
            } else if ($this->subaction == 'abort-import') {
                $this->runner->reset();
                $form = $this->statusForm();
            } else if ($this->subaction == 'progress') {
                $form = $this->statusForm();
            } else {
                // TRANS: Client exception thrown when encountering an unhandled sub action.
                throw new ClientException(_m('Invalid POST'));
            }
            return $this->showAjaxForm($form);
        }
        return parent::handle($args);
    }

    function saveKeys()
    {
        $key = $this->trimmed('consumer_key');
        $secret = $this->trimmed('consumer_secret');
        Config::save('yammer', 'consumer_key', $key);
        Config::save('yammer', 'consumer_secret', $secret);

        return !empty($key) && !empty($secret);
    }

    function showAjaxForm($form)
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Page title for Yammer import administration panel.
        $this->element('title', null, _m('Yammer import'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $form->show();
        $this->elementEnd('body');
        $this->endHTML();
    }

    /**
     * Fetch the appropriate form for our current state.
     * @return Form
     */
    function statusForm()
    {
        if (!(common_config('yammer', 'consumer_key'))
            || !(common_config('yammer', 'consumer_secret'))) {
            return new YammerApiKeyForm($this);
        }
        switch($this->runner->state())
        {
            case 'init':
                return new YammerAuthInitForm($this, $this->runner);
            case 'requesting-auth':
                return new YammerAuthVerifyForm($this, $this->runner);
            default:
                return new YammerProgressForm($this, $this->runner);
        }
    }

    /**
     * Show the Yammer admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $this->elementStart('fieldset');
        $this->statusForm()->show();
        $this->elementEnd('fieldset');
    }

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink(Plugin::staticPath('YammerImport', 'css/admin.css'), null, 'screen, projection, tv');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->script(Plugin::staticPath('YammerImport', 'js/yammer-admin.js'));
    }
}
