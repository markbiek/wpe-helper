# WPEngine Helper

This is a [Laravel Zero](https://laravel-zero.com/) application with a series of commands to help in interacting with WPEngine sites.

# Setup

1. Locate your [WPEngine API credentials](https://my.wpengine.com/api_access) and make note of them.

## User Setup

If you've downloaded the `wpeh` binary release, all you have to do is run

```
wpeh setup
```

and enter your WPE api credentials when prompted

## Developer Setup

1. Copy `.env.example` to `.env`
1. Edit `.env` to fill in the appropriate values for your database connection
1. `composer install`
1. `npm install`

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
