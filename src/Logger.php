<?php

namespace Concat\Http\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Promise;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Guzzle middleware which logs a request and its response.
 */
class Logger
{
  /**
   * @var \Psr\Log\LoggerInterface|callable
   */
  protected $logger;

  /**
   * @var \GuzzleHttp\MessageFormatter|callable
   */
  protected $formatter;

  /**
   * @var string|callable Constant or callable that accepts a Response.
   */
  protected $logLevel;

  /**
   * @var boolean Whether or not to log requests as they are made.
   */
  protected $logRequests;

  /**
   * @var string Request ID
   */
  protected $requestId;

  /**
   * Creates a callable middleware for logging requests and responses.
   *
   * @param LoggerInterface|callable $logger
   * @param string|callable Constant or callable that accepts a Response.
   */
  public function __construct($logger, $formatter = NULL) {
    // Use the setters to take care of type validation
    $this->setLogger($logger);
    $this->setFormatter($formatter ?: $this->getDefaultFormatter());
  }

  /**
   * Returns the default formatter;
   *
   * @return MessageFormatter
   */
  protected function getDefaultFormatter() {
    return new MessageFormatter();
  }

  /**
   * Sets whether requests should be logged before the response is received.
   *
   * @param boolean $logRequests
   */
  public function setRequestLoggingEnabled($logRequests = TRUE) {
    $this->logRequests = (bool) $logRequests;
  }

  /**
   * Sets the logger, which can be a PSR-3 logger or a callable that accepts
   * a log level, message, and array context.
   *
   * @param LoggerInterface|callable $logger
   *
   * @throws InvalidArgumentException
   */
  public function setLogger($logger) {
    if ($logger instanceof LoggerInterface || is_callable($logger)) {
      $this->logger = $logger;
    }
    else {
      throw new InvalidArgumentException(
        "Logger has to be a Psr\Log\LoggerInterface or callable"
      );
    }
  }

  /**
   * Sets the formatter, which can be a MessageFormatter or callable that
   * accepts a request, response, and a reason if an error has occurred.
   *
   * @param MessageFormatter|callable $formatter
   *
   * @throws InvalidArgumentException
   */
  public function setFormatter($formatter) {
    if ($formatter instanceof MessageFormatter || is_callable($formatter)) {
      $this->formatter = $formatter;
    }
    else {
      throw new InvalidArgumentException(
        "Formatter has to be a \GuzzleHttp\MessageFormatter or callable"
      );
    }
  }

  /**
   * Sets the log level to use, which can be either a string or a callable
   * that accepts a response (which could be null). A log level could also
   * be null, which indicates that the default log level should be used.
   *
   * @param string|callable|null
   */
  public function setLogLevel($logLevel) {
    $this->logLevel = $logLevel;
  }

  /**
   * Sets ID of log record[s].
   *
   * @param string $id
   */
  public function setRequestId($id) {
    $this->requestId = $id;
  }

  /**
   * Logs a request and/or a response.
   *
   * @param RequestInterface $request
   * @param ResponseInterface|null $response
   * @param mixed $reason
   *
   * @return null | mixed
   */
  protected function log(
    RequestInterface $request,
    ResponseInterface $response = NULL,
    $reason = NULL
  ) {
    if ($reason instanceof RequestException) {
      $response = $reason->getResponse();
    }

    $level   = $this->getLogLevel($response);
    $message = $this->getLogMessage($request, $response, $reason);
    $context = compact('request', 'response', 'reason');

    if ($this->requestId) {
      $context['request_id'] = $this->requestId;
    }

    if (is_callable($this->logger)) {
      return call_user_func($this->logger, $level, $message, $context);
    }

    return $this->logger->log($level, $message, $context);
  }

  /**
   * Formats a request and response as a log message.
   *
   * @param RequestInterface $request
   * @param ResponseInterface|null $response
   * @param mixed $reason
   *
   * @return string The formatted message.
   */
  protected function getLogMessage(
    RequestInterface $request,
    ResponseInterface $response = NULL,
    $reason = NULL
  ) {
    if ($this->formatter instanceof MessageFormatter) {
      return $this->formatter->format(
        $request,
        $response,
        $reason
      );
    }

    return call_user_func($this->formatter, $request, $response, $reason);
  }

  /**
   * Returns a log level for a given response.
   *
   * @param ResponseInterface $response The response being logged.
   *
   * @return string LogLevel
   */
  protected function getLogLevel(ResponseInterface $response = NULL) {
    if (!$this->logLevel) {
      return $this->getDefaultLogLevel($response);
    }

    if (is_callable($this->logLevel)) {
      return call_user_func($this->logLevel, $response);
    }

    return (string) $this->logLevel;
  }

  /**
   * Returns the default log level for a response.
   *
   * @param ResponseInterface $response
   *
   * @return string LogLevel
   */
  protected function getDefaultLogLevel(ResponseInterface $response = NULL) {
    if ($response && $response->getStatusCode() >= 300) {
      return LogLevel::NOTICE;
    }

    return LogLevel::INFO;
  }

  /**
   * Returns a function which is handled when a request was successful.
   *
   * @param RequestInterface $request
   *
   * @return Closure
   */
  protected function onSuccess(RequestInterface $request) {
    return function ($response) use ($request) {
      $this->log($request, $response);
      return $response;
    };
  }

  /**
   * Returns a function which is handled when a request was rejected.
   *
   * @param RequestInterface $request
   *
   * @return Closure
   */
  protected function onFailure(RequestInterface $request) {
    return function ($reason) use ($request) {

      // Only log a rejected requests if it hasn't already been logged
      if (!$this->logRequests) {
        $this->log($request, NULL, $reason);
      }

      return Promise\rejection_for($reason);
    };
  }

  /**
   * Called when the middleware is handled by the client.
   *
   * @param callable $handler
   *
   * @return Closure
   */
  public function logHandler() {

    return function (callable $handler) {
      return function ($request, array $options) use ($handler) {

        // Only log requests if explicitly set to do so
        if ($this->logRequests) {
          $this->log($request);
        }

        return $handler($request, $options)->then(
          $this->onSuccess($request),
          $this->onFailure($request)
        );
      };
    };
  }
}
