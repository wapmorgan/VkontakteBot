<?php
namespace wapmorgan\VkontakteBot;


class Event
{
    /**
     * @var mixed
     */
    protected $eventType;

    /**
     * @var mixed
     */
    protected $eventData;

    /**
     * Event constructor.
     * @param $eventType
     * @param $eventData
     */
    public function __construct($eventType, $eventData)
    {
        $this->eventType = $eventType;
        $this->eventData = $eventData;
    }

    /**
     * @return mixed
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * @return mixed
     */
    public function getEventData()
    {
        return $this->eventData;
    }
}