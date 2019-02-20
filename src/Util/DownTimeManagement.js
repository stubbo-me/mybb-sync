/*
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 */

import DotEnv from 'dotenv';
import MySQL from 'mysql2/promise';
import Logging from "./Logging";

export default function DownTimeManagement(client) {
    DotEnv.config();

    const Logger = Logging.getLogger('DownTime Catch Up');

    MySQL.createConnection({
        host: process.env.DB_HOST,
        user: process.env.DB_USER,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_DATABASE,
        port: process.env.DB_PORT,
        supportBigNumbers: true
    }).then(db => {
        db.query("create table if not exists DownTimeToUpdate (" +
            "id int auto_increment primary key," +
            "discord_id bigint null," +
            "discord_guild bigint null," +
            "role_name varchar(100) null," +
            "completed bool default false null" +
            ");");

        Logger.info('Checking if there was missed sync requests.');
        db.query("select * from DownTimeToUpdate where completed=false").then((data) => {
            db.query("update DownTimeToUpdate SET completed=true where completed=false;");

            const rows = data[0];

            if (rows.length > 0)
                Logger.info(`There are ${rows.length} ${(rows.length > 1) ? "roles" : "role"} to sync.`);
            else
                return Logger.info(`There are no roles to sync.`);

            rows.forEach(role => {
                const guild = client.guilds.find(g => g.id === role.discord_guild);
                if (!guild)
                    return Logger.info('Guild does not exist');

                guild.fetchMember(role.discord_id).then(member => {
                    const hasRole = member.roles.find(r => r.name === role.role_name);
                    const roleExists = guild.roles.find(r => r.name === role.role_name);
                    if (hasRole)
                        return;

                    if (roleExists) {
                        member.addRole(roleExists, 'Rank Sync').then(() => {
                            Logger.info(`Added the role ${roleExists.name} to ${member.user.tag}`);
                        }).catch(err => {
                            Logger.error('Role Sync error, see error log for more information');
                            Logging.getLogger('error').error('Role Sync error', err);
                        });
                    } else {
                        guild.createRole({
                            name: role.role_name,
                            color: 'RANDOM'
                        }, 'Rank Sync').then(r => {
                            Logger.info(`Created the role ${role.role_name}`);
                            member.addRole(r, 'Rank Sync').then(() => {
                                Logger.info(`Added the role ${r.name} to ${member.user.tag}`);
                            }).catch(err => {
                                Logger.error('Role Sync error, see error log for more information');
                                Logging.getLogger('error').error('Role Sync error', err);
                            });
                        }).catch(err => {
                            Logger.error('Role Sync error, see error log for more information');
                            Logging.getLogger('error').error('Role Sync error', err);
                        });
                    }
                });
            });
        });
    }).catch(err => {
        Logger.error('Issue connecting to database, see error log for more information.');
        Logging.getLogger('error').error('Database error', err);
    });
}