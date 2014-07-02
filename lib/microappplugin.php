<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Superclass for microapp plugin
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
 * @category  Microapp
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
 * Superclass for microapp plugins
 *
 * This class lets you define micro-applications with different kinds of activities.
 *
 * The applications work more-or-less like other
 *
 * @category  Microapp
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class MicroAppPlugin extends ActivityHandlerPlugin
{
    /**
     * Returns a localized string which represents this micro-app,
     * to be shown to users selecting what type of post to make.
     * This is paired with the key string in $this->tag().
     *
     * All micro-app classes must override this method.
     *
     * @return string
     */
    abstract function appTitle();

    /**
     * Returns a key string which represents this micro-app in HTML
     * ids etc, as when offering selection of what type of post to make.
     * This is paired with the user-visible localizable $this->appTitle().
     *
     * All micro-app classes must override this method.
     */
    abstract function tag();

    /**
     * When building the primary notice form, we'll fetch also some
     * alternate forms for specialized types -- that's you!
     *
     * Return a custom Widget or Form object for the given output
     * object, and it'll be included in the HTML output. Beware that
     * your form may be initially hidden.
     *
     * All micro-app classes must override this method.
     *
     * @param HTMLOutputter $out
     * @return Widget
     */
    abstract function entryForm($out);

    /**
     *
     */
    public function newFormAction() {
        // such as 'newbookmark' or 'newevent' route
        return 'new'.$this->tag();
    }

    /**
     * Output the HTML for this kind of object in a list
     *
     * @param NoticeListItem $nli The list item being shown.
     *
     * @return boolean hook value
     *
     * @fixme WARNING WARNING WARNING this closes a 'div' that is implicitly opened in BookmarkPlugin's showNotice implementation
     */
    function onStartShowNoticeItem($nli)
    {
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $adapter = $this->adaptNoticeListItem($nli);

        if (!empty($adapter)) {
            $adapter->showNotice();
            $adapter->showNoticeAttachments();
            $adapter->showNoticeInfo();
            $adapter->showNoticeOptions();
        } else {
            $this->oldShowNotice($nli);
        }

        return false;
    }

    /**
     * Given a notice list item, returns an adapter specific
     * to this plugin.
     *
     * @param NoticeListItem $nli item to adapt
     *
     * @return NoticeListItemAdapter adapter or null
     */
    function adaptNoticeListItem($nli)
    {
      return null;
    }

    function oldShowNotice($nli)
    {
        $out = $nli->out;
        $notice = $nli->notice;

        try {
            $this->showNotice($notice, $out);
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            // try to fall back
            $out->elementStart('div');
            $nli->showAuthor();
            $nli->showContent();
        }

        $nli->showNoticeLink();
        $nli->showNoticeSource();
        $nli->showNoticeLocation();
        $nli->showContext();
        $nli->showRepeat();

        $out->elementEnd('div');

        $nli->showNoticeOptions();
    }

    function onStartShowEntryForms(&$tabs)
    {
        $tabs[$this->tag()] = array('title' => $this->appTitle(),
                                    'href'  => common_local_url($this->newFormAction()),
                                   );
        return true;
    }

    function onStartMakeEntryForm($tag, $out, &$form)
    {
        if ($tag == $this->tag()) {
            $form = $this->entryForm($out);
            return false;
        }

        return true;
    }

    function showNotice($notice, $out)
    {
        // TRANS: Server exception thrown when a micro app plugin developer has not done his job too well.
        throw new ServerException(_('You must implement either adaptNoticeListItem() or showNotice().'));
    }
}
