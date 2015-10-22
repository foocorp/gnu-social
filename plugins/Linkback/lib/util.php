<?php

function linkback_lenient_target_match($body, $target) {
    return strpos(''.$body, str_replace(array('http://www.', 'http://', 'https://www.', 'https://'), '', preg_replace('/\/+$/', '', preg_replace( '/#.*/', '', $target))));
}

function linkback_get_source($source, $target) {
    $request = HTTPClient::start();

    try {
        $response = $request->get($source);
    } catch(Exception $ex) {
        return NULL;
    }

    $body = htmlspecialchars_decode($response->getBody());
    // We're slightly more lenient in our link detection than the spec requires
    if(!linkback_lenient_target_match($body, $target)) {
        return NULL;
    }

    return $response;
}

function linkback_get_target($target) {
    // Resolve target (https://github.com/converspace/webmention/issues/43)
    $request = HTTPClient::start();

    try {
        $response = $request->head($target);
    } catch(Exception $ex) {
        return NULL;
    }

    try {
        $notice = Notice::fromUri($response->getEffectiveUrl());
    } catch(UnknownUriException $ex) {
        preg_match('/\/notice\/(\d+)(?:#.*)?$/', $response->getEffectiveUrl(), $match);
        $notice = Notice::getKV('id', $match[1]);
    }

    if($notice instanceof Notice && $notice->isLocal()) {
        return $notice;
    } else {
        $user = User::getKV('uri', $response->getEffectiveUrl());
        if(!$user) {
            preg_match('/\/user\/(\d+)(?:#.*)?$/', $response->getEffectiveUrl(), $match);
            $user = User::getKV('id', $match[1]);
        }
        if(!$user) {
            preg_match('/\/([^\/\?#]+)(?:#.*)?$/', $response->getEffectiveUrl(), $match);
            if(linkback_lenient_target_match(common_profile_url($match[1]), $response->getEffectiveUrl())) {
                $user = User::getKV('nickname', $match[1]);
            }
        }
        if($user instanceof User) {
            return $user;
        }
    }

    return NULL;
}

function linkback_is_contained_in($entry, $target) {
    foreach ((array)$entry['properties'] as $key => $values) {
        if(count(array_filter($values, function($x) use ($target) { return linkback_lenient_target_match($x, $target); })) > 0) {
            return $entry['properties'];
        }

        // check included h-* formats and their links
        foreach ($values as $obj) {
            if(isset($obj['type']) && array_intersect(array('h-cite', 'h-entry'), $obj['type']) &&
               isset($obj['properties']) && isset($obj['properties']['url']) &&
               count(array_filter($obj['properties']['url'],
                     function($x) use ($target) { return linkback_lenient_target_match($x, $target); })) > 0
            ) {
                return $entry['properties'];
            }
        }

        // check content for the link
        if ($key == "content" && preg_match_all("/<a[^>]+?".preg_quote($target, "/")."[^>]*>([^>]+?)<\/a>/i", htmlspecialchars_decode($values[0]['html']), $context)) {
            return $entry['properties'];
        // check summary for the link
        } elseif ($key == "summary" && preg_match_all("/<a[^>]+?".preg_quote($target, "/")."[^>]*>([^>]+?)<\/a>/i", htmlspecialchars_decode($values[0]), $context)) {
            return $entry['properties'];
        }
    }

    foreach((array)$entry['children'] as $mf2) {
        if(linkback_is_contained_in($mf2, $target)) {
            return $entry['properties'];
        }
    }

    return null;
}

// Based on https://github.com/acegiak/Semantic-Linkbacks/blob/master/semantic-linkbacks-microformats-handler.php, GPL-2.0+
function linkback_find_entry($mf2, $target) {
    if(isset($mf2['items'][0]['type']) && in_array("h-feed", $mf2['items'][0]["type"]) && isset($mf2['items'][0]['children'])) {
        $mf2['items'] = $mf2['items'][0]['children'];
    }

    $entries = array_filter($mf2['items'], function($x) { return isset($x['type']) && in_array('h-entry', $x['type']); });

    foreach ($entries as $entry) {
        if($prop = linkback_is_contained_in($entry, $target)) {
            return $prop;
        }
    }

    // Default to first one
    if(count($entries) > 0) {
        return $entries[0]['properties'];
    }

    return NULL;
}

function linkback_entry_type($entry, $mf2, $target) {
    if(!$entry) { return 'mention'; }

    if($mf2['rels'] && $mf2['rels']['in-reply-to']) {
        foreach($mf2['rels']['in-reply-to'] as $url) {
            if(linkback_lenient_target_match($url, $target)) {
                return 'reply';
            }
        }
    }

    $classes = array(
        'in-reply-to' => 'reply',
        'repost-of' => 'repost',
        'like-of' => 'like',
        'tag-of' => 'tag'
    );

    foreach((array)$entry as $key => $values) {
        if(count(array_filter($values, function($x) use ($target) { return linkback_lenient_target_match($x, $target); })) > 0) {
            if($classes[$key]) { return $classes[$key]; }
        }

        foreach ($values as $obj) {
            if(isset($obj['type']) && array_intersect(array('h-cite', 'h-entry'), $obj['type']) &&
               isset($obj['properties']) && isset($obj['properties']['url']) &&
               count(array_filter($obj['properties']['url'],
                     function($x) use ($target) { return linkback_lenient_target_match($x, $target); })) > 0
            ) {
                if($classes[$key]) { return $classes[$key]; }
            }
        }
    }

    return 'mention';
}

