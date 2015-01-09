<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections (sidebar widgets)
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL') && !defined('STATUSNET')) { exit(1); }

/**
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
abstract class Section extends Widget
{
    /**
     * Show the form
     *
     * Uses a recipe to output the form.
     *
     * @return void
     * @see Widget::show()
     */
    function show()
    {
        $this->out->elementStart('div',
                                 array('id' => $this->divId(),
                                       'class' => 'section'));

        $this->showTitle();

        $have_more = $this->showContent();

        if ($have_more) {
            $this->showMore();
        }

        $this->out->elementEnd('div');
    }

    function showTitle()
    {
        $link = $this->link();
        if (!empty($link)) {
            $this->out->elementStart('h2');
            $this->out->element('a', array('href' => $link), $this->title());
            $this->out->elementEnd('h2');
        } else {
            $this->out->element('h2', null,
                                $this->title());
        }
    }

    function showMore()
    {
        $this->out->elementStart('p');
        $this->out->element('a', array('href' => $this->moreUrl(),
                                       'class' => 'more'),
                            $this->moreTitle());
        $this->out->elementEnd('p');
    }

    abstract public function divId();

    function title()
    {
        // TRANS: Default title for section/sidebar widget.
        return _('Untitled section');
    }

    function link()
    {
        return null;
    }

    function showContent()
    {
        $this->out->element('p', null,
                            // TRANS: Default content for section/sidebar widget.
                            _('(None)'));
        return false;
    }

    function moreUrl()
    {
        return null;
    }

    function moreTitle()
    {
        // TRANS: Default "More..." title for section/sidebar widget.
        return _('More...');
    }
}
