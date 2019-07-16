<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use Eloquent\Liberator\Liberator;
use m_rubin_itmegastar_com\lock\util\PcntlTimeout;

/**
 * @author Willem Stuursma-Ruwen <willem@stuursma.name>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @see CASMutex
 */
class FlockMutexTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FlockMutex
     */
    private $mutex;

    /**
     * @var resource
     */
    private $file;

    protected function setUp()
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), "flock-");
        $this->mutex = Liberator::liberate(new FlockMutex(fopen($this->file, "r"), 1));
    }

    protected function tearDown()
    {
        unlink($this->file);

        parent::tearDown();
    }

    /**
     * @dataProvider dpTimeoutableStrategies
     */
    public function testCodeExecutedOutsideLockIsNotThrown($strategy)
    {
        $this->mutex->strategy = $strategy;

        $this->assertTrue($this->mutex->synchronized(function () {
            usleep(1.1e6);
            return true;
        }));
    }

    /**
     * @expectedException \m_rubin_itmegastar_com\lock\exception\TimeoutException
     * @expectedExceptionMessage Timeout of 1 seconds exceeded.
     * @dataProvider dpTimeoutableStrategies
     */
    public function testTimeoutOccurs($strategy)
    {
        $another_resource = fopen($this->file, "r");
        flock($another_resource, LOCK_EX);

        $this->mutex->strategy = $strategy;

        try {
            $this->mutex->synchronized(
                function () {
                    $this->fail("Did not expect code to be executed");
                }
            );
        } finally {
            fclose($another_resource);
        }
    }

    public function dpTimeoutableStrategies()
    {
        return [
            [FlockMutex::STRATEGY_PCNTL],
            [FlockMutex::STRATEGY_BUSY],
        ];
    }

    /**
     * @expectedException \m_rubin_itmegastar_com\lock\exception\DeadlineException
     */
    public function testNoTimeoutWaitsForever()
    {
        $another_resource = fopen($this->file, "r");
        flock($another_resource, LOCK_EX);

        $this->mutex->strategy = FlockMutex::STRATEGY_BLOCK;

        $timebox = new PcntlTimeout(1);
        $timebox->timeBoxed(function () {
            $this->mutex->synchronized(function () {
                $this->fail("Did not expect code execution.");
            });
        });
    }
}
