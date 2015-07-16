<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List the OAuth applications that a user has registered with this instance
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
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Show a user's registered OAuth applications
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class OauthappssettingsAction extends SettingsAction
{
    protected $page = null;

    protected function doPreparation()
    {
        $this->page = $this->int('page') ?: 1;
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        // TRANS: Page title for OAuth applications
        return _('OAuth applications');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        // TRANS: Page instructions for OAuth applications
        return _('Applications you have registered');
    }

    function showContent()
    {
        $offset = ($this->page - 1) * APPS_PER_PAGE;
        $limit  =  APPS_PER_PAGE + 1;

        $application = new Oauth_application();
        $application->owner = $this->scoped->getID();
        $application->whereAdd("name != 'anonymous'");
        $application->limit($offset, $limit);
        $application->orderBy('created DESC');
        $application->find();

        $cnt = 0;

        if ($application) {
            $al = new ApplicationList($application, $this->scoped, $this);
            $cnt = $al->show();
            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }
        }

        $this->elementStart('p', array('id' => 'application_register'));
        $this->element('a',
            array('href' => common_local_url('newapplication'),
                  'class' => 'more'
            ),
            // TRANS: Link description to add a new OAuth application.
            'Register a new application');
        $this->elementEnd('p');

        $this->pagination(
            $this->page > 1,
            $cnt > APPS_PER_PAGE,
            $this->page,
            'oauthappssettings'
        );
    }

    function showEmptyListMessage()
    {
        // TRANS: Empty list message on page with OAuth applications. Markup allowed
        $message = sprintf(_('You have not registered any applications yet.'));

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}
