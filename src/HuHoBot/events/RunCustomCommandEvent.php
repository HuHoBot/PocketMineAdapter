<?php

namespace HuHoBot\events;

use HuHoBot\customCommand\CustomCommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use HuHoBot\customCommand\RunCustomCommandEvent as pmRunCustomCommandEvent;

class RunCustomCommandEvent extends Event{

	public function getHeaderType() : string{
		return 'run';
	}

	function onReceive(string $packId, array $data) : void{
		$sender = new CustomCommandSender(
			$data['author']['bindNick'] ?? "error bindNick",
			$data['author']['qlogoUrl'] ?? "error qlogoUrl",
			$data['author']['openId'] ?? "error author openId",
			$data['group']['openId'] ?? "error group openId",
		);

		$event = new pmRunCustomCommandEvent(
			$data['key'],
			$data['runParams'],
			$this->isAdminCommand(),
			$sender
		);
		$event->call();

		$this->getPlugin()->sendResponse($event->getResponseMessage(), $data['groupId'] ?? [], 'success', $packId);
	}

	public function isAdminCommand() : bool{
		return false;
	}
}