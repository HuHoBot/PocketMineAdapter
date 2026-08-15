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

	/** @param array<string, mixed> $data */
	function onReceive(string $packId, array $data) : void{
		$sender = CustomCommandSender::fromProtocolData($data);
		$command = $data['key'] ?? null;
		$runParams = $data['runParams'] ?? null;
		if(!is_string($command) || !is_array($runParams)){
			$this->getPlugin()->getLogger()->warning('自定义命令数据缺少 key 或 runParams');
			return;
		}

		$event = new pmRunCustomCommandEvent(
			$command,
			$runParams,
			$this->isAdminCommand(),
			$sender,
			$data,
			$packId
		);
		$event->call();

		$groupId = $data['groupId'] ?? [];
		$this->getPlugin()->sendResponse($event->getResponseMessage(), is_array($groupId) ? $groupId : [], 'success', $packId);
	}

	public function isAdminCommand() : bool{
		return false;
	}
}
