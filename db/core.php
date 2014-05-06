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

$classes = array('Schema_version',
                 'Profile',
                 'Avatar',
                 'Sms_carrier',
                 'User',
                 'Subscription',
                 'Group_join_queue',
                 'Subscription_queue',
                 'Oauth_token_association',
                 'Notice',
                 'Notice_source',
                 'Reply',
                 'Fave',
                 'Consumer',
                 'Token',
                 'Nonce',
                 'Oauth_application',
                 'Oauth_application_user',
                 'Confirm_address',
                 'Remember_me',
                 'Queue_item',
                 'Notice_tag',
                 'Foreign_service',
                 'Foreign_user',
                 'Foreign_link',
                 'Foreign_subscription',
                 'Invitation',
                 'Message',
                 'Profile_prefs',
                 'Profile_tag',
                 'Profile_list',
                 'Profile_tag_subscription',
                 'Profile_block',
                 'User_group',
                 'Related_group',
                 'Group_inbox',
                 'Group_member',
                 'File',
                 'File_redirection',
                 'File_thumbnail',
                 'File_to_post',
                 'Group_block',
                 'Group_alias',
                 'Session',
                 'Deleted_notice',
                 'Config',
                 'Profile_role',
                 'Location_namespace',
                 'Login_token',
                 'User_location_prefs',
                 'User_im_prefs',
                 'Conversation',
                 'Local_group',
                 'User_urlshortener_prefs',
                 'Old_school_prefs',
                 'User_username',
                 'Attention',
);

foreach ($classes as $cls) {
    $schema[strtolower($cls)] = call_user_func(array($cls, 'schemaDef'));
}
