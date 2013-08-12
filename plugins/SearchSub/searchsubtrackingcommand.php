<?php

class SearchSubTrackingCommand extends Command
{
    function handle($channel)
    {
        $cur = $this->user;
        $all = new SearchSub();
        $all->profile_id = $cur->id;
        $all->find();

        if ($all->N == 0) {
            // TRANS: Error text shown a user tries to disable all a search subscriptions with track off command, but has none.
            $channel->error($cur, _m('You are not tracking any searches.'));
            return;
        }

        $list = array();
        while ($all->fetch()) {
            $list[] = $all->search;
        }

        // TRANS: Separator for list of tracked searches.
        $separator = _m('SEPARATOR','", "');

        // TRANS: Message given having disabled all search subscriptions with 'track off'.
        // TRANS: %s is a list of searches. Separator default is '", "'.
        $channel->output($cur, sprintf(_m('You are tracking searches for: "%s".'),
                                       implode($separator, $list)));
    }
}
