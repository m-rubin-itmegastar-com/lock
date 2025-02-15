<?php

namespace m_rubin_itmegastar_com\lock\mutex;

use m_rubin_itmegastar_com\lock\exception\LockAcquireException;
use m_rubin_itmegastar_com\lock\exception\TimeoutException;

class MySQLMutex extends LockMutex
{
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $name;
    /**
     * @var int
     */
    private $timeout;

    public function __construct(\PDO $PDO, string $name, int $timeout = 0)
    {
        $this->pdo = $PDO;

        if (\strlen($name) > 64) {
            throw new \InvalidArgumentException("The maximum length of the lock name is 64 characters.");
        }

        $this->name = $name;
        $this->timeout = $timeout;
    }

    /**
     * @throws LockAcquireException
     */
    public function lock(): void
    {
        $statement = $this->pdo->prepare("SELECT GET_LOCK(?,?)");

        $statement->execute([
            $this->name,
            $this->timeout,
        ]);

        $statement->setFetchMode(\PDO::FETCH_NUM);
        $row = $statement->fetch();

        if ($row[0] == 1) {
            /*
             * Returns 1 if the lock was obtained successfully.
             */
            return;
        }

        if ($row[0] === null) {
            /*
             *  NULL if an error occurred (such as running out of memory or the thread was killed with mysqladmin kill).
             */
            throw new LockAcquireException("An error occurred while acquiring the lock");
        }

        throw TimeoutException::create($this->timeout);
    }

    public function unlock(): void
    {
        $statement = $this->pdo->prepare("DO RELEASE_LOCK(?)");
        $statement->execute([
            $this->name
        ]);
    }
}
