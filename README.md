# API Speedtest - PHP 

Speed testing a simple REST API built with PHP and allowing user to use MySQL or MongoDB as the DB.

## Requirements

- PHP 5.3
	- Using PDO
	- Need Mongo extension
- MySQL 5
- Slim Framework 1.6.4
- Mongo 2
	- All writes are "saafe"
- Apache 2

## Usage

The following environmental variables are used to set credentials for the databases:

- MYSQL_USER 
- MYSQL_PASS 
- MYSQL_DB (Default: api_speedtest)
- MYSQL_HOST (Default: localhost)
- MONGO_USER (Default: <empty>)
- MONGO_PASS (Default: <empty>)
- MONGO_DB (Default: api_speedtest)
- MONGO_HOST (Default: localhost)

Here are the routes that have been setup as an example of common API methods.  In all cases, {:db_type} may be "mysql" or "mongo".  For instance, test the insert API using the Mongo DB. by requesting `/insert/mongo`.  The expected MySQL schema is found in `schema.sql`.

- /seed/{:db_type} - Populate the databases with sample data.  Note: I had problems with timeouts when running them on the production servers.  You may need to import from the CLI.
- /{:db_type} - Select a list of records by a criteria, order them, and return them
- /show/{:db_type} - Select a single record by ID
- /insert/{:db_type} - Insert a record
- /update/{:db_type} - Update a record

## Getting Mongo running on MAMP

I use MAMP locally.  To get Mongo running, I downloaded the Mac PHP 5.3 binary from the [Mongo Github](https://github.com/mongodb/mongo-php-driver/downloads) and dropped it in my `/Applications/MAMP/bin/php/php5.3.6/lib/php/extensions/no-debug-non-zts-20090626` directory.  Then I added `extension=mongo.so` to the Extensions block of the MAMP php.ini file. I installed Mongo using [Homebrew](http://mxcl.github.com/homebrew/).

## Getting Mongo running on Heroku

Follow [this gist](https://gist.github.com/1288447)