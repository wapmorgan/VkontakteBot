<?php
namespace wapmorgan\VkontakteBot;

use Exception;
use stdClass;
use Symfony\Component\Yaml\Yaml;
use wapmorgan\SystemDaemon\AbstractDaemon;
use wapmorgan\Threadable\WorkersPool;

class Bot extends AbstractDaemon
{
    const VK_EVENT = 1;

    const NEW_MESSAGE_EVENT = 2;
    const MESSAGE_EDIT_EVENT = 4;

    const UNREAD_HISTORY_MESSAGE_EVENT = 3;

    /**
     * @var string Расположение файла с yaml-конфигом
     */
    public $configFile = 'config.yaml';

    /**
     * @var string Версия API ВК
     */
    public $apiVersion = '5.68';

    /**
     * @var string Имя lps-файла
     */
    public $lpsFilename = '{name}-lps';

    /**
     * @var string Полный путь до lps-файла
     */
    protected $lpsPath = false;

    /**
     * @var array Загруженный конфиг
     */
    protected $config = false;

    /**
     * @var VkApi Объект соединения ВК
     */
    protected $vk = false;

    /**
     * @var WorkersPool Пул обработчиков
     */
    protected $workers = false;

    /**
     * @var array
     */
    protected $eventListeners = [];

    /**
     * Создаёт объект и переопределяет некоторые параметры бота
     * @throws Exception
     */
    public function __construct(array $config = [])
    {
        parent::__construct(AbstractDaemon::NORMAL);
        foreach ($config as $param => $value)
            $this->{$param} = $value;
    }

    /**
     * @throws Exception
     */
    public function onStart()
    {
        $this->init();
        $this->start();
    }

    /**
     * Загружает конфигурацию бота и открывает соединение с ВК
     * @throws Exception
     */
    public function init()
    {
        if (!file_exists($configPath = $this->configFile))
            throw new Exception('Invalid config path: '.$this->configFile);

        $this->config = Yaml::parseFile($configPath);

        $this->vk = new VkApi($this->config->api->application->id,
            $this->config->api->application->key,
            $this->config->api->community_key);
        $this->vk->setApiVersion($this->apiVersion);
        $this->vk->setCommonParameters([
            'lang' => 'ru',
        ]);

        $this->registerEventListener(self::UNREAD_HISTORY_MESSAGE_EVENT, [$this, 'handleUnreadHistoryMessageEvent']);
        $this->registerEventListener(self::NEW_MESSAGE_EVENT, [$this, 'handleNewMessageEvent']);
    }

    /**
     * Запускает бота
     * @throws VkException
     * @throws Exception
     */
    public function start()
    {
        if ($this->config->bot->work_mode === 'threaded') {
            // создаем обработчика сообщений
            $this->workers = new WorkersPool(__NAMESPACE__.'\\MessageHandlerWorker');
            $this->workers->setPoolSize($this->config->bot->threads->count);
            $this->workers->enableDataOverhead();
        }

        // получаем ранее непрочитанные сообщения пользователей
        $this->processHistory();
        // начинаем обработку поступающих сообщений
        $this->connectToStream();

        // после выхода из этой функции останавливаем бота (и все порожденные процессы)
        if ($this->config->bot->work_mode === 'threaded') {
            $this->workers->waitToFinish();
        }
    }

    /**
     * Обрабатывает непрочитанные сообщения, пришедшие до запуска бота
     * @throws VkException
     */
    protected function processHistory()
    {
        $this->log(self::DEBUG, 'Получение истории сообщений');
        $dialogs = $this->vk->api('messages.getDialogs', [
            'unread' => 1,
        ]);

        foreach ($dialogs->items as $dialog) {
            $this->log(self::DEBUG, 'Получение непрочитанных сообщений диалога (user = '.$dialog->message->user_id.', количество непрочитанных - '.$dialog->unread.')');
            $n_unread_messages = $dialog->unread;

            $offset = 0;
            while ($n_unread_messages > 0) {
                $dialog_unread_messages = $this->vk->api('messages.getHistory', [
                    'user_id' => $dialog->message->user_id,
                    'count' => min(VkApi::MAX_MESSAGES_COUNT_IN_DIALOG_QUERY, $n_unread_messages),
                    'offset' => $offset,
                ]);
                $this->log(self::DEBUG, 'Чтение очередной порции сообщений из диалога '.$dialog->message->user_id.' (со смещения '.$offset.') -> получено непрочитанных - '.$dialog_unread_messages->unread);

                foreach ($dialog_unread_messages->items as $dialog_unread_message) {
                    $this->raiseEvent(self::UNREAD_HISTORY_MESSAGE_EVENT, $this->createMessageFromDialogHistory($dialog_unread_message));
                }

                $n_unread_messages -= $dialog_unread_messages->unread;
                $offset += VkApi::MAX_MESSAGES_COUNT_IN_DIALOG_QUERY;
            }

        }
        $this->log(self::INFO, print_r($dialogs, true));
    }

    /**
     * Соединяется с LongPoll-сервером ВКонтакте и начинает принимать поступающие сообщения от пользователей.
     * При необходимости переподсоединяется к новому серверу или обновляет настройки подключения.
     * @throws VkException
     */
    protected function connectToStream()
    {
        while ($this->running) {
            $current_lps = $this->getWorkingLps();
            $lps_answer = $this->vk->connectToLpsAndGetUpdates($current_lps ? $current_lps->server : null, $current_lps ? $current_lps->key : null, $current_lps ? $current_lps->ts : null);
            if ($current_lps === false) {
                $this->log(self::DEBUG, 'Соединение с новым LP-сервером (' . print_r($lps_answer['lps'], true));
                $this->changeWorkingLps($lps_answer['lps']);
            } else {
                if (!isset($lps_answer['lps']->server)) {
                    $this->log(self::DEBUG, 'Обновление параметров LP-сервера (' . print_r($lps_answer['lps'], true) . ')');
                    $this->changeWorkingLps($lps_answer['lps']);
                } else {
                    $this->log(self::DEBUG, 'Смена LP-сервера (' . print_r($lps_answer['lps'], true) . ')');
                    $this->changeWorkingLps($lps_answer['lps']);
                }
            }

            foreach ($lps_answer['updates'] as $update) {
                switch ($update[0]) {
                    case VkApi::NEW_MESSAGE_ADDED_CODE:
                        $this->raiseEvent(self::NEW_MESSAGE_EVENT, Message::createFromLongPollEvent($update));
                        break;

                    case VkApi::MESSAGE_EDITED_CODE:
                        $this->raiseEvent(self::MESSAGE_EDIT_EVENT, );
                        break;

                    case VkApi::FRIEND_BECAME_ONLINE:
                        break;

                    case VkApi::FRIEND_BECAME_OFFLINE:
                        break;

                    case VkApi::USER_TYPING_IN_DIALOG:
                        break;

                    case VkApi::USER_TYPING_IN_CHAT:
                        break;

                    default:
                        break;
                }


            }
        }
    }

    /**
     * @param $userId
     * @param array $attachments
     * @return bool
     * @throws VkException
     */
    protected function validateMessageAndQueue($userId, array $attachments)
    {
        // Если есть изображения, отправляем в очередь
        foreach ($attachments as $attachment) {
            if ($attachment->type == 'photo') {
                $this->database->addImage($userId, $attachment->id);
                $this->vk->api('messages.send', [
                    'user_id' => $userId,
                    'peer_id' => $userId,
                    'message' => 'Ваша фотография поставлена в очередь на выполнение. Ожидайте.',
                ]);
                $this->log(self::INFO, 'Добавлено изображение '.$attachment->id.' от пользователя ' . $userId);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $eventType
     * @param $eventData
     * @return bool
     */
    protected function raiseEvent($eventType, $eventData)
    {
        if (!isset($this->eventListeners[$eventType]))
            return true;

        $event = new Event($eventType, $eventData);

        foreach ($this->eventListeners[$eventType] as $eventListener) {
            $result = call_user_func($eventListener, $event);
            if ($result === false)
                return false;
            else if ($result === true)
                return true;
        }

        return true;
    }

    /**
     * @param $eventType
     * @param $eventListener
     */
    protected function registerEventListener($eventType, $eventListener)
    {
        $this->eventListeners[$eventType][] = $eventListener;
    }

    /**
     * @param $dialog_unread_message
     */
    private function createMessageFromDialogHistory(array $dialog_unread_message)
    {
        var_dump($dialog_unread_message);
    }

    /**
     * Возвращает последний отмеченный как рабочий LP-сервер
     * @return stdClass|false
     */
    public function getWorkingLps()
    {
        if (!file_exists($this->getLpsPath()))
            return false;

        return json_decode(file_get_contents($this->getLpsPath()));
    }

    /**
     * @param stdclass $lps Объект с одним или несколькими свойствами: key, ts
     * @return mixed
     */
    public function changeWorkingLps(stdclass $lpsChange)
    {
        $lps = $this->getWorkingLps();

        if (isset($lpsChange->key))
            $lps['key'] = $lpsChange->key;
        if (isset($lps->ts))
            $lps['ts'] = $lpsChange->ts;

        $lps_encoded = json_encode($lps);

        return file_put_contents($this->getLpsPath(), $lps_encoded) === strlen($lps_encoded);
    }

    /**
     * @return string
     */
    protected function getLpsPath()
    {
        if ($this->lpsPath === null) {
            $this->lpsPath = sys_get_temp_dir().'/'.strtr($this->lpsFilename, [
                '{name}' => $this->name,
            ]);
        }
        return $this->lpsPath;
    }

    /**
     * @throws VkException
     */
    protected function handleNewMessageEvent($eventType, Message $message)
    {
        // Помечаем прочитанным
        $this->vk->api('messages.markAsRead', [
            'peer_id' => $update[3],
            'start_message_id' => $update[1],
        ]);

        // Работает только с теми сообщениями, в которых если прикрепленные файлы
        if (!isset($update[6])) {
            continue;
        }

        // Преобразуем список вложений в массив объектов
        $attachments = [];
        foreach ($update[6] as $field => $value) {
            if (fnmatch('attach*_type', $field)) {
                $attachments[] = (object)[
                    'type' => $value,
                    'id' => $update[6]->{'attach' . substr(strstr($field, '_', true), 6)},
                ];
            }
        }
    }
}
