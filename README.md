# API Speedtest - PHP 

Speed testing a simple REST API built with PHP and MySQL.

## Requirements

- PHP 5.3
	- Using PDO
	- Need Mongo extension
- MySQL 5
- Slim Framework 1.6.4
- Mongo 2
	- All writes are "saafe"
- Apache 2

## Getting Mongo running on MAMP

I use MAMP locally.  To get Mongo running, I downloaded the Mac PHP 5.3 binary from the [Mongo Github](https://github.com/mongodb/mongo-php-driver/downloads) and dropped it in my `/Applications/MAMP/bin/php/php5.3.6/lib/php/extensions/no-debug-non-zts-20090626` directory.  Then I added `extension=mongo.so` to the Extensions block of the MAMP php.ini file. I installed Mongo using [Homebrew](http://mxcl.github.com/homebrew/).