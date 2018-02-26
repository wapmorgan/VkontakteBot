<?php

use wapmorgan\VkontakteBot\Bot;
use wapmorgan\VkontakteBot\Event;
use wapmorgan\VkontakteBot\Message;

$daemon = new Bot([
    'configFile' => dirname(__DIR__).'/config.yaml',
    'name' => 'vkontakte-bot',
    'fullname' => 'Test vkontakte bot',
]);
$daemon->setLogger(Bot::FILES);

$daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, function (Event $event) {
    /** @var Message $message */
    $message = $event->getEventData();

    $event->getBot()->getApi()->api('messages.send', [
        'user_id' => $message->peerId,
        'peer_id' => $message->peerId,
        'message' => 'Сообщение из истории: ' . strrev($message->text)
    ]);
});

$daemon->registerEventListener(Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT, function (Event $event) {
    /** @var Message $message */
    $message = $event->getEventData();

    $event->getBot()->getApi()->api('messages.send', [
        'user_id' => $message->peerId,
        'peer_id' => $message->peerId,
        'message' => 'Новое сообщение: ' . strrev($message->text)
    ]);
});

return $daemon;
