<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LoggerAwareInterface;
use m_rubin_itmegastar_com\lock\exception\LockAcquireException;
use m_rubin_itmegastar_com\lock\exception\LockReleaseException;

/**
 * Mutex based on the Redlock algorithm.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @license WTFPL
 *
 * @link http://redis.io/topics/distlock
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 */
abstract class RedisMutex extends SpinlockMutex implements LoggerAwareInterface
{
    
    /**
     * @var string The random value token for key identification.
     */
    private $token;
    
    /**
     * @var array The Redis APIs.
     */
    private $redisAPIs;
    
    /**
     * @var LoggerInterface The logger.
     */
    private $logger;

    /**
     * Sets the Redis APIs.
     *
     * @param array  $redisAPIs The Redis APIs.
     * @param string $name      The lock name.
     * @param int    $timeout   The time in seconds a lock expires, default is 3.
     *
     * @throws \LengthException The timeout must be greater than 0.
     */
    public function __construct(array $redisAPIs, string $name, int $timeout = 3)
    {
        parent::__construct($name, $timeout);

        $this->redisAPIs = $redisAPIs;
        $this->logger    = new NullLogger();
    }
    
    /**
     * Sets a logger instance on the object
     *
     * RedLock is a fault tolerant lock algorithm. I.e. it does tolerate
     * failing redis connections without breaking. If you want to get notified
     * about such events you'll have to provide a logger. Those events will
     * be logged as warnings.
     *
     * @param LoggerInterface $logger The logger.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    protected function acquire(string $key, int $expire): bool
    {
        // 1. This differs from the specification to avoid an overflow on 32-Bit systems.
        $time = microtime(true);
        
        // 2.
        $acquired = 0;
        $errored  = 0;
        $this->token = \random_bytes(16);
        $exception   = null;
        foreach ($this->redisAPIs as $index => $redisAPI) {
            try {
                if ($this->add($redisAPI, $key, $this->token, $expire)) {
                    $acquired++;
                }
            } catch (LockAcquireException $exception) {
                // todo if there is only one redis server, throw immediately.
                $context = [
                    "key"       => $key,
                    "token"     => $this->token,
                    "exception" => $exception
                ];
                $this->logger->warning("Could not set {key} = {token} at server #{index}.", $context);

                $errored++;
            }
        }
        
        // 3.
        $elapsedTime = microtime(true) - $time;
        $isAcquired  = $this->isMajority($acquired) && $elapsedTime <= $expire;
        
        if ($isAcquired) {
            // 4.
            return true;
        }

        // 5.
        $this->release($key);

        // In addition to RedLock it's an exception if too many servers fail.
        if (!$this->isMajority(count($this->redisAPIs) - $errored)) {
            assert(!is_null($exception)); // The last exception for some context.
            throw new LockAcquireException(
                "It's not possible to acquire a lock because at least half of the Redis server are not available.",
                LockAcquireException::REDIS_NOT_ENOUGH_SERVERS,
                $exception
            );
        }

        return false;
    }
    
    protected function release(string $key): bool
    {
        /*
         * All Redis commands must be analyzed before execution to determine which keys the command will operate on. In
         * order for this to be true for EVAL, keys must be passed explicitly.
         *
         * @link https://redis.io/commands/set
         */
        $script = 'if redis.call("get",KEYS[1]) == ARGV[1] then
                return redis.call("del",KEYS[1])
            else
                return 0
            end
        ';
        $released = 0;
        foreach ($this->redisAPIs as $index => $redisAPI) {
            try {
                if ($this->evalScript($redisAPI, $script, 1, [$key, $this->token])) {
                    $released++;
                }
            } catch (LockReleaseException $e) {
                // todo throw if there is only one redis server
                $context = [
                    "key"       => $key,
                    "index"     => $index,
                    "token"     => $this->token,
                    "exception" => $e
                ];
                $this->logger->warning("Could not unset {key} = {token} at server #{index}.", $context);
            }
        }
        return $this->isMajority($released);
    }
    
    /**
     * Returns if a count is the majority of all servers.
     *
     * @param int $count The count.
     * @return bool True if the count is the majority.
     */
    private function isMajority(int $count): bool
    {
        return $count > count($this->redisAPIs) / 2;
    }

    /**
     * Sets the key only if such key doesn't exist at the server yet.
     *
     * @param mixed $redisAPI The connected Redis API.
     * @param string $key The key.
     * @param string $value The value.
     * @param int $expire The TTL seconds.
     *
     * @return bool True, if the key was set.
     */
    abstract protected function add($redisAPI, string $key, string $value, int $expire): bool;

    /**
     * @param mixed  $redisAPI The connected Redis API.
     * @param string $script The Lua script.
     * @param int    $numkeys The number of values in $arguments that represent Redis key names.
     * @param array  $arguments Keys and values.
     *
     * @return mixed The script result, or false if executing failed.
     * @throws LockReleaseException An unexpected error happened.
     */
    abstract protected function evalScript($redisAPI, string $script, int $numkeys, array $arguments);
}
