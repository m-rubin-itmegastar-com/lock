<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use Eloquent\Liberator\Liberator;
use Memcached;
use org\bovigo\vfs\vfsStream;
use Predis\Client;
use Redis;

/**
 * Tests for Mutex.
 *
 * If you want to run integrations tests you should provide these environment variables:
 *
 * - MEMCACHE_HOST
 * - REDIS_URIS - a comma separated list of redis:// URIs.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @see Mutex
 */
class MutexTest extends \PHPUnit_Framework_TestCase
{
    const TIMEOUT = 4;

    public static function setUpBeforeClass()
    {
        vfsStream::setup("test");
    }

    /**
     * Provides Mutex factories.
     *
     * @return callable[][] The mutex factories.
     */
    public function provideMutexFactories()
    {
        $cases = [
            "NoMutex" => [function () {
                return new NoMutex();
            }],

            "TransactionalMutex" => [function () {
                $pdo = new \PDO("sqlite::memory:");
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return new TransactionalMutex($pdo, self::TIMEOUT);
            }],

            "FlockMutex" => [function () {
                $file = fopen(vfsStream::url("test/lock"), "w");
                return new FlockMutex($file);
            }],

            "flockWithTimoutPcntl" => [function () {
                $file = fopen(vfsStream::url("test/lock"), "w");
                $lock = Liberator::liberate(new FlockMutex($file, 3));
                $lock->stategy = FlockMutex::STRATEGY_PCNTL;

                return $lock;
            }],

            "flockWithTimoutBusy" => [function ($timeout = 3) {
                $file = fopen(vfsStream::url("test/lock"), "w");
                $lock = Liberator::liberate(new FlockMutex($file, 3));
                $lock->stategy = FlockMutex::STRATEGY_BUSY;

                return $lock;
            }],

            "SemaphoreMutex" => [function () {
                return new SemaphoreMutex(sem_get(ftok(__FILE__, "a")));
            }],

            "SpinlockMutex" => [function () {
                $mock = $this->getMockForAbstractClass(SpinlockMutex::class, ["test"]);
                $mock->expects($this->any())->method("acquire")->willReturn(true);
                $mock->expects($this->any())->method("release")->willReturn(true);
                return $mock;
            }],

            "LockMutex" => [function () {
                $mock = $this->getMockForAbstractClass(LockMutex::class);
                $mock->expects($this->any())->method("lock")->willReturn(true);
                $mock->expects($this->any())->method("unlock")->willReturn(true);
                return $mock;
            }],
        ];

        if (getenv("MEMCACHE_HOST")) {
            $cases["MemcachedMutex"] = [function () {
                $memcache = new Memcached();
                $memcache->addServer(getenv("MEMCACHE_HOST"), 11211);
                return new MemcachedMutex("test", $memcache, self::TIMEOUT);
            }];
        }

        if (getenv("REDIS_URIS")) {
            $uris = explode(",", getenv("REDIS_URIS"));

            $cases["PredisMutex"] = [function () use ($uris) {
                $clients = array_map(
                    function ($uri) {
                        return new Client($uri);
                    },
                    $uris
                );
                return new PredisMutex($clients, "test", self::TIMEOUT);
            }];

            $cases["PHPRedisMutex"] = [function () use ($uris) {
                $apis = array_map(
                    function ($uri) {
                        $redis = new Redis();
                        
                        $uri = parse_url($uri);
                        if (!empty($uri["port"])) {
                            $redis->connect($uri["host"], $uri["port"]);
                        } else {
                            $redis->connect($uri["host"]);
                        }
                        
                        return $redis;
                    },
                    $uris
                );
                return new PHPRedisMutex($apis, "test", self::TIMEOUT);
            }];
        }

        if (getenv("MYSQL_DSN")) {
            $cases["MySQLMutex"] = [function () {
                $pdo = new \PDO(getenv("MYSQL_DSN"), getenv("MYSQL_USER"));
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                return new MySQLMutex($pdo, "test" . time(), self::TIMEOUT);
            }];
        }

        if (getenv("PGSQL_DSN")) {
            $cases["PgAdvisoryLockMutex"] = [function () {
                $pdo = new \PDO(getenv("PGSQL_DSN"), getenv("PGSQL_USER"));
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                return new PgAdvisoryLockMutex($pdo, "test");
            }];
        }

        return $cases;
    }
    
    /**
     * Tests synchronized() executes the code and returns its result.
     *
     * @param callable $mutexFactory The Mutex factory.
     * @test
     * @dataProvider provideMutexFactories
     */
    public function testSynchronizedDelegates(callable $mutexFactory)
    {
        $mutex  = call_user_func($mutexFactory);
        $result = $mutex->synchronized(function () {
            return "test";
        });
        $this->assertEquals("test", $result);
    }
    
    /**
     * Tests that synchronized() released the lock.
     *
     * @param callable $mutexFactory The Mutex factory.
     * @test
     * @dataProvider provideMutexFactories
     */
    public function testRelease(callable $mutexFactory)
    {
        $mutex = call_user_func($mutexFactory);
        $mutex->synchronized(function () {
        });
        $mutex->synchronized(function () {
        });
    }

    /**
     * Tests synchronized() rethrows the exception of the code.
     *
     * @param callable $mutexFactory The Mutex factory.
     * @test
     * @dataProvider provideMutexFactories
     * @expectedException \DomainException
     */
    public function testSynchronizedPassesExceptionThrough(callable $mutexFactory)
    {
        $mutex = call_user_func($mutexFactory);
        $mutex->synchronized(function () {
            throw new \DomainException();
        });
    }
}
