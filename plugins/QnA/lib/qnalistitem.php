<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Adapter to show QnA in a nicer way
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
 * @category  QnA
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
 * An adapter to show QnA in a nicer way
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class QnAListItem extends NoticeListItemAdapter
{
    /**
     * Custom HTML output for our notices
     *
     * @param Notice $notice
     * @param HTMLOutputter $out
     */
    function showNotice(Notice $notice, $out)
    {
        switch ($notice->object_type) {
        case QnA_Question::OBJECT_TYPE:
            return $this->showNoticeQuestion($notice, $out);
        case QnA_Answer::OBJECT_TYPE:
            return $this->showNoticeAnswer($notice, $out);
        default:
            throw new Exception(
                // TRANS: Exception thrown when performing an unexpected action on a question.
                // TRANS: %s is the unpexpected object type.
                sprintf(_m('Unexpected type for QnA plugin: %s.'),
                        $notice->object_type
                )
            );
        }
    }

    function showNoticeQuestion(Notice $notice, $out)
    {
        $user = common_current_user();

        // @hack we want regular rendering, then just add stuff after that
        $nli = new NoticeListItem($notice, $out);
        $nli->showNotice();

        $out->elementStart('div', array('class' => 'e-content question-description'));

        $question = QnA_Question::getByNotice($notice);

        if (!empty($question)) {

            $form = new QnashowquestionForm($out, $question);
            $form->show();

        } else {
            // TRANS: Error message displayed when question data is not present.
            $out->text(_m('Question data is missing.'));
        }
        $out->elementEnd('div');

        // @fixme
        $out->elementStart('div', array('class' => 'e-content'));
    }

    function showNoticeAnswer(Notice $notice, $out)
    {
        $user = common_current_user();

        $answer   = QnA_Answer::getByNotice($notice);
        $question = $answer->getQuestion();

        $nli = new NoticeListItem($notice, $out);
        $nli->showNotice();

        $out->elementStart('div', array('class' => 'e-content answer-content'));

        if (!empty($answer)) {
            $form = new QnashowanswerForm($out, $answer);
            $form->show();
        } else {
            // TRANS: Error message displayed when answer data is not present.
            $out->text(_m('Answer data is missing.'));
        }

        $out->elementEnd('div');

        // @todo FIXME
        $out->elementStart('div', array('class' => 'e-content'));
    }
}
