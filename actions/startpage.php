<?php
/**
 * Startpage action. Decides what to show on the first page.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class StartpageAction extends ManagedAction
{
    function isReadOnly($args)
    {
        return true;
    }

    function showPage()
    {
        if (common_config('singleuser', 'enabled')) {
            $user = User::singleUser();
            common_redirect(common_local_url('showstream', array('nickname' => $user->nickname)), 303);
        } elseif (common_config('public', 'localonly')) {
            common_redirect(common_local_url('public'), 303);
        } else {
            common_redirect(common_local_url('networkpublic'), 303);
        }
    }
}
