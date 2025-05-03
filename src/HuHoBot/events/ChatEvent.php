<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;

class ChatEvent extends Event{

	function getHeaderType() : string{
		return 'chat';
	}

	function onReceive(string $packId, array $data) : void{
		$nick = $data['nick'];
		$msg = $data['msg'];
		if($this->getPlugin()->getConfig()->get('enableGroupChat')){
			$chat = $this->getPlugin()->getConfig()->get('chatFormatGroup', '群:<{nick}> {msg}');
			$chat = str_replace(['{nick}', '{msg}'], [$nick, $msg], $chat);
			$this->getPlugin()->getServer()->broadcastMessage($chat);
		}
	}
}