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
			$this->plugin->sendMessage("heart", []);
		}
	}
}