<?php
namespace wapmorgan\VkontakteBot;


class FriendOfflineStatus
{
    const LOGOUT = 1;
    const TIMEOUT = 2;

    public $userId;
    public $reason;
    public $lastActivity;

    /**
     * FriendOfflineStatus constructor.
     */
    protected function __construct()
    {
    }

    /**
     * @param array $update
     * @return FriendOfflineStatus
     */
    public static function createFromLongPollEvent(array $update)
    {
        $friend = new self();
        $friend->userId = $update[1];
        $friend->reason = $update[2] == 0 ? self::LOGOUT : self::TIMEOUT;
        $friend->lastActivity = $update[3];
        return $friend;
    }
}