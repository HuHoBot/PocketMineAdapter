<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;

class QueryWhiteListEvent extends Event{

	function getHeaderType() : string{
		return 'queryList';
	}

	function onReceive(string $packId, array $data) : void{
		$name = $data['key'] ?? (string) $data['page'] ?? "";
		$response = "玩家 $name 不在白名单内!";
		if(Server::getInstance()->getWhitelisted()->exists($name, true)){
			$response = "玩家 $name 在白名单内!";
		}
		$this->getPlugin()->sendMessage('queryWl', ['list' => $response], $packId);
	}
}