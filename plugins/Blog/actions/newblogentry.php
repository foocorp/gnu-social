<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Save a new blog entry
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
 * @category  Blog
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
 * Save a new blog entry
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class NewblogentryAction extends Action
{
    protected $user;
    protected $title;
    protected $content;
    
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

        if (!$this->isPost()) {
            throw new ClientException(_('Must be a POST.'), 405);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown when trying to post a blog entry while not logged in.
            throw new ClientException(_m('Must be logged in to post a blog entry.'),
                                      403);
        }

        $this->checkSessionToken();
        
        $this->title = $this->trimmed('title');

        if (empty($this->title)) {
            // TRANS: Client exception thrown when trying to post a blog entry without providing a title.
            throw new ClientException(_m('Title required.'));
        }

        $this->content = $this->trimmed('content');

        if (empty($this->content)) {
            // TRANS: Client exception thrown when trying to post a blog entry without providing content.
            throw new ClientException(_m('Content required.'));
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
        $options = array();

        // Does the heavy-lifting for getting "To:" information

        ToSelector::fillOptions($this, $options);

        $options['source'] = 'web';
            
        $profile = $this->user->getProfile();

        $saved = Blog_entry::saveNew($profile,
                                    $this->title,
                                    $this->content,
                                    $options);
        
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _m('Blog entry saved'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $nli = new NoticeListItem($saved, $this);
            $nli->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect($saved->getUrl(), 303);
        }
    }
}
