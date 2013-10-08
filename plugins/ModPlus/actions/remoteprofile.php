<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class RemoteProfileAction extends ShowstreamAction
{
    function title()
    {
        $base = $this->target->getBestName();
        $host = parse_url($this->target->profileurl, PHP_URL_HOST);
        // TRANS: Remote profile action page title.
        // TRANS: %1$s is a username, %2$s is a hostname.
        return sprintf(_m('%1$s on %2$s'), $base, $host);
    }

    /**
     * Instead of showing notices, link to the original offsite profile.
     */
    function showNotices()
    {
        $url = $this->target->profileurl;
        $host = parse_url($url, PHP_URL_HOST);
        $markdown = sprintf(
                // TRANS: Message on remote profile page.
                // TRANS: This message contains Markdown links in the form [description](link).
                // TRANS: %1$s is a profile nickname, %2$s is a hostname, %3$s is a URL.
                _m('This remote profile is registered on another site; see [%1$s\'s original profile page on %2$s](%3$s).'),
                $this->target->nickname,
                $host,
                $url);
        $html = common_markup_to_html($markdown);
        $this->raw($html);

        if ($this->target->hasRole(Profile_role::SILENCED)) {
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

            $args = array('id' => $this->target->id);
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
