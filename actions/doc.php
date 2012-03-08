<?php
/**
 * Documentation action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Documentation class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class DocAction extends Action
{
    var $output   = null;
    var $filename = null;
    var $title    = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->title  = $this->trimmed('title');
        if (!preg_match('/^[a-zA-Z0-9_-]*$/', $this->title)) {
            $this->title = 'help';
        }
        $this->output = null;

        $this->loadDoc();
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Page title
     *
     * Gives the page title of the document. Override default for hAtom entry.
     *
     * @return void
     */
    function showPageTitle()
    {
        $this->element('h1', array('class' => 'entry-title'), $this->title());
    }

    /**
     * Block for content.
     *
     * Overrides default from Action to wrap everything in an hAtom entry.
     *
     * @return void.
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

    /**
     * Display content.
     *
     * Shows the content of the document.
     *
     * @return void
     */
    function showContent()
    {
        $this->raw($this->output);
    }

    /**
     * Page title.
     *
     * Uses the title of the document.
     *
     * @return page title
     */
    function title()
    {
        return ucfirst($this->title);
    }

    /**
     * These pages are read-only.
     *
     * @param array $args unused.
     *
     * @return boolean read-only flag (false)
     */
    function isReadOnly($args)
    {
        return true;
    }

    function loadDoc()
    {
        if (Event::handle('StartLoadDoc', array(&$this->title, &$this->output))) {

            $paths = DocFile::defaultPaths();

            $docfile = DocFile::forTitle($this->title, $paths);

            if (empty($docfile)) {
                // TRANS: Client exception thrown when requesting a document from the documentation that does not exist.
                // TRANS: %s is the non-existing document.
                throw new ClientException(sprintf(_('No such document "%s".'), $this->title), 404);
            }

            $this->output = $docfile->toHTML();

            Event::handle('EndLoadDoc', array($this->title, &$this->output));
        }
    }

    function showLocalNav()
    {
        $menu = new DocNav($this);
        $menu->show();
    }
}

class DocNav extends Menu
{
    function show()
    {
        $stub = new HomeStubNav($this->action);
        $this->submenu(_m('MENU','Home'), $stub);

        $docs = new DocListNav($this->action);
        $this->submenu(_m('MENU','Docs'), $docs);
    }
}

class DocListNav extends Menu
{
    function getItems()
    {
        $items = array();

        if (Event::handle('StartDocsMenu', array(&$items))) {

            $items = array(array('doc',
                                 array('title' => 'help'),
                                 _m('MENU', 'Help'),
                                 _('Getting started'),
                                 'nav_doc_help'),
                           array('doc',
                                 array('title' => 'about'),
                                 _m('MENU', 'About'),
                                 _('About this site'),
                                 'nav_doc_about'),
                           array('doc',
                                 array('title' => 'faq'),
                                 _m('MENU', 'FAQ'),
                                 _('Frequently asked questions'),
                                 'nav_doc_faq'),
                           array('doc',
                                 array('title' => 'contact'),
                                 _m('MENU', 'Contact'),
                                 _('Contact info'),
                                 'nav_doc_contact'),
                           array('doc',
                                 array('title' => 'tags'),
                                 _m('MENU', 'Tags'),
                                 _('Using tags'),
                                 'nav_doc_tags'),
                           array('doc',
                                 array('title' => 'groups'),
                                 _m('MENU', 'Groups'),
                                 _('Using groups'),
                                 'nav_doc_groups'),
                           array('doc',
                                 array('title' => 'api'),
                                 _m('MENU', 'API'),
                                 _('RESTful API'),
                                 'nav_doc_api'));

            Event::handle('EndDocsMenu', array(&$items));
        }
        return $items;
    }
}
