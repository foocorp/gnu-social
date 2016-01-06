<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class SubscribersListItem extends SubscriptionListItem
{
    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            // Relevant code!
            $this->showBlockForm();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showBlockForm()
    {
        $user = common_current_user();

        if (!empty($user) && $this->owner->id == $user->id) {
            $returnto = array('action' => 'subscribers',
                              'nickname' => $this->owner->getNickname());
            $page = $this->out->arg('page');
            if ($page) {
                $returnto['param-page'] = $page;
            }
            $bf = new BlockForm($this->out, $this->profile, $returnto);
            $bf->show();
        }
    }

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'subscribers')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'subscribers')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }
}
