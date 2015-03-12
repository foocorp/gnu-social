<?php

if (!defined('GNUSOCIAL')) { exit(1); }

// We don't show the author for a profile, since we already know who it is!

/**
 * Slightly modified from standard list; the author & avatar are hidden
 * in CSS. We used to remove them here too, but as it turns out that
 * confuses the inline reply code... and we hide them in CSS anyway
 * since realtime updates come through in original form.
 *
 * Remaining customization right now is for the repeat marker, where
 * it'll list who the original poster was instead of who did the repeat
 * (since the repeater is you, and the repeatee isn't shown!)
 * This will remain inconsistent if realtime updates come through,
 * since those'll get rendered as a regular NoticeListItem.
 */
class ProfileNoticeListItem extends DoFollowListItem
{
    /**
     * show a link to the author of repeat
     *
     * @return void
     */
    function showRepeat()
    {
        if (!empty($this->repeat)) {

            // FIXME: this code is almost identical to default; need to refactor

            $attrs = array('href' => $this->profile->profileurl,
                           'class' => 'url');

            if (!empty($this->profile->fullname)) {
                $attrs['title'] = $this->profile->getFancyName();
            }

            $this->out->elementStart('span', 'repeat');

            $text_link = XMLStringer::estring('a', $attrs, $this->profile->nickname);

            // TRANS: Link to the author of a repeated notice. %s is a linked nickname.
            $this->out->raw(sprintf(_('Repeat of %s'), $text_link));

            $this->out->elementEnd('span');
        }
    }
}
