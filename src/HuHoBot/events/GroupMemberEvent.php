<?php

namespace HuHoBot\events;

use HuHoBot\customCommand\CustomCommandSender;
use HuHoBot\customCommand\RunCustomCommandEvent;

class GroupMemberEvent extends Event{

	public function getHeaderType() : string{
		return 'groupMember';
	}

	/** @param array<string, mixed> $data */
	public function onReceive(string $packId, array $data) : void{
		$command = match($data['action'] ?? null){
			'add' => '#MemberAdd',
			'remove' => '#MemberRemove',
			default => null
		};
		if($command === null){
			$this->getPlugin()->getLogger()->warning('收到未知的群成员变动类型');
			return;
		}

		$runParams = is_array($data['runParams'] ?? null) ? $data['runParams'] : [];
		$event = new RunCustomCommandEvent(
			$command,
			$runParams,
			true,
			CustomCommandSender::fromProtocolData($data),
			$data,
			$packId
		);
		$event->call();

		if($event->hasResponseMessage()){
			$groupId = $data['groupId'] ?? [];
			$this->getPlugin()->sendResponse(
				$event->getResponseMessage(),
				is_array($groupId) ? $groupId : [],
				'success',
				$packId
			);
		}
	}
}
