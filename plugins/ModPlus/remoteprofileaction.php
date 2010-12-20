<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class RemoteProfileAction extends ShowstreamAction
{
    function prepare($args)
    {
        OwnerDesignAction::prepare($args); // skip the ProfileAction code and replace it...

        $id = $this->arg('id');
        $this->user = false;
        $this->profile = Profile::staticGet('id', $id);

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $user = User::staticGet('id', $this->profile->id);
        if ($user) {
            // This is a local user -- send to their regular profile.
            $url = common_local_url('showstream', array('nickname' => $user->nickname));
            common_redirect($url);
            return false;
        }

        $this->tag = $this->trimmed('tag');
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        common_set_returnto($this->selfUrl());
        return true;
    }

    function handle($args)
    {
        // skip yadis thingy
        $this->showPage();
    }

    function title()
    {
        // maybe fixed in 0.9.x
        if (!empty($this->profile->fullname)) {
            $base = $this->profile->fullname . ' (' . $this->profile->nickname . ') ';
        } else {
            $base = $this->profile->nickname;
        }
        $host = parse_url($this->profile->profileurl, PHP_URL_HOST);
        return sprintf(_m('%s on %s'), $base, $host);
    }

    /**
     * Instead of showing notices, link to the original offsite profile.
     */
    function showNotices()
    {
        $url = $this->profile->profileurl;
        $host = parse_url($url, PHP_URL_HOST);
        $markdown = sprintf(
                _m('This remote profile is registered on another site; see [%s\'s original profile page on %s](%s).'),
                $this->profile->nickname,
                $host,
                $url);
        $html = common_markup_to_html($markdown);
        $this->raw($html);

        if ($this->profile->hasRole(Profile_role::SILENCED)) {
            $markdown = _m('Site moderators have silenced this profile, which prevents delivery of new messages to any users on this site.');
            $this->raw(common_markup_to_html($markdown));
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
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    function showSections()
    {
        ProfileAction::showSections();
        // skip tag cloud
    }

    function showStatistics()
    {
        // skip
    }

}