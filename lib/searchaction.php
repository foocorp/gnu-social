<?php
/**
 * Base search action class.
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
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

require_once INSTALLDIR.'/lib/searchgroupnav.php';

/**
 * Base search action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class SearchAction extends Action
{
    /**
     * Return true if read only.
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showTop($arr=null)
    {
        $error = null;
        if ($arr) {
            $error = $arr[1];
        }
        if (!empty($error)) {
            $this->element('p', 'error', $error);
        } else {
            $instr = $this->getInstructions();
            $output = common_markup_to_html($instr);
            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    function title()
    {
        return null;
    }

    function showContent() {
        $this->showTop();
        $this->showForm();
    }

    function showForm($error=null)
    {
        $q = $this->trimmed('q');
        $page = $this->trimmed('page', 1);
        $this->elementStart('form', array('method' => 'get',
                                           'id' => 'form_search',
                                           'class' => 'form_settings',
                                           'action' => common_local_url($this->trimmed('action'))));
        $this->elementStart('fieldset');
        // TRANS: Fieldset legend for the search form.
        $this->element('legend', null, _('Search site'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        if (!common_config('site', 'fancy')) {
            $this->hidden('action', $this->trimmed('action'));
        }
        // TRANS: Used as a field label for the field where one or more keywords
        // TRANS: for searching can be entered.
        $this->input('q', _('Keyword(s)'), $q);
        // TRANS: Button text for searching site.
        $this->element('input', array('type'=>'submit', 'class'=>'submit', 'value'=>_m('BUTTON','Search')));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
        if ($q) {
            $this->showResults($q, $page);
        }
    }

    function searchSuggestions($q) {
        // Don't change these long strings to HEREDOC; xgettext won't pick them up.
        // TRANS: Standard search suggestions shown when a search does not give any results.
        $message = _("* Make sure all words are spelled correctly.
* Try different keywords.
* Try more general keywords.
* Try fewer keywords.");
            $message .= "\n";

        if (!common_config('site', 'private')) {
            $qe = urlencode($q);
            $message .= "\n";
            // Don't change these long strings to HEREDOC; xgettext won't pick them up.
            // TRANS: Standard search suggestions shown when a search does not give any results.
            $message .= sprintf(_("You can also try your search on other engines:

* [Google](http://www.google.com/search?q=site%%3A%%%%site.server%%%%+%s)
* [Yahoo](http://search.yahoo.com/search?p=site%%3A%%%%site.server%%%%+%s)
"), $qe, $qe);
            $message .= "\n";
        }
        $this->elementStart('div', 'help instructions');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}
