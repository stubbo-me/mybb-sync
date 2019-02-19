/*
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 */

import Express from 'express';
import DotEnv from 'dotenv';
import https from 'https';
import fs from 'fs';
import bodyParser from 'body-parser';

import Logging from './Logging';

export default function WebHooks(client) {
    DotEnv.config();

    const Logger = Logging.getLogger('WebHook');
    const syncLogger = Logging.getLogger('Rank Sync');

    const options = {
        key: fs.readFileSync(process.env.WEBHOOK_KEY),
        cert: fs.readFileSync(process.env.WEBHOOK_CERT)
    };
    const app = Express();

    const verify = (req, res, next) => {
        const header = req.headers["authorization"] || "";
        const auth = /Bearer (.+)/gm.exec(header);

        if (!auth || auth[1] !== process.env.WEBHOOK_SECRET)
            return res.status(403).end();
        return next();
    };

    app.use(verify);
    app.use(bodyParser.json());
    app.use(bodyParser.urlencoded({extended: true}));

    app.all('/', (request, response) => {
        response.send('Please enter a valid path');
    });

    app.post('/user/:userId/roles/update', (request, response) => {
        const user = request.params['userId'],
            roles = request.body['roles'],
            guildId = request.body['guild'];

        const guild = client.guilds.find(g => g.id === guildId);
        if (!guild)
            return response.status(400).json({'error': 'invalid guild'});

        guild.fetchMember(user).then(member => {
            roles.forEach(role => {
                const hasRole = member.roles.find(r => r.name === role);
                const roleExists = guild.roles.find(r => r.name === role);
                if (hasRole)
                    return;
                if (roleExists) {
                    member.addRole(roleExists, 'Rank Sync').then(() => {
                        syncLogger.info(`Added the role ${roleExists.name} to ${member.user.tag}`);
                    }).catch(err => {
                        syncLogger.error('Role Sync error, see error log for more information');
                        Logging.getLogger('error').error('Role Sync error', err);
                    });
                } else {
                    guild.createRole({
                        name: role,
                        color: 'RANDOM'
                    }, 'Rank Sync').then(r => {
                        syncLogger.info(`Created the role ${role}`);
                        member.addRole(r, 'Rank Sync').then(() => {
                            syncLogger.info(`Added the role ${r.name} to ${member.user.tag}`);
                        }).catch(err => {
                            syncLogger.error('Role Sync error, see error log for more information');
                            Logging.getLogger('error').error('Role Sync error', err);
                        });
                    }).catch(err => {
                        syncLogger.error('Role Sync error, see error log for more information');
                        Logging.getLogger('error').error('Role Sync error', err);
                    });
                }
            });
        });
        response.status(200).end();
    });

    if (process.env.WEBHOOK_SECURE) {
        Logger.info('Starting https WebHook server.');
        https.createServer(options, app).listen(process.env.WEBHOOK_PORT);
    } else {
        Logger.info('Starting insecure WebHook server, look into securing!');
        app.listen(process.env.WEBHOOK_PORT);
    }
}