<?php

namespace HuHoBot\events;

class BindRequestEvent extends Event{

	function getHeaderType() : string{
		return 'bindRequest';
	}

	function onReceive(string $packId, array $data) : void{
		$this->getPlugin()->getLogger()->info("绑定校验码已下发，请通过'/bind <校验码>'验证");
		$this->getPlugin()->bindCode = $data['bindCode'];
		$this->getPlugin()->bindId = $packId;
	}
}