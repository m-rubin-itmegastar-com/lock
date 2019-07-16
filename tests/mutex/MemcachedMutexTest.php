<?php

namespace m_rubin_itmegastar_com\lock\mutex;

/**
 * Tests for MemcachedMutex.
 *
 * Please provide the environment variable MEMCACHE_HOST.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @requires extension memcached
 * @see MemcachedMutex
 */
class MemcachedMutexTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Memcached
     */
    protected $memcached;

    protected function setUp()
    {
        $this->memcached = new \Memcached();
        $this->memcached->addServer(getenv("MEMCACHE_HOST") ?: "localhost", 11211);
        $this->memcached->flush();
    }

    /**
     * Tests failing to acquire the lock.
     *
     * @test
     * @expectedException \m_rubin_itmegastar_com\lock\exception\TimeoutException
     */
    public function testFailAcquireLock()
    {
        $mutex = new MemcachedMutex("testFailAcquireLock", $this->memcached, 1);

        $this->memcached->add(MemcachedMutex::PREFIX."testFailAcquireLock", "xxx", 999);

        $mutex->synchronized(function () {
            $this->fail("execution is not expected");
        });
    }
    
    /**
     * Tests failing to release a lock.
     *
     * @test
     * @expectedException \m_rubin_itmegastar_com\lock\exception\LockReleaseException
     */
    public function testFailReleasingLock()
    {
        $mutex = new MemcachedMutex("testFailReleasingLock", $this->memcached, 1);
        $mutex->synchronized(function () {
            $this->memcached->delete(MemcachedMutex::PREFIX."testFailReleasingLock");
        });
    }
}
