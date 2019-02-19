/*
 * Copyright (c) 2019.
 * This file is licensed under a GNU General Public License v3.0 a more detailed license file called "LICENSE" MUST be included with these files.
 * If the file is missing then more information can be found at https://github.com/stubbo-me/mybb-sync/blob/master/LICENSE
 */

import DotEnv from 'dotenv';

import Log from 'log4js';
import stdout from 'log4js/lib/appenders/stdout';
import file from 'log4js/lib/appenders/file';
import dateFile from 'log4js/lib/appenders/dateFile';

DotEnv.config();

Log.configure({
    appenders: {
        out: {
            type: 'stdout'
        },
        app: {
            type: 'dateFile',
            filename: 'logs/RankSync.log',
            compress: true
        },
        errors: {
            type: 'file',
            filename: 'logs/Errors.log'
        }
    },
    categories: {
        error: {
            appenders: ['errors', 'out'],
            level: 'trace'
        },
        default: {
            appenders: ['app', 'out'],
            level: (process.env.BOT_DEBUG) ? 'debug' : 'info'
        }
    }
});

export default Log;