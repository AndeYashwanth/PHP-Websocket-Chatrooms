<?php

class MessageIdGenerator
{

    private $prev_timestamp; //Increment $increment if current time equals to this.
    private $increment; //If multiple messages arrive at same 10ms then increment this.
    private $server_id; //should be generated during server startup.
    private $epoch; //custom time in ms from where the time is calculated(from GMT).
    const MAX_SERVER_ID = 2**12; //12 bits
    const MAX_INCREMENT = 2**9; //9 bits
    const MAX_TIMESTAMP = 2 ** 42; //42 bits

    public function __construct()
    {
        $this->server_id = 123; //generated during server startup.
        $this->epoch = 1577836800 * 1000; //1ms precision. Since Jan 1 2020.
        $this->increment = 0;
    }

    /**
     * @return mixed
     */
    public function getPrevTimestamp()
    {
        return $this->prev_timestamp;
    }

    /**
     * @param mixed $prev_timestamp
     */
    public function setPrevTimestamp($prev_timestamp): void
    {
        $this->prev_timestamp = $prev_timestamp;
    }

    /**
     * @return mixed
     */
    public function getIncrement()
    {
        return $this->increment;
    }

    /**
     * Returns false if increment by 1 exceeds MAX_INCREMENT.
     * @param int $increment
     * @return bool
     */
    public function setIncrement($increment): bool
    {
        if ($increment < self::MAX_INCREMENT) {
            $this->increment = $increment;
            return true;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getServerId(): int
    {
        return $this->server_id;
    }

    /**
     * @param int $server_id
     * @return bool
     */
    public function setServerId(int $server_id): bool
    {
        if ($server_id < self::MAX_SERVER_ID) {
            $this->server_id = $server_id;
            return true;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getEpoch(): int
    {
        return $this->epoch;
    }

    /**
     * @param int $epoch
     */
    public function setEpoch(int $epoch): void
    {
        $this->epoch = $epoch;
    }


    /**
     * Generate 64bit message id.
     * @param int $unix_time_stamp_milli
     * @return bool|int
     */
    public function generateMessageId(int $unix_time_stamp_milli)
    {
        if ($unix_time_stamp_milli === $this->getPrevTimestamp()) {
            if (!$this->setIncrement($this->getIncrement() + 1))
                return false;
        } else {
            $this->setIncrement(1);
        }
        $this->setPrevTimestamp($unix_time_stamp_milli);
        if ($unix_time_stamp_milli - $this->getEpoch() < self::MAX_TIMESTAMP) {
            $message_id = ($unix_time_stamp_milli - $this->getEpoch()) << 12; //give 11 bits space for server id.
            $message_id = ($message_id + $this->getServerId()) << 9; //give 10 bit space for increment.
            $message_id += $this->getIncrement();
            return $message_id;
        }
        return false;
    }
}