<?php

/**
 *
 * Some notes...
 *
 * Drupal docs don't list a bool type, but it might be nice to use rather than 'tinyint'
 * Note however that we use bitfields and things as well in tinyints, and PG's
 * "bool" type isn't 100% compatible with 0/1 checks. Just keeping tinyints. :)
 *
 * decimal <-> numeric
 *
 * MySQL 'timestamp' columns were formerly used for 'modified' files for their
 * auto-updating properties. This didn't play well with changes to cache usage
 * in 0.9.x, as we don't know the timestamp value at INSERT time and never
 * have a chance to load it up again before caching. For now I'm leaving them
 * in, but we may want to clean them up later.
 *
 * Current code should be setting 'created' and 'modified' fields explicitly;
 * this also avoids mismatches between server and client timezone settings.
 *
 *
 * fulltext indexes?
 * got one or two things wanting a custom charset setting on a field?
 *
 * foreign keys are kinda funky...
 *     those specified in inline syntax (as all in the original .sql) are NEVER ENFORCED on mysql
 *     those made with an explicit 'foreign key' WITHIN INNODB and IF there's a proper index, do get enforced
 *     double-check what we've been doing on postgres?
 */

$schema['profile'] = array(
    'description' => 'local and remote users have profiles',
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username'),
        'fullname' => array('type' => 'varchar', 'length' => 255, 'description' => 'display name'),
        'profileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL, cached so we dont regenerate'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'identifying URL'),
        'bio' => array('type' => 'text', 'description' => 'descriptive biography'),
        'location' => array('type' => 'varchar', 'length' => 255, 'description' => 'physical location'),
        'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
        'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
        'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
        'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'indexes' => array(
        'profile_nickname_idx' => array('nickname'),
    ),
    'fulltext indexes' => array(
        'nickname' => array('nickname', 'fullname', 'location', 'bio', 'homepage')
    ),
);

$schema['avatar'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
        'original' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'uploaded by user or generated?'),
        'width' => array('type' => 'int', 'not null' => true, 'description' => 'image width'),
        'height' => array('type' => 'int', 'not null' => true, 'description' => 'image height'),
        'mediatype' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'file type'),
        'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'local filename, if local'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'avatar location'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('profile_id', 'width', 'height'),
    'unique keys' => array(
        'avatar_url_key' => array('url'),
    ),
    'foreign keys' => array(
        'avatar_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
    ),
    'indexes' => array(
        'avatar_profile_id_idx' => array('profile_id'),
    ),
);

$schema['sms_carrier'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'primary key for SMS carrier'),
        'name' => array('type' => 'varchar', 'length' => 64, 'description' => 'name of the carrier'),
        'email_pattern' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'sprintf pattern for making an email address from a phone number'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'sms_carrier_name_key' => array('name'),
    ),
);

$schema['user'] = array(
    'description' => 'local users',
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'nickname or username, duped in profile'),
        'password' => array('type' => 'varchar', 'length' => 255, 'description' => 'salted password, can be null for OpenID users'),
        'email' => array('type' => 'varchar', 'length' => 255, 'description' => 'email address for password recovery etc.'),
        'incomingemail' => array('type' => 'varchar', 'length' => 255, 'description' => 'email address for post-by-email'),
        'emailnotifysub' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of subscriptions'),
        'emailnotifyfav' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of favorites'),
        'emailnotifynudge' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of nudges'),
        'emailnotifymsg' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of direct messages'),
        'emailnotifyattn' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of @-replies'),
        'emailmicroid' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'whether to publish email microid'),
        'language' => array('type' => 'varchar', 'length' => 50, 'description' => 'preferred language'),
        'timezone' => array('type' => 'varchar', 'length' => 50, 'description' => 'timezone'),
        'emailpost' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Post by email'),
        'sms' => array('type' => 'varchar', 'length' => 64, 'description' => 'sms phone number'),
        'carrier' => array('type' => 'int', 'description' => 'foreign key to sms_carrier'),
        'smsnotify' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to send notices to SMS'),
        'smsreplies' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to send notices to SMS on replies'),
        'smsemail' => array('type' => 'varchar', 'length' => 255, 'description' => 'built from sms and carrier'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'autosubscribe' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'automatically subscribe to users who subscribe to us'),
        'subscribe_policy' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => '0 = anybody can subscribe; 1 = require approval'),
        'urlshorteningservice' => array('type' => 'varchar', 'length' => 50, 'default' => 'internal', 'description' => 'service to use for auto-shortening URLs'),
        'inboxed' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'has an inbox been created for this user?'),
        'design_id' => array('type' => 'int', 'description' => 'id of a design'),
        'viewdesigns' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'whether to view user-provided designs'),
        'private_stream' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to limit all notices to followers only'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'user_nickname_key' => array('nickname'),
        'user_email_key' => array('email'),
        'user_incomingemail_key' => array('incomingemail'),
        'user_sms_key' => array('sms'),
        'user_uri_key' => array('uri'),
    ),
    'foreign keys' => array(
        'user_id_fkey' => array('profile', array('id' => 'id')),
        'user_carrier_fkey' => array('sms_carrier', array('carrier' => 'id')),
        'user_design_id_fkey' => array('design', array('design_id' => 'id')),
    ),
    'indexes' => array(
        'user_smsemail_idx' => array('smsemail'),
    ),
);

