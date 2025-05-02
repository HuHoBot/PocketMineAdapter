<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;

class QueryWhiteListEvent extends Event{

	function getHeaderType() : string{
		return 'queryList';
	}

	function onReceive(string $packId, array $data) : void{
		//TODO
		$server = Server::getInstance();
		$this->getPlugin()->sendMessage('queryWl', ['list' => "[W.I.P]本适配器暂不支持此功能\n服务端".$server->getName()." v".$server->getPocketMineVersion()], $packId);
	}
}