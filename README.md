# Vkontakte Bot

Как создать простого бота:

1. Склонировать проект: `composer create-project wapmorgan/vkontakte-bot`.
2. Отредактировать `config.yaml` в соответствии с комментариями.
3. Открыть файл `bot-initialization.php` и изменить / добавить обработчики на основные события.
4. Запустить `sudo bin/bot-laucnher`.

## События
| Событие | Описание | Передаваемые данные |
|---------|----------|---------------------|
`Bot::NEW_UNREAD_INBOX_MESSAGE_EVENT` |Событие о приходе нового непрочитанного сообщения | `Message` |
`Bot::UNREAD_HISTORY_MESSAGE_EVENT` |Событие о наличии в истории сообщений непрочитанного сообщения | `Message` |
`Bot::USER_TYPING_IN_DIALOG_EVENT` |Событие о том, что пользователь в диалоге начал печатать сообщение | `TypingInDialog` |
