<?php
namespace wapmorgan\VkontakteBot;


use stdClass;

class Message
{
    /**
     * Message flags
     */
    const UNREAD = 1;
    const OUTBOX = 2;
    const REPLIED = 4;
    const IMPORTANT = 8;
    const CHAT = 16;
    const FRIENDS = 32;
    const SPAM = 64;
    const DELETED = 128;
    const FIXED = 256;
    const MEDIA = 512;
    const HIDDEN = 65536;
    const DELETED_FOR_ALL = 131072;

    const PHOTO = 'photo';
    const VIDEO = 'video';
    const AUDIO = 'audio';
    const DOC = 'doc';
    const WALL = 'wall';
    const STICKER = 'sticker';
    const LINK = 'link';
    const MONEY = 'money';

    /**
     * @var int
     */
    public $messageId;
    public $flags;
    public $peerId;
    public $timestamp;
    public $text;
    public $attachments = [];

    /**
     * Message constructor.
     */
    protected function __construct()
    {
    }

    /**
     * @param array $update
     * @return Message
     */
    public static function createFromLongPollEvent(array $update)
    {
        $message = new self();
        $message->messageId = $update[1];
        $message->flags = $update[2];
        $message->peerId = $update[3];
        if (isset($update[4])) {
            $message->timestamp = $update[4];
            $message->text = $update[5];
            if (isset($update[6]) && is_array($update[6])) {
                foreach ($update[6] as $field => $value) {
                    if (fnmatch('attach*_type', $field)) {
                        $message->attachments[] = (object)[
                            'type' => $value,
                            'id' => $update[6]->{'attach' . substr(strstr($field, '_', true), 6)},
                        ];
                    }
                }
            }
        }
        return $message;
    }

    /**
     * @param stdClass $historyMessage
     * @return Message
     */
    public static function createFromDialogHistory(stdClass $historyMessage)
    {
        $message = new self();
        $message->messageId = $historyMessage->id;
        $message->peerId = $historyMessage->from_id;
        $message->timestamp = $historyMessage->date;
        if ($historyMessage->read_state == 0)
            $message->flags |= self::UNREAD;
        if ($historyMessage->out == 0)
            $message->flags |= self::OUTBOX;
        if (isset($historyMessage->important) && $historyMessage->important)
            $message->flags |= self::IMPORTANT;
        if (isset($historyMessage->deleted) && $historyMessage->deleted)
            $message->flags |= self::DELETED;
        $message->text = $historyMessage->body;
        return $message;
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasAttachmentsOfType($type) {
        foreach ($this->attachments as $key => $value) {
            if (strpos($key, '_type') !== false && $value === $type) return true;
        }
        return false;
    }

}