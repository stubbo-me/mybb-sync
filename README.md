# MyBB forum rank syncing
Group syncing between MyBB and Discord

# Setting up the bot

## How to run
### Installing requirements
Run `npm install` in the project folder where the `package.json` file is, this will install all the dependencies used.

### Running in a dev environment
There is a NPM script that can be used to run it easily, however only its not advised to use it for running the bot in a production environment because it means that it will build the bot every start up.

Running `npm run dev` will build and start up the bot.

### Building for use in a production environment
Running `npm run buld` will execute the building process and will output to `./out`.

### Running the built bot
Its recommended to run the bot as a service when used in production, operating systems vary on how to implement services so I advise you look into this your self.
