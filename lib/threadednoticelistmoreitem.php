<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Placeholder for loading more replies...
 */
class ThreadedNoticeListMoreItem extends NoticeListItem
{
    protected $cnt;

    function __construct(Notice $notice, Action $out, $cnt)
    {
        parent::__construct($notice, $out);
        $this->cnt = $cnt;
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showMiniForm();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-reply-comments'));
    }

    function showEnd()
    {
        $this->out->elementEnd('li');
    }

    function showMiniForm()
    {
        $id = $this->notice->conversation;
        $url = common_local_url('conversation', array('id' => $id));

        $n = Conversation::noticeCount($id) - 1;

        // TRANS: Link to show replies for a notice.
        // TRANS: %d is the number of replies to a notice and used for plural.
        $msg = sprintf(_m('Show reply', 'Show all %d replies', $n), $n);

        $this->out->element('a', array('href' => $url), $msg);
    }
}
