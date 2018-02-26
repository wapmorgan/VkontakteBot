# Vkontakte Bot

Как создать простого бота:

1. Скопировать `config.yaml.sample` в `config.yaml` и отредактировать
2. Открыть файл `bin/bot` и добавить обработчик на непрочитанные сообщения из истории и приход новых сообщений:

    ```php
    #!/usr/bin/env php
    <?php
    
    use wapmorgan\VkontakteBot\Bot;
    use wapmorgan\SystemDaemon\DaemonManager;
    use wapmorgan\VkontakteBot\Event;
    use wapmorgan\VkontakteBot\Message;
    
    require_once __DIR__.'/../vendor/autoload.php';
    
    $daemon = new Bot([
        'configFile' => dirname(__DIR__).'/config.yaml',
        'name' => 'vkontakte-bot',
        'fullname' => 'Test vkontakte bot',
    ]);
    $daemon->setLogger(Bot::FILES);
    
    // Обработчик непрочитанных сообщений из истории
    $daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, function (Event $event) {
        /** @var Message $message */
        $message = $event->getEventData();
    
        $event->getBot()->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => 'Сообщение из истории: ' . strrev($message->text)
        ]);
    });
    
    // Обработчик новых сообщений
    $daemon->registerEventListener(Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT, function (Event $event) {
        /** @var Message $message */
        $message = $event->getEventData();
    
        $event->getBot()->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => 'Новое сообщение: ' . strrev($message->text)
        ]);
    });
    
    (new DaemonManager($daemon))->handleConsole($argc, $argv);
    ```

3. Запустить `sudo bin/bot start`

## События
- `Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT` - событие о приходе нового непрочитанного сообщения
- `Bot::UNREAD_HISTORY_MESSAGE_EVENT` - событие о наличии в истории сообщений непрочитанного сообщения
- `Bot::USER_TYPING_IN_DIALOG_EVENT` - событие о том, что пользователь в диалоге начал печатать сообщение
