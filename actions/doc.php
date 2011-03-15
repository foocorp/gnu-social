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

            $this->filename = $this->getFilename();

            if (empty($this->filename)) {
                // TRANS: Client exception thrown when requesting a document from the documentation that does not exist.
                // TRANS: %s is the non-existing document.
                throw new ClientException(sprintf(_('No such document "%s".'), $this->title), 404);
            }

            $c = file_get_contents($this->filename);

            $this->output = common_markup_to_html($c);

            Event::handle('EndLoadDoc', array($this->title, &$this->output));
        }
    }

    function getFilename()
    {
        $localDef = null;
        $local    = null;

        $site = StatusNet::currentSite();

        if (!empty($site) && file_exists(INSTALLDIR.'/local/doc-src/'.$site.'/'.$this->title)) {
            $localDef = INSTALLDIR.'/local/doc-src/'.$site.'/'.$this->title;

            $local = glob(INSTALLDIR.'/local/doc-src/'.$site.'/'.$this->title.'.*');
            if ($local === false) {
                // Some systems return false, others array(), if dir didn't exist.
                $local = array();
            }
        } else {
            if (file_exists(INSTALLDIR.'/local/doc-src/'.$this->title)) {
                $localDef = INSTALLDIR.'/local/doc-src/'.$this->title;
            }

            $local = glob(INSTALLDIR.'/local/doc-src/'.$this->title.'.*');
            if ($local === false) {
                $local = array();
            }
        }

        if (count($local) || isset($localDef)) {
            return $this->negotiateLanguage($local, $localDef);
        }

        if (file_exists(INSTALLDIR.'/doc-src/'.$this->title)) {
            $distDef = INSTALLDIR.'/doc-src/'.$this->title;
        }

        $dist = glob(INSTALLDIR.'/doc-src/'.$this->title.'.*');
        if ($dist === false) {
            $dist = array();
        }

        if (count($dist) || isset($distDef)) {
            return $this->negotiateLanguage($dist, $distDef);
        }

        return null;
    }

    function negotiateLanguage($filenames, $defaultFilename=null)
    {
        // XXX: do this better

        $langcode = common_language();

        foreach ($filenames as $filename) {
            if (preg_match('/\.'.$langcode.'$/', $filename)) {
                return $filename;
            }
        }

        return $defaultFilename;
    }
}
