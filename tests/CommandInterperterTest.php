<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('GNUSOCIAL', true);
define('STATUSNET', true);  // compatibility

require_once INSTALLDIR . '/lib/common.php';

class CommandInterpreterTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider commandInterpreterCases
     */
    public function testCommandInterpreter($input, $expectedType, $comment='')
    {
        $inter = new CommandInterpreter();

        $user = new User(); // fake user
        $user->limit(1);
        $user->find();
        $cmd = $inter->handle_command($user, $input);

        $type = $cmd ? get_class($cmd) : null;
        $this->assertEquals(strtolower($expectedType), strtolower($type), $comment);
    }

    static public function commandInterpreterCases()
    {
        $sets = array(
            array('help', 'HelpCommand'),
            array('help me bro', null, 'help does not accept multiple params'),
            array('HeLP', 'HelpCommand', 'case check'),
            array('HeLP Me BRO!', null, 'case & non-params check'),

            array('login', 'LoginCommand'),
            array('login to savings!', null, 'login does not accept params'),

            array('lose', null, 'lose must have at least 1 parameter'),
            array('lose foobar', 'LoseCommand', 'lose requires 1 parameter'),
            array('lose        foobar', 'LoseCommand', 'check for space norm'),
            array('lose more weight', null, 'lose does not accept multiple params'),

            array('subscribers', 'SubscribersCommand'),
            array('subscribers foo', null, 'subscribers does not take params'),

            array('subscriptions', 'SubscriptionsCommand'),
            array('subscriptions foo', null, 'subscriptions does not take params'),

            array('groups', 'GroupsCommand'),
            array('groups foo', null, 'groups does not take params'),

            array('off', 'OffCommand', 'off accepts 0 or 1 params'),
            array('off foo', 'OffCommand', 'off accepts 0 or 1 params'),
            array('off foo bar', null, 'off accepts 0 or 1 params'),

            array('stop', 'OffCommand', 'stop accepts 0 params'),
            array('stop foo', null, 'stop accepts 0 params'),

            array('quit', 'OffCommand', 'quit accepts 0 params'),
            array('quit foo', null, 'quit accepts 0 params'),

            array('on', 'OnCommand', 'on accepts 0 or 1 params'),
            array('on foo', 'OnCommand', 'on accepts 0 or 1 params'),
            array('on foo bar', null, 'on accepts 0 or 1 params'),

            array('join', null),
            array('join foo', 'JoinCommand'),
            array('join foo bar', null),

            array('drop', null),
            array('drop foo', 'DropCommand'),
            array('drop foo bar', null),

            array('follow', null),
            array('follow foo', 'SubCommand'),
            array('follow foo bar', null),

            array('sub', null),
            array('sub foo', 'SubCommand'),
            array('sub foo bar', null),

            array('leave', null),
            array('leave foo', 'UnsubCommand'),
            array('leave foo bar', null),

            array('unsub', null),
            array('unsub foo', 'UnsubCommand'),
            array('unsub foo bar', null),

            array('leave', null),
            array('leave foo', 'UnsubCommand'),
            array('leave foo bar', null),

            array('d', null),
            array('d foo', null),
            array('d foo bar', 'MessageCommand'),

            array('dm', null),
            array('dm foo', null),
            array('dm foo bar', 'MessageCommand'),

            array('r', null),
            array('r foo', null),
            array('r foo bar', 'ReplyCommand'),

            array('reply', null),
            array('reply foo', null),
            array('reply foo bar', 'ReplyCommand'),

            array('repeat', null),
            array('repeat foo', 'RepeatCommand'),
            array('repeat foo bar', null),

            array('rp', null),
            array('rp foo', 'RepeatCommand'),
            array('rp foo bar', null),

            array('rt', null),
            array('rt foo', 'RepeatCommand'),
            array('rt foo bar', null),

            array('rd', null),
            array('rd foo', 'RepeatCommand'),
            array('rd foo bar', null),

            array('whois', null),
            array('whois foo', 'WhoisCommand'),
            array('whois foo bar', null),

/*            array('fav', null),
            array('fav foo', 'FavCommand'),
            array('fav foo bar', null),*/

            array('nudge', null),
            array('nudge foo', 'NudgeCommand'),
            array('nudge foo bar', null),

            array('stats', 'StatsCommand'),
            array('stats foo', null),

            array('invite', null),
            array('invite foo', 'InviteCommand'),
            array('invite foo bar', null),

            array('track', null),
            array('track foo', 'TrackCommand'),
            array('track off', 'TrackOffCommand'),
            array('track foo bar', null),
            array('track off foo', null),

            array('untrack', null),
            array('untrack foo', 'UntrackCommand'),
            array('untrack all', 'TrackOffCommand'),
            array('untrack foo bar', null),
            array('untrack all foo', null),

            array('tracking', 'TrackingCommand'),
            array('tracking foo', null),

            array('tracks', 'TrackingCommand'),
            array('tracks foo', null),

        );
        return $sets;
    }

}