$schema['remote_profile'] = array(
    'description' => 'remote people (OMB)',
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'postnoticeurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for posting notices'),
        'updateprofileurl' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL we use for updates to this profile'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'remote_profile_uri_key' => array('uri'),
    ),
    'foreign keys' => array(
        'remote_profile_id_fkey' => array('profile', array('id' => 'id')),
    ),
);

$schema['subscription'] = array(
    'fields' => array(
        'subscriber' => array('type' => 'int', 'not null' => true, 'description' => 'profile listening'),
        'subscribed' => array('type' => 'int', 'not null' => true, 'description' => 'profile being listened to'),
        'jabber' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'deliver jabber messages'),
        'sms' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'deliver sms messages'),
        'token' => array('type' => 'varchar', 'length' => 255, 'description' => 'authorization token'),
        'secret' => array('type' => 'varchar', 'length' => 255, 'description' => 'token secret'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('subscriber', 'subscribed'),
    'indexes' => array(
        'subscription_subscriber_idx' => array('subscriber', 'created'),
        'subscription_subscribed_idx' => array('subscribed', 'created'),
        'subscription_token_idx' => array('token'),
    ),
);

$schema['notice'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'who made the update'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'content' => array('type' => 'text', 'description' => 'update content'),
        'rendered' => array('type' => 'text', 'description' => 'HTML version of the content'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of any attachment (image, video, bookmark, whatever)'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
        'reply_to' => array('type' => 'int', 'description' => 'notice replied to (usually a guess)'),
        'is_local' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'notice was generated by a user'),
        'source' => array('type' => 'varchar', 'length' => 32, 'description' => 'source of comment, like "web", "im", or "clientname"'),
        'conversation' => array('type' => 'int', 'description' => 'id of root notice in this conversation'),
        'lat' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'),
        'lon' => array('type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'),
        'location_id' => array('type' => 'int', 'description' => 'location id if possible'),
        'location_ns' => array('type' => 'int', 'description' => 'namespace for location'),
        'repeat_of' => array('type' => 'int', 'description' => 'notice this is a repeat of'),
        'object_type' => array('type' => 'varchar', 'length' => 255, 'description' => 'URI representing activity streams object type', 'default' => 'http://activitystrea.ms/schema/1.0/note'),
        'scope' => array('type' => 'int',
                         'default' => '1',
                         'description' => 'bit map for distribution scope; 0 = everywhere; 1 = this server only; 2 = addressees; 4 = followers'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'notice_uri_key' => array('uri'),
    ),
    'foreign keys' => array(
        'notice_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
        'notice_reply_to_fkey' => array('notice', array('reply_to' => 'id')),
        'notice_conversation_fkey' => array('conversation', array('conversation' => 'id')), # note... used to refer to notice.id
        'notice_repeat_of_fkey' => array('notice', array('repeat_of' => 'id')), # @fixme: what about repeats of deleted notices?
    ),
    'indexes' => array(
        'notice_profile_id_idx' => array('profile_id', 'created', 'id'),
        'notice_conversation_idx' => array('conversation'),
        'notice_created_idx' => array('created'),
        'notice_replyto_idx' => array('reply_to'),
        'notice_repeatof_idx' => array('repeat_of'),
    ),
    'fulltext indexes' => array(
        'content' => array('content'),
    )
);

$schema['notice_source'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'source code'),
        'name' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'name of the source'),
        'url' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'url to link to'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'date this record was created'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('code'),
);

