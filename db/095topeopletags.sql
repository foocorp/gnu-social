/* populate people tags metadata */

insert into profile_list (tagger, tag, modified, description, private)
    select distinct tagger, tag, modified, null, false from profile_tag;
