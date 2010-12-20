-- SQL commands to update an 0.8.x version of Laconica
-- to 0.9.x.

--these are just comments
/*
alter table notice
     modify column content text comment 'update content';

alter table message
     modify column content text comment 'message content';

alter table profile
     modify column bio text comment 'descriptive biography';

alter table user_group
     modify column description text comment 'group description';
*/

alter table file_oembed
     add column mimetype varchar(50) /*comment 'mime type of resource'*/;

create table config (

    section varchar(32) /* comment 'configuration section'*/,
    setting varchar(32) /* comment 'configuration setting'*/,
    value varchar(255) /* comment 'configuration value'*/,

    primary key (section, setting)

);

create table profile_role (

    profile_id integer not null /* comment 'account having the role'*/ references profile (id),
    role    varchar(32) not null /* comment 'string representing the role'*/,
    created timestamp /* not null comment 'date the role was granted'*/,

    primary key (profile_id, role)

);

create table location_namespace (

    id integer /*comment 'identity for this namespace'*/,
    description text /* comment 'description of the namespace'*/ ,
    created integer not null /*comment 'date the record was created*/ ,
   /* modified timestamp comment 'date this record was modified',*/
    primary key (id)

);

create table login_token (
    user_id integer not null /* comment 'user owning this token'*/ references "user" (id),
    token char(32) not null /* comment 'token useable for logging in'*/,
    created timestamp not null DEFAULT CURRENT_TIMESTAMP /* comment 'date this record was created'*/,
    modified timestamp /* comment 'date this record was modified'*/,

    primary key (user_id)
);

DROP index fave_user_id_idx;
CREATE index fave_user_id_idx on fave (user_id,modified);

DROP index subscription_subscriber_idx;
CREATE index subscription_subscriber_idx ON subscription (subscriber,created);

DROP index subscription_subscribed_idx;
CREATE index subscription_subscribed_idx ON subscription (subscribed,created);

DROP index notice_profile_id_idx;
CREATE index notice_profile_id_idx ON notice (profile_id,created,id);

ALTER TABLE notice ADD COLUMN lat decimal(10, 7) /* comment 'latitude'*/;
ALTER TABLE notice ADD COLUMN lon decimal(10,7) /* comment 'longitude'*/;
ALTER TABLE notice ADD COLUMN location_id integer /* comment 'location id if possible'*/ ;
ALTER TABLE notice ADD COLUMN location_ns integer /* comment 'namespace for location'*/;
ALTER TABLE notice ADD COLUMN repeat_of integer /* comment 'notice this is a repeat of' */ references notice (id);

ALTER TABLE profile ADD COLUMN lat decimal(10,7) /*comment 'latitude'*/ ;
ALTER TABLE profile ADD COLUMN lon decimal(10,7) /*comment 'longitude'*/;
ALTER TABLE profile ADD COLUMN location_id integer /* comment 'location id if possible'*/;
ALTER TABLE profile ADD COLUMN location_ns integer /* comment 'namespace for location'*/;

ALTER TABLE consumer add COLUMN consumer_secret varchar(255) not null ; /*comment 'secret value'*/

ALTER TABLE token ADD COLUMN verifier varchar(255); /* comment 'verifier string for OAuth 1.0a',*/
ALTER TABLE token ADD COLUMN verified_callback varchar(255); /* comment 'verified callback URL for OAuth 1.0a',*/

create table queue_item_new (
     id serial /* comment 'unique identifier'*/,
     frame bytea not null /* comment 'data: object reference or opaque string'*/,
     transport varchar(8) not null /*comment 'queue for what? "email", "jabber", "sms", "irc", ...'*/,
     created timestamp not null default CURRENT_TIMESTAMP /*comment 'date this record was created'*/,
     claimed timestamp /*comment 'date this item was claimed'*/,
     PRIMARY KEY (id)
);
 
insert into queue_item_new (frame,transport,created,claimed)
    select ('0x' || notice_id::text)::bytea,transport,created,claimed from queue_item;
alter table queue_item rename to queue_item_old;
alter table queue_item_new rename to queue_item;

ALTER TABLE confirm_address ALTER column sent set default CURRENT_TIMESTAMP;

create table user_location_prefs (
    user_id integer not null /*comment 'user who has the preference'*/ references "user" (id),
    share_location int default 1 /* comment 'Whether to share location data'*/,
    created timestamp not null /*comment 'date this record was created'*/,
    modified timestamp /* comment 'date this record was modified'*/,

    primary key (user_id)
);
 
create table inbox (

    user_id integer not null /* comment 'user receiving the notice' */ references "user" (id),
    notice_ids bytea /* comment 'packed list of notice ids' */,

    primary key (user_id)

);

create table user_location_prefs (
    user_id integer not null /*comment 'user who has the preference'*/ references "user" (id),
    share_location int default 1 /* comment 'Whether to share location data'*/,
    created timestamp not null /*comment 'date this record was created'*/,
    modified timestamp /* comment 'date this record was modified'*/,

    primary key (user_id)
);
 
create table inbox (

    user_id integer not null /* comment 'user receiving the notice' */ references "user" (id),
    notice_ids bytea /* comment 'packed list of notice ids' */,

    primary key (user_id)

);

