# WPEngine Helper

This is a Laravel application with a series of artisan commands to interacting with WPEngine sites.

## Setup

1. Copy `.env.example` to `.env`
1. Edit `.env` to fill in the appropriate values for your database connection
1. Locate your [WPEngine API credentials](https://my.wpengine.com/api_access) and add them to the `.env` file

Once the `.env` is complete, you can run `sh ./setup.sh` to initialize the project.

And now you're ready to run commands!

Run `./artisan installs` to see a list of available commands.

To make it easier to run commands from anywhere, you can create an alias in your `.bashrc` or `.zshrc` file.

`wpeh='/path/to/wpe/artisan'`

## Available Commands

### `installs:find`

Given a partial name, find any matching installs and return information about them.

By default, data is displayed in the following form:

```
name: <install name>
domain: <install primary domain>
environment: <wpe environment type (production/staging/etc)>
url: <wpe install dashboard url>
```

You can get the output as JSON with the `--output=json` or only output the install name with `--name-only`.

### `installs:list`

List all active, production installs. You can include dev/staging and/or inactive installs with the `--development`/`--staging` and `--inactive` options.

This command also supports `--output-json` and `--name-only`.

### `installs:ssh`

Displays the command to connect to an install via ssh.

You can find the command and connect in one step likes so:

```
`wpeh installs:ssh myinstall`
```

### `installs:dump-db`

Dump the contents of an install database. Output is sent to STDOUT and is gzipped by default. You can use `--raw` to get an uncompressed dump.

Example

```
wpeh installs:dump-db myinstall > myinstall.sql.gz
```

### `installs:cache`

The wpe helper stores information about all of your WPEngine installs in the database to make for faster queries. This command refreshes that database cache.

### `installs:clear`

Deletes all entries in the database cache.
