<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for config
 */

class Config extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'config';                          // table name
    public $section;                         // varchar(32)  primary_key not_null
    public $setting;                         // varchar(32)  primary_key not_null
    public $value;                           // text

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'section' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'default' => '', 'description' => 'configuration section'),
                'setting' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'default' => '', 'description' => 'configuration setting'),
                'value' => array('type' => 'text', 'description' => 'configuration value'),
            ),
            'primary key' => array('section', 'setting'),
        );
    }

    const settingsKey = 'config:settings';

    static function loadSettings()
    {
        try {
            $settings = self::_getSettings();
            if (!empty($settings)) {
                self::_applySettings($settings);
            }
        } catch (Exception $e) {
            return;
        }
    }

    static function _getSettings()
    {
        $c = self::memcache();

        if (!empty($c)) {
            $settings = $c->get(Cache::key(self::settingsKey));
            if ($settings !== false) {
                return $settings;
            }
        }

        $settings = array();

        $config = new Config();

        $config->find();

        while ($config->fetch()) {
            $settings[] = array($config->section, $config->setting, $config->value);
        }

        $config->free();

        if (!empty($c)) {
            $c->set(Cache::key(self::settingsKey), $settings);
        }

        return $settings;
    }

    static function _applySettings($settings)
    {
        global $config;

        foreach ($settings as $s) {
            list($section, $setting, $value) = $s;
            $config[$section][$setting] = $value;
        }
    }

    function insert()
    {
        $result = parent::insert();
        if ($result) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    function delete($useWhere=false)
    {
        $result = parent::delete($useWhere);
        if ($result !== false) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    function update($dataObject=false)
    {
        $result = parent::update($dataObject);
        if ($result !== false) {
            Config::_blowSettingsCache();
        }
        return $result;
    }

    static function save($section, $setting, $value)
    {
        $result = null;

        $config = Config::pkeyGet(array('section' => $section,
                                        'setting' => $setting));

        if (!empty($config)) {
            $orig = clone($config);
            $config->value = $value;
            $result = $config->update($orig);
        } else {
            $config = new Config();

            $config->section = $section;
            $config->setting = $setting;
            $config->value   = $value;

            $result = $config->insert();
        }

        return $result;
    }

    function _blowSettingsCache()
    {
        $c = self::memcache();

        if (!empty($c)) {
            $c->delete(Cache::key(self::settingsKey));
        }
    }
}
