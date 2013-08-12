<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Site notice administration panel
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

require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

/**
 * Update the site-wide notice text
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SitenoticeadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for site-wide notice tab in admin panel.
        return _('Site Notice');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        // TRANS: Instructions for site-wide notice tab in admin panel.
        return _('Edit site-wide message');
    }

    /**
     * Show the site notice admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new SiteNoticeAdminPanelForm($this);
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
        $siteNotice = $this->trimmed('site-notice');

        // assert(all values are valid);
        // This throws an exception on validation errors

        $this->validate($siteNotice);

        $config = new Config();

        $result = Config::save('site', 'notice', $siteNotice);

        if (!$result) {
            // TRANS: Server error displayed when saving a site-wide notice was impossible.
            $this->ServerError(_('Unable to save site notice.'));
        }
    }

    function validate(&$siteNotice)
    {
        // Validate notice text

        if (mb_strlen($siteNotice) > 255)  {
            $this->clientError(
                // TRANS: Client error displayed when a site-wide notice was longer than allowed.
                _('Maximum length for the site-wide notice is 255 characters.')
            );
        }

        // scrub HTML input

        $config = array(
            'safe' => 1,
            'deny_attribute' => 'id,style,on*'
        );

        $siteNotice = htmLawed($siteNotice, $config);
    }
}

class SiteNoticeAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_site_notice_admin_panel';
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
        return common_local_url('sitenoticeadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li');
        $this->out->textarea(
            'site-notice',
            // TRANS: Label for site-wide notice text field in admin panel.
            _('Site notice text'),
            common_config('site', 'notice'),
            // TRANS: Tooltip for site-wide notice text field in admin panel.
            _('Site-wide notice text (255 characters maximum; HTML allowed)')
        );
        $this->out->elementEnd('li');

        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit(
            'submit',
            // TRANS: Button text for saving site notice in admin panel.
            _m('BUTTON','Save'),
            'submit',
            null,
            // TRANS: Button title to save site notice in admin panel.
            _('Save site notice.')
        );
    }
}
