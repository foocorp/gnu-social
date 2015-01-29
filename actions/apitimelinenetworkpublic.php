<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ApiTimelineNetworkPublicAction extends ApiTimelinePublicAction
{
    function title()
    {
        return sprintf(_("%s network public timeline"), common_config('site', 'name'));
    }

    protected function getStream()
    {
        if (!$this->scoped instanceof Profile && common_config('public', 'localonly')) {
            $this->clientError(_('Network wide public feed is not permitted without authorization'), 403);
        }
        return new NetworkPublicNoticeStream($this->scoped);
    }
}
