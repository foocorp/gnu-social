<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of OAuth applications
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
 * @category  Application
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget to show a list of connected OAuth clients
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ConnectedAppsList extends Widget
{
    /** Current connected application query */
    var $connection = null;

    /** Owner of this list */
    var $owner = null;

    function __construct($connection, Profile $owner, Action $out=null)
    {
        parent::__construct($out);

        common_debug("ConnectedAppsList constructor");

        $this->connection = $connection;
        $this->owner      = $owner;
    }

    /* Override this in subclasses. */
    function showOwnerControls()
    {
        return;
    }

    function show()
    {
        $this->out->elementStart('ul', 'applications');

        $cnt = 0;

        while ($this->connection->fetch()) {
            $cnt++;
            if($cnt > APPS_PER_PAGE) {
                break;
            }
            $this->showConnection();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showConnection()
    {
        $app = Oauth_application::getKV('id', $this->connection->application_id);

        $this->out->elementStart('li', array('class' => 'application h-entry',
                                             'id'    => 'oauthclient-' . $app->id));

        $this->out->elementStart('a', array('href' => $app->source_url,
                                            'class' => 'h-card p-name'));

        if (!empty($app->icon)) {
            $this->out->element('img', array('src' => $app->icon,
                                             'class' => 'avatar u-photo'));
        }
        if ($app->name != 'anonymous') {
            $this->out->text($app->name);
        } else {
            // TRANS: Name for an anonymous application in application list.
            $this->out->element('span', 'p-name', _('Unknown application'));
        }
        $this->out->elementEnd('a');

        if ($app->name != 'anonymous') {
            // @todo FIXME: i18n trouble.
            // TRANS: Message has a leading space and a trailing space. Used in application list.
            // TRANS: Before this message the application name is put, behind it the organisation that manages it.
            $this->out->raw(_(' by '));

            $this->out->element('a', array('href' => $app->homepage,
                                           'class' => 'h-card'),
                                $app->organization);
        }

        // TRANS: Application access type
        $readWriteText = _('read-write');
        // TRANS: Application access type
        $readOnlyText = _('read-only');

        $access = ($this->connection->access_type & Oauth_application::$writeAccess)
            ? $readWriteText : $readOnlyText;
        $modifiedDate = common_date_string($this->connection->modified);
        // TRANS: Used in application list. %1$s is a modified date, %2$s is access type ("read-write" or "read-only")
        $txt = sprintf(_('Approved %1$s - "%2$s" access.'), $modifiedDate, $access);

        // @todo FIXME: i18n trouble.
        $this->out->raw(" - $txt");
        if (!empty($app->description)) {
            $this->out->element(
                'p', array('class' => 'application_description'),
                $app->description
            );
        }
        $this->out->element(
            'p', array(
            'class' => 'access_token'),
            // TRANS: Access token in the application list.
            // TRANS: %s are the first 7 characters of the access token.
            sprintf(_('Access token starting with: %s'), substr($this->connection->token, 0, 7))
        );

        $this->out->elementStart(
            'form',
            array(
                'id' => 'form_revoke_app',
                'class' => 'form_revoke_app',
                'method' => 'POST',
                'action' => common_local_url('oauthconnectionssettings')
            )
        );
        $this->out->elementStart('fieldset');
        $this->out->hidden('oauth_token', $this->connection->token);
        $this->out->hidden('token', common_session_token());
        // TRANS: Button label in application list to revoke access to user data.
        $this->out->submit('revoke', _m('BUTTON','Revoke'));
        $this->out->elementEnd('fieldset');
        $this->out->elementEnd('form');

        $this->out->elementEnd('li');
    }
}
