<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;

class DelWhiteListEvent extends Event{

	function getHeaderType() : string{
		return 'delete';
	}

	function onReceive(string $packId, array $data) : void{
		$name = $data['xboxid'];
		$response = "玩家 $name 不在白名单内!";
		if(Server::getInstance()->getWhitelisted()->exists($name, true)){
			Server::getInstance()->removeWhitelist($name);
			$response = "成功从白名单内移除玩家 $name";
		}

		$this->getPlugin()->sendResponse($response, $data['groupId'] ?? [], 'success', $packId);
	}
}