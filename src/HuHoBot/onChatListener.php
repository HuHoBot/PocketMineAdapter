<?php

namespace HuHoBot;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;

class onChatListener implements Listener{

    public function __construct(private Main $plugin){}

    public function onChat(PlayerChatEvent $event) : void{
        if((!$event->isCancelled()) && $this->plugin->getConfig()->get('enableGroupChat')){
            $playerName = $event->getPlayer()->getName();
            $message = $event->getMessage();
            $prefix = $this->plugin->getConfig()->get('chatFormatGamePrefix');

            //检测开头前缀
            if(strpos($message, $prefix) === 0){
                $chat = $this->plugin->getConfig()->get('chatFormatGame', '<{name}> {msg}');
                $noPrefixMsg = substr($message, strlen($prefix));
                $chat = str_replace(['{name}', '{msg}'], [$playerName, $noPrefixMsg], $chat);

                $this->plugin->sendMessage('chat', [
                    'serverId' => $this->plugin->getConfig()->get('serverId'),
                    'msg' => $chat,
                ]);
            }

        }
    }
}
