<?php

namespace HuHoBot;

use pocketmine\scheduler\Task;
use function var_dump;

class HeartBeatTask extends Task{

	public function __construct(
		protected Main $plugin
	){
	}

	public function onRun() : void{
		if($this->plugin->handShaked){
			if(!$this->plugin->isConnected()){
				$this->plugin->getLogger()->notice("已和服务器断开连接, 尝试重连...");
				$this->plugin->handShaked = false;
				$this->plugin->reConnect();
			}
			$this->plugin->sendMessage("heart", []);
		}
	}
}