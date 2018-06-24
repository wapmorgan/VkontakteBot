<?php
use wapmorgan\VkontakteBot\Bot;
use wapmorgan\VkontakteBot\Event;
use wapmorgan\VkontakteBot\FriendOfflineStatus;
use wapmorgan\VkontakteBot\FriendOnlineStatus;
use wapmorgan\VkontakteBot\Message;
use wapmorgan\VkontakteBot\DialogTyping;
use wapmorgan\VkontakteBot\VkException;

try {
    $daemon = new Bot([
        'configFile' => dirname(__DIR__) . '/config.yaml',
        'name' => 'vkontakte-bot',
        'fullname' => 'Test vkontakte bot',
    ]);
} catch (Exception $e) {
    fwrite(STDERR, 'Невозможно запустить бота: '.$e->getMessage().PHP_EOL);
    fwrite(STDERR, 'файл '.$e->getFile().' ('.$e->getLine().')'.PHP_EOL);
    exit(1);
}

class SimpleBot {
    /**
     * @param Event $event
     * @throws VkException
     */
    public function onUnreadMessageEvent(Event $event)
    {
        $this->onMessage($event->getBot(), $event->getEventData());
    }

    /**
     * @param Event $event
     * @throws VkException
     */
    public function onDialogTypingEvent(Event $event)
    {
        /** @var DialogTyping $typing_in_dialog */
        $typing_in_dialog = $event->getEventData();
        $event->getBot()->getApi()->api('messages.send', [
            'user_id' => $typing_in_dialog->userId,
            'peer_id' => $typing_in_dialog->userId,
            'message' => '...',
        ]);
    }

    /**
     * @param Bot $bot
     * @param Message $message
     * @throws VkException
     */
    public function onMessage(Bot $bot, Message $message)
    {
        $bot->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => 'засыпаю на 10 секунд ...',
        ]);

        sleep(10);

        $bot->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => mb_strtoupper($message->text)
        ]);
    }
}

$bot = new SimpleBot();

$daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, [$bot, 'onUnreadMessageEvent']);
$daemon->registerEventListener(Bot::NEW_MESSAGE_RECEIVED_EVENT, [$bot, 'onUnreadMessageEvent']);
$daemon->registerEventListener(Bot::USER_TYPING_IN_DIALOG_EVENT, [$bot, 'onDialogTypingEvent']);

$daemon->registerEventListener(Bot::FRIEND_BECAME_ONLINE_EVENT, function (Event $event) {
    /** @var FriendOnlineStatus $online_status */
    $online_status = $event->getEventData();
    $event->getBot()->log(Bot::DEBUG, 'Онлайн: '.$online_status->userId);
});

$daemon->registerEventListener(Bot::FRIEND_BECAME_OFFLINE_EVENT, function (Event $event) {
    /** @var FriendOfflineStatus $offline_status */
    $offline_status = $event->getEventData();
    $event->getBot()->log(Bot::DEBUG, 'Оффлайн: '.$offline_status->userId);
});

return $daemon;
