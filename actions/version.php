<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
 *
 * Show version information for this software and plugins
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
 * @category Info
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Version info page
 *
 * A page that shows version information for this site. Helpful for
 * debugging, for giving credit to authors, and for linking to more
 * complete documentation for admins.
 *
 * @category Info
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @copyright 2009-2011 Free Software Foundation, Inc http://www.fsf.org
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */
class VersionAction extends Action
{
    var $pluginVersions = array();

    /**
     * Return true since we're read-only.
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Title for version page. %s is the StatusNet version.
        return sprintf(_("StatusNet %s"), STATUSNET_VERSION);
    }

    /**
     * Prepare to run
     *
     * Fire off an event to let plugins report their
     * versions.
     *
     * @param array $args array misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        parent::prepare($args);

        Event::handle('PluginVersion', array(&$this->pluginVersions));

        return true;
    }

    /**
     * Execute the action
     *
     * Shows a page with the version information in the
     * content area.
     *
     * @param array $args ignored.
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }


    /*
    * Override to add hentry, and content-inner classes
    *
    * @return void
    */
    function showContentBlock()
     {
         $this->elementStart('div', array('id' => 'content', 'class' => 'hentry'));
         $this->showPageTitle();
         $this->showPageNoticeBlock();
         $this->elementStart('div', array('id' => 'content_inner',
                                          'class' => 'entry-content'));
         // show the actual content (forms, lists, whatever)
         $this->showContent();
         $this->elementEnd('div');
         $this->elementEnd('div');
     }

    /*
    * Overrride to add entry-title class
    *
    * @return void
    */
    function showPageTitle() {
        $this->element('h1', array('class' => 'entry-title'), $this->title());
    }


    /**
     * Show version information
     *
     * @return void
     */
    function showContent()
    {
        $this->elementStart('p');

        // TRANS: Content part of StatusNet version page.
        // TRANS: %1$s is the engine name (StatusNet) and %2$s is the StatusNet version.
        $this->raw(sprintf(_('This site is powered by %1$s version %2$s, '.
                             'Copyright 2008-2011 StatusNet, Inc. '.
                             'and contributors.'),
                           XMLStringer::estring('a', array('href' => 'http://status.net/'),
                                                // TRANS: Engine name.
                                                _('StatusNet')),
                           STATUSNET_VERSION));
        $this->elementEnd('p');

        // TRANS: Header for StatusNet contributors section on the version page.
        $this->element('h2', null, _('Contributors'));

        sort($this->contributors);
        $this->element('p', null, implode(', ', $this->contributors));

        // TRANS: Header for StatusNet license section on the version page.
        $this->element('h2', null, _('License'));

        $this->element('p', null,
                       // TRANS: Content part of StatusNet version page.
                       _('StatusNet is free software: you can redistribute it and/or modify '.
                         'it under the terms of the GNU Affero General Public License as published by '.
                         'the Free Software Foundation, either version 3 of the License, or '.
                         '(at your option) any later version.'));

        $this->element('p', null,
                       // TRANS: Content part of StatusNet version page.
                       _('This program is distributed in the hope that it will be useful, '.
                         'but WITHOUT ANY WARRANTY; without even the implied warranty of '.
                         'MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the '.
                         'GNU Affero General Public License for more details.'));

        $this->elementStart('p');
        // TRANS: Content part of StatusNet version page.
        // TRANS: %s is a link to the AGPL license with link description "http://www.gnu.org/licenses/agpl.html".
        $this->raw(sprintf(_('You should have received a copy of the GNU Affero General Public License '.
                             'along with this program.  If not, see %s.'),
                           XMLStringer::estring('a', array('href' => 'http://www.gnu.org/licenses/agpl.html'),
                                                'http://www.gnu.org/licenses/agpl.html')));
        $this->elementEnd('p');

        // XXX: Theme information?

        if (count($this->pluginVersions)) {
            // TRANS: Header for StatusNet plugins section on the version page.
            $this->element('h2', null, _('Plugins'));

            $this->elementStart('table', array('id' => 'plugins_enabled'));

            $this->elementStart('thead');
            $this->elementStart('tr');
            // TRANS: Column header for plugins table on version page.
            $this->element('th', array('id' => 'plugin_name'), _m('HEADER','Name'));
            // TRANS: Column header for plugins table on version page.
            $this->element('th', array('id' => 'plugin_version'), _m('HEADER','Version'));
            // TRANS: Column header for plugins table on version page.
            $this->element('th', array('id' => 'plugin_authors'), _m('HEADER','Author(s)'));
            // TRANS: Column header for plugins table on version page.
            $this->element('th', array('id' => 'plugin_description'), _m('HEADER','Description'));
            $this->elementEnd('tr');
            $this->elementEnd('thead');

            $this->elementStart('tbody');
            foreach ($this->pluginVersions as $plugin) {
                $this->elementStart('tr');
                if (array_key_exists('homepage', $plugin)) {
                    $this->elementStart('th');
                    $this->element('a', array('href' => $plugin['homepage']),
                                   $plugin['name']);
                    $this->elementEnd('th');
                } else {
                    $this->element('th', null, $plugin['name']);
                }

                $this->element('td', null, $plugin['version']);

                if (array_key_exists('author', $plugin)) {
                    $this->element('td', null, $plugin['author']);
                }

                if (array_key_exists('rawdescription', $plugin)) {
                    $this->elementStart('td');
                    $this->raw($plugin['rawdescription']);
                    $this->elementEnd('td');
                } else if (array_key_exists('description', $plugin)) {
                    $this->element('td', null, $plugin['description']);
                }
                $this->elementEnd('tr');
            }
            $this->elementEnd('tbody');
            $this->elementEnd('table');
        }

    }

    var $contributors = array('Evan Prodromou (StatusNet)',
                              'Zach Copley (StatusNet)',
                              'Earle Martin (StatusNet)',
                              'Marie-Claude Doyon (StatusNet)',
                              'Sarven Capadisli (StatusNet)',
                              'Robin Millette (StatusNet)',
                              'Ciaran Gultnieks',
                              'Michael Landers',
                              'Ori Avtalion',
                              'Garret Buell',
                              'Mike Cochrane',
                              'Matthew Gregg',
                              'Florian Biree',
                              'Erik Stambaugh',
                              'drry',
                              'Gina Haeussge',
                              'Tryggvi Björgvinsson',
                              'Adrian Lang',
                              'Meitar Moscovitz',
                              'Sean Murphy',
                              'Leslie Michael Orchard',
                              'Eric Helgeson',
                              'Ken Sedgwick',
                              'Brian Hendrickson',
                              'Tobias Diekershoff',
                              'Dan Moore',
                              'Fil',
                              'Jeff Mitchell',
                              'Brenda Wallace',
                              'Jeffery To',
                              'Federico Marani',
                              'Craig Andrews',
                              'mEDI',
                              'Brett Taylor',
                              'Brigitte Schuster',
                              'Brion Vibber (StatusNet)',
                              'Siebrand Mazeland',
                              'Samantha Doherty (StatusNet)');
}
