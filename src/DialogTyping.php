<?php
namespace wapmorgan\VkontakteBot;


class DialogTyping
{
    public $userId;
    public $flags;

    /**
     * TypingInDialogEvent constructor.
     */
    protected function __construct()
    {
    }

    /**
     * @param array $update
     * @return DialogTyping
     */
    public static function createFromLongPollEvent(array $update)
    {
        $event = new self();
        $event->userId = $update[1];
        $event->flags = $update[2];
        return $event;
    }
}