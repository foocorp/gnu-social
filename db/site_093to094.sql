alter table status_network 
      drop primary key,
      add column site_id integer auto_increment primary key first,
      add unique key (nickname);

create table status_network_tag (
    site_id integer  comment 'unique id',
    tag varchar(64) comment 'tag name',
    created datetime not null comment 'date the record was created',

    constraint primary key (site_id, tag)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

