<?php

class SearchSubTrackoffCommand extends Command
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

        $profile = $cur->getProfile();
        while ($all->fetch()) {
            try {
                SearchSub::cancel($profile, $all->search);
            } catch (Exception $e) {
                // TRANS: Message given having failed to cancel one of the search subs with 'track off' command.
                // TRANS: %s is the search for which the subscription removal failed.
                $channel->error($cur, sprintf(_m('Error disabling search subscription for query "%s".'),
                                              $all->search));
                return;
            }
        }

        // TRANS: Message given having disabled all search subscriptions with 'track off'.
        $channel->output($cur, _m('Disabled all your search subscriptions.'));
    }
}
