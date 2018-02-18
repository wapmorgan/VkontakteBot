<?php
namespace wapmorgan\VkontakteBot;

use wapmorgan\Threadable\Worker;

class EventHandlerWorker extends Worker {
    public function onPayload(array $payload)
    {
        var_dump($payload);
    }
}