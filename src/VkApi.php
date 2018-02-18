<?php
namespace wapmorgan\VkontakteBot;

use VK\VK;

class VkApi extends VK {
    const MAX_MESSAGES_COUNT_IN_DIALOG_QUERY = 200;

    // Коды событий метода getLongPollHistory
    const NEW_MESSAGE_ADDED_CODE = 4;
    const MESSAGE_EDITED_CODE = 5;

    const FRIEND_BECAME_ONLINE = 8;
    const FRIEND_BECAME_OFFLINE = 9;

    const USER_TYPING_IN_DIALOG = 61;
    const USER_TYPING_IN_CHAT = 62;

    /**
     * Параметры получения обновлений с LP-сервера
     */
    public $defaultLpsParams = [
        'wait' => 25,
        'mode' => 2,
        'version' => 2,
    ];

    /**
     * @var array Общие для всех запросов параметры
     */
    protected $params = [];

    /**
     * Устанавливает общие параметр, передаваемые во всех запросах
     * @param array $params
     * @return VkApi
     */
    public function setCommonParameters(array $params = [])
    {
        if (!empty($params))
            $this->params = $params;
        return $this;
    }

    /**
     * Выполняет запрос к АПИ ВК и преобразует ошибки в исключения
     * @param $method
     * @param array $parameters
     * @param string $format
     * @param string $requestMethod
     * @return bool
     * @throws VkException
     */
    public function api($method, $parameters = array(), $format = 'array', $requestMethod = 'get')
    {
        $result = json_decode(parent::api($method, $this->params + $parameters, 'json', $requestMethod));
        if (isset($result->response))
            return $result->response;
        if (isset($result->error)) {
            throw new VkException($result->error->error_msg, $result->error->error_code);
        }
        return false;
    }

    /**
     * Получает новый адрес для подключения к LP-серверу
     * @throws VkException
     */
    public function getNewLps()
    {
        return $this->api('messages.getLongPollServer', ['lp_version' => 2]);
    }

    /**
     * Комплексная функция:
     * - Если переданы данные прошлого LP-сервера, пытается получить обновления с него:
     * * - Если сервер устарел, действует как если бы данные не были переданы.
     * * - Если сервер не устарел, запрашивает обновления с него. Возвращает только обновленные параметры сервера и обновления: [lps => {}, updates => []]
     * - Если не переданы данные прошлого LP-сервера, запрашивает новый и получает данные с него. Возвращает новый сервер и обновления: [lps => {}, updates => []]
     * @param null $server
     * @param null $key
     * @param null $ts
     * @param array $options
     * @return array Массив с одним или несколькими элементами
     * @throws VkException
     */
    public function connectToLpsAndGetUpdates($server = null, $key = null, $ts = null, array $options = [])
    {
        if ($server !== null && $key !== null && $ts !== null) {
            $result = $this->getUpdatesFromLps($server, $key, $ts, $this->defaultLpsParams + $options);
            if (isset($result->failed)) {
                switch ($result->failed) {
                    case 1:
                        // запрашиваем заново с новым параметром ts
                        return call_user_func([$this, __FUNCTION__], $server, $key, $result->ts, $options);
                    case 2:
                        // запрашиваем заново с новым параметром key
                        $new_lps = $this->getNewLps();
                        $result = call_user_func([$this, __FUNCTION__], $server, $new_lps->key, $ts, $options);
                        $result['lps'] = (object)['key' => $new_lps->key];
                        return $result;
                    case 3:
                        // запрашиваем заново с новыми параметрами key и ts
                        $new_lps = $this->getNewLps();
                        $result =  call_user_func([$this, __FUNCTION__], $server, $new_lps->key, $new_lps->ts, $options);
                        $result['lps'] = (object)['key' => $new_lps->key, 'ts' => $new_lps->ts];
                        return $result;
                    case 4:
                        throw new VkException('Недопустимый номер версии LP-сервера!');
                }
            } else {
                return [
                    'lps' => (object)['ts' => $result->ts],
                    'updates' => $result->updates,
                ];
            }
        }

        $new_lps = $this->getNewLps();
        $result =  call_user_func([$this, __FUNCTION__], $new_lps->server, $new_lps->key, $new_lps->ts, $options);
        $result['lps'] = (object)['server' => $new_lps->server, 'key' => $new_lps->key, 'ts' => $new_lps->ts];
        return $result;
    }

    /**
     * @param $server
     * @param $key
     * @param $ts
     * @param $params
     * @return mixed
     */
    protected function getUpdatesFromLps($server, $key, $ts, $params)
    {
        return json_decode(file_get_contents('https://'.$server.'?'.http_build_query([
            'act' => 'a_check',
            'key' => $key,
            'ts' => $ts,
        ] + $params)));
    }
}
