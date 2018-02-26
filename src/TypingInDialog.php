<?php
/**
 * Created by PhpStorm.
 * User: wapmorgan
 * Date: 26.02.18
 * Time: 19:01
 */

namespace wapmorgan\VkontakteBot;


class TypingInDialog
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
     * @return TypingInDialog
     */
    public static function createFromLongPollEvent(array $update)
    {
        $event = new self();
        $event->userId = $update[1];
        $event->flags = $update[2];
        return $event;
    }
}