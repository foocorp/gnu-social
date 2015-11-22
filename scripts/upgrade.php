#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008-2011 StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'x::';
$longoptions = array('extensions=');

$helptext = <<<END_OF_UPGRADE_HELP
php upgrade.php [options]
Upgrade database schema and data to latest software

END_OF_UPGRADE_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

function main()
{
    if (Event::handle('StartUpgrade')) {
        fixupConversationURIs();

        updateSchemaCore();
        updateSchemaPlugins();

        // These replace old "fixup_*" scripts

        fixupNoticeRendered();
        fixupNoticeConversation();
        initConversation();
        fixupGroupURI();
        fixupFileGeometry();
        deleteLocalFileThumbnailsWithoutFilename();
        deleteMissingLocalFileThumbnails();
        setFilehashOnLocalFiles();

        initGroupProfileId();
        initLocalGroup();
        initNoticeReshare();
    
        initSubscriptionURI();
        initGroupMemberURI();

        initProfileLists();

        Event::handle('EndUpgrade');
    }
}

function tableDefs()
{
	$schema = array();
	require INSTALLDIR.'/db/core.php';
	return $schema;
}

function updateSchemaCore()
{
    printfnq("Upgrading core schema...");

    $schema = Schema::get();
    $schemaUpdater = new SchemaUpdater($schema);
    foreach (tableDefs() as $table => $def) {
        $schemaUpdater->register($table, $def);
    }
    $schemaUpdater->checkSchema();

    printfnq("DONE.\n");
}

function updateSchemaPlugins()
{
    printfnq("Upgrading plugin schema...");

    Event::handle('BeforePluginCheckSchema');
    Event::handle('CheckSchema');

    printfnq("DONE.\n");
}

function fixupNoticeRendered()
{
    printfnq("Ensuring all notices have rendered HTML...");

    $notice = new Notice();

    $notice->whereAdd('rendered IS NULL');
    $notice->find();

    while ($notice->fetch()) {
        $original = clone($notice);
        $notice->rendered = common_render_content($notice->content, $notice);
        $notice->update($original);
    }

    printfnq("DONE.\n");
}

function fixupNoticeConversation()
{
    printfnq("Ensuring all notices have a conversation ID...");

    $notice = new Notice();
    $notice->whereAdd('conversation is null');
    $notice->orderBy('id'); // try to get originals before replies
    $notice->find();

    while ($notice->fetch()) {
        try {
            $cid = null;
    
            $orig = clone($notice);
    
            if (empty($notice->reply_to)) {
                $notice->conversation = $notice->id;
            } else {
                $reply = Notice::getKV('id', $notice->reply_to);

                if (empty($reply)) {
                    $notice->conversation = $notice->id;
                } else if (empty($reply->conversation)) {
                    $notice->conversation = $notice->id;
                } else {
                    $notice->conversation = $reply->conversation;
                }
	
                unset($reply);
                $reply = null;
            }

            $result = $notice->update($orig);

            $orig = null;
            unset($orig);
        } catch (Exception $e) {
            printv("Error setting conversation: " . $e->getMessage());
        }
    }

    printfnq("DONE.\n");
}

function fixupGroupURI()
{
    printfnq("Ensuring all groups have an URI...");

    $group = new User_group();
    $group->whereAdd('uri IS NULL');

    if ($group->find()) {
        while ($group->fetch()) {
            $orig = User_group::getKV('id', $group->id);
            $group->uri = $group->getUri();
            $group->update($orig);
        }
    }

    printfnq("DONE.\n");
}

function initConversation()
{
    printfnq("Ensuring all conversations have a row in conversation table...");

    $notice = new Notice();
    $notice->query('select distinct notice.conversation from notice '.
                   'where notice.conversation is not null '.
                   'and not exists (select conversation.id from conversation where id = notice.conversation)');

    while ($notice->fetch()) {

        $id = $notice->conversation;

        $uri = common_local_url('conversation', array('id' => $id));

        // @fixme db_dataobject won't save our value for an autoincrement
        // so we're bypassing the insert wrappers
        $conv = new Conversation();
        $sql = "insert into conversation (id,uri,created) values(%d,'%s','%s')";
        $sql = sprintf($sql,
                       $id,
                       $conv->escape($uri),
                       $conv->escape(common_sql_now()));
        $conv->query($sql);
    }

    printfnq("DONE.\n");
}

function fixupConversationURIs()
{
    printfnq("Ensuring all conversations have a URI...");

    $conv = new Conversation();
    $conv->whereAdd('uri IS NULL');

    if ($conv->find()) {
        $rounds = 0;
        while ($conv->fetch()) {
            $uri = common_local_url('conversation', array('id' => $conv->id));
            $sql = sprintf('UPDATE conversation SET uri="%1$s" WHERE id="%2$d";',
                            $conv->escape($uri), $conv->id);
            $conv->query($sql);
            if (($conv->N-++$rounds) % 500 == 0) {
                printfnq(sprintf(' %d items left...', $conv->N-$rounds));
            }
        }
    }

    printfnq("DONE.\n");
}

