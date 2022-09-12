<?php

namespace Nexus\Database;

use Illuminate\Cache\LuaScripts;
use Illuminate\Cache\RedisLock;

class NexusLock extends RedisLock
{

    /**
     * @var \Redis
     */
    protected $redis;
    /**
     * NexusLock constructor.
     * @param string $name
     * @param int $seconds
     * @param null $owner
     */
    public function __construct($name, $seconds, $owner = null)
    {
        parent::__construct(NexusDB::redis(), $name, $seconds, $owner);
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        if ($this->seconds > 0) {
            return $this->redis->set($this->name, $this->owner, ['nx', 'ex' => $this->seconds]) == true;
        } else {
            return $this->redis->setnx($this->name, $this->owner) == true;
        }
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        return (bool) $this->redis->eval(LuaScripts::releaseLock(), [$this->name, $this->owner], 1);
    }

}
