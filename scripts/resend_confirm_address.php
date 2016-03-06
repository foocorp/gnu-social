#!/usr/bin/env php
<?php

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'e::ay';
$longoptions = array('email=', 'all', 'yes');

$self = basename($_SERVER['PHP_SELF']);

$helptext = <<<END_OF_HELP
{$self} [options]
resends confirmation email either for a specific email (if found) or for
all lingering confirmations.

NOTE: You probably want to do something like this to your database first so
only relatively fresh accounts get resent this:

    DELETE FROM confirm_address WHERE modified < DATE_SUB(NOW(), INTERVAL 1 month);

Options:

  -e --email        e-mail address to send for
  -a --all          send for all emails in confirm_address table
  -y --yes          skip interactive verification

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$all = false;
$ca = null;

if (have_option('e', 'email')) {
    $email = get_option_value('e', 'email');
    $ca = Confirm_address::getAddress($email, 'email');
    if (!$ca instanceof Confirm_address) {
        print "Can't find email $email in confirm_address table.\n";
        exit(1);
    }
} elseif (have_option('a', 'all')) {
    $all = true;
    $ca = new Confirm_address();
    $ca->address_type = 'email';
    if (!$ca->find()) {
        print "confirm_address table contains no lingering email addresses\n";
        exit(0);
    }
} else {
    print "You must provide an email (or --all).\n";
    exit(1);
}

if (!have_option('y', 'yes')) {
    print "About to resend confirm_address email to {$ca->N} recipients. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

function mailConfirmAddress(Confirm_address $ca)
{
    try {
        $user = User::getByID($ca->user_id);
        $profile = $user->getProfile();
        if ($profile->isSilenced()) {
            $ca->delete();
            return;
        }
        if ($user->email === $ca->address) {
            throw new AlreadyFulfilledException('User already has identical confirmed email address.');
        }
    } catch (AlreadyFulfilledException $e) {
        print "\n User already had verified email: "._ve($ca->address);
        $ca->delete();
    } catch (Exception $e) {
        print "\n Failed to get user with ID "._ve($user_id).', deleting confirm_address entry: '._ve($e->getMessage());
        $ca->delete();
        return;
    }
    mail_confirm_address($user, $ca->code, $user->getNickname(), $ca->address);
}

require_once(INSTALLDIR . '/lib/mail.php');

if (!$all) {
    mailConfirmAddress($ca);
} else {
    while ($ca->fetch()) {
        mailConfirmAddress($ca);
    }
}

print "\nDONE.\n";
