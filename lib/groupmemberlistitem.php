<?php
// @todo FIXME: add documentation.

class GroupMemberListItem extends ProfileListItem
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function showFullName()
    {
        parent::showFullName();
        if ($this->profile->isAdmin($this->group)) {
            $this->out->text(' '); // for separating the classes.
            // TRANS: Indicator in group members list that this user is a group administrator.
            $this->out->element('span', 'role', _m('GROUPADMIN','Admin'));
        }
    }

    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            $this->showMakeAdminForm();
            $this->showGroupBlockForm();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function showMakeAdminForm()
    {
        $user = common_current_user();

        if (!empty($user) &&
            $user->id != $this->profile->id &&
            ($user->isAdmin($this->group) || $user->hasRight(Right::MAKEGROUPADMIN)) &&
            !$this->profile->isAdmin($this->group)) {
            $this->out->elementStart('li', 'entity_make_admin');
            $maf = new MakeAdminForm($this->out, $this->profile, $this->group,
                                     $this->returnToArgs());
            $maf->show();
            $this->out->elementEnd('li');
        }

    }

    function showGroupBlockForm()
    {
        $user = common_current_user();

        if (!empty($user) && $user->id != $this->profile->id && $user->isAdmin($this->group)) {
            $this->out->elementStart('li', 'entity_block');
            $bf = new GroupBlockForm($this->out, $this->profile, $this->group,
                                     $this->returnToArgs());
            $bf->show();
            $this->out->elementEnd('li');
        }
    }

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }

    /**
     * Fetch necessary return-to arguments for the profile forms
     * to return to this list when they're done.
     *
     * @return array
     */
    protected function returnToArgs()
    {
        $args = array('action' => 'groupmembers',
                      'nickname' => $this->group->nickname);
        $page = $this->out->arg('page');
        if ($page) {
            $args['param-page'] = $page;
        }
        return $args;
    }
}
