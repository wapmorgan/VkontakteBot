<?php
namespace wapmorgan\VkontakteBot;


class FriendOnlineStatus
{
    public $userId;
    public $flags;
    public $lastActivity;

    /**
     * FriendOnlineStatus constructor.
     */
    protected function __construct()
    {
    }

    /**
     * @param array $update
     * @return FriendOnlineStatus
     */
    public static function createFromLongPollEvent(array $update)
    {
        $friend = new self();
        $friend->userId = $update[1];
        $friend->flags = $update[2];
        $friend->lastActivity = $update[3];
        return $friend;
    }
}