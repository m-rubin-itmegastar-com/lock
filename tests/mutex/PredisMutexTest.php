<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use Predis\Client;
use Predis\ClientInterface;

/**
 * Tests for PredisMutex.
 *
 * These tests require the environment variable:
 *
 * REDIS_URIS - a comma separated list of redis:// URIs.
 *
 * @author  Markus Malkusch <markus@malkusch.de>
 * @link    bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @see     PredisMutex
 * @group   redis
 */
class PredisMutexTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        parent::setUp();

        $config = $this->getPredisConfig();

        if (null === $config) {
            $this->markTestSkipped();
            return;
        }

        $this->client = new Client($config);

        if (count($config) === 1) {
            $this->client->flushall(); // Clear any existing locks
        }
    }

    private function getPredisConfig()
    {
        if (getenv("REDIS_URIS") === false) {
            return null;
        }

        $servers = explode(",", getenv("REDIS_URIS"));

        return array_map(
            function ($redisUri) {
                return str_replace("redis://", "tcp://", $redisUri);
            },
            $servers
        );
    }

    /**
     * Tests add() fails.
     *
     * @test
     * @expectedException     \m_rubin_itmegastar_com\lock\exception\LockAcquireException
     * @expectedExceptionCode \m_rubin_itmegastar_com\lock\exception\MutexException::REDIS_NOT_ENOUGH_SERVERS
     */
    public function testAddFails()
    {
        $client = new Client("redis://127.0.0.1:12345");

        $mutex  = new PredisMutex([$client], "test");

        $mutex->synchronized(
            function () {
                $this->fail("Code execution is not expected");
            }
        );
    }

    /**
     * Tests evalScript() fails.
     *
     * @test
     * @expectedException \m_rubin_itmegastar_com\lock\exception\LockReleaseException
     */
    public function testEvalScriptFails()
    {
        $this->markTestIncomplete();
    }
}
