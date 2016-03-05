<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Moderated notice stream, will take into account the scoping of
 * notices as well as whether the profile is moderated somehow,
 * such as by sandboxing or silencing.
 *
 * Inherits $this->scoped from ScopingNoticeStream as the Profile
 * this stream is meant for. Can be null in case we're not logged in.
 *
 * @category  Stream
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2016 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      https://gnu.io/social
 */

class ModeratedNoticeStream extends ScopingNoticeStream
{
    protected function filter(Notice $notice)
    {
        if (!parent::filter($notice)) {
            return false;
        }
        
        if(self::include_or_not($notice) === false) {
            return false;
        }
        
        // If this is a repeat the repeated notice is moderated
        if($notice->isRepeat()) {
            try {
                $repeated_notice = Notice::getById($notice->repeat_of);
            } catch (Exception $e) {
                // if we can't get the repeated notice by id, something is seriously wrong with it, so don't include it
                return false;
            }

            if(self::include_or_not($repeated_notice) === false) {
                return false;
            }
        }         

        return true;
    }
    
    protected function include_or_not(Notice $notice)
    {    
        $profile = $notice->getProfile();

        if ($profile->isSandboxed()) {
            if (!$this->scoped instanceof Profile) {
                // Non-logged in users don't get to see posts by sandboxed users
                return false;
            } elseif (!$profile->sameAs($this->scoped) && !$this->scoped->hasRight(Right::REVIEWSPAM)) {
                // And if we are logged in, deny if scoped user is neither the author nor has the right to review spam
                return false;
            } 
        }    
    }
}
