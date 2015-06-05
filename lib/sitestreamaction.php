<?php

if (!defined('GNUSOCIAL')) { exit(1); }

// Farther than any human will go

define('MAX_PUBLIC_PAGE', 100);

class SitestreamAction extends ManagedAction
{
    /**
     * page of the stream we're on; default = 1
     */

    var $page = null;
    var $notice;

    protected $stream = null;

    function isReadOnly($args)
    {
        return true;
    }

    protected function doPreparation()
    {
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        if ($this->page > MAX_PUBLIC_PAGE) {
            // TRANS: Client error displayed when requesting a public timeline page beyond the page limit.
            // TRANS: %s is the page limit.
            $this->clientError(sprintf(_('Beyond the page limit (%s).'), MAX_PUBLIC_PAGE));
        }

        common_set_returnto($this->selfUrl());

        $this->streamPrepare();

        $this->notice = $this->stream->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                            NOTICES_PER_PAGE + 1);

        if (!$this->notice) {
            // TRANS: Server error displayed when a public timeline cannot be retrieved.
            $this->serverError(_('Could not retrieve public timeline.'));
        }

        if ($this->page > 1 && $this->notice->N == 0){
            // TRANS: Client error when page not found (404).
            $this->clientError(_('No such page.'), 404);
        }

        return true;
    }

    /**
     * Title of the page
     *
     * @return page title, including page number if over 1
     */
    function title()
    {
        if ($this->page > 1) {
            // TRANS: Title for all public timeline pages but the first.
            // TRANS: %d is the page number.
            return sprintf(_('Public timeline, page %d'), $this->page);
        } else {
            // TRANS: Title for the first public timeline page.
            return _('Public timeline');
        }
    }

    function extraHead()
    {
        parent::extraHead();
        $rsd = common_local_url('rsd');

        // RSD, http://tales.phrasewise.com/rfc/rsd

        $this->element('link', array('rel' => 'EditURI',
                                     'type' => 'application/rsd+xml',
                                     'href' => $rsd));

        if ($this->page != 1) {
            $this->element('link', array('rel' => 'canonical',
                                         'href' => common_local_url('public')));
        }
    }

    /**
     * Output <head> elements for RSS and Atom feeds
     *
     * @return void
     */
    function getFeeds()
    {
        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'as')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (Activity Streams JSON)')),
                    new Feed(Feed::RSS1, common_local_url('publicrss'),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (RSS 1.0)')),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'rss')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (RSS 2.0)')),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'atom')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (Atom)')));
    }

    function showEmptyList()
    {
        // TRANS: Text displayed for public feed when there are no public notices.
        $message = _('This is the public timeline for %%site.name%% but no one has posted anything yet.') . ' ';

        if (common_logged_in()) {
            // TRANS: Additional text displayed for public feed when there are no public notices for a logged in user.
            $message .= _('Be the first to post!');
        }
        else {
            if (! (common_config('site','closed') || common_config('site','inviteonly'))) {
                // TRANS: Additional text displayed for public feed when there are no public notices for a not logged in user.
                $message .= _('Why not [register an account](%%action.register%%) and be the first to post!');
            }
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Fill the content area
     *
     * Shows a list of the notices in the public stream, with some pagination
     * controls.
     *
     * @return void
     */
    function showContent()
    {
        if ($this->scoped instanceof Profile && $this->scoped->isLocal() && $this->scoped->getUser()->streamModeOnly()) {
            $nl = new PrimaryNoticeList($this->notice, $this, array('show_n'=>NOTICES_PER_PAGE));
        } else {
            $nl = new ThreadedNoticeList($this->notice, $this, $this->scoped);
        }

        $cnt = $nl->show();

        if ($cnt == 0) {
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, $this->action);
    }

    function showAnonymousMessage()
    {
        if (! (common_config('site','closed') || common_config('site','inviteonly'))) {
            // TRANS: Message for not logged in users at an invite-only site trying to view the public feed of notices.
            // TRANS: This message contains Markdown links. Please mind the formatting.
            $m = _('This is %%site.name%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                   'based on the Free Software [StatusNet](http://status.net/) tool. ' .
                   '[Join now](%%action.register%%) to share notices about yourself with friends, family, and colleagues! ' .
                   '([Read more](%%doc.help%%))');
        } else {
            // TRANS: Message for not logged in users at a closed site trying to view the public feed of notices.
            // TRANS: This message contains Markdown links. Please mind the formatting.
            $m = _('This is %%site.name%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                   'based on the Free Software [StatusNet](http://status.net/) tool.');
        }
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($m));
        $this->elementEnd('div');
    }
}
