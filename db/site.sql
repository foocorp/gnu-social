/* For managing multiple sites */

create table status_network (
       
    site_id  integer auto_increment primary key comment 'unique id',
    nickname varchar(64)  unique key comment 'nickname',
    hostname varchar(255) unique key comment 'alternate hostname if any',
    pathname varchar(255) unique key comment 'alternate pathname if any',

    dbhost varchar(255) comment 'database host',
    dbuser varchar(255) comment 'database username',
    dbpass varchar(255) comment 'database password',
    dbname varchar(255) comment 'database name',

    sitename varchar(255) comment 'display name',
    theme varchar(255) comment 'theme name',
    logo varchar(255) comment 'site logo',
    
    tags text comment 'site meta-info tags (pipe-separated)',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

create table status_network_tag (
    site_id integer  comment 'unique id',
    tag varchar(64) comment 'tag name',
    created datetime not null comment 'date the record was created',

    constraint primary key (site_id, tag),
    index status_network_tag_tag_idx (tag)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

