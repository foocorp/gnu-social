<?php

if (!defined('GNUSOCIAL')) { exit(1); }

require_once __DIR__ . '/lib/util.php';

/*
 * This plugin lets you type @twitter.com/singpolyma
 * so that you can be specific instead of relying on heuristics.
 */
class MentionURLPlugin extends Plugin
{
    public function onStartFindMentions($sender, $text, &$mentions)
    {
        preg_match_all('/(?:^|\s+)@([A-Za-z0-9_:\-\.\/%]+)\b/',
                       $text,
                       $atmatches,
                       PREG_OFFSET_CAPTURE);

        foreach ($atmatches[1] as $match) {
            $url = $match[0];
            if(!common_valid_http_url($url)) { $url = 'http://' . $url; }
            if(common_valid_http_url($url)) {
                $mentioned = Mention_url_profile::fromUrl($url);
                $text = mb_strlen($mentioned->nickname) <= mb_strlen($match[0]) ? $mentioned->nickname : $match[0];
            }

            if($mentioned instanceof Profile) {
                $mentions[] = array('mentioned' => array($mentioned),
                                   'type' => 'mention',
                                   'text' => $text,
                                   'position' => $match[1],
                                   'length' => mb_strlen($match[0]),
                                   'url' => $mentioned->profileurl);
            }
        }

        return true;
    }

    public function onStartGetProfileFromURI($uri, &$profile)
    {
        $mention_profile = Mention_url_profile::getKV('profileurl', $uri);
        if($mention_profile instanceof Mention_url_profile) {
            $profile = $mention_profile->getProfile();
            return !($profile instanceof Profile);
        }

        return true;
    }

    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('mention_url_profile', Mention_url_profile::schemaDef());
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'MentionURL',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Stephen Paul Weber',
                            'homepage' => 'http://gnu.io/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Plugin to allow mentioning arbitrary URLs.'));
        return true;
    }
}
