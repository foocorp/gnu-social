<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class GroupAdminSection extends ProfileSection
{
    var $group;

    function __construct($out, $group)
    {
        parent::__construct($out);
        $this->group = $group;
    }

    function getProfiles()
    {
        return $this->group->getAdmins();
    }

    function title()
    {
        // TRANS: Title for list of group administrators on a group page.
        return _m('TITLE','Admins');
    }

    function divId()
    {
        return 'group_admins';
    }

    function moreUrl()
    {
        return null;
    }
}
