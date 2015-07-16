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
 * Widget to show a list of OAuth applications
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApplicationList extends Widget
{
    /** Current application, application query */
    var $application = null;

    /** Owner of this list */
    var $owner = null;

    function __construct($application, Profile $owner, Action $out=null)
    {
        parent::__construct($out);

        $this->application = $application;
        $this->owner       = $owner;
    }

    function show()
    {
        $this->out->elementStart('ul', 'applications');

        $cnt = 0;

        while ($this->application->fetch()) {
            $cnt++;
            if($cnt > APPS_PER_PAGE) {
                break;
            }
            $this->showApplication();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showApplication()
    {
        $this->out->elementStart('li', array('class' => 'application h-entry',
                                             'id'    => 'oauthclient-' . $this->application->id));

        $this->out->elementStart('a', array('href' => common_local_url('showapplication',
                                                                       array('id' => $this->application->id)),
                                            'class' => 'h-card'));

        if (!empty($this->application->icon)) {
            $this->out->element('img', array('src' => $this->application->icon,
                                             'class' => 'avatar u-photo'));
        }

        $this->out->text($this->application->name);
        $this->out->elementEnd('a');

        $this->out->raw(' by ');

        $this->out->element('a', array('href' => $this->application->homepage,
                                       'class' => 'u-url'),
                            $this->application->organization);

        $this->out->element('p', 'note', $this->application->description);
        $this->out->elementEnd('li');

    }

    /* Override this in subclasses. */
    function showOwnerControls()
    {
        return;
    }
}
