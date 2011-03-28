<?php

class SearchSubTrackCommand extends Command
{
    var $keyword = null;

    function __construct($user, $keyword)
    {
        parent::__construct($user);
        $this->keyword = $keyword;
    }

    function handle($channel)
    {
        $cur = $this->user;
        $searchsub = SearchSub::pkeyGet(array('search' => $this->keyword,
                                              'profile_id' => $cur->id));

        if ($searchsub) {
            // TRANS: Error text shown a user tries to track a search query they're already subscribed to.
            $channel->error($cur, sprintf(_m('You are already tracking the search "%s".'), $this->keyword));
            return;
        }

        try {
            SearchSub::start($cur->getProfile(), $this->keyword);
        } catch (Exception $e) {
            // TRANS: Message given having failed to set up a search subscription by track command.
            $channel->error($cur, sprintf(_m('Could not start a search subscription for query "%s".'),
                                          $this->keyword));
            return;
        }

        // TRANS: Message given having added a search subscription by track command.
        $channel->output($cur, sprintf(_m('You are subscribed to the search "%s".'),
                                              $this->keyword));
    }
}