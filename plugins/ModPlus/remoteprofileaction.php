<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class RemoteProfileAction extends ShowstreamAction
{
    function prepare($args)
    {
        Action::prepare($args); // skip the ProfileAction code and replace it...

        $id = $this->arg('id');
        $this->user = false;
        $this->profile = Profile::getKV('id', $id);

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_m('User has no profile.'));
            return false;
        }

        $user = User::getKV('id', $this->profile->id);
        if ($user) {
            // This is a local user -- send to their regular profile.
            $url = common_local_url('showstream', array('nickname' => $user->nickname));
            common_redirect($url);
            return false;
        }

        $this->tag = $this->trimmed('tag');
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        common_set_returnto($this->selfUrl());

        $p = Profile::current();
        if (empty($this->tag)) {
            $stream = new ProfileNoticeStream($this->profile, $p);
        } else {
            $stream = new TaggedProfileNoticeStream($this->profile, $this->tag, $p);
        }
        $this->notice = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        return true;
    }

    function handle($args)
    {
        // skip yadis thingy
        $this->showPage();
    }

    function title()
    {
        $base = $this->profile->getBestName();
        $host = parse_url($this->profile->profileurl, PHP_URL_HOST);
        // TRANS: Remote profile action page title.
        // TRANS: %1$s is a username, %2$s is a hostname.
        return sprintf(_m('%1$s on %2$s'), $base, $host);
    }

    /**
     * Instead of showing notices, link to the original offsite profile.
     */
    function showNotices()
    {
        $url = $this->profile->profileurl;
        $host = parse_url($url, PHP_URL_HOST);
        $markdown = sprintf(
                // TRANS: Message on remote profile page.
                // TRANS: This message contains Markdown links in the form [description](link).
                // TRANS: %1$s is a profile nickname, %2$s is a hostname, %3$s is a URL.
                _m('This remote profile is registered on another site; see [%1$s\'s original profile page on %2$s](%3$s).'),
                $this->profile->nickname,
                $host,
                $url);
        $html = common_markup_to_html($markdown);
        $this->raw($html);

        if ($this->profile->hasRole(Profile_role::SILENCED)) {
            // TRANS: Message on blocked remote profile page.
            $markdown = _m('Site moderators have silenced this profile, which prevents delivery of new messages to any users on this site.');
            $this->raw(common_markup_to_html($markdown));
        }else{

            $pnl = null;
            if (Event::handle('ShowStreamNoticeList', array($this->notice, $this, &$pnl))) {
                $pnl = new ProfileNoticeList($this->notice, $this);
            }
            $cnt = $pnl->show();
            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }

            $args = array('id' => $this->profile->id);
            if (!empty($this->tag))
            {
                $args['tag'] = $this->tag;
            }
            $this->pagination($this->page>1, $cnt>NOTICES_PER_PAGE, $this->page,
                              'remoteprofile', $args);

        }
    }

    function getFeeds()
    {
        // none
    }

    /**
     * Don't do various extra stuff, and also trim some things to avoid crawlers.
     */
    function extraHead()
    {
        $this->element('meta', array('name' => 'robots',
                                     'content' => 'noindex,nofollow'));
    }

    function showLocalNav()
    {
        // skip
    }

    function showSections()
    {
        // skip
    }

    function showStatistics()
    {
        // skip
    }
}
