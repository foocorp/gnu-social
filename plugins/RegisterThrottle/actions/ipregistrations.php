<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class IpregistrationsAction extends ManagedAction
{
    protected $needLogin = true;

    protected $ipaddress = null;

    function title()
    {
        return sprintf(_('Registrations from IP %s'), $this->ipaddress);
    }

    protected function doPreparation()
    {
        if (!$this->scoped->hasRight(Right::SILENCEUSER) && !$this->scoped->hasRole(Profile_role::ADMINISTRATOR)) {
            throw new AuthorizationException(_('You are not authorized to view this page.'));
        }

        $this->ipaddress    = $this->trimmed('ipaddress');
        $this->profile_ids  = Registration_ip::usersByIP($this->ipaddress);
    }

    public function showContent()
    {
        $this->elementStart('ul');
        foreach (Profile::listGet('id', $this->profile_ids) as $profile) {
            $this->elementStart('li');
            try {
                $this->element('a', ['href'=>$profile->getUrl()], $profile->getFancyName());
            } catch (InvalidUrlException $e) {
                $this->element('span', null, $profile->getFancyName());
            }
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
    }
}
