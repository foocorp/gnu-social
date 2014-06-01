<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of notices
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('NOTICES_PER_SECTION', 6);

/**
 * Base class for sections showing lists of notices
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @todo migrate this to use a variant of NoticeList
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class NoticeSection extends Section
{
    protected $maxchars = 140;

    function showContent()
    {
        $notices = $this->getNotices();
        $cnt = 0;
        $this->out->elementStart('ol', 'notices xoxo');
        while ($notices->fetch() && ++$cnt <= NOTICES_PER_SECTION) {
            $this->showNotice($notices);
        }

        $this->out->elementEnd('ol');
        return ($cnt > NOTICES_PER_SECTION);
    }

    function getNotices()
    {
        return null;
    }

    function showNotice(Notice $notice)
    {
        $profile = $notice->getProfile();
        if (empty($profile)) {
            common_log(LOG_WARNING, sprintf("Notice %d has no profile",
                                            $notice->id));
            return;
        }
        $this->out->elementStart('li', 'hentry notice');
        $this->out->elementStart('div', 'entry-title');
        $this->out->elementStart('span', 'vcard author');
        $this->out->elementStart('a', array('title' => ($profile->fullname) ?
                                            $profile->fullname :
                                            $profile->nickname,
                                            'href' => $profile->profileurl,
                                            'class' => 'url'));
        $avatarUrl = $profile->avatarUrl(AVATAR_MINI_SIZE);
        $this->out->element('img', array('src' => $avatarUrl,
                                         'width' => AVATAR_MINI_SIZE,
                                         'height' => AVATAR_MINI_SIZE,
                                         'class' => 'avatar photo',
                                         'alt' =>  ($profile->fullname) ?
                                         $profile->fullname :
                                         $profile->nickname));
        $this->out->text(' ');
        $this->out->element('span', 'fn nickname', $profile->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');

        $this->out->elementStart('p', 'entry-content');
        $this->out->text(mb_strlen($notice->content) > $this->maxchars
            ? mb_substr($notice->content, 0, $this->maxchars) . '[…]'
            : $notice->content);
        $this->out->elementEnd('p');

        $this->out->elementStart('div', 'entry_content');
        $nli = new NoticeListItem($notice, $this->out);
        $nli->showNoticeLink();
        $this->out->elementEnd('div');

        if (!empty($notice->value)) {
            $this->out->elementStart('p');
            $this->out->text($notice->value);
            $this->out->elementEnd('p');
        }
        $this->out->elementEnd('div');
        $this->out->elementEnd('li');
    }
}
