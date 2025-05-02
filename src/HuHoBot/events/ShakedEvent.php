<?php

namespace HuHoBot\events;


use function var_dump;

class ShakedEvent extends Event{

	function getHeaderType() : string{
		return 'shaked';
	}

	function onReceive(string $packId, array $data) : void{
		$logger = $this->getPlugin()->getLogger();
		switch ((int) $data["code"]) {
			case 1:
				$this->getPlugin()->handShaked = true;
				$logger->info("握手成功");
				break;
			case 2:
				$this->getPlugin()->handShaked = true;
				$logger->info("握手成功, 附加消息: {$data["msg"]}");
				break;
			case 3:
				$logger->warning("握手失败，原因: {$data["msg"]}");
				break;
			case 6:
				$this->getPlugin()->handShaked = true;
				$logger->notice("握手成功，等待绑定...");
				if($this->getPlugin()->getConfig()->get('hashKey') == false){
					$logger->notice("服务器尚未在机器人进行绑定，请在群内输入 '/绑定 {$this->getPlugin()->getConfig()->get('serverId')}' 来绑定");
				}
				break;
			default:
				$reason = $data["msg"] ?? "未知";
				$logger->warning("握手失败({$data['code']}), 原因:".$reason);
		}
	}
}