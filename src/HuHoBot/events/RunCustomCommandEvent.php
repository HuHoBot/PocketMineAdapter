<?php

namespace HuHoBot\events;

use pocketmine\Server;

class RunCustomCommandEvent extends Event{

	function getHeaderType() : string{
		return 'run';
	}

	function onReceive(string $packId, array $data) : void{
		//TODO
		$server = Server::getInstance();
		$this->getPlugin()->sendResponse("[W.I.P]本适配器暂不支持此功能\n服务端".$server->getName()." v".$server->getPocketMineVersion(), $data['groupId'] ?? [], 'success', $packId);
	}
}