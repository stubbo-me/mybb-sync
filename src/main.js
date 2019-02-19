/*
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 */

import Commando from 'discord.js-commando';
import DotEnv from 'dotenv';
import MySQL from 'mysql2/promise';
import CommandoProvider from 'discord.js-commando-mysqlprovider';

import Logging from './Util/Logging';
import WebHooks from './Util/WebHooks';

let Logger = Logging.getLogger('Initialise');

Logger.info('Loading Config.');
let env = DotEnv.config();
if (env.error) {
    Logger.error('Failed to load config, see error log for more information.');
    Logging.getLogger('error').error('Config failed to load.', env.error);
}

const client = new Commando.CommandoClient({
    owner: '216302050970042368',
    disableEveryone: true,
    unknownCommandResponse: false
});

client.on('ready', () => {
    Logger = Logging.getLogger(client.user.tag);
    Logger.info(`Discord ~ Logged in as ${client.user.tag}`);

    client.user.setActivity(process.env.BOT_ACTIVITY);

    Logger.info('Starting WebHook service.');
    WebHooks(client);
});

MySQL.createConnection({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
    port: process.env.DB_PORT
}).then(db => {
    client.setProvider(new CommandoProvider(db));
    client.login(process.env.BOT_TOKEN);
}).catch(err => {
    Logger.error('Issue connecting to database, see error log for more information.');
    Logging.getLogger('error').error('Database error', err);
});