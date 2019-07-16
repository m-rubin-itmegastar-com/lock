<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use m_rubin_itmegastar_com\lock\exception\LockAcquireException;
use m_rubin_itmegastar_com\lock\exception\LockReleaseException;

/**
 * Tests for LockMutex.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 * @see LockMutex
 */
class LockMutexTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject The SUT
     */
    private $mutex;
    
    protected function setUp()
    {
        parent::setUp();
        
        $this->mutex = $this->getMockForAbstractClass(LockMutex::class);
    }

    /**
     * Tests lock() fails and the code is not executed.
     *
     * @test
     * @expectedException m_rubin_itmegastar_com\lock\exception\LockAcquireException
     */
    public function testLockFails()
    {
        $this->mutex->expects($this->any())
            ->method("lock")
            ->willThrowException(new LockAcquireException());
        
        $this->mutex->synchronized(function () {
            $this->fail("Should not execute code.");
        });
    }
    
    /**
     * Tests unlock() is called after the code was executed.
     *
     * @test
     */
    public function testUnlockAfterCode()
    {
        $this->mutex->expects($this->once())->method("unlock");
        
        $this->mutex->synchronized(function () {
        });
    }
    
    /**
     * Tests unlock() is called after an exception.
     *
     * @test
     */
    public function testUnlockAfterException()
    {
        $this->mutex->expects($this->once())->method("unlock");
        
        try {
            $this->mutex->synchronized(function () {
                throw new \DomainException();
            });
        } catch (\DomainException $e) {
            // expected
        }
    }
    
    /**
     * Tests unlock() fails after the code was executed.
     *
     * @test
     * @expectedException m_rubin_itmegastar_com\lock\exception\LockReleaseException
     */
    public function testUnlockFailsAfterCode()
    {
        $this->mutex->expects($this->any())
            ->method("unlock")
            ->willThrowException(new LockReleaseException());
        
        $this->mutex->synchronized(function () {
        });
    }
    
    /**
     * Tests unlock() fails after the code threw an exception.
     *
     * The previous exception should be the code's exception.
     *
     * @test
     * @expectedException m_rubin_itmegastar_com\lock\exception\LockReleaseException
     */
    public function testUnlockFailsAfterException()
    {
        $this->mutex->expects($this->any())
            ->method("unlock")
            ->willThrowException(new LockReleaseException());
        
        $this->mutex->synchronized(function () {
            throw new \DomainException();
        });
    }
}
