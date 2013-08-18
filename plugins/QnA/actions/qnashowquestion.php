<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Show a question
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
 * @author    Zach Copley <zach@status.net>
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
 * Show a question
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnashowquestionAction extends ShownoticeAction
{
    protected $question = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        Action::prepare($argarray);

        $this->id = $this->trimmed('id');

        $this->question = QnA_Question::getKV('id', $this->id);

        if (empty($this->question)) {
            // TRANS: Client exception thrown trying to view a non-existing question.
            throw new ClientException(_m('No such question.'), 404);
        }

        $this->notice = $this->question->getNotice();

        if (empty($this->notice)) {
            // Did we used to have it, and it got deleted?
            // TRANS: Client exception thrown trying to view a non-existing question notice.
            throw new ClientException(_m('No such question notice.'), 404);
        }

        $this->user = User::getKV('id', $this->question->profile_id);

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to view a question of a non-existing user.
            throw new ClientException(_m('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (empty($this->profile)) {
            // TRANS: Server exception thrown trying to view a question for a user for which the profile could not be loaded.
            throw new ServerException(_m('User without a profile.'));
        }

        $this->avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);

        return true;
    }

    function showContent()
    {
        $this->elementStart('div', 'qna-full-question');
        $this->raw($this->question->asHTML());

        $answer = $this->question->getAnswers();

        $this->elementStart('div', 'qna-full-question-answers');

        $answerIds = array();

        // @fixme use a filtered stream!

        if (!empty($answer)) {
            while ($answer->fetch()) {
                $answerIds[] = $answer->getNotice()->id;
            }
        }

        if (count($answerIds) > 0) {
            $notice = Notice::multiGet('id', $answerIds);

            $nli = new NoticeList($notice, $this);
            $nli->show();
        }

        $user = common_current_user();

        if (!empty($user)) {
            $profile = $user->getProfile();
            $answer  = QnA_Question::getAnswer($profile);
            if (empty($answer)) {
                $form = new QnanewanswerForm($this, $this->question, false);
                $form->show();
            }
        }

        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Title of the page
     *
     * Used by Action class for layout.
     *
     * @return string page tile
     */
    function title()
    {
        return sprintf(
            // TRANS: Page title for a question.
            // TRANS: %1$s is the nickname of the user who asked the question, %2$s is the question.
            _m('%1$s\'s question: %2$s'),
            $this->user->nickname,
            $this->question->title
        );
    }

    /**
     * @fixme combine the notice time with question update time
     */
    function lastModified()
    {
        return Action::lastModified();
    }

    /**
     * @fixme combine the notice time with question update time
     */
    function etag()
    {
        return Action::etag();
    }
}