$schema['reply'] = array(
    'fields' => array(
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the reply'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'profile replied to'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
        'replied_id' => array('type' => 'int', 'description' => 'notice replied to (not used, see notice.reply_to)'),
    ),
    'primary key' => array('notice_id', 'profile_id'),
    'foreign keys' => array(
        'reply_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
        'reply_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
    ),
    'indexes' => array(
        'reply_notice_id_idx' => array('notice_id'),
        'reply_profile_id_idx' => array('profile_id'),
        'reply_replied_id_idx' => array('replied_id'),
    ),
);

$schema['fave'] = array(
    'fields' => array(
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice that is the favorite'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who likes this notice'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('notice_id', 'user_id'),
    'foreign keys' => array(
        'fave_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
        'fave_user_id_fkey' => array('profile', array('user_id' => 'id')), // note: formerly referenced notice.id, but we can now record remote users' favorites
    ),
    'indexes' => array(
        'fave_notice_id_idx' => array('notice_id'),
        'fave_user_id_idx' => array('user_id', 'modified'),
        'fave_modified_idx' => array('modified'),
    ),
);

/* tables for OAuth */

$schema['consumer'] = array(
    'description' => 'OAuth consumer record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
        'consumer_secret' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'secret value'),
        'seed' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'seed for new tokens by this consumer'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key'),
);

$schema['token'] = array(
    'description' => 'OAuth token record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
        'tok' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'identifying value'),
        'secret' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'secret value'),
        'type' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 0, 'description' => 'request or access'),
        'state' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'for requests, 0 = initial, 1 = authorized, 2 = used'),
        'verifier' => array('type' => 'varchar', 'length' => 255, 'description' => 'verifier string for OAuth 1.0a'),
        'verified_callback' => array('type' => 'varchar', 'length' => 255, 'description' => 'verified callback URL for OAuth 1.0a'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key', 'tok'),
    'foreign keys' => array(
        'token_consumer_key_fkey' => array('consumer', array('consumer_key'=> 'consumer_key')),
    ),
);

$schema['nonce'] = array(
    'description' => 'OAuth nonce record',
    'fields' => array(
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'unique identifier, root URL'),
        'tok' => array('type' => 'char', 'length' => 32, 'description' => 'buggy old value, ignored'),
        'nonce' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'nonce'),
        'ts' => array('type' => 'datetime', 'not null' => true, 'description' => 'timestamp sent'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('consumer_key', 'ts', 'nonce'),
);

$schema['oauth_application'] = array(
    'description' => 'OAuth application registration record',
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'owner' => array('type' => 'int', 'not null' => true, 'description' => 'owner of the application'),
        'consumer_key' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'application consumer key'),
        'name' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'name of the application'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description of the application'),
        'icon' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'application icon'),
        'source_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'application homepage - used for source link'),
        'organization' => array('type' => 'varchar', 'length' => 255, 'description' => 'name of the organization running the application'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'homepage for the organization'),
        'callback_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'url to redirect to after authentication'),
        'type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'type of app, 1 = browser, 2 = desktop'),
        'access_type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'default access type, bit 1 = read, bit 2 = write'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'oauth_application_name_key' => array('name'), // in the long run, we should perhaps not force these unique, and use another source id
    ),
    'foreign keys' => array(
        'oauth_application_owner_fkey' => array('profile', array('owner' => 'id')), // Are remote users allowed to create oauth application records?
        'oauth_application_consumer_key_fkey' => array('consumer', array('consumer_key' => 'consumer_key')),
    ),
);

$schema['oauth_application_user'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'user of the application'),
        'application_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of the application'),
        'access_type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'access type, bit 1 = read, bit 2 = write'),
        'token' => array('type' => 'varchar', 'length' => 255, 'description' => 'request or access token'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('profile_id', 'application_id'),
    'foreign keys' => array(
        'oauth_application_user_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
        'oauth_application_user_application_id_fkey' => array('oauth_application', array('application_id' => 'id')),
    ),
);

/* These are used by JanRain OpenID library */

$schema['oid_associations'] = array(
    'fields' => array(
        'server_url' => array('type' => 'blob', 'not null' => true),
        'handle' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'default' => ''), // character set latin1,
        'secret' => array('type' => 'blob'),
        'issued' => array('type' => 'int'),
        'lifetime' => array('type' => 'int'),
        'assoc_type' => array('type' => 'varchar', 'length' => 64),
    ),
    'primary key' => array(array('server_url', 255), 'handle'),
);

$schema['oid_nonces'] = array(
    'fields' => array(
        'server_url' => array('type' => 'varchar', 'length' => 2047),
        'timestamp' => array('type' => 'int'),
        'salt' => array('type' => 'char', 'length' => 40),
    ),
    'unique keys' => array(
        'oid_nonces_server_url_timestamp_salt_key' => array(array('server_url', 255), 'timestamp', 'salt'),
    ),
);

