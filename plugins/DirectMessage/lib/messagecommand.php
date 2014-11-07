<?php

class MessageCommand extends Command
{
    var $other = null;
    var $text = null;
    function __construct($user, $other, $text)
    {
        parent::__construct($user);
        $this->other = $other;
        $this->text = $text;
    }

    function handle($channel)
    {
        try {
            $other = $this->getUser($this->other)->getProfile();
        } catch (CommandException $e) {
            try {
                $profile = $this->getProfile($this->other);
            } catch (CommandException $f) {
                throw $e;
            }
            // TRANS: Command exception text shown when trying to send a direct message to a remote user (a user not registered at the current server).
            // TRANS: %s is a remote profile.
            throw new CommandException(sprintf(_('%s is a remote profile; you can only send direct messages to users on the same server.'), $this->other));
        }

        $len = mb_strlen($this->text);

        if ($len == 0) {
            // TRANS: Command exception text shown when trying to send a direct message to another user without content.
            $channel->error($this->user, _('No content!'));
            return;
        }

        $this->text = $this->user->shortenLinks($this->text);

        if (Message::contentTooLong($this->text)) {
            // XXX: i18n. Needs plural support.
            // TRANS: Message given if content is too long. %1$sd is used for plural.
            // TRANS: %1$d is the maximum number of characters, %2$d is the number of submitted characters.
            $channel->error($this->user, sprintf(_m('Message too long - maximum is %1$d character, you sent %2$d.',
                                                    'Message too long - maximum is %1$d characters, you sent %2$d.',
                                                    Message::maxContent()),
                                                 Message::maxContent(), mb_strlen($this->text)));
            return;
        }

        if (!$other instanceof Profile) {
            // TRANS: Error text shown when trying to send a direct message to a user that does not exist.
            $channel->error($this->user, _('No such user.'));
            return;
        } else if (!$this->user->mutuallySubscribed($other)) {
            // TRANS: Error text shown when trying to send a direct message to a user without a mutual subscription (each user must be subscribed to the other).
            $channel->error($this->user, _('You can\'t send a message to this user.'));
            return;
        } else if ($this->user->id == $other->id) {
            // TRANS: Error text shown when trying to send a direct message to self.
            $channel->error($this->user, _('Do not send a message to yourself; just say it to yourself quietly instead.'));
            return;
        }
        try {
            $message = Message::saveNew($this->user->id, $other->id, $this->text, $channel->source());
            $message->notify();
            // TRANS: Message given have sent a direct message to another user.
            // TRANS: %s is the name of the other user.
            $channel->output($this->user, sprintf(_('Direct message to %s sent.'), $this->other));
        } catch (Exception $e) {
            // TRANS: Error text shown sending a direct message fails with an unknown reason.
            $channel->error($this->user, $e->getMessage());
        }
    }
}
