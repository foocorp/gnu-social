<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Secondary menu, shown at foot of all pages
 * 
 * PHP version 5
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
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Secondary menu, shown at the bottom of all pages
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class SecondaryNav extends Menu
{
    function show()
    {
        $this->out->elementStart('ul', array('class' => 'nav',
                                             'id' => 'site_nav_global_secondary'));
        if (Event::handle('StartSecondaryNav', array($this->action))) {
            $this->out->menuItem(common_local_url('doc', array('title' => 'help')),
                                 // TRANS: Secondary navigation menu option leading to help on StatusNet.
                                 _('Help'));
            $this->out->menuItem(common_local_url('doc', array('title' => 'about')),
                                 // TRANS: Secondary navigation menu option leading to text about StatusNet site.
                                 _('About'));
            $this->out->menuItem(common_local_url('doc', array('title' => 'faq')),
                                 // TRANS: Secondary navigation menu option leading to Frequently Asked Questions.
                                 _('FAQ'));
            $bb = common_config('site', 'broughtby');
            if (!empty($bb)) {
                $this->out->menuItem(common_local_url('doc', array('title' => 'tos')),
                                     // TRANS: Secondary navigation menu option leading to Terms of Service.
                                     _('TOS'));
            }
            $this->out->menuItem(common_local_url('doc', array('title' => 'privacy')),
                                 // TRANS: Secondary navigation menu option leading to privacy policy.
                                 _('Privacy'));
            $this->out->menuItem(common_local_url('doc', array('title' => 'source')),
                                 // TRANS: Secondary navigation menu option. Leads to information about StatusNet and its license.
                                 _('Source'));
            $this->out->menuItem(common_local_url('version'),
                                 // TRANS: Secondary navigation menu option leading to version information on the StatusNet site.
                                 _('Version'));
            $this->out->menuItem(common_local_url('doc', array('title' => 'contact')),
                                 // TRANS: Secondary navigation menu option leading to e-mail contact information on the
                                 // TRANS: StatusNet site, where to report bugs, ...
                                 _('Contact'));
            $this->out->menuItem(common_local_url('doc', array('title' => 'badge')),
                                 // TRANS: Secondary navigation menu option. Leads to information about embedding a timeline widget.
                                 _('Badge'));
            Event::handle('EndSecondaryNav', array($this->action));
        }
        $this->out->elementEnd('ul');
    }
}