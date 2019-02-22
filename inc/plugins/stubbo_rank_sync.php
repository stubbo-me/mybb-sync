<?php
/**
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 * Created by PhpStorm.
 * User: George
 * Date: 20/02/2019
 * Time: 13:27
 */

if (!defined("IN_MYBB"))
    die("This file must be run from MyBB and not directly.");

/** @var object $plugins */
//$plugins->add_hook('admin_user_users_edit_commit');
$plugins->add_hook('misc_start', 'stubbo_rank_sync_redirect');

require __DIR__ . '/../rankSyncVendor.phar';

use GuzzleHttp\Client;

$guz = new Client([
    'base_uri' => 'https://discordapp.com/api/'
]);

/**
 * MyBB plugin information
 * @return array
 */
function stubbo_rank_sync_info()
{
    return [
        "name" => "Discord Rank Sync",
        "description" => "rank syncing and stuffs",
        "website" => "https://github.com/stubbo-me/mybb-sync",
        "author" => "Stubbo",
        "authorsite" => "https://stubbo.me",
        "version" => "0.1.0",
        "guid" => "",
        "compatibility" => "18*"
    ];
}

function stubbo_rank_sync_activate()
{
    /** @var object $db */
    global $db;

    $db->query("CREATE TABLE IF NOT EXISTS `discordSync` (uid int primary key not null, discord_flake bigint null, discord_name varchar(32) null, discord_discriminator int null, discord_avatar varchar(34) null, in_guild bool default false null);");
    $db->query("create table if not exists `DownTimeToUpdate` (id int auto_increment primary key, discord_id bigint null, discord_guild bigint null, role_name varchar(100) null, completed bool default false null);");

    $setting_group = [
        'name' => 'stubboDiscordRankSync',
        'title' => 'Stubbo Discord Rank Sync',
        'description' => 'Settings for discord rank syncing',
        "disporder" => "0",
        "isdefault" => 0
    ];

    $gid = $db->insert_query("settinggroups", $setting_group);

    $settings = [
        'stubboDiscordHookUrl' => [
            'title' => 'Url for the discord WebHooks',
            'description' => 'Will be bot&apos;s &quot;http(s)://ip(:port)&quot;\<br\>e.g. &quot;http://bot.com&quot;, &quot;https://localhost:1337&quot;',
            "optionscode" => "text",
            "value" => "",
            "disporder" => 1,
            "gid" => $gid
        ],
        'stubboDiscordBotSecret' => [
            'title' => 'WebHook secret',
            'description' => 'WebHook security',
            "optionscode" => "text",
            "value" => "",
            "disporder" => 2,
            "gid" => $gid
        ],
        'stubboDiscordClientID' => [
            'title' => 'OAuth Client ID',
            'description' => 'Discord client ID from \<a href\=\"https://discordapp.com/developers/applications/\" target=\"_blank\"\>Discord Dev Portal\<a\>',
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 3,
            'gid' => $gid
        ],
        'stubboDiscordClientSec' => [
            'title' => 'OAuth Client Secret',
            'description' => 'Discord client secret from \<a href\=\"https://discordapp.com/developers/applications/\" target=\"_blank\"\>Discord Dev Portal\<a\>',
            'optionscode' => 'text',
            'value' => '',
            'disporder' => 4,
            'gid' => $gid
        ],
        'stubboDiscordGuild' => [
            'title' => 'Discord Guild',
            'description' => 'The id for the discord server',
            'optionscode' => 'text',
            'value' => 1337,
            'disporder' => 5,
            'gid' => $gid
        ],
        'stubboDiscordInvite' => [
            'title' => 'Discord Invite',
            'description' => 'Discord invite link',
            'optionscode' => 'text',
            'value' => 'discord.gg/wow',
            'disporder' => 6,
            'gid' => $gid
        ],
        'stubboDiscordFallback' => [
            'title' => 'Down time sync',
            'description' => 'If the bot goes down should we update the roles when it comes back online',
            'optionscode' => 'onoff',
            'value' => 1,
            'disporder' => 7,
            'gid' => $gid
        ]
    ];

    foreach ($settings as $name => $setting) {
        $setting['name'] = $name;
        $db->insert_query('settings', $setting);
    }

    rebuild_settings();

    $templates = [
        [
            'title' => 'discordLink',
            'template' => $db->escape_string('<html>
                <head>
                <title>{$mybb->settings[\'bbname\']} - Discord Sync</title>
                {$headerinclude}
                </head>
                <body>
                {$header}
                {$text}
                {$footer}
                </body>
                </html>'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ],
        [
            'title' => 'discordLinkAlreadyDone',
            'template' => $db->escape_string('You have already linked your discord account.'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ],
        [
            'title' => 'discordLinkInvalidCode',
            'template' => $db->escape_string('You have entered a invalid code, please try again.'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ],
        [
            'title' => 'discordLinkNotInGuild',
            'template' => $db->escape_string('You are not in the discord server, <a href="{$discordInviteLink}">Click Here</a> to join.<br>'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ],
        [
            'title' => 'discordLinkNotRedirect',
            'template' => $db->escape_string('You cant directly goto this page, <a href={$discordOAuthURL}>Click Here</a> to link your discord account.'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ],
        [
            'title' => 'discordLinkSuccess',
            'template' => $db->escape_string('You linked your account :D'),
            'sid' => '-1',
            'version' => '',
            'dateline' => time()
        ]
    ];

    foreach ($templates as $template) {
        $db->insert_query("templates", $template);
    }
}

function stubbo_rank_sync_deactivate()
{
    /** @var object $db */
    global $db;

    $db->delete_query("settings", "name LIKE 'stubboDiscord%'");
    $db->delete_query("settinggroups", "name = 'stubboDiscordRankSync'");
    $db->delete_query("templates", "title LIKE 'stubboDiscord%'");
}

function stubbo_rank_sync_is_installed()
{
    return true;
}

global $discordOAuthURL, $discordInviteLink, $mybb;
$discordOAuthURL = "https://discordapp.com/oauth2/authorize?client_id={$mybb->settings['stubboDiscordClientID']}&redirect_uri={$mybb->settings['bburl']}/discord.php&response_type=code&scope=identify%20guilds";
$discordInviteLink = $mybb->settings['stubboDiscordInvite'];

function stubbo_rank_sync_redirect()
{
    /** @var object $mybb */
    global $mybb, $discordOAuthURL;

    if ($mybb->get_input('action') !== 'discord-login')
        return;
    header('Location: ' . $discordOAuthURL);
}

function stubbo_rank_sync_verified($discord_flake)
{
    global $mybb, $db;

    $guz = new Client([
        'base_uri' => $mybb->settings['stubboDiscordHookUrl']
    ]);

    $groups = [];
    $groups[] = $mybb->user['usergroup'];
    if ($mybb->user['additionalgroups'] !== "")
        $groups = array_merge($groups, explode(",", $mybb->user['additionalgroups']));

    $namedGroups = [];

    $query = $db->simple_select("usergroups", "gid, title");
    while ($group = $db->fetch_array($query)) {
        if (in_array($group['gid'], $groups))
            $namedGroups[] = $group['title'];
    }

    try {
        $data = $guz->post("/user/{$discord_flake}/roles/update", [
            "json" => [
                "roles" => $namedGroups,
                "guild" => $mybb->settings['stubboDiscordGuild']
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $mybb->settings['stubboDiscordBotSecret']
            ],
            'verify' => false,
            'timeout' => 1
        ]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        $dbData = [];

        foreach ($namedGroups as $group) {
            $dbData[] = [
                'discord_id' => $discord_flake,
                'discord_guild' => $mybb->settings['stubboDiscordGuild'],
                'role_name' => $group
            ];
        }

        $prefix = $db->table_prefix;

        $db->set_table_prefix('');
        $db->insert_query_multiple('DownTimeToUpdate', $dbData);
        $db->set_table_prefix($prefix);
    }
}