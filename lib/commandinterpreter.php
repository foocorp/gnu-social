<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/lib/command.php';

class CommandInterpreter
{
    function handle_command($user, $text)
    {
        // XXX: localise

        $text = preg_replace('/\s+/', ' ', trim($text));
        list($cmd, $arg) = self::split_arg($text);

        // We try to support all the same commands as Twitter, see
        // http://getsatisfaction.com/twitter/topics/what_are_the_twitter_commands
        // There are a few compatibility commands from earlier versions of
        // StatusNet

        $cmd = strtolower($cmd);

        if (Event::handle('StartInterpretCommand', array($cmd, $arg, $user, &$result))) {
            switch($cmd) {
            case 'help':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new HelpCommand($user);
                }
                break;
            case 'login':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new LoginCommand($user);
                }
                break;
            case 'lose':
                if ($arg) {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new LoseCommand($user, $other);
                    }
                } else {
                    $result = null;
                }
                break;
            case 'subscribers':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new SubscribersCommand($user);
                }
                break;
            case 'subscriptions':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new SubscriptionsCommand($user);
                }
                break;
            case 'groups':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new GroupsCommand($user);
                }
                break;
            case 'on':
                if ($arg) {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new OnCommand($user, $other);
                    }
                } else {
                    $result = new OnCommand($user);
                }
                break;
            case 'off':
                if ($arg) {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new OffCommand($user, $other);
                    }
                } else {
                    $result = new OffCommand($user);
                }
                break;
            case 'stop':
            case 'quit':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new OffCommand($user);
                }
                break;
            case 'join':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new JoinCommand($user, $other);
                    }
                }
                break;
            case 'drop':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new DropCommand($user, $other);
                    }
                }
                break;
            case 'follow':
            case 'sub':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new SubCommand($user, $other);
                    }
                }
                break;
            case 'leave':
            case 'unsub':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new UnsubCommand($user, $other);
                    }
                }
                break;
            case 'get':
            case 'last':
                if (!$arg) {
                    $result = null;
                }
                list($other, $extra) = self::split_arg($arg);
                if ($extra) {
                    $result = null;
                } else {
                    $result = new GetCommand($user, $other);
                }
                break;
            case 'r':
            case 'reply':
                if (!$arg) {
                    $result = null;
                }
                list($other, $extra) = self::split_arg($arg);
                if (!$extra) {
                    $result = null;
                } else {
                    $result = new ReplyCommand($user, $other, $extra);
                }
                break;
            case 'repeat':
            case 'rp':
            case 'rt':
            case 'rd':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new RepeatCommand($user, $other);
                    }
                }
                break;
            case 'whois':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new WhoisCommand($user, $other);
                    }
                }
                break;
            case 'nudge':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new NudgeCommand($user, $other);
                    }
                }
                break;
            case 'stats':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new StatsCommand($user);
                }
                break;
            case 'invite':
                if (!$arg) {
                    $result = null;
                } else {
                    list($other, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else {
                        $result = new InviteCommand($user, $other);
                    }
                }
                break;
             case 'list':
             case 'tag':
                if (!$arg) {
                    $result = null;
                    break;
                }
                list($other, $tags) = self::split_arg($arg);
                if (!$tags) {
                    $result = null;
                } else {
                    $result = new TagCommand($user, $other, $tags);
                }
                break;
             case 'unlist':
             case 'untag':
                if (!$arg) {
                    $result = null;
                    break;
                }
                list($other, $tags) = self::split_arg($arg);
                if (!$tags) {
                    $result = null;
                } else {
                    $result = new UntagCommand($user, $other, $tags);
                }
                break;
            case 'track':
                if (!$arg) {
                    $result = null;
                } else {
                    list($word, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else if ($word == 'off') {
                        $result = new TrackOffCommand($user);
                    } else {
                        $result = new TrackCommand($user, $word);
                    }
                }
                break;
            case 'untrack':
                if (!$arg) {
                    $result = null;
                } else {
                    list($word, $extra) = self::split_arg($arg);
                    if ($extra) {
                        $result = null;
                    } else if ($word == 'all') {
                        $result = new TrackOffCommand($user);
                    } else {
                        $result = new UntrackCommand($user, $word);
                    }
                }
                break;
            case 'tracks':
            case 'tracking':
                if ($arg) {
                    $result = null;
                } else {
                    $result = new TrackingCommand($user);
                }
                break;
            default:
                $result = false;
            }

            Event::handle('EndInterpretCommand', array($cmd, $arg, $user, &$result));
        }

        return $result;
    }

    /**
     * Split arguments without triggering a PHP notice warning
     */
    static function split_arg($text)
    {
        $pieces = explode(' ', $text, 2);
        if (count($pieces) == 1) {
            $pieces[] = null;
        }
        return $pieces;
    }
}
