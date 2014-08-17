<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Adapter to show polls in a nicer way
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
 * @category  Poll
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
 * An adapter to show polls in a nicer way
 *
 * @category  Poll
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class PollListItem extends NoticeListItemAdapter
{
    // @fixme which domain should we use for these namespaces?
    const POLL_OBJECT          = 'http://activityschema.org/object/poll';
    const POLL_RESPONSE_OBJECT = 'http://activityschema.org/object/poll-response';

    function showNotice()
    {
        $notice = $this->nli->notice;
        $out = $this->nli->out;

        switch ($notice->object_type) {
        case self::POLL_OBJECT:
            return $this->showNoticePoll($notice, $out);
        case self::POLL_RESPONSE_OBJECT:
            return $this->showNoticePollResponse($notice, $out);
        default:
            // TRANS: Exception thrown when performing an unexpected action on a poll.
            // TRANS: %s is the unexpected object type.
            throw new Exception(sprintf(_m('Unexpected type for poll plugin: %s.'), $notice->object_type));
        }
    }

    function showNoticePoll(Notice $notice, $out)
    {
        $user = common_current_user();

        // @hack we want regular rendering, then just add stuff after that
        $nli = new NoticeListItem($notice, $out);
        $nli->showNotice();

        $out->elementStart('div', array('class' => 'e-content poll-content'));
        $poll = Poll::getByNotice($notice);
        if ($poll) {
            if ($user) {
                $profile = $user->getProfile();
                $response = $poll->getResponse($profile);
                if ($response) {
                    // User has already responded; show the results.
                    $form = new PollResultForm($poll, $out);
                } else {
                    $form = new PollResponseForm($poll, $out);
                }
                $form->show();
            }
        } else {
            // TRANS: Error text displayed if no poll data could be found.
            $out->text(_m('Poll data is missing'));
        }
        $out->elementEnd('div');

        // @fixme
        $out->elementStart('div', array('class' => 'e-content'));
    }

    function showNoticePollResponse(Notice $notice, $out)
    {
        $user = common_current_user();

        // @hack we want regular rendering, then just add stuff after that
        $nli = new NoticeListItem($notice, $out);
        $nli->showNotice();

        // @fixme
        $out->elementStart('div', array('class' => 'e-content'));
    }
}
