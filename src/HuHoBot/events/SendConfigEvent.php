<?php

namespace HuHoBot\events;

class SendConfigEvent extends Event{

	function getHeaderType() : string{
		return 'sendConfig';
	}

	function onReceive(string $packId, array $data) : void{
		$this->getPlugin()->getConfig()->set('serverId', $data['serverId']);
		$this->getPlugin()->getConfig()->set('hashKey', $data['hashKey']);
		$this->getPlugin()->saveConfig();

		$this->getPlugin()->sendResponse("服务器已接受下发配置文件", $data['groupId'] ?? [], 'success', $packId);

		$this->getPlugin()->getLogger()->notice("绑定成功！正在重新握手...");
		$this->getPlugin()->reConnect();
	}
}