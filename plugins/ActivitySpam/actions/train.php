<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * Train a notice as spam
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
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Train a notice as spam
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class TrainAction extends Action
{
    protected $notice = null;
    protected $filter = null;
    protected $category = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        parent::prepare($argarray);

        // User must be logged in.

        $user = common_current_user();

        if (empty($user)) {
            throw new ClientException(_("You must be logged in to train spam."), 403);
        }

        // User must have the right to review spam

        if (!$user->hasRight(ActivitySpamPlugin::TRAINSPAM)) {
            throw new ClientException(_('You cannot review spam on this site.'), 403);
        }

        $id = $this->trimmed('notice');

        $this->notice = Notice::getKV('id', $id);

        if (empty($this->notice)) {
            throw new ClientException(_("No such notice."));
        }

        $this->checkSessionToken();

        $filter = null;

        Event::handle('GetSpamFilter', array(&$filter));

        if (empty($filter)) {
            throw new ServerException(_("No spam filter configured."));
        }

        $this->filter = $filter;

        $this->category = $this->trimmed('category');

        if ($this->category !== SpamFilter::SPAM &&
            $this->category !== SpamFilter::HAM)
        {
            throw new ClientException(_("No such category."));
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
        // Train

        $this->filter->trainOnError($this->notice, $this->category);

        // Re-test

        $result = $this->filter->test($this->notice);

        // Update or insert

        $score = Spam_score::save($this->notice, $result);

        // Show new toggle form

        if ($this->category === SpamFilter::SPAM) {
            $form = new TrainHamForm($this, $this->notice);
        } else {
            $form = new TrainSpamForm($this, $this->notice);
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title for page on which favorite notices can be unfavourited.
            $this->element('title', null, _('Disfavor favorite.'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect(common_local_url('spam'), 303);
        }
    }
}
