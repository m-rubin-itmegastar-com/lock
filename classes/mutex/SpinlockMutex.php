<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use m_rubin_itmegastar_com\lock\exception\ExecutionOutsideLockException;
use m_rubin_itmegastar_com\lock\exception\LockAcquireException;
use m_rubin_itmegastar_com\lock\exception\LockReleaseException;
use m_rubin_itmegastar_com\lock\util\Loop;

/**
 * Spinlock implementation.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @internal
 */
abstract class SpinlockMutex extends LockMutex
{
    
    /**
     * @var int The timeout in seconds a lock may live.
     */
    private $timeout;
    
    /**
     * @var Loop The loop.
     */
    private $loop;
    
    /**
     * @var string The lock key.
     */
    private $key;
    
    /**
     * @var double The timestamp when the lock was acquired.
     */
    private $acquired;
    
    /**
     * The prefix for the lock key.
     */
    private const PREFIX = "lock_";
    
    /**
     * Sets the timeout.
     *
     * @param int $timeout The time in seconds a lock expires, default is 3.
     *
     * @throws \LengthException The timeout must be greater than 0.
     */
    public function __construct(string $name, int $timeout = 3)
    {
        $this->timeout = $timeout;
        $this->loop    = new Loop($this->timeout);
        $this->key     = self::PREFIX.$name;
    }
    
    protected function lock(): void
    {
        $this->loop->execute(function (): void {
            $this->acquired = microtime(true);
            
            /*
             * The expiration time for the lock is increased by one second
             * to ensure that we delete only our keys. This will prevent the
             * case that this key expires before the timeout, and another process
             * acquires successfully the same key which would then be deleted
             * by this process.
             */
            if ($this->acquire($this->key, $this->timeout + 1)) {
                $this->loop->end();
            }
        });
    }

    protected function unlock(): void
    {
        $elapsed_time = microtime(true) - $this->acquired;
        if ($elapsed_time > $this->timeout) {
            throw ExecutionOutsideLockException::create($elapsed_time, $this->timeout);
        }

        /*
         * Worst case would still be one second before the key expires.
         * This guarantees that we don't delete a wrong key.
         */
        if (!$this->release($this->key)) {
            throw new LockReleaseException("Failed to release the lock.");
        }
    }
    
    /**
     * Tries to acquire a lock.
     *
     * @param string $key The lock key.
     * @param int $expire The timeout in seconds when a lock expires.
     *
     * @return bool True, if the lock could be acquired.
     * @throws LockAcquireException An unexpected error happened.
     */
    abstract protected function acquire(string $key, int $expire): bool;

    /**
     * Tries to release a lock.
     *
     * @param string $key The lock key.
     * @return bool True, if the lock could be released.
     */
    abstract protected function release(string $key): bool;
}
