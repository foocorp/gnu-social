-- Add indexes for sorting changes in 0.9.7

-- Allows sorting public timeline, api/statuses/repeats, and conversations by timestamp efficiently
alter table notice
    add index notice_created_id_is_local_idx (created,id,is_local),

    add index notice_repeat_of_created_id_idx (repeat_of, created, id),
    drop index notice_repeatof_idx,

    add index notice_conversation_created_id_idx (conversation, created, id),
    drop index notice_conversation_idx;

-- Allows sorting tag-filtered public timeline by timestamp efficiently
alter table notice_tag add index notice_tag_tag_created_notice_id_idx (tag, created, notice_id);

-- Needed for sorting reply/mentions timelines
alter table reply add index reply_profile_id_modified_notice_id_idx (profile_id, modified, notice_id);

-- Needed for sorting group messages by timestamp
alter table group_inbox add index group_inbox_group_id_created_notice_id_idx (group_id, created, notice_id);

-- Helps make some reverse role lookups more efficient if there's a lot of assigned accounts
alter table profile_role add index profile_role_role_created_profile_id_idx (role, created, profile_id);

-- Fix for sorting a user's group memberships by order joined
alter table group_member add index group_member_profile_id_created_idx (profile_id, created);
