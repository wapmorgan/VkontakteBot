<?php
namespace wapmorgan\VkontakteBot;

use wapmorgan\Threadable\Worker;

class EventHandlerWorker extends Worker {
    /** @var Bot */
    public $bot;

    /**
     * Payload handler
     * @param array $payload EventType and EventData
     * @return bool|void
     */
    public function onPayload($payload)
    {
        $this->bot->log(Bot::INFO, 'Got event '.print_r($payload, true));
        $this->bot->raiseEvent($payload[0], $payload[1]);
    }
}