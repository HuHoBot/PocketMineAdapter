<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;

class AddWhiteListEvent extends Event{

	function getHeaderType() : string{
		return 'add';
	}

	function onReceive(string $packId, array $data) : void{
		$name = $data['xboxid'];
		Server::getInstance()->addWhitelist($name);
		$this->getPlugin()->sendResponse("已经成功添加玩家 $name 到白名单", $data['groupId'] ?? [], 'success', $packId);
	}
}