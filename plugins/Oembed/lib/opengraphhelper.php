<?php

if (!defined('GNUSOCIAL')) { exit(1); }


/**
 * Utility class to get OpenGraph data from HTML DOMs etc.
 */
class OpenGraphHelper
{
    const KEY_REGEX = '/^og\:(\w+(?:\:\w+)?)/';
    protected static $property_map = [
                            'site_name'      => 'provider_name',
                            'title'          => 'title',
                            'description'    => 'html',
                            'type'           => 'type',
                            'url'            => 'url',
                            'image'          => 'thumbnail_url',
                            'image:height'   => 'thumbnail_height',
                            'image:width'    => 'thumbnail_width',
                            ];

    // This regex map has:    /pattern(match)/ => matchindex | string
    protected static $type_regex_map = [
                            '/^(video)/'   => 1,
                            '/^image/'     => 'photo',
                            ];

    static function ogFromHtml(DOMDocument $dom) {
        $obj = new stdClass();
        $obj->version = '1.0';  // fake it til u make it

        $nodes = $dom->getElementsByTagName('meta');
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            if (!$node->hasAttributes()) {
                continue;
            }
            $property = $node->attributes->getNamedItem('property');
            $matches = array();
            if ($property === null || !preg_match(self::KEY_REGEX, $property->value, $matches)) {
                // not property="og:something"
                continue;
            }
            if (!isset(self::$property_map[$matches[1]])) {
                // unknown metadata property, nothing we would care about anyway
                continue;
            }

            $prop = self::$property_map[$matches[1]];
            $obj->{$prop} = $node->attributes->getNamedItem('content')->value;
            // I don't care right now if they're empty
        }
        if (isset($obj->type)) {
            // Loop through each known OpenGraph type where we have a match in oEmbed
            foreach (self::$type_regex_map as $pattern=>$replacement) {
                $matches = array();
                if (preg_match($pattern, $obj->type, $matches)) {
                    $obj->type = is_int($replacement)
                                    ? $matches[$replacement]
                                    : $replacement;
                    break;
                }
            }
            // If it's not known to our type map, we just pass it through in hopes of it getting handled anyway
        } elseif (isset($obj->url)) {
            // If no type is set but we have a URL, let's set type=link
            $obj->type = 'link';
        }
        return $obj;
    }
}
