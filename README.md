# Vkontakte Bot

## Быстрый старт
Как создать простого бота для сообщений сообщества:

1. Склонировать проект: `composer create-project wapmorgan/vkontakte-bot dev-master`.
2. Скопировать `config.yaml.sample` в `config.yaml` и отредактировать в соответствии с комментариями (внести данные приложения и сообщества)
3. Открыть файл `bin/bot-initialization.php`. Добавить/изменить обработчики событий:

    - Обработчик любого события регистрируется методом `registerEventListener()` объекта `Bot`, например:
    ```php
    $daemon->registerEventListener(Bot::UNREAD_HISTORY_MESSAGE_EVENT, $historyMessageListener);
    ```
    
    - Например, для обработки входящих сообщений, присланных ДО запуска бота, подойдет такой обработчик:
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

4. Запустить `sudo bin/bot-launcher` (режим работы бота в реальном времени; остановка по Ctrl+C) или `sudo bin/bot-daemon start` (для запуска бота в качестве демона; остановка по `sudo bin/bot-daemon stop`).

## Конфигурация
Основные настройки бота хранятся в файле `config.yaml` в корне проекта.

- `api` (настройки VK Api)
  - `community-key` - сервисный ключ вашего сообщества (добавляется в настройках сообщества - https://vk.com/publicXXX?act=tokens)
  - `application` (настройки приложения - https://vk.com/editapp?id=XXX&section=options)
    - `id` - ID приложения
    - `key` - ключ доступа приложения
    - `service_key` - сервисный ключ приложения

  - `language` - язык, в котором должны приходить имена пользователей и другая информация. (возможные значения на https://vk.com/dev/api_requests?f=2.%20%D0%9E%D0%B1%D1%89%D0%B8%D0%B5%20%D0%BF%D0%B0%D1%80%D0%B0%D0%BC%D0%B5%D1%82%D1%80%D1%8B)

   - `lps-timeout` - тайм-аут для запросов к LP-серверу. Обычно не требует изменения.

- `bot` (настройки самого бота)
  - `work_mode` - режим работы: **simple** (в одном потоке) или **threaded** (в нескольких потоках)
  - `threads` (настройки для мультипоточного режима)
    - `count` - количество запускаемых потоков

## Модель событий

В зарегистрированный обработчик при наступлении события передаётся объект типа `wapmorgan\VkontakteBot\Event`, который имеет следующие полезные методы:

- `Bot getBot()` - возвращает экземпляр бота. Через этот объект можно взаимодействовать с API.
- `object getEventData()` - возвращает объект данных события. В зависимости от типа события могут передаваться объект сообщения, объект уведомления и так далее.

Объект `Bot` имеет два полезных метода:
- `log($level, string $message)` - логгирует сообщение на терминал либо в файл (в зависимости от того, в каком режиме бот был запущен).
- `getApi()` - возвращает объект `VkApi`, который имеет метод `api($method, $parameters = array(), $format = 'array', $requestMethod = 'get')` и позволяет взаимодействовать с API VK. 

### Все события
| Событие | Описание | Передаваемые данные |
|---------|----------|---------------------|
`Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT` | Событие о приходе нового непрочитанного сообщения | `Message` |
`Bot::UNREAD_HISTORY_MESSAGE_EVENT` | Событие о наличии в истории сообщений непрочитанного сообщения | `Message` |
`Bot::USER_TYPING_IN_DIALOG_EVENT` | Событие о том, что пользователь в диалоге начал печатать сообщение | `DialogTyping` |
`Bot::FRIEND_BECAME_ONLINE_EVENT` | Событие о том, что друг появился в сети | `FriendOnlineStatus` |
`Bot::FRIEND_BECAME_OFFLINE_EVENT` | Событие о том, что друг вышел | `FriendOfflineStatus` |

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

### DialogTyping

Объект `wapmorgan\VkontakteBot\DialogTyping` имеет следующие полезные поля:
- `$userId` - id пользователя
- `$flags` - флаги
