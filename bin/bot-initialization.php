<?php

use wapmorgan\VkontakteBot\Bot;
use wapmorgan\VkontakteBot\Event;
use wapmorgan\VkontakteBot\Message;
use wapmorgan\VkontakteBot\TypingInDialog;

$daemon = new Bot([
    'configFile' => dirname(__DIR__).'/config.yaml',
    'name' => 'vkontakte-bot',
    'fullname' => 'Test vkontakte bot',
]);
$daemon->setLogger(Bot::FILES);

class SimpleBot {
    public function onUnreadMessageEvent(Event $event) {
        return $this->onMessage($event->getBot(), $event->getEventData());
    }

    public function onMessage(Bot $bot, Message $message) {
        $bot->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => mb_strtoupper($message->text)
        ]);
    }
}

$bot = new SimpleBot();

$daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, [$bot, 'onUnreadMessageEvent']);
$daemon->registerEventListener(Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT, [$bot, 'onUnreadMessageEvent']);

return $daemon;