$schema['confirm_address'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who requested confirmation'),
        'address' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'address (email, xmpp, SMS, etc.)'),
        'address_extra' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'carrier ID, for SMS'),
        'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
        'claimed' => array('type' => 'datetime', 'description' => 'date this was claimed for queueing'),
        'sent' => array('type' => 'datetime', 'description' => 'date this was sent for queueing'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('code'),
    'foreign keys' => array(
        'confirm_address_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['remember_me'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who is logged in'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('code'),
    'foreign keys' => array(
        'remember_me_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['queue_item'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'frame' => array('type' => 'blob', 'not null' => true, 'description' => 'data: object reference or opaque string'),
        'transport' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'queue for what? "email", "xmpp", "sms", "irc", ...'), // @fixme 8 chars is too short; bump up.
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'claimed' => array('type' => 'datetime', 'description' => 'date this item was claimed'),
    ),
    'primary key' => array('id'),
    'indexes' => array(
        'queue_item_created_idx' => array('created'),
    ),
);

$schema['notice_tag'] = array(
    'description' => 'Hash tags',
    'fields' => array(
        'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice tagged'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('tag', 'notice_id'),
    'foreign keys' => array(
        'notice_tag_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
    ),
    'indexes' => array(
        'notice_tag_created_idx' => array('created'),
        'notice_tag_notice_id_idx' => array('notice_id'),
    ),
);

/* Synching with foreign services */

$schema['foreign_service'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'numeric key for service'),
        'name' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'name of the service'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'foreign_service_name_key' => array('name'),
    ),
);

$schema['foreign_user'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'unique numeric key on foreign service'),
        'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'identifying URI'),
        'nickname' => array('type' => 'varchar', 'length' => 255, 'description' => 'nickname on foreign service'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id', 'service'),
    'foreign keys' => array(
        'foreign_user_service_fkey' => array('foreign_service', array('service' => 'id')),
    ),
    'unique keys' => array(
        'foreign_user_uri_key' => array('uri'),
    ),
);

$schema['foreign_link'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'link to user on this system, if exists'),
        'foreign_id' => array('type' => 'int', 'size' => 'big', 'unsigned' => true, 'not null' => true, 'description' => 'link to user on foreign service, if exists'),
        'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service'),
        'credentials' => array('type' => 'varchar', 'length' => 255, 'description' => 'authc credentials, typically a password'),
        'noticesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'notice synchronization, bit 1 = sync outgoing, bit 2 = sync incoming, bit 3 = filter local replies'),
        'friendsync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 2, 'description' => 'friend synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
        'profilesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'profile synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
        'last_noticesync' => array('type' => 'datetime', 'description' => 'last time notices were imported'),
        'last_friendsync' => array('type' => 'datetime', 'description' => 'last time friends were imported'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id', 'foreign_id', 'service'),
    'foreign keys' => array(
        'foreign_link_user_id_fkey' => array('user', array('user_id' => 'id')),
        'foreign_link_foreign_id_fkey' => array('foreign_user', array('foreign_id' => 'id', 'service' => 'service')),
        'foreign_link_service_fkey' => array('foreign_service', array('service' => 'id')),
    ),
    'indexes' => array(
        'foreign_user_user_id_idx' => array('user_id'),
    ),
);

$schema['foreign_subscription'] = array(
    'fields' => array(
        'service' => array('type' => 'int', 'not null' => true, 'description' => 'service where relationship happens'),
        'subscriber' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'subscriber on foreign service'),
        'subscribed' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'subscribed user'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('service', 'subscriber', 'subscribed'),
    'foreign keys' => array(
        'foreign_subscription_service_fkey' => array('foreign_service', array('service' => 'id')),
        'foreign_subscription_subscriber_fkey' => array('foreign_user', array('subscriber' => 'id', 'service' => 'service')),
        'foreign_subscription_subscribed_fkey' => array('foreign_user', array('subscribed' => 'id', 'service' => 'service')),
    ),
    'indexes' => array(
        'foreign_subscription_subscriber_idx' => array('service', 'subscriber'),
        'foreign_subscription_subscribed_idx' => array('service', 'subscribed'),
    ),
);

$schema['invitation'] = array(
    'fields' => array(
        'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'random code for an invitation'),
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'who sent the invitation'),
        'address' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'invitation sent to'),
        'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'registered_user_id' => array('type' => 'int', 'not null' => false, 'description' => 'if the invitation is converted, who the new user is'),
    ),
    'primary key' => array('code'),
    'foreign keys' => array(
        'invitation_user_id_fkey' => array('user', array('user_id' => 'id')),
        'invitation_registered_user_id_fkey' => array('user', array('registered_user_id' => 'id')),
    ),
    'indexes' => array(
        'invitation_address_idx' => array('address', 'address_type'),
        'invitation_user_id_idx' => array('user_id'),
        'invitation_registered_user_id_idx' => array('registered_user_id'),
    ),
);

$schema['message'] = array(
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

$schema['notice_inbox'] = array(
    'description' => 'Obsolete; old entries here are converted to packed entries in the inbox table since 0.9',
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the message'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
        'source' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'reason it is in the inbox, 1=subscription'),
    ),
    'primary key' => array('user_id', 'notice_id'),
    'foreign keys' => array(
        'notice_inbox_user_id_fkey' => array('user', array('user_id' => 'id')),
        'notice_inbox_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
    ),
    'indexes' => array(
        'notice_inbox_notice_id_idx' => array('notice_id'),
    ),
);

$schema['profile_tag'] = array(
    'fields' => array(
        'tagger' => array('type' => 'int', 'not null' => true, 'description' => 'user making the tag'),
        'tagged' => array('type' => 'int', 'not null' => true, 'description' => 'profile tagged'),
        'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date the tag was added'),
    ),
    'primary key' => array('tagger', 'tagged', 'tag'),
    'foreign keys' => array(
        'profile_tag_tagger_fkey' => array('profile', array('tagger' => 'id')),
        'profile_tag_tagged_fkey' => array('profile', array('tagged' => 'id')),
        'profile_tag_tag_fkey' => array('profile_list', array('tag' => 'tag')),
    ),
    'indexes' => array(
        'profile_tag_modified_idx' => array('modified'),
        'profile_tag_tagger_tag_idx' => array('tagger', 'tag'),
        'profile_tag_tagged_idx' => array('tagged'),
    ),
);

$schema['profile_list'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'tagger' => array('type' => 'int', 'not null' => true, 'description' => 'user making the tag'),
        'tag' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'people tag'),
        'description' => array('type' => 'text', 'description' => 'description of the people tag'),
        'private' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'is this tag private'),

        'created' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date the tag was added'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date the tag was modified'),

        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universal identifier'),
        'mainpage' => array('type' => 'varchar', 'length' => 255, 'description' => 'page to link to'),
        'tagged_count' => array('type' => 'int', 'default' => 0, 'description' => 'number of people tagged with this tag by this user'),
        'subscriber_count' => array('type' => 'int', 'default' => 0, 'description' => 'number of subscribers to this tag'),
    ),
    'primary key' => array('tagger', 'tag'),
    'unique keys' => array(
      'profile_list_id_key' => array('id')
    ),
    'foreign keys' => array(
        'profile_list_tagger_fkey' => array('profile', array('tagger' => 'id')),
    ),
    'indexes' => array(
        'profile_list_modified_idx' => array('modified'),
        'profile_list_tag_idx' => array('tag'),
        'profile_list_tagger_tag_idx' => array('tagger', 'tag'),
        'profile_list_tagged_count_idx' => array('tagged_count'),
        'profile_list_subscriber_count_idx' => array('subscriber_count'),
    ),
);

$schema['profile_tag_inbox'] = array(
    'description' => 'Many-many table listing notices associated with people tags.',
    'fields' => array(
        'profile_tag_id' => array('type' => 'int', 'not null' => true, 'description' => 'people tag receiving the message'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
    ),
    'primary key' => array('profile_tag_id', 'notice_id'),
    'foreign keys' => array(
        'profile_tag_inbox_profile_list_id_fkey' => array('profile_list', array('profile_tag_id' => 'id')),
        'profile_tag_inbox_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
    ),
    'indexes' => array(
        'profile_tag_inbox_created_idx' => array('created'),
        'profile_tag_inbox_profile_tag_id_idx' => array('profile_tag_id'),
    ),
);

$schema['profile_tag_subscription'] = array(
    'fields' => array(
        'profile_tag_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile_tag'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('profile_tag_id', 'profile_id'),
    'foreign keys' => array(
        'profile_tag_subscription_profile_list_id_fkey' => array('profile_list', array('profile_tag_id' => 'id')),
        'profile_tag_subscription_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
    ),
    'indexes' => array(
        // @fixme probably we want a (profile_id, created) index here?
        'profile_tag_subscription_profile_id_idx' => array('profile_id'),
        'profile_tag_subscription_created_idx' => array('created'),
    ),
);

$schema['profile_block'] = array(
    'fields' => array(
        'blocker' => array('type' => 'int', 'not null' => true, 'description' => 'user making the block'),
        'blocked' => array('type' => 'int', 'not null' => true, 'description' => 'profile that is blocked'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date of blocking'),
    ),
    'foreign keys' => array(
        'profile_block_blocker_fkey' => array('user', array('blocker' => 'id')),
        'profile_block_blocked_fkey' => array('profile', array('blocked' => 'id')),
    ),
    'primary key' => array('blocker', 'blocked'),
);

$schema['user_group'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),

        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'nickname for addressing'),
        'fullname' => array('type' => 'varchar', 'length' => 255, 'description' => 'display name'),
        'homepage' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL, cached so we dont regenerate'),
        'description' => array('type' => 'text', 'description' => 'group description'),
        'location' => array('type' => 'varchar', 'length' => 255, 'description' => 'related physical location, if any'),

        'original_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'original size logo'),
        'homepage_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'homepage (profile) size logo'),
        'stream_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'stream-sized logo'),
        'mini_logo' => array('type' => 'varchar', 'length' => 255, 'description' => 'mini logo'),
        'design_id' => array('type' => 'int', 'description' => 'id of a design'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),

        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universal identifier'),
        'mainpage' => array('type' => 'varchar', 'length' => 255, 'description' => 'page for group info to link to'),
        'join_policy' => array('type' => 'int', 'size' => 'tiny', 'description' => '0=open; 1=requires admin approval'),      
        'force_scope' => array('type' => 'int', 'size' => 'tiny', 'description' => '0=never,1=sometimes,-1=always'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'user_group_uri_key' => array('uri'),
    ),
    'foreign keys' => array(
        'user_group_design_id_fkey' => array('design', array('design_id' => 'id')),
    ),
    'indexes' => array(
        'user_group_nickname_idx' => array('nickname'),
    ),
);

$schema['group_member'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
        'is_admin' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'is this user an admin?'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('group_id', 'profile_id'),
    'foreign keys' => array(
        'group_member_group_id_fkey' => array('user_group', array('group_id' => 'id')),
        'group_member_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
    ),
    'indexes' => array(
        // @fixme probably we want a (profile_id, created) index here?
        'group_member_profile_id_idx' => array('profile_id'),
        'group_member_created_idx' => array('created'),
    ),
);

$schema['related_group'] = array(
    // @fixme description for related_group?
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),
        'related_group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
    ),
    'primary key' => array('group_id', 'related_group_id'),
    'foreign keys' => array(
        'related_group_group_id_fkey' => array('user_group', array('group_id' => 'id')),
        'related_group_related_group_id_fkey' => array('user_group', array('related_group_id' => 'id')),
    ),
);

$schema['group_inbox'] = array(
    'description' => 'Many-many table listing notices posted to a given group, or which groups a given notice was posted to.',
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group receiving the message'),
        'notice_id' => array('type' => 'int', 'not null' => true, 'description' => 'notice received'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice was created'),
    ),
    'primary key' => array('group_id', 'notice_id'),
    'foreign keys' => array(
        'group_inbox_group_id_fkey' => array('user_group', array('group_id' => 'id')),
        'group_inbox_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
    ),
    'indexes' => array(
        'group_inbox_created_idx' => array('created'),
        'group_inbox_notice_id_idx' => array('notice_id'),
    ),
);

$schema['file'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'destination URL after following redirections'),
        'mimetype' => array('type' => 'varchar', 'length' => 50, 'description' => 'mime type of resource'),
        'size' => array('type' => 'int', 'description' => 'size of resource when available'),
        'title' => array('type' => 'varchar', 'length' => 255, 'description' => 'title of resource when available'),
        'date' => array('type' => 'int', 'description' => 'date of resource according to http query'),
        'protected' => array('type' => 'int', 'description' => 'true when URL is private (needs login)'),
        'filename' => array('type' => 'varchar', 'length' => 255, 'description' => 'if a local file, name of the file'),

        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'file_url_key' => array('url'),
    ),
);

