# Guzzle 6 middleware to log requests and responses

[![Author](http://img.shields.io/badge/author-@rudi_theunissen-blue.svg?style=flat-square)](https://twitter.com/rudi_theunissen)
[![License](https://img.shields.io/packagist/l/rtheunissen/guzzle-log-middleware.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-log-middleware)
[![Latest Version](https://img.shields.io/packagist/v/rtheunissen/guzzle-log-middleware.svg?style=flat-square)](https://packagist.org/packages/rtheunissen/guzzle-log-middleware)
[![Build Status](https://img.shields.io/travis/rtheunissen/guzzle-log-middleware.svg?style=flat-square&branch=master)](https://travis-ci.org/rtheunissen/guzzle-log-middleware)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/rtheunissen/guzzle-log-middleware.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-log-middleware/)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/rtheunissen/guzzle-log-middleware.svg?style=flat-square)](https://scrutinizer-ci.com/g/rtheunissen/guzzle-log-middleware/)

## Installation

```bash
composer require vbukreyev/guzzle-log-middleware
```

## Usage

```
use Concat\Http\Middleware\Logger;
```

### Logger
You can use either a [PSR-3](http://www.php-fig.org/psr/psr-3/) logger
(such as [Monolog](https://github.com/Seldaek/monolog))
or a callable that accepts a log level, message, and context as an array.

```php
$logger = new myLogger(); // should implements Psr\Log\LoggerInterface 
$middleware = new Logger($logger);
$middleware->setLogLevel(LogLevel::DEBUG);
$middleware->setFormatter(new \GuzzleHttp\MessageFormatter(myLogger::MY_FORMAT));
$middleware->setRequestLoggingEnabled(false);

$stack = \GuzzleHttp\HandlerStack::create();
$stack->push($middleware->logHandler());

$client = new GuzzleHttp\Client([
  
  'handler' => $stack,
  
]);

### 

class myLogger  extends \Psr\Log\AbstractLogger {

  const MY_FORMAT = "{hostname} {req_header_User-Agent} - [{date_common_log}] \"{method} {target} HTTP/{version}\" {code} {res_header_Content-Length} :: {req_body}";

  public function log($level, $message, array $context = array()) {

    file_put_contents('/tmp/my-logger.log', $message . PHP_EOL, FILE_APPEND);

  }

}

```

or

```php
$middleware = new Logger(function ($level, $message, array $context) {
    // Log the message
});
```

#### Log level
You can set a log level as a string or a callable that accepts a response. If the
response is not set, it can be assumed that it's a request-only log, or that the
request has been rejected. If the log level is not set, the default log level will be
used which is `LogLevel::NOTICE` for status codes >= 400, or `LogLevel::INFO` otherwise.
```php
use Psr\Log\LogLevel;

$middleware->setLogLevel(LogLevel::DEBUG);
```

or

```php
$middleware->setLogLevel(function ($response) {
    // Return log level
});
```
#### Formatter
You can set a message formatter as a [MessageFormatter](https://github.com/guzzle/guzzle/blob/master/src/MessageFormatter.php) or a callable that accepts
a request, response, as well as a reason when a request has been rejected.

```php
$middleware->setFormatter($formatter);
```

or

```php
$middleware->setFormatter(function ($request, $response, $reason) {
    // Return log message
});
```

or

```php
$middleware = new Logger($logger, $formatter);
```

#### Request logging

A request will only be logged when its response is received. You can enable request logging
by using `$middleware->setRequestLoggingEnabled(true)`, which will log a request when it is requested,
as well as its response when it is received. Any `$response` references will be `null` in the case
where only the request exists.

#### Middleware

This package is designed to function as [Guzzle 6 Middleware](http://guzzle.readthedocs.org/en/latest/handlers-and-middleware.html#middleware).
