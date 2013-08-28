<?php

class SearchSubUntrackCommand extends Command
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

        if (!$searchsub) {
            // TRANS: Error text shown a user tries to untrack a search query they're not subscribed to.
            // TRANS: %s is the keyword for the search.
            $channel->error($cur, sprintf(_m('You are not tracking the search "%s".'), $this->keyword));
            return;
        }

        try {
            SearchSub::cancel($cur->getProfile(), $this->keyword);
        } catch (Exception $e) {
            // TRANS: Message given having failed to cancel a search subscription by untrack command.
            // TRANS: %s is the keyword for the query.
            $channel->error($cur, sprintf(_m('Could not end a search subscription for query "%s".'),
                                          $this->keyword));
            return;
        }

        // TRANS: Message given having removed a search subscription by untrack command.
        // TRANS: %s is the keyword for the search.
        $channel->output($cur, sprintf(_m('You are no longer subscribed to the search "%s".'),
                                              $this->keyword));
    }
}