function initGroupProfileId()
{
    printfnq("Ensuring all User_group entries have a Profile and profile_id...");

    $group = new User_group();
    $group->whereAdd('NOT EXISTS (SELECT id FROM profile WHERE id = user_group.profile_id)');
    $group->find();

    while ($group->fetch()) {
        try {
            // We must create a new, incrementally assigned profile_id
            $profile = new Profile();
            $profile->nickname   = $group->nickname;
            $profile->fullname   = $group->fullname;
            $profile->profileurl = $group->mainpage;
            $profile->homepage   = $group->homepage;
            $profile->bio        = $group->description;
            $profile->location   = $group->location;
            $profile->created    = $group->created;
            $profile->modified   = $group->modified;

            $profile->query('BEGIN');
            $id = $profile->insert();
            if (empty($id)) {
                $profile->query('ROLLBACK');
                throw new Exception('Profile insertion failed, profileurl: '.$profile->profileurl);
            }
            $group->query("UPDATE user_group SET profile_id={$id} WHERE id={$group->id}");
            $profile->query('COMMIT');

            $profile->free();
        } catch (Exception $e) {
            printfv("Error initializing Profile for group {$group->nickname}:" . $e->getMessage());
        }
    }

    printfnq("DONE.\n");
}

function initLocalGroup()
{
    printfnq("Ensuring all local user groups have a local_group...");

    $group = new User_group();
    $group->whereAdd('NOT EXISTS (select group_id from local_group where group_id = user_group.id)');
    $group->find();

    while ($group->fetch()) {
        try {
            // Hack to check for local groups
            if ($group->getUri() == common_local_url('groupbyid', array('id' => $group->id))) {
                $lg = new Local_group();

                $lg->group_id = $group->id;
                $lg->nickname = $group->nickname;
                $lg->created  = $group->created; // XXX: common_sql_now() ?
                $lg->modified = $group->modified;

                $lg->insert();
            }
        } catch (Exception $e) {
            printfv("Error initializing local group for {$group->nickname}:" . $e->getMessage());
        }
    }

    printfnq("DONE.\n");
}

function initNoticeReshare()
{
    printfnq("Ensuring all reshares have the correct verb and object-type...");
    
    $notice = new Notice();
    $notice->whereAdd('repeat_of is not null');
    $notice->whereAdd('(verb != "'.ActivityVerb::SHARE.'" OR object_type != "'.ActivityObject::ACTIVITY.'")');

    if ($notice->find()) {
        while ($notice->fetch()) {
            try {
                $orig = Notice::getKV('id', $notice->id);
                $notice->verb = ActivityVerb::SHARE;
                $notice->object_type = ActivityObject::ACTIVITY;
                $notice->update($orig);
            } catch (Exception $e) {
                printfv("Error updating verb and object_type for {$notice->id}:" . $e->getMessage());
            }
        }
    }

    printfnq("DONE.\n");
}

function initSubscriptionURI()
{
    printfnq("Ensuring all subscriptions have a URI...");

    $sub = new Subscription();
    $sub->whereAdd('uri IS NULL');

    if ($sub->find()) {
        while ($sub->fetch()) {
            try {
                $sub->decache();
                $sub->query(sprintf('update subscription '.
                                    'set uri = "%s" '.
                                    'where subscriber = %d '.
                                    'and subscribed = %d',
                                    $sub->escape(Subscription::newUri($sub->getSubscriber(), $sub->getSubscribed(), $sub->created)),
                                    $sub->subscriber,
                                    $sub->subscribed));
            } catch (Exception $e) {
                common_log(LOG_ERR, "Error updated subscription URI: " . $e->getMessage());
            }
        }
    }

    printfnq("DONE.\n");
}

function initGroupMemberURI()
{
    printfnq("Ensuring all group memberships have a URI...");

    $mem = new Group_member();
    $mem->whereAdd('uri IS NULL');

    if ($mem->find()) {
        while ($mem->fetch()) {
            try {
                $mem->decache();
                $mem->query(sprintf('update group_member set uri = "%s" '.
                                    'where profile_id = %d ' . 
                                    'and group_id = %d ',
                                    Group_member::newURI($mem->profile_id, $mem->group_id, $mem->created),
                                    $mem->profile_id,
                                    $mem->group_id));
            } catch (Exception $e) {
                common_log(LOG_ERR, "Error updated membership URI: " . $e->getMessage());  
          }
        }
    }

    printfnq("DONE.\n");
}

function initProfileLists()
{
    printfnq("Ensuring all profile tags have a corresponding list...");

    $ptag = new Profile_tag();
    $ptag->selectAdd();
    $ptag->selectAdd('tagger, tag, count(*) as tagged_count');
    $ptag->whereAdd('NOT EXISTS (SELECT tagger, tagged from profile_list '.
                    'where profile_tag.tagger = profile_list.tagger '.
                    'and profile_tag.tag = profile_list.tag)');
    $ptag->groupBy('tagger, tag');
    $ptag->orderBy('tagger, tag');

    if ($ptag->find()) {
        while ($ptag->fetch()) {
            $plist = new Profile_list();

            $plist->tagger   = $ptag->tagger;
            $plist->tag      = $ptag->tag;
            $plist->private  = 0;
            $plist->created  = common_sql_now();
            $plist->modified = $plist->created;
            $plist->mainpage = common_local_url('showprofiletag',
                                                array('tagger' => $plist->getTagger()->nickname,
                                                      'tag'    => $plist->tag));;

            $plist->tagged_count     = $ptag->tagged_count;
            $plist->subscriber_count = 0;

            $plist->insert();

            $orig = clone($plist);
            // After insert since it uses auto-generated ID
            $plist->uri      = common_local_url('profiletagbyid',
                                        array('id' => $plist->id, 'tagger_id' => $plist->tagger));

            $plist->update($orig);
        }
    }

    printfnq("DONE.\n");
}

/*
 * Added as we now store interpretd width and height in File table.
 */
function fixupFileGeometry()
{
    printfnq("Ensuring width and height is set for supported local File objects...");

    $file = new File();
    $file->whereAdd('filename IS NOT NULL');    // local files
    $file->whereAdd('width IS NULL OR width = 0');

    if ($file->find()) {
        while ($file->fetch()) {
            // Set file geometrical properties if available
            try {
                $image = ImageFile::fromFileObject($file);
            } catch (ServerException $e) {
                // We couldn't make out an image from the file.
                continue;
            }
            $orig = clone($file);
            $file->width = $image->width;
            $file->height = $image->height;
            $file->update($orig);

            // FIXME: Do this more automagically inside ImageFile or so.
            if ($image->getPath() != $file->getPath()) {
                $image->unlink();
            }
            unset($image);
        }
    }

    printfnq("DONE.\n");
}

/*
 * File_thumbnail objects for local Files store their own filenames in the database.
 */
function deleteLocalFileThumbnailsWithoutFilename()
{
    printfnq("Removing all local File_thumbnail entries without filename property...");

    $file = new File();
    $file->whereAdd('filename IS NOT NULL');    // local files

    if ($file->find()) {
        // Looping through local File entries
        while ($file->fetch()) {
            $thumbs = new File_thumbnail();
            $thumbs->file_id = $file->id;
            $thumbs->whereAdd('filename IS NULL');
            // Checking if there were any File_thumbnail entries without filename
            if (!$thumbs->find()) {
                continue;
            }
            // deleting incomplete entry to allow regeneration
            while ($thumbs->fetch()) {
                $thumbs->delete();
            }
        }
    }

    printfnq("DONE.\n");
}

/*
 * Delete File_thumbnail entries where the referenced file does not exist.
 */
function deleteMissingLocalFileThumbnails()
{
    printfnq("Removing all local File_thumbnail entries without existing files...");

    $thumbs = new File_thumbnail();
    $thumbs->whereAdd('filename IS NOT NULL');  // only fill in names where they're missing
    // Checking if there were any File_thumbnail entries without filename
    if ($thumbs->find()) {
        while ($thumbs->fetch()) {
            try {
                $thumbs->getPath();
            } catch (FileNotFoundException $e) {
                $thumbs->delete();
            }
        }
    }

    printfnq("DONE.\n");
}

/*
 * Files are now stored with their hash, so let's generate for previously uploaded files.
 */
function setFilehashOnLocalFiles()
{
    printfnq('Ensuring all local files have the filehash field set...');

    $file = new File();
    $file->whereAdd('filename IS NOT NULL');        // local files
    $file->whereAdd('filehash IS NULL', 'AND');     // without filehash value

    if ($file->find()) {
        while ($file->fetch()) {
            try {
                $orig = clone($file);
                $file->filehash = hash_file(File::FILEHASH_ALG, $file->getPath());
                $file->update($orig);
            } catch (FileNotFoundException $e) {
                echo "\n    WARNING: file ID {$file->id} does not exist on path '{$e->path}'. If there is no file system error, run: php scripts/clean_file_table.php";
            }
        }
    }

    printfnq("DONE.\n");
}

main();
