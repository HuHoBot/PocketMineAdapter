<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;

class AddWhiteListEvent extends Event{

	function getHeaderType() : string{
		return 'add';
	}

	function onReceive(string $packId, array $data) : void{
		//TODO
		$server = Server::getInstance();
		$this->getPlugin()->sendResponse("[W.I.P]本适配器暂不支持此功能\n服务端".$server->getName()." v".$server->getPocketMineVersion(), $data['groupId'] ?? [], 'success', $packId);
	}
}