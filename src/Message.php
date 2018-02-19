<?php
namespace wapmorgan\VkontakteBot;


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
            if (isset($update[6]) && is_array($update[6]))
                $message->attachments = $update[6];
        }
        return $message;
    }
}