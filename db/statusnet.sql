/* local and remote users have profiles */

create table profile (

    id integer auto_increment primary key comment 'unique identifier',
    nickname varchar(64) not null comment 'nickname or username',
    fullname varchar(255) comment 'display name',
    profileurl varchar(255) comment 'URL, cached so we dont regenerate',
    homepage varchar(255) comment 'identifying URL',
    bio text comment 'descriptive biography',
    location varchar(255) comment 'physical location',
    lat decimal(10,7) comment 'latitude',
    lon decimal(10,7) comment 'longitude',
    location_id integer comment 'location id if possible',
    location_ns integer comment 'namespace for location',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index profile_nickname_idx (nickname),
    FULLTEXT(nickname, fullname, location, bio, homepage)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;

create table avatar (
    profile_id integer not null comment 'foreign key to profile table' references profile (id),
    original boolean default false comment 'uploaded by user or generated?',
    width integer not null comment 'image width',
    height integer not null comment 'image height',
    mediatype varchar(32) not null comment 'file type',
    filename varchar(255) null comment 'local filename, if local',
    url varchar(255) unique key comment 'avatar location',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (profile_id, width, height),
    index avatar_profile_id_idx (profile_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table sms_carrier (
    id integer primary key comment 'primary key for SMS carrier',
    name varchar(64) unique key comment 'name of the carrier',
    email_pattern varchar(255) not null comment 'sprintf pattern for making an email address from a phone number',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* local users */

create table user (

    id integer primary key comment 'foreign key to profile table' references profile (id),
    nickname varchar(64) unique key comment 'nickname or username, duped in profile',
    password varchar(255) comment 'salted password, can be null for OpenID users',
    email varchar(255) unique key comment 'email address for password recovery etc.',
    incomingemail varchar(255) unique key comment 'email address for post-by-email',
    emailnotifysub tinyint default 1 comment 'Notify by email of subscriptions',
    emailnotifyfav tinyint default 1 comment 'Notify by email of favorites',
    emailnotifynudge tinyint default 1 comment 'Notify by email of nudges',
    emailnotifymsg tinyint default 1 comment 'Notify by email of direct messages',
    emailnotifyattn tinyint default 1 comment 'Notify by email of @-replies',
    emailmicroid tinyint default 1 comment 'whether to publish email microid',
    language varchar(50) comment 'preferred language',
    timezone varchar(50) comment 'timezone',
    emailpost tinyint default 1 comment 'Post by email',
    sms varchar(64) unique key comment 'sms phone number',
    carrier integer comment 'foreign key to sms_carrier' references sms_carrier (id),
    smsnotify tinyint default 0 comment 'whether to send notices to SMS',
    smsreplies tinyint default 0 comment 'whether to send notices to SMS on replies',
    smsemail varchar(255) comment 'built from sms and carrier',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    autosubscribe tinyint default 0 comment 'automatically subscribe to users who subscribe to us',
    urlshorteningservice varchar(50) default 'ur1.ca' comment 'service to use for auto-shortening URLs',
    inboxed tinyint default 0 comment 'has an inbox been created for this user?',
    design_id integer comment 'id of a design' references design(id),
    viewdesigns tinyint default 1 comment 'whether to view user-provided designs',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index user_smsemail_idx (smsemail)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

/* remote people */

create table remote_profile (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    postnoticeurl varchar(255) comment 'URL we use for posting notices',
    updateprofileurl varchar(255) comment 'URL we use for updates to this profile',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table subscription (
    subscriber integer not null comment 'profile listening',
    subscribed integer not null comment 'profile being listened to',
    jabber tinyint default 1 comment 'deliver jabber messages',
    sms tinyint default 1 comment 'deliver sms messages',
    token varchar(255) comment 'authorization token',
    secret varchar(255) comment 'token secret',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (subscriber, subscribed),
    index subscription_subscriber_idx (subscriber, created),
    index subscription_subscribed_idx (subscribed, created),
    index subscription_token_idx (token)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table notice (
    id integer auto_increment primary key comment 'unique identifier',
    profile_id integer not null comment 'who made the update' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    content text comment 'update content',
    rendered text comment 'HTML version of the content',
    url varchar(255) comment 'URL of any attachment (image, video, bookmark, whatever)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    reply_to integer comment 'notice replied to (usually a guess)' references notice (id),
    is_local tinyint default 0 comment 'notice was generated by a user',
    source varchar(32) comment 'source of comment, like "web", "im", or "clientname"',
    conversation integer comment 'id of root notice in this conversation' references notice (id),
    lat decimal(10,7) comment 'latitude',
    lon decimal(10,7) comment 'longitude',
    location_id integer comment 'location id if possible',
    location_ns integer comment 'namespace for location',
    repeat_of integer comment 'notice this is a repeat of' references notice (id),

    -- For public timeline...
    index notice_created_id_is_local_idx (created,id,is_local),

    -- For profile timelines...
    index notice_profile_id_idx (profile_id,created,id),

    -- For api/statuses/repeats...
    index notice_repeat_of_created_id_idx (repeat_of, created, id),

    -- For conversation views
    index notice_conversation_created_id_idx (conversation, created, id),

    -- Are these needed/used?
    index notice_replyto_idx (reply_to),

    FULLTEXT(content)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;

create table notice_source (
     code varchar(32) primary key not null comment 'source code',
     name varchar(255) not null comment 'name of the source',
     url varchar(255) not null comment 'url to link to',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table reply (
    notice_id integer not null comment 'notice that is the reply' references notice (id),
    profile_id integer not null comment 'profile replied to' references profile (id),
    modified timestamp not null comment 'date this record was modified',
    replied_id integer comment 'notice replied to (not used, see notice.reply_to)',

    constraint primary key (notice_id, profile_id),
    index reply_notice_id_idx (notice_id),
    index reply_profile_id_idx (profile_id),
    index reply_replied_id_idx (replied_id),

    -- Needed for sorting reply/mentions timelines
    index reply_profile_id_modified_notice_id_idx (profile_id, modified, notice_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table fave (
    notice_id integer not null comment 'notice that is the favorite' references notice (id),
    user_id integer not null comment 'user who likes this notice' references user (id),
    modified timestamp not null comment 'date this record was modified',

    constraint primary key (notice_id, user_id),
    index fave_notice_id_idx (notice_id),
    index fave_user_id_idx (user_id,modified),
    index fave_modified_idx (modified)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* tables for OAuth */

create table consumer (
    consumer_key varchar(255) primary key comment 'unique identifier, root URL',
    consumer_secret varchar(255) not null comment 'secret value',
    seed char(32) not null comment 'seed for new tokens by this consumer',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table token (
    consumer_key varchar(255) not null comment 'unique identifier, root URL' references consumer (consumer_key),
    tok char(32) not null comment 'identifying value',
    secret char(32) not null comment 'secret value',
    type tinyint not null default 0 comment 'request or access',
    state tinyint default 0 comment 'for requests, 0 = initial, 1 = authorized, 2 = used',
    verifier varchar(255) comment 'verifier string for OAuth 1.0a',
    verified_callback varchar(255) comment 'verified callback URL for OAuth 1.0a',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (consumer_key, tok)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table nonce (
    consumer_key varchar(255) not null comment 'unique identifier, root URL',
    tok char(32) null comment 'buggy old value, ignored',
    nonce char(32) not null comment 'nonce',
    ts datetime not null comment 'timestamp sent',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (consumer_key, ts, nonce)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oauth_application (
    id integer auto_increment primary key comment 'unique identifier',
    owner integer not null comment 'owner of the application' references profile (id),
    consumer_key varchar(255) not null comment 'application consumer key' references consumer (consumer_key),
    name varchar(255) not null unique key comment 'name of the application',
    description varchar(255) comment 'description of the application',
    icon varchar(255) not null comment 'application icon',
    source_url varchar(255) comment 'application homepage - used for source link',
    organization varchar(255) comment 'name of the organization running the application',
    homepage varchar(255) comment 'homepage for the organization',
    callback_url varchar(255) comment 'url to redirect to after authentication',
    type tinyint default 0 comment 'type of app, 1 = browser, 2 = desktop',
    access_type tinyint default 0 comment 'default access type, bit 1 = read, bit 2 = write',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oauth_application_user (
    profile_id integer not null comment 'user of the application' references profile (id),
    application_id integer not null comment 'id of the application' references oauth_application (id),
    access_type tinyint default 0 comment 'access type, bit 1 = read, bit 2 = write',
    token varchar(255) comment 'request or access token',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    constraint primary key (profile_id, application_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oauth_token_association (
    profile_id integer not null comment 'user of the application' references profile (id),
    application_id integer not null comment 'id of the application' references oauth_application (id),
    token varchar(255) comment 'request or access token',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    constraint primary key (profile_id, application_id, token)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* These are used by JanRain OpenID library */

create table oid_associations (
    server_url BLOB,
    handle VARCHAR(255) character set latin1,
    secret BLOB,
    issued INTEGER,
    lifetime INTEGER,
    assoc_type VARCHAR(64),
    PRIMARY KEY (server_url(255), handle)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oid_nonces (
    server_url VARCHAR(2047),
    timestamp INTEGER,
    salt CHAR(40),
    UNIQUE (server_url(255), timestamp, salt)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table confirm_address (
    code varchar(32) not null primary key comment 'good random code',
    user_id integer not null comment 'user who requested confirmation' references user (id),
    address varchar(255) not null comment 'address (email, xmpp, SMS, etc.)',
    address_extra varchar(255) not null comment 'carrier ID, for SMS',
    address_type varchar(8) not null comment 'address type ("email", "xmpp", "sms")',
    claimed datetime comment 'date this was claimed for queueing',
    sent datetime comment 'date this was sent for queueing',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table remember_me (
    code varchar(32) not null primary key comment 'good random code',
    user_id integer not null comment 'user who is logged in' references user (id),
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table queue_item (
    id integer auto_increment primary key comment 'unique identifier',
    frame blob not null comment 'data: object reference or opaque string',
    transport varchar(8) not null comment 'queue for what? "email", "xmpp", "sms", "irc", ...',
    created datetime not null comment 'date this record was created',
    claimed datetime comment 'date this item was claimed',

    index queue_item_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* Hash tags */
create table notice_tag (
    tag varchar( 64 ) not null comment 'hash tag associated with this notice',
    notice_id integer not null comment 'notice tagged' references notice (id),
    created datetime not null comment 'date this record was created',

    constraint primary key (tag, notice_id),
    index notice_tag_created_idx (created),
    index notice_tag_notice_id_idx (notice_id),

    -- For sorting tag-filtered public timeline
    index notice_tag_tag_created_notice_id_idx (tag, created, notice_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* Synching with foreign services */

create table foreign_service (
     id int not null primary key comment 'numeric key for service',
     name varchar(32) not null unique key comment 'name of the service',
     description varchar(255) comment 'description',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table foreign_user (
     id bigint not null comment 'unique numeric key on foreign service',
     service int not null comment 'foreign key to service' references foreign_service(id),
     uri varchar(255) not null unique key comment 'identifying URI',
     nickname varchar(255) comment 'nickname on foreign service',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified',

     constraint primary key (id, service)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table foreign_link (
     user_id int comment 'link to user on this system, if exists' references user (id),
     foreign_id bigint unsigned comment 'link to user on foreign service, if exists' references foreign_user(id),
     service int not null comment 'foreign key to service' references foreign_service(id),
     credentials varchar(255) comment 'authc credentials, typically a password',
     noticesync tinyint not null default 1 comment 'notice synchronization, bit 1 = sync outgoing, bit 2 = sync incoming, bit 3 = filter local replies',
     friendsync tinyint not null default 2 comment 'friend synchronization, bit 1 = sync outgoing, bit 2 = sync incoming',
     profilesync tinyint not null default 1 comment 'profile synchronization, bit 1 = sync outgoing, bit 2 = sync incoming',
     last_noticesync datetime default null comment 'last time notices were imported',
     last_friendsync datetime default null comment 'last time friends were imported',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified',

     constraint primary key (user_id, foreign_id, service),
     index foreign_user_user_id_idx (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table foreign_subscription (
     service int not null comment 'service where relationship happens' references foreign_service(id),
     subscriber int not null comment 'subscriber on foreign service' references foreign_user (id),
     subscribed int not null comment 'subscribed user' references foreign_user (id),
     created datetime not null comment 'date this record was created',

     constraint primary key (service, subscriber, subscribed),
     index foreign_subscription_subscriber_idx (subscriber),
     index foreign_subscription_subscribed_idx (subscribed)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table invitation (
     code varchar(32) not null primary key comment 'random code for an invitation',
     user_id int not null comment 'who sent the invitation' references user (id),
     address varchar(255) not null comment 'invitation sent to',
     address_type varchar(8) not null comment 'address type ("email", "xmpp", "sms")',
     created datetime not null comment 'date this record was created',

     index invitation_address_idx (address, address_type),
     index invitation_user_id_idx (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table message (
    id integer auto_increment primary key comment 'unique identifier',
    uri varchar(255) unique key comment 'universally unique identifier',
    from_profile integer not null comment 'who the message is from' references profile (id),
    to_profile integer not null comment 'who the message is to' references profile (id),
    content text comment 'message content',
    rendered text comment 'HTML version of the content',
    url varchar(255) comment 'URL of any attachment (image, video, bookmark, whatever)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    source varchar(32) comment 'source of comment, like "web", "im", or "clientname"',

    index message_from_idx (from_profile),
    index message_to_idx (to_profile),
    index message_created_idx (created)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

create table notice_inbox (
    user_id integer not null comment 'user receiving the message' references user (id),
    notice_id integer not null comment 'notice received' references notice (id),
    created datetime not null comment 'date the notice was created',
    source tinyint default 1 comment 'reason it is in the inbox, 1=subscription',

    constraint primary key (user_id, notice_id),
    index notice_inbox_notice_id_idx (notice_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_tag (
   tagger integer not null comment 'user making the tag' references profile (id),
   tagged integer not null comment 'profile tagged' references profile (id),
   tag varchar(64) not null comment 'hash tag associated with this notice',
   modified timestamp comment 'date the tag was added',

   constraint primary key (tagger, tagged, tag),
   index profile_tag_modified_idx (modified),
   index profile_tag_tagger_tag_idx (tagger, tag),
   index profile_tag_tagged_idx (tagged)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* people tag metadata */
create table profile_list (
    id integer auto_increment unique key comment 'unique identifier',
    tagger integer not null comment 'user making the tag' references profile (id),
    tag varchar(64) not null comment 'hash tag',
    description text comment 'description for the tag',
    private tinyint(1) default 0 comment 'is this list private',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    uri varchar(255) unique key comment 'universal identifier',
    mainpage varchar(255) comment 'page for tag info info to link to',
    tagged_count smallint not null default 0 comment 'number of people tagged',
    subscriber_count smallint not null default 0 comment 'number of people subscribing',

    constraint primary key (tagger, tag),
    index profile_list_tag_idx (tag),
    index profile_list_tagged_count_idx (tagged_count),
    index profile_list_modified_idx (modified),
    index profile_list_subscriber_count_idx (subscriber_count)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_tag_inbox (
    profile_tag_id integer not null comment 'peopletag receiving the message' references profile_tag (id),
    notice_id integer not null comment 'notice received' references notice (id),
    created datetime not null comment 'date the notice was created',

    constraint primary key (profile_tag_id, notice_id),
    index profile_tag_inbox_created_idx (created),
    index profile_tag_inbox_notice_id_idx (notice_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_tag_subscription (
    profile_tag_id integer not null comment 'foreign key to profile_tag' references profile_list (id),

    profile_id integer not null comment 'foreign key to profile table' references profile (id),
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (profile_tag_id, profile_id),
    index profile_tag_subscription_profile_id_idx (profile_id),
    index profile_tag_subscription_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_block (
   blocker integer not null comment 'user making the block' references user (id),
   blocked integer not null comment 'profile that is blocked' references profile (id),
   modified timestamp comment 'date of blocking',

   constraint primary key (blocker, blocked)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_group (
    id integer auto_increment primary key comment 'unique identifier',

    nickname varchar(64) comment 'nickname for addressing',
    fullname varchar(255) comment 'display name',
    homepage varchar(255) comment 'URL, cached so we dont regenerate',
    description text comment 'group description',
    location varchar(255) comment 'related physical location, if any',

    original_logo varchar(255) comment 'original size logo',
    homepage_logo varchar(255) comment 'homepage (profile) size logo',
    stream_logo varchar(255) comment 'stream-sized logo',
    mini_logo varchar(255) comment 'mini logo',
    design_id integer comment 'id of a design' references design(id),

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    uri varchar(255) unique key comment 'universal identifier',
    mainpage varchar(255) comment 'page for group info to link to',

    index user_group_nickname_idx (nickname)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

create table group_member (
    group_id integer not null comment 'foreign key to user_group' references user_group (id),
    profile_id integer not null comment 'foreign key to profile table' references profile (id),
    is_admin boolean default false comment 'is this user an admin?',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (group_id, profile_id),
    index group_member_profile_id_idx (profile_id),
    index group_member_created_idx (created),

    -- To pull up a list of someone's groups in order joined
    index group_member_profile_id_created_idx (profile_id, created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table related_group (
    group_id integer not null comment 'foreign key to user_group' references user_group (id),
    related_group_id integer not null comment 'foreign key to user_group' references user_group (id),

    created datetime not null comment 'date this record was created',

    constraint primary key (group_id, related_group_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table group_inbox (
    group_id integer not null comment 'group receiving the message' references user_group (id),
    notice_id integer not null comment 'notice received' references notice (id),
    created datetime not null comment 'date the notice was created',

    constraint primary key (group_id, notice_id),
    index group_inbox_created_idx (created),
    index group_inbox_notice_id_idx (notice_id),

    -- Needed for sorting group messages by timestamp
    index group_inbox_group_id_created_notice_id_idx (group_id, created, notice_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table file (

    id integer primary key auto_increment,
    url varchar(255) comment 'destination URL after following redirections',
    mimetype varchar(50) comment 'mime type of resource',
    size integer comment 'size of resource when available',
    title varchar(255) comment 'title of resource when available',
    date integer(11) comment 'date of resource according to http query',
    protected integer(1) comment 'true when URL is private (needs login)',
    filename varchar(255) comment 'if a local file, name of the file',

    modified timestamp comment 'date this record was modified',

    unique(url)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

create table file_oembed (
    file_id integer primary key comment 'oEmbed for that URL/file' references file (id),
    version varchar(20) comment 'oEmbed spec. version',
    type varchar(20) comment 'oEmbed type: photo, video, link, rich',
    mimetype varchar(50) comment 'mime type of resource',
    provider varchar(50) comment 'name of this oEmbed provider',
    provider_url varchar(255) comment 'URL of this oEmbed provider',
    width integer comment 'width of oEmbed resource when available',
    height integer comment 'height of oEmbed resource when available',
    html text comment 'html representation of this oEmbed resource when applicable',
    title varchar(255) comment 'title of oEmbed resource when available',
    author_name varchar(50) comment 'author name for this oEmbed resource',
    author_url varchar(255) comment 'author URL for this oEmbed resource',
    url varchar(255) comment 'URL for this oEmbed resource when applicable (photo, link)',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

create table file_redirection (

    url varchar(255) primary key comment 'short URL (or any other kind of redirect) for file (id)',
    file_id integer comment 'short URL for what URL/file' references file (id),
    redirections integer comment 'redirect count',
    httpcode integer comment 'HTTP status code (20x, 30x, etc.)',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table file_thumbnail (

    file_id integer primary key comment 'thumbnail for what URL/file' references file (id),
    url varchar(255) comment 'URL of thumbnail',
    width integer comment 'width of thumbnail',
    height integer comment 'height of thumbnail',
    modified timestamp comment 'date this record was modified',

    unique(url)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table file_to_post (

    file_id integer comment 'id of URL/file' references file (id),
    post_id integer comment 'id of the notice it belongs to' references notice (id),
    modified timestamp comment 'date this record was modified',

    constraint primary key (file_id, post_id),
    index post_id_idx (post_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table design (
    id integer primary key auto_increment comment 'design ID',
    backgroundcolor integer comment 'main background color',
    contentcolor integer comment 'content area background color',
    sidebarcolor integer comment 'sidebar background color',
    textcolor integer comment 'text color',
    linkcolor integer comment 'link color',
    backgroundimage varchar(255) comment 'background image, if any',
    disposition tinyint default 1 comment 'bit 1 = hide background image, bit 2 = display background image, bit 4 = tile background image'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table group_block (
   group_id integer not null comment 'group profile is blocked from' references user_group (id),
   blocked integer not null comment 'profile that is blocked' references profile (id),
   blocker integer not null comment 'user making the block' references user (id),
   modified timestamp comment 'date of blocking',

   constraint primary key (group_id, blocked)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table group_alias (

   alias varchar(64) primary key comment 'additional nickname for the group',
   group_id integer not null comment 'group profile is blocked from' references user_group (id),
   modified timestamp comment 'date alias was created',

   index group_alias_group_id_idx (group_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table session (

    id varchar(32) primary key comment 'session ID',
    session_data text comment 'session data',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index session_modified_idx (modified)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table deleted_notice (

    id integer primary key comment 'identity of notice',
    profile_id integer not null comment 'author of the notice',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    created datetime not null comment 'date the notice record was created',
    deleted datetime not null comment 'date the notice record was created',

    index deleted_notice_profile_id_idx (profile_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table config (

    section varchar(32) comment 'configuration section',
    setting varchar(32) comment 'configuration setting',
    value varchar(255) comment 'configuration value',

    constraint primary key (section, setting)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_role (

    profile_id integer not null comment 'account having the role' references profile (id),
    role    varchar(32) not null comment 'string representing the role',
    created datetime not null comment 'date the role was granted',

    constraint primary key (profile_id, role),
    index profile_role_role_created_profile_id_idx (role, created, profile_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table location_namespace (

    id integer primary key comment 'identity for this namespace',
    description varchar(255) comment 'description of the namespace',
    created datetime not null comment 'date the record was created',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table login_token (
    user_id integer not null comment 'user owning this token' references user (id),
    token char(32) not null comment 'token useable for logging in',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_location_prefs (
    user_id integer not null comment 'user who has the preference' references user (id),
    share_location tinyint default 1 comment 'Whether to share location data',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table inbox (

    user_id integer not null comment 'user receiving the notice' references user (id),
    notice_ids blob comment 'packed list of notice ids',

    constraint primary key (user_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_im_prefs (
    user_id integer not null comment 'user' references user (id),
    screenname varchar(255) not null comment 'screenname on this service',
    transport varchar(255) not null comment 'transport (ex xmpp, aim)',
    notify tinyint(1) not null default 0 comment 'Notify when a new notice is sent',
    replies tinyint(1) not null default 0 comment 'Send replies  from people not subscribed to',
    microid tinyint(1) not null default 1 comment 'Publish a MicroID',
    updatefrompresence tinyint(1) not null default 0 comment 'Send replies  from people not subscribed to.',
    created timestamp not null DEFAULT CURRENT_TIMESTAMP comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id, transport),
    constraint unique key `transport_screenname_key` ( `transport` , `screenname` )
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table conversation (
    id integer auto_increment primary key comment 'unique identifier',
    uri varchar(225) unique comment 'URI of the conversation',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table local_group (

   group_id integer primary key comment 'group represented' references user_group (id),
   nickname varchar(64) unique key comment 'group represented',

   created datetime not null comment 'date this record was created',
   modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_urlshortener_prefs (

   user_id integer not null comment 'user' references user (id),
   urlshorteningservice varchar(50) default 'ur1.ca' comment 'service to use for auto-shortening URLs',
   maxurllength integer not null comment 'urls greater than this length will be shortened, 0 = always, null = never',
   maxnoticelength integer not null comment 'notices with content greater than this value will have all urls shortened, 0 = always, null = never',

   created datetime not null comment 'date this record was created',
   modified timestamp comment 'date this record was modified',

   constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;
