<?php
/**
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 * Created by PhpStorm.
 * User: George
 * Date: 20/02/2019
 * Time: 20:07
 */

define('IN_MYBB', 1);
require "./global.php";
/**
 * @var object $mybb
 * @var object $db
 */
if (!$mybb->user['uid'])
    error_no_permission();

global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer;

add_breadcrumb('Discord Sync', "discord.php");

$prefix = $db->table_prefix;

require __DIR__ . '/inc/rankSyncVendor.phar';

use GuzzleHttp\Client;

$guz = new Client([
    'base_uri' => 'https://discordapp.com/api/'
]);
if (isset($_GET['code'])) {
    $data = $guz->post('oauth2/token', [
        'form_params' => [
            'code' => $_GET['code'],
            'scope' => 'identify guilds',
            'grant_type' => 'authorization_code',
            'redirect_uri' => $mybb->settings['bburl'] . "/discord.php"
        ],
        'auth' => [
            $mybb->settings['stubboDiscordClientID'],
            $mybb->settings['stubboDiscordClientSec']
        ],
        'verify' => false,
        'exceptions' => false
    ]);

    if ($data->getStatusCode() !== 200) {
        eval('$text = "' . $templates->get("discordLinkInvalidCode") . '";');
        eval('$page = "' . $templates->get("discordLink") . '";');
        output_page($page);
        return;
    }

    $access_token = json_decode($data->getBody(), true)['access_token'];
    unset($data);

    $data = $guz->get('users/@me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token
        ],
        'verify' => false
    ]);

    $me = json_decode($data->getBody(), true);
    $userData = [
        'username' => $me['username'],
        'discrim' => $me['discriminator'],
        'avatar' => "https://cdn.discordapp.com/{$me['username']}/{$me['avatar']}",
        'avatarID' => $me['avatar'],
        'id' => $me['id'],
        'inGuild' => false
    ];

    $db->set_table_prefix('');
    $query = $db->fetch_array($db->simple_select("discordSync", "uid", "discord_flake={$me['id']}", [
        'limit' => 1
    ]));
    $db->set_table_prefix($prefix);

    if (sizeof($query) === 1) {
        eval('$text = "' . $templates->get("discordLinkAlreadyDone") . '";');
        eval('$page = "' . $templates->get("discordLink") . '";');
        output_page($page);
        return;
    }

    unset($data);

    $data = $guz->get('users/@me/guilds', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token
        ],
        'verify' => false
    ]);

    foreach (json_decode($data->getBody(), true) as $guild) {
        if ($guild['id'] === $mybb->settings['stubboDiscordGuild']) {
            $userData['inGuild'] = true;
            break;
        }
    }

    $db->set_table_prefix('');
    $db->insert_query('discordSync', [
        "uid" => $mybb->user['uid'],
        "discord_flake" => $userData['id'],
        "discord_name" => $userData['username'],
        "discord_discriminator" => $userData['discrim'],
        "discord_avatar" => $userData['avatarID'],
        "in_guild" => true
    ]);
    $db->set_table_prefix($prefix);

    if (!$userData['inGuild']) {
        eval('$text = "' . $templates->get("discordInviteLink") . '";');
    } else {
        eval('$text = "' . $templates->get("discordLinkSuccess") . '";');

        stubbo_rank_sync_verified($userData['id']);
    }
} else {
    eval('$text = "' . $templates->get("discordLinkNotRedirect") . '";');
}

eval('$page = "' . $templates->get("discordLink") . '";');
output_page($page);