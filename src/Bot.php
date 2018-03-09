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

//    const NEW_MESSAGE_EVENT = 2;
    const NEW_OUTBOX_MESSAGE_EVENT = 3;
    const NEW_INBOX_MESSAGE_EVENT = 4;
    const NEW_UNREAD_INBOX_MESSAGE_EVENT = 5;

    const MESSAGE_EDIT_EVENT = 10;

    const UNREAD_HISTORY_MESSAGE_EVENT = 20;

    const USER_TYPING_IN_DIALOG_EVENT = 30;

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
    protected $lpsPath;

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

    /** @var bool  */
    public $running = true;

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
     * @return VkApi
     */
    public function getApi()
    {
        return $this->vk;
    }

    /**
     * @throws Exception
     */
    public function onStart()
    {
        $this->init();
        $this->mainLoop();
    }

    /**
     * Загружает конфигурацию бота и открывает соединение с ВК
     * @throws Exception
     */
    public function init()
    {
        if (!file_exists($configPath = $this->configFile))
            throw new Exception('Invalid config path: '.$this->configFile);

        $this->config = (array)Yaml::parseFile($configPath);

        $this->vk = new VkApi($this->config['api']['application']['id'],
            $this->config['api']['application']['key'],
            $this->config['api']['community-key']);
        $this->vk->setApiVersion($this->apiVersion);
        $this->vk->setCommonParameters([
            'lang' => $this->config['api']['language'],
        ]);
        $this->vk->defaultLpsParams['wait'] = $this->config['api']['lps-timeout'];
    }

    /**
     * @throws Exception
     * @throws VkException
     */
    protected function mainLoop()
    {
        if ($this->config['bot']['work_mode'] === 'threaded') {
            // создаем обработчика сообщений
            $worker = new EventHandlerWorker();
            $worker->bot = $this;

            $this->workers = new WorkersPool($worker);
            $this->workers->setPoolSize($this->config['bot']['threads']['count']);
            $this->workers->enableDataOverhead();

            $this->log(self::DEBUG, 'Запуск потоковых обработчиков ('.$this->config['bot']['threads']['count'].')');
        }

        // получаем ранее непрочитанные сообщения пользователей
        $this->processHistory();
        // начинаем обработку поступающих сообщений
        $this->connectToStream();

        $this->log(self::DEBUG, 'Завершение работы');

        unlink($this->getLpsPath());
        // после выхода из этой функции останавливаем бота (и все порожденные процессы)
        if ($this->config['bot']['work_mode'] === 'threaded') {
            $this->workers->waitToFinish();
        }
    }

    /**
     * Обрабатывает непрочитанные сообщения, пришедшие до запуска бота
     * @throws VkException
     * @throws Exception
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
                    if ($dialog_unread_message->out == 0
                        && $dialog_unread_message->read_state == 0) {
                        $n_unread_messages--;


                        $this->handleEvent(self::UNREAD_HISTORY_MESSAGE_EVENT, Message::createFromDialogHistory($dialog_unread_message));
                    }
                }
                $offset += VkApi::MAX_MESSAGES_COUNT_IN_DIALOG_QUERY;
            }

        }
    }

    /**
     * Соединяется с LongPoll-сервером ВКонтакте и начинает принимать поступающие сообщения от пользователей.
     * При необходимости переподсоединяется к новому серверу или обновляет настройки подключения.
     * @throws VkException
     * @throws Exception
     */
    protected function connectToStream()
    {
        while ($this->running) {
            $current_lps = $this->getWorkingLps();
            $lps_answer = $this->vk->connectToLpsAndGetUpdates($current_lps ? $current_lps->server : null, $current_lps ? $current_lps->key : null, $current_lps ? $current_lps->ts : null);

            if ($current_lps === false) {
                $this->log(self::DEBUG, 'Соединение с новым LP-сервером (' . print_r($lps_answer['lps'], true));
                $this->changeWorkingLps($lps_answer['lps']);
            } else if (!isset($lps_answer['lps']->server)) {
//                $this->log(self::DEBUG, 'Обновление параметров LP-сервера (' . print_r($lps_answer['lps'], true) . ')');
                $this->changeWorkingLps($lps_answer['lps']);
            } else {
//                $this->log(self::DEBUG, 'Смена LP-сервера (' . print_r($lps_answer['lps'], true) . ')');
                $this->changeWorkingLps($lps_answer['lps']);
            }

            foreach ($lps_answer['updates'] as $update) {
                switch ($update[0]) {
                    case VkApi::NEW_MESSAGE_ADDED_CODE:
                        $message = Message::createFromLongPollEvent($update);

                        if ($message->flags & Message::OUTBOX)
                            $this->handleEvent(self::NEW_OUTBOX_MESSAGE_EVENT, $message);
                        else {
                            if ($message->flags & Message::UNREAD)
                                $this->handleEvent(self::NEW_UNREAD_INBOX_MESSAGE_EVENT, $message);
                            else
                                $this->handleEvent(self::NEW_INBOX_MESSAGE_EVENT, $message);
                        }
                        break;

                    case VkApi::MESSAGE_EDITED_CODE:
                        break;

                    case VkApi::FRIEND_BECAME_ONLINE:
                        break;

                    case VkApi::FRIEND_BECAME_OFFLINE:
                        break;

                    case VkApi::USER_TYPING_IN_DIALOG:
                        $this->handleEvent(self::USER_TYPING_IN_DIALOG_EVENT, TypingInDialog::createFromLongPollEvent($update));
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
     * @param $eventType
     * @param $eventData
     * @throws Exception
     */
    protected function handleEvent($eventType, $eventData)
    {
        if ($this->config['bot']['work_mode'] === 'threaded') {
            $this->workers->sendData([$eventType, $eventData]);
        } else {
            $this->raiseEvent($eventType, $eventData);
        }
    }

    /**
     * @param $eventType
     * @param $eventData
     * @return bool
     */
    public function raiseEvent($eventType, $eventData)
    {
        if (!isset($this->eventListeners[$eventType]))
            return true;

        $event = new Event($eventType, $eventData, $this);

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
    public function registerEventListener($eventType, $eventListener)
    {
        $this->eventListeners[$eventType][] = $eventListener;
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
     * @param stdClass $lpsChange
     * @return mixed
     */
    public function changeWorkingLps(stdclass $lpsChange)
    {
        $lps = $this->getWorkingLps();

        if ($lps === false)
            $lps = new stdClass();

        if (isset($lpsChange->server))
            $lps->server = $lpsChange->server;
        if (isset($lpsChange->key))
            $lps->key = $lpsChange->key;
        if (isset($lpsChange->ts))
            $lps->ts = $lpsChange->ts;

        $lps_encoded = json_encode($lps);

        return file_put_contents($this->getLpsPath(), $lps_encoded) === strlen($lps_encoded);
    }

    /**
     * @return string
     */
    public function getLpsPath()
    {
        if ($this->lpsPath === null) {
            $this->lpsPath = sys_get_temp_dir().'/'.strtr($this->lpsFilename, [
                '{name}' => $this->name,
            ]);
        }
        return $this->lpsPath;
    }

    /**
     * @param $level
     * @param $message
     */
    public function log($level, $message)
    {
        parent::log($level, $message);
    }
}
