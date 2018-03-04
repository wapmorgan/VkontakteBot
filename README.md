# Vkontakte Bot

Как создать простого бота для сообщений сообщества:

1. Склонировать проект: `composer create-project wapmorgan/vkontakte-bot`.
2. Отредактировать `config.yaml` в соответствии с комментариями (внести данные приложения и сообщества)
3. Открыть файл `bot-initialization.php`. Добавить/изменить обработчики событий:
    - Обработчик любого события регистрируется методов `registerEventListener()` объекта `Bot`, например:
    ```php
    $daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, $historyMessageListener);
    ```

    - В зарегистрированный обработчик при наступлении события передаётся объект типа `wapmorgan\VkontakteBot\Event`, который имеет следующие полезные методы:
        - `Bot getBot()` - возвращает экземпляр бота. Через этот объект можно взаимодействовать с API.
        - `object getEventData()` - возвращает объект данные события. В зависимости от типа события могут передаваться:
            - объект `wapmorgan\VkontakteBot\Message` - объект сообщения.
            - объект `wapmorgan\VkontakteBot\TypingInDialog` - объект уведомления, что пользователь начал что-то писать в диалоге.

    - То есть для обработки входящих сообщений, присланных ДО запуска бота, подойдет такой обработчик:

    ```php
    $historyMessageListener = function (Event $event) {
        // Получаем объект непрочитанного сообщения со всеми данными
        /** @var Message $message */
        $message = $event->getEventData();

        // Взаимодействуем с API: отправляем сообщение назад, изменив регистр всех букв на ВЕРХНИЙ
        $event->getBot()->getApi()->api('messages.send', [
            'user_id' => $message->peerId,
            'peer_id' => $message->peerId,
            'message' => 'Сообщение из истории: ' . mb_strtoupper($message->text)
        ]);
    }

    $daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, $historyMessageListener);
    ```

4. Запустить `sudo bin/bot-laucnher` (режим работы бота в реальном времени) или `sudo bin/bot-daemon start` (для запуска бота в качестве демона).

## События
| Событие | Описание | Передаваемые данные |
|---------|----------|---------------------|
`Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT` | Событие о приходе нового непрочитанного сообщения | `Message` |
`Bot::UNREAD_HISTORY_MESSAGE_EVENT` | Событие о наличии в истории сообщений непрочитанного сообщения | `Message` |
`Bot::USER_TYPING_IN_DIALOG_EVENT` | Событие о том, что пользователь в диалоге начал печатать сообщение | `TypingInDialog` |

## Данные событий

### Message

Объект `wapmorgan\VkontakteBot\Message` имеет следующие полезные поля:
- `$messageId` - id сообщения
- `$flags` - флаги
- `$peerId` - id отправителя
- `$timestamp` - дата отправки, в формате unix timestamp
- `$text` - текст сообщения
- `$attachments = []` - список прикрепленных файлов или объектов

Свойствой `$flags` - это побитовое ИЛИ установленных для сообщения флагов из числа всех возможных:

- `Message::UNREAD` - непрочитанное сообщение
- `Message::OUTBOX` - исходящее сообщение
- `Message::REPLIED`
- `Message::IMPORTANT` - важное сообщение
- `Message::CHAT` - сообщение в чате
- `Message::FRIENDS`
- `Message::SPAM` - помечено как спам
- `Message::DELETED`
- `Message::FIXED`
- `Message::MEDIA`
- `Message::HIDDEN`
- `Message::DELETED_FOR_ALL`

То есть, чтобы проверить, непрочитанно ли сообщение, подойдет конструкция `if ($message->flags & Message::UNREAD)`.

### TypingInDialog

Объект `wapmorgan\VkontakteBot\TypingInDialog` имеет следующие полезные поля:
- `$userId` - id пользователя
- `$flags` - флаги