function linkback_is_dupe($key, $url) {
    $dupe = Notice::getKV($key, $url);
    if ($dupe instanceof Notice) {
        return $dupe;
    }

    return false;
}


function linkback_hcard($mf2, $url) {
    if(empty($mf2['items'])) {
        return null;
    }
  
    $hcards = array();
    foreach($mf2['items'] as $item) {
        if(!in_array('h-card', $item['type'])) {
            continue;
        }
      
        // We found a match, return it immediately
        if(isset($item['properties']['url']) && in_array($url, $item['properties']['url'])) {
            return $item['properties'];
      
            // Let's keep all the hcards for later, to return one of them at least
            $hcards[] = $item['properties'];
        }
    }
  
    // No match immediately for the url we expected, but there were h-cards found
    if (count($hcards) > 0) {
        return $hcards[0];
    }
  
    return null;
}

function linkback_notice($source, $notice_or_user, $entry, $author, $mf2) {
    $content = $entry['content'] ? $entry['content'][0]['html'] :
              ($entry['summary'] ? $entry['sumary'][0] : $entry['name'][0]);

    $rendered = common_purify($content);

    if($notice_or_user instanceof Notice && $entry['type'] == 'mention') {
        $name = $entry['name'] ? $entry['name'][0] : substr(common_strip_html($content), 0, 20).'…';
        $rendered = _m('linked to this from <a href="'.htmlspecialchars($source).'">'.htmlspecialchars($name).'</a>');
    }

    $content = common_strip_html($rendered);
    $shortened = common_shorten_links($content);
    if(Notice::contentTooLong($shortened)) {
        $content = substr($content,
                          0,
                          Notice::maxContent() - (mb_strlen($source) + 2));
        $rendered = $content . '<a href="'.htmlspecialchars($source).'">…</a>';
        $content .= ' ' . $source;
    }

    $options = array('is_local' => Notice::REMOTE,
                    'url' => $entry['url'][0],
                    'uri' => $source,
                    'rendered' => $rendered,
                    'replies' => array(),
                    'groups' => array(),
                    'peopletags' => array(),
                    'tags' => array(),
                    'urls' => array());

    if($notice_or_user instanceof User) {
        $options['replies'][] = $notice_or_user->getUri();
    } else {
        if($entry['type'] == 'repost') {
            $options['repeat_of'] = $notice_or_user->id;
        } else {
            $options['reply_to'] = $notice_or_user->id;
        }
    }

    if($entry['published'] || $entry['updated']) {
        $options['created'] = $entry['published'] ? common_sql_date($entry['published'][0]) : common_sql_date($entry['updated'][0]);
    }

    if($entry['photo']) {
        $options['urls'][] = $entry['photo'][0];
    }

    foreach((array)$entry['category'] as $tag) {
        $tag = common_canonical_tag($tag);
        if($tag) { $options['tags'][] = $tag; }
    }


    if($mf2['rels'] && $mf2['rels']['enclosure']) {
        foreach($mf2['rels']['enclosure'] as $url) {
            $options['urls'][] = $url;
        }
    }

    if($mf2['rels'] && $mf2['rels']['tag']) {
        foreach($mf2['rels']['tag'] as $url) {
            preg_match('/\/([^\/]+)\/*$/', $url, $match);
            $tag = common_canonical_tag($match[1]);
            if($tag) { $options['tags'][] = $tag; }
         }
    }

    if($entry['type'] != 'reply' && $entry['type'] != 'repost') {
        $options['urls'] = array();
    }

    return array($content, $options);
}

function linkback_profile($entry, $mf2, $response, $target) {
    if(isset($entry['properties']['author']) && isset($entry['properties']['author'][0]['properties'])) {
        $author = $entry['properties']['author'][0]['properties'];
    } else {
        $author = linkback_hcard($mf2, $response->getEffectiveUrl());
    }

    if(!$author) {
        $author = array('name' => array($entry['name']));
    }

    if(!$author['url']) {
        $author['url'] = array($response->getEffectiveUrl());
    }

    $user = User::getKV('uri', $author['url'][0]);
    if ($user instanceof User) {
        common_log(LOG_INFO, "Linkback: ignoring linkback from local user: $url");
        return true;
    }

    try {
        $profile = Profile::fromUri($author['url'][0]);
    } catch(UnknownUriException $ex) {}

    if(!($profile instanceof Profile)) {
        $profile = Profile::getKV('profileurl', $author['url'][0]);
    }

    if(!($profile instanceof Profile)) {
        $profile = new Profile();
        $profile->profileurl = $author['url'][0];
        $profile->fullname = $author['name'][0];
        $profile->nickname = $author['nickname'] ? $author['nickname'][0] : str_replace(' ', '', $author['name'][0]);
        $profile->created = common_sql_now();
        $profile->insert();
    }

    return array($profile, $author);
}

