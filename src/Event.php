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
     * @var Bot
     */
    protected $bot;

    /**
     * Event constructor.
     * @param $eventType
     * @param $eventData
     * @param Bot $bot
     */
    public function __construct($eventType, $eventData, Bot $bot)
    {
        $this->eventType = $eventType;
        $this->eventData = $eventData;
        $this->bot = $bot;
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

    /**
     * @return Bot
     */
    public function getBot()
    {
        return $this->bot;
    }
}