<?php

class MessageIdGenerator
{

    private $prev_timestamp; //Increment $increment if current time equals to this.
    private $increment; //If multiple messages arrive at same 10ms then increment this.
    private $server_id;
    private $epoch; //custom time from where the time is calculated(in unix time from GMT).
    private $time_zone_epoch; //no.of 1ms from GMT.
    private $max_server_id;
    private $max_increment;
    private $max_timestamp;

    public function __construct()
    {
        $this->server_id = 123; //generated during server startup.
        $this->epoch = 1577836800 * 1000; //1ms precision. Jan 1 2020.
        $this->time_zone_epoch = 9000 * 1000; //1ms precision.
        $this->increment = 0;
        $this->max_timestamp = 2 ** 42;
        $this->max_server_id = 512; //9 bits
        $this->max_increment = 4096; //12 bits
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
     * @param int $increment
     * @return bool
     */
    public function setIncrement($increment): bool
    {
        if ($increment < $this->max_increment) {
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
        if ($server_id >= $this->max_server_id) {
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
     * @return int
     */
    public function getTimeZoneEpoch(): int
    {
        return $this->time_zone_epoch;
    }

    /**
     * @param int $time_zone_epoch
     */
    public function setTimeZoneEpoch(int $time_zone_epoch): void
    {
        $this->time_zone_epoch = $time_zone_epoch;
    }

    /**
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
        if ($unix_time_stamp_milli - ($this->getEpoch() + $this->getTimeZoneEpoch()) < $this->max_timestamp) {
            $message_id = ($unix_time_stamp_milli - ($this->getEpoch() + $this->getTimeZoneEpoch())) << 9;
            $message_id = ($message_id + $this->getServerId()) << 12;
            $message_id += $this->getIncrement();
            return $message_id;
        }
        return false;
    }
}