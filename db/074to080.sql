alter table user
     add column design_id integer comment 'id of a design' references design(id),
     add column viewdesigns tinyint default 1 comment 'whether to view user-provided designs';

alter table notice add column
     conversation integer comment 'id of root notice in this conversation' references notice (id),
     add index notice_conversation_idx (conversation);

alter table foreign_user
     modify column id bigint not null comment 'unique numeric key on foreign service';

alter table foreign_link
     modify column foreign_id bigint unsigned comment 'link to user on foreign service, if exists';

alter table user_group
      add column design_id integer comment 'id of a design' references design(id);

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

    constraint primary key (file_id, post_id)

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

