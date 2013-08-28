<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Importer class for Delicious.com backups
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
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Importer class for Delicious bookmarks
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DeliciousBackupImporter extends QueueHandler
{
    /**
     * Transport of the importer
     *
     * @return string transport string
     */
    function transport()
    {
        return 'dlcsback';
    }

    /**
     * Import an in-memory bookmark list to a user's account
     *
     * Take a delicious.com backup file (same as Netscape bookmarks.html)
     * and import to StatusNet as Bookmark activities.
     *
     * The document format is terrible. It consists of a <dl> with
     * a bunch of <dt>'s, occasionally with <dd>'s adding descriptions.
     * There are sometimes <p>'s lost inside.
     *
     * @param array $data pair of user, text
     *
     * @return boolean success value
     */
    function handle($data)
    {
        list($user, $body) = $data;

        try {
            $doc = $this->importHTML($body);
        } catch (ClientException $cex) {
            // XXX: message to the user
            common_log(LOG_WARNING, $cex->getMessage());
            return true;
        }

        // If we can't parse it, it's no good

        if (empty($doc)) {
            return true;
        }

        $dls = $doc->getElementsByTagName('dl');

        if ($dls->length != 1) {
            // XXX: message to the user
            common_log(LOG_WARNING, 'Bad input file');
            return true;
        }

        $dl = $dls->item(0);

        $children = $dl->childNodes;

        $dt = null;

        for ($i = 0; $i < $children->length; $i++) {
            try {
                $child = $children->item($i);
                if ($child->nodeType != XML_ELEMENT_NODE) {
                    continue;
                }
                switch (strtolower($child->tagName)) {
                case 'dt':
                    // <dt> nodes contain primary information about a bookmark.
                    // We can't import the current one just yet though, since
                    // it may be followed by a <dd>.
                    if (!empty($dt)) {
                        // No DD provided
                        $this->importBookmark($user, $dt);
                        $dt = null;
                    }
                    $dt = $child;
                    break;
                case 'dd':
                    $dd = $child;

                    if (!empty($dt)) {
                        // This <dd> contains a description for the bookmark in
                        // the preceding <dt> node.
                        $saved = $this->importBookmark($user, $dt, $dd);
                    }

                    $dt = null;
                    $dd = null;
                    break;
                case 'p':
                    common_log(LOG_INFO, 'Skipping the <p> in the <dl>.');
                    break;
                default:
                    common_log(LOG_WARNING,
                               "Unexpected element $child->tagName ".
                               " found in import.");
                }
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                $dt = $dd = null;
            }
        }
        if (!empty($dt)) {
            // There was a final bookmark without a description.
            try {
                $this->importBookmark($user, $dt);
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Import a single bookmark
     *
     * Takes a <dt>/<dd> pair. The <dt> has a single
     * <a> in it with some non-standard attributes.
     *
     * A <dt><dt><dd> sequence will appear as a <dt> with
     * anothe <dt> as a child. We handle this case recursively.
     *
     * @param User       $user User to import data as
     * @param DOMElement $dt   <dt> element
     * @param DOMElement $dd   <dd> element
     *
     * @return Notice imported notice
     */
    function importBookmark($user, $dt, $dd = null)
    {
        $as = $dt->getElementsByTagName('a');

        if ($as->length == 0) {
            // TRANS: Client exception thrown when a bookmark in an import file is incorrectly formatted.
            throw new ClientException(_m("No <A> tag in a <DT>."));
        }

        $a = $as->item(0);

        $private = $a->getAttribute('private');

        if ($private != 0) {
            // TRANS: Client exception thrown when a bookmark in an import file is private.
            throw new ClientException(_m('Skipping private bookmark.'));
        }

        if (!empty($dd)) {
            $description = $dd->nodeValue;
        } else {
            $description = null;
        }
        $addDate = $a->getAttribute('add_date');

        $data = array(
            'profile_id' => $user->id,
            'title' => $a->nodeValue,
            'description' => $description,
            'url' => $a->getAttribute('href'),
            'tags' => $a->getAttribute('tags'),
            'created' => common_sql_date(intval($addDate))
        );

        $qm = QueueManager::get();
        $qm->enqueue($data, 'dlcsbkmk');
    }

    /**
     * Parse some HTML
     *
     * Hides the errors that the dom parser returns
     *
     * @param string $body Data to import
     *
     * @return DOMDocument parsed document
     */

    function importHTML($body)
    {
        // DOMDocument::loadHTML may throw warnings on unrecognized elements,
        // and notices on unrecognized namespaces.
        $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));
        $dom = new DOMDocument();
        $ok  = $dom->loadHTML($body);
        error_reporting($old);

        if ($ok) {
            foreach ($dom->getElementsByTagName('body') as $node) {
                $this->fixListsIn($node);
            }
            return $dom;
        } else {
            return null;
        }
    }


    function fixListsIn(DOMNode $body) {
        $toFix = array();

        foreach ($body->childNodes as $node) {
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $el = strtolower($node->nodeName);
                if ($el == 'dl') {
                    $toFix[] = $node;
                }
            }
        }

        foreach ($toFix as $node) {
            $this->fixList($node);
        }
    }

    function fixList(DOMNode $list) {
        $toFix = array();

        foreach ($list->childNodes as $node) {
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $el = strtolower($node->nodeName);
                if ($el == 'dt' || $el == 'dd') {
                    $toFix[] = $node;
                }
                if ($el == 'dl') {
                    // Sublist.
                    // Technically, these can only appear inside a <dd>...
                    $this->fixList($node);
                }
            }
        }

        foreach ($toFix as $node) {
            $this->fixListItem($node);
        }
    }

    function fixListItem(DOMNode $item) {
        // The HTML parser in libxml2 doesn't seem to properly handle
        // many cases of implied close tags, apparently because it doesn't
        // understand the nesting rules specified in the HTML DTD.
        //
        // This leads to sequences of adjacent <dt>s or <dd>s being incorrectly
        // interpreted as parent->child trees instead of siblings:
        //
        // When parsing this input: "<dt>aaa <dt>bbb"
        // should be equivalent to: "<dt>aaa </dt><dt>bbb</dt>"
        // but we're seeing instead: "<dt>aaa <dt>bbb</dt></dt>"
        //
        // It does at least know that going from dt to dd, or dd to dt,
        // should make a break.

        $toMove = array();

        foreach ($item->childNodes as $node) {
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $el = strtolower($node->nodeName);
                if ($el == 'dt' || $el == 'dd') {
                    // dt & dd cannot contain each other;
                    // This node was incorrectly placed; move it up a level!
                    $toMove[] = $node;
                }
                if ($el == 'dl') {
                    // Sublist.
                    // Technically, these can only appear inside a <dd>.
                    $this->fixList($node);
                }
            }
        }

        $parent = $item->parentNode;
        $next = $item->nextSibling;
        foreach ($toMove as $node) {
            $item->removeChild($node);
            $parent->insertBefore($node, $next);
            $this->fixListItem($node);
        }
    }
}
