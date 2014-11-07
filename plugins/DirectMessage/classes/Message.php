<?php
/**
 * Table Definition for message
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Message extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'message';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key
    public $from_profile;                    // int(4)   not_null
    public $to_profile;                      // int(4)   not_null
    public $content;                         // text()
    public $rendered;                        // text()
    public $url;                             // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $source;                          // varchar(32)

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier'),
                'from_profile' => array('type' => 'int', 'not null' => true, 'description' => 'who the message is from'),
                'to_profile' => array('type' => 'int', 'not null' => true, 'description' => 'who the message is to'),
                'content' => array('type' => 'text', 'description' => 'message content'),
                'rendered' => array('type' => 'text', 'description' => 'HTML version of the content'),
                'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of any attachment (image, video, bookmark, whatever)'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
                'source' => array('type' => 'varchar', 'length' => 32, 'description' => 'source of comment, like "web", "im", or "clientname"'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'message_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'message_from_profile_fkey' => array('profile', array('from_profile' => 'id')),
                'message_to_profile_fkey' => array('profile', array('to_profile' => 'id')),
            ),
            'indexes' => array(
                // @fixme these are really terrible indexes, since you can only sort on one of them at a time.
                // looks like we really need a (to_profile, created) for inbox and a (from_profile, created) for outbox
                'message_from_idx' => array('from_profile'),
                'message_to_idx' => array('to_profile'),
                'message_created_idx' => array('created'),
            ),
        );
    }

    function getFrom()
    {
        return Profile::getKV('id', $this->from_profile);
    }

    function getTo()
    {
        return Profile::getKV('id', $this->to_profile);
    }

    static function saveNew($from, $to, $content, $source) {
        $sender = Profile::getKV('id', $from);

        if (!$sender->hasRight(Right::NEWMESSAGE)) {
            // TRANS: Client exception thrown when a user tries to send a direct message while being banned from sending them.
            throw new ClientException(_('You are banned from sending direct messages.'));
        }

        $user = User::getKV('id', $sender->id);

        $msg = new Message();

        $msg->from_profile = $from;
        $msg->to_profile = $to;
        if ($user) {
            // Use the sender's URL shortening options.
            $msg->content = $user->shortenLinks($content);
        } else {
            $msg->content = common_shorten_links($content);
        }
        $msg->rendered = common_render_text($msg->content);
        $msg->created = common_sql_now();
        $msg->source = $source;

        $result = $msg->insert();

        if (!$result) {
            common_log_db_error($msg, 'INSERT', __FILE__);
            // TRANS: Message given when a message could not be stored on the server.
            throw new ServerException(_('Could not insert message.'));
        }

        $orig = clone($msg);
        $msg->uri = common_local_url('showmessage', array('message' => $msg->id));

        $result = $msg->update($orig);

        if (!$result) {
            common_log_db_error($msg, 'UPDATE', __FILE__);
            // TRANS: Message given when a message could not be updated on the server.
            throw new ServerException(_('Could not update message with new URI.'));
        }

        return $msg;
    }

    static function maxContent()
    {
        $desclimit = common_config('message', 'contentlimit');
        // null => use global limit (distinct from 0!)
        if (is_null($desclimit)) {
            $desclimit = common_config('site', 'textlimit');
        }
        return $desclimit;
    }

    static function contentTooLong($content)
    {
        $contentlimit = self::maxContent();
        return ($contentlimit > 0 && !empty($content) && (mb_strlen($content) > $contentlimit));
    }

    function notify()
    {
        $from = User::getKV('id', $this->from_profile);
        $to   = User::getKV('id', $this->to_profile);

        mail_notify_message($this, $from, $to);
    }

    function getSource()
    {
        if (empty($this->source)) {
            return false;
        }

        $ns = new Notice_source();
        switch ($this->source) {
        case 'web':
        case 'xmpp':
        case 'mail':
        case 'omb':
        case 'system':
        case 'api':
            $ns->code = $this->source;
            break;
        default:
            $ns = Notice_source::getKV($this->source);
            if (!$ns instanceof Notice_source) {
                $ns = new Notice_source();
                $ns->code = $this->source;
                $app = Oauth_application::getKV('name', $this->source);
                if ($app) {
                    $ns->name = $app->name;
                    $ns->url  = $app->source_url;
                }
            }
            break;
        }
        return $ns;
    }

    function asActivity()
    {
        $act = new Activity();

        if (Event::handle('StartMessageAsActivity', array($this, &$act))) {

            $act->id      = TagURI::mint(sprintf('activity:message:%d', $this->id));
            $act->time    = strtotime($this->created);
            $act->link    = $this->url;

            $profile = Profile::getKV('id', $this->from_profile);

            if (empty($profile)) {
                throw new Exception(sprintf("Sender profile not found: %d", $this->from_profile));
            }
            
            $act->actor            = $profile->asActivityObject();
            $act->actor->extra[]   = $profile->profileInfo();

            $act->verb = ActivityVerb::POST;

            $act->objects[] = ActivityObject::fromMessage($this);

            $ctx = new ActivityContext();

            $rprofile = Profile::getKV('id', $this->to_profile);

            if (empty($rprofile)) {
                throw new Exception(sprintf("Receiver profile not found: %d", $this->to_profile));
            }

            $ctx->attention[$rprofile->getUri()] = ActivityObject::PERSON;

            $act->context = $ctx;

            $source = $this->getSource();

            if ($source instanceof Notice_source) {
                $act->generator = ActivityObject::fromNoticeSource($source);
            }

            Event::handle('EndMessageAsActivity', array($this, &$act));
        }

        return $act;
    }
}
