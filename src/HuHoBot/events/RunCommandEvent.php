<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\Server;

class RunCommandEvent extends Event{

	function getHeaderType() : string{
		return 'cmd';
	}

	function onReceive(string $packId, array $data) : void{
		$server = Server::getInstance();
		$sender = new RunCommandSender($server, $server->getLanguage());
		Server::getInstance()->dispatchCommand($sender, $data['cmd']);
		$this->getPlugin()->sendResponse($sender->getResult(), $data['groupId'] ?? [], 'success', $packId);
	}
}