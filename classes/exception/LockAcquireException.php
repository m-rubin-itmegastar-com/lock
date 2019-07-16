<?php

namespace m_rubin_itmegastar_com\lock\exception;

/**
 * Failed to acquire lock.
 *
 * This exception implies that the critical code was not executed, or at
 * least had no side effects.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 */
class LockAcquireException extends MutexException
{

}