$schema['file_oembed'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'oEmbed for that URL/file'),
        'version' => array('type' => 'varchar', 'length' => 20, 'description' => 'oEmbed spec. version'),
        'type' => array('type' => 'varchar', 'length' => 20, 'description' => 'oEmbed type: photo, video, link, rich'),
        'mimetype' => array('type' => 'varchar', 'length' => 50, 'description' => 'mime type of resource'),
        'provider' => array('type' => 'varchar', 'length' => 50, 'description' => 'name of this oEmbed provider'),
        'provider_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of this oEmbed provider'),
        'width' => array('type' => 'int', 'description' => 'width of oEmbed resource when available'),
        'height' => array('type' => 'int', 'description' => 'height of oEmbed resource when available'),
        'html' => array('type' => 'text', 'description' => 'html representation of this oEmbed resource when applicable'),
        'title' => array('type' => 'varchar', 'length' => 255, 'description' => 'title of oEmbed resource when available'),
        'author_name' => array('type' => 'varchar', 'length' => 50, 'description' => 'author name for this oEmbed resource'),
        'author_url' => array('type' => 'varchar', 'length' => 255, 'description' => 'author URL for this oEmbed resource'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL for this oEmbed resource when applicable (photo, link)'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('file_id'),
    'foreign keys' => array(
         'file_oembed_file_id_fkey' => array('file', array('file_id' => 'id')),
    ),
);

$schema['file_redirection'] = array(
    'fields' => array(
        'url' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'short URL (or any other kind of redirect) for file (id)'),
        'file_id' => array('type' => 'int', 'description' => 'short URL for what URL/file'),
        'redirections' => array('type' => 'int', 'description' => 'redirect count'),
        'httpcode' => array('type' => 'int', 'description' => 'HTTP status code (20x, 30x, etc.)'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('url'),
    'foreign keys' => array(
         'file_redirection_file_id_fkey' => array('file' => array('file_id' => 'id')),
    ),
);

$schema['file_thumbnail'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'thumbnail for what URL/file'),
        'url' => array('type' => 'varchar', 'length' => 255, 'description' => 'URL of thumbnail'),
        'width' => array('type' => 'int', 'description' => 'width of thumbnail'),
        'height' => array('type' => 'int', 'description' => 'height of thumbnail'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('file_id'),
    'foreign keys' => array(
        'file_thumbnail_file_id_fkey' => array('file', array('file_id' => 'id')),
    ),
    'unique keys' => array(
        'file_thumbnail_url_key' => array('url'),
    ),
);

$schema['file_to_post'] = array(
    'fields' => array(
        'file_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of URL/file'),
        'post_id' => array('type' => 'int', 'not null' => true, 'description' => 'id of the notice it belongs to'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('file_id', 'post_id'),
    'foreign keys' => array(
        'file_to_post_file_id_fkey' => array('file', array('file_id' => 'id')),
        'file_to_post_post_id_fkey' => array('notice', array('post_id' => 'id')),
    ),
    'indexes' => array(
        'post_id_idx' => array('post_id'),
    ),
);

$schema['design'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'design ID'),
        'backgroundcolor' => array('type' => 'int', 'description' => 'main background color'),
        'contentcolor' => array('type' => 'int', 'description' => 'content area background color'),
        'sidebarcolor' => array('type' => 'int', 'description' => 'sidebar background color'),
        'textcolor' => array('type' => 'int', 'description' => 'text color'),
        'linkcolor' => array('type' => 'int', 'description' => 'link color'),
        'backgroundimage' => array('type' => 'varchar', 'length' => 255, 'description' => 'background image, if any'),
        'disposition' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'bit 1 = hide background image, bit 2 = display background image, bit 4 = tile background image'),
    ),
    'primary key' => array('id'),
);

$schema['group_block'] = array(
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group profile is blocked from'),
        'blocked' => array('type' => 'int', 'not null' => true, 'description' => 'profile that is blocked'),
        'blocker' => array('type' => 'int', 'not null' => true, 'description' => 'user making the block'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date of blocking'),
    ),
    'primary key' => array('group_id', 'blocked'),
    'foreign keys' => array(
        'group_block_group_id_fkey' => array('user_group', array('group_id' => 'id')),
        'group_block_blocked_fkey' => array('profile', array('blocked' => 'id')),
        'group_block_blocker_fkey' => array('user', array('blocker' => 'id')),
    ),
);

$schema['group_alias'] = array(
    'fields' => array(
        'alias' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'additional nickname for the group'),
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group profile is blocked from'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date alias was created'),
    ),
    'primary key' => array('alias'),
    'foreign keys' => array(
        'group_alias_group_id_fkey' => array('user_group', array('group_id' => 'id')),
    ),
    'indexes' => array(
        'group_alias_group_id_idx' => array('group_id'),
    ),
);