function linkback_save($source, $target, $response, $notice_or_user) {
    $dupe = linkback_is_dupe('uri', $response->getEffectiveUrl());
    if(!$dupe) { $dupe = linkback_is_dupe('url', $response->getEffectiveUrl()); }
    if(!$dupe) { $dupe = linkback_is_dupe('uri', $source); }
    if(!$dupe) { $dupe = linkback_is_dupe('url', $source); }

    $mf2 = new Mf2\Parser($response->getBody(), $response->getEffectiveUrl());
    $mf2 = $mf2->parse();

    $entry = linkback_find_entry($mf2, $target);
    if(!$entry) {
        preg_match('/<title>([^<]+)', $response->getBody(), $match);
        $entry = array(
            'content' => array('html' => $response->getBody()),
            'name' => $match[1] ? htmlspecialchars_decode($match[1]) : $source
        );
    }

    if(!$entry['url']) {
        $entry['url'] = array($response->getEffectiveUrl());
    }

    if(!$dupe) { $dupe = linkback_is_dupe('uri', $entry['url'][0]); }
    if(!$dupe) { $dupe = linkback_is_dupe('url', $entry['url'][0]); }

    $entry['type'] = linkback_entry_type($entry, $mf2, $target);
    list($profile, $author) =  linkback_profile($entry, $mf2, $response, $target);
    list($content, $options) = linkback_notice($source, $notice_or_user, $entry, $author, $mf2);

    if($dupe) {
        $orig = clone($dupe);

        try {
            // Ignore duplicate save error
            try { $dupe->saveKnownReplies($options['replies']); } catch (ServerException $ex) {}
            try { $dupe->saveKnownTags($options['tags']); } catch (ServerException $ex) {}
            try { $dupe->saveKnownUrls($options['urls']); } catch (ServerException $ex) {}

            if($options['reply_to']) { $dupe->reply_to = $options['reply_to']; }
            if($options['repost_of']) { $dupe->repost_of = $options['repost_of']; }
            if($dupe->update($orig)) { $saved = $dupe; }
        } catch (Exception $e) {
            common_log(LOG_ERR, "Linkback update of remote message $source failed: " . $e->getMessage());
            return false;
        }
        common_log(LOG_INFO, "Linkback updated remote message $source as notice id $saved->id");
    } else if($entry['type'] == 'like' || ($entry['type'] == 'reply' && $entry['rsvp'])) {
        $act = new Activity();
        $act->type    = ActivityObject::ACTIVITY;
        $act->time    = $options['created'] ? strtotime($options['created']) : time();
        $act->title   = $entry["name"] ? $entry["name"][0] : _m("Favor");
        $act->actor   = $profile->asActivityObject();
        $act->target  = $notice_or_user->asActivityObject();
        $act->objects = array(clone($act->target));

        // TRANS: Message that is the "content" of a favorite (%1$s is the actor's nickname, %2$ is the favorited
        //        notice's nickname and %3$s is the content of the favorited notice.)
        $act->content = sprintf(_('%1$s favorited something by %2$s: %3$s'),
                                $profile->getNickname(), $notice_or_user->getProfile()->getNickname(),
                                $notice_or_user->rendered ?: $notice_or_user->content);
        if($entry['rsvp']) {
            $act->content = $options['rendered'];
        }

        $act->verb    = ActivityVerb::FAVORITE;
        if(strtolower($entry['rsvp'][0]) == 'yes') {
            $act->verb = 'http://activitystrea.ms/schema/1.0/rsvp-yes';
        } else if(strtolower($entry['rsvp'][0]) == 'no') {
            $act->verb = 'http://activitystrea.ms/schema/1.0/rsvp-no';
        } else if(strtolower($entry['rsvp'][0]) == 'maybe') {
            $act->verb = 'http://activitystrea.ms/schema/1.0/rsvp-maybe';
        }

        $act->id = $source;
        $act->link = $entry['url'][0];

        $options['source'] = 'linkback';
        $options['mentions'] = $options['replies'];
        unset($options['reply_to']);
        unset($options['repeat_of']);

        try {
            $saved = Notice::saveActivity($act, $profile, $options);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Linkback save of remote message $source failed: " . $e->getMessage());
            return false;
        }
        common_log(LOG_INFO, "Linkback saved remote message $source as notice id $saved->id");
    } else {
        // Fallback is to make a notice manually
        try {
            $saved = Notice::saveNew($profile->id,
                                     $content,
                                     'linkback',
                                     $options);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Linkback save of remote message $source failed: " . $e->getMessage());
            return false;
        }
        common_log(LOG_INFO, "Linkback saved remote message $source as notice id $saved->id");
    }

    return $saved->getLocalUrl();
}
