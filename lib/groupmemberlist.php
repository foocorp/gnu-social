<?php
// @todo FIXME: add documentation.

class GroupMemberList extends ProfileList
{
    var $group = null;

    function __construct($profile, $group, $action)
    {
        parent::__construct($profile, $action);

        $this->group = $group;
    }

    function newListItem($profile)
    {
        return new GroupMemberListItem($profile, $this->group, $this->action);
    }
}