$schema['session'] = array(
    'fields' => array(
        'id' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'session ID'),
        'session_data' => array('type' => 'text', 'description' => 'session data'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'indexes' => array(
        'session_modified_idx' => array('modified'),
    ),
);

$schema['deleted_notice'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'identity of notice'),
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'author of the notice'),
        'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'universally unique identifier, usually a tag URI'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),
        'deleted' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the notice record was created'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'deleted_notice_uri_key' => array('uri'),
    ),
    'indexes' => array(
        'deleted_notice_profile_id_idx' => array('profile_id'),
    ),
);

$schema['config'] = array(
    'fields' => array(
        'section' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'default' => '', 'description' => 'configuration section'),
        'setting' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'default' => '', 'description' => 'configuration setting'),
        'value' => array('type' => 'varchar', 'length' => 255, 'description' => 'configuration value'),
    ),
    'primary key' => array('section', 'setting'),
);

$schema['profile_role'] = array(
    'fields' => array(
        'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'account having the role'),
        'role' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'string representing the role'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the role was granted'),
    ),
    'primary key' => array('profile_id', 'role'),
    'foreign keys' => array(
        'profile_role_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
    ),
);

$schema['location_namespace'] = array(
    'fields' => array(
        'id' => array('type' => 'int', 'not null' => true, 'description' => 'identity for this namespace'),
        'description' => array('type' => 'varchar', 'length' => 255, 'description' => 'description of the namespace'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date the record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
);

$schema['login_token'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user owning this token'),
        'token' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'token useable for logging in'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id'),
    'foreign keys' => array(
        'login_token_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['user_location_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who has the preference'),
        'share_location' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Whether to share location data'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id'),
    'foreign keys' => array(
        'user_location_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['inbox'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the notice'),
        'notice_ids' => array('type' => 'blob', 'description' => 'packed list of notice ids'),
    ),
    'primary key' => array('user_id'),
    'foreign keys' => array(
        'inbox_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

// @fixme possibly swap this for a more general prefs table?
$schema['user_im_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user'),
        'screenname' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'screenname on this service'),
        'transport' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'transport (ex xmpp, aim)'),
        'notify' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 0, 'description' => 'Notify when a new notice is sent'),
        'replies' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 0, 'description' => 'Send replies  from people not subscribed to'),
        'microid' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'Publish a MicroID'),
        'updatefrompresence' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 0, 'description' => 'Send replies from people not subscribed to.'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id', 'transport'),
    'unique keys' => array(
        'transport_screenname_key' => array('transport', 'screenname'),
    ),
    'foreign keys' => array(
        'user_im_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['conversation'] = array(
    'fields' => array(
        'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
        'uri' => array('type' => 'varchar', 'length' => 225, 'description' => 'URI of the conversation'),
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('id'),
    'unique keys' => array(
        'conversation_uri_key' => array('uri'),
    ),
);

$schema['local_group'] = array(
    'description' => 'Record for a user group on the local site, with some additional info not in user_group',
    'fields' => array(
        'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group represented'),
        'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'group represented'),

        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('group_id'),
    'foreign keys' => array(
        'local_group_group_id_fkey' => array('user_group', array('group_id' => 'id')),
    ),
    'unique keys' => array(
        'local_group_nickname_key' => array('nickname'),
    ),
);

$schema['user_urlshortener_prefs'] = array(
    'fields' => array(
        'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user'),
        'urlshorteningservice' => array('type' => 'varchar', 'length' => 50, 'default' => 'internal', 'description' => 'service to use for auto-shortening URLs'),
        'maxurllength' => array('type' => 'int', 'not null' => true, 'description' => 'urls greater than this length will be shortened, 0 = always, null = never'),
        'maxnoticelength' => array('type' => 'int', 'not null' => true, 'description' => 'notices with content greater than this value will have all urls shortened, 0 = always, null = never'),
        
        'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('user_id'),
    'foreign keys' => array(
        'user_urlshortener_prefs_user_id_fkey' => array('user', array('user_id' => 'id')),
    ),
);

$schema['schema_version'] = array(
    'description' => 'To avoid checking database structure all the time, we store a checksum of the expected schema info for each table here. If it has not changed since the last time we checked the table, we can leave it as is.',
    'fields' => array(
        'table_name' => array('type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Table name'),
        'checksum' => array('type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Checksum of schema array; a mismatch indicates we should check the table more thoroughly.'),
        'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
    ),
    'primary key' => array('table_name'),
);

$schema['group_join_queue'] = Group_join_queue::schemaDef();

$schema['subscription_queue'] = Subscription_queue::schemaDef();

$schema['oauth_token_association'] = Oauth_token_association::schemaDef();
