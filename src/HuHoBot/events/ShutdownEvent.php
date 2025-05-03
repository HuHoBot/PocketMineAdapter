<?php

namespace HuHoBot\events;

class ShutdownEvent extends Event{

	function getHeaderType() : string{
		return 'shutdown';
	}

	function onReceive(string $packId, array $data) : void{
		$logger = $this->getPlugin()->getLogger();
		$logger->warning('服务端命令断开连接 原因: '.$data['msg']);
		$logger->warning('此错误具有不可容错性!请检查插件配置文件!');
		$this->getPlugin()->handShaked = false; //不进行自动重连
	}
}