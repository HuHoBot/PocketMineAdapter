<?php

namespace HuHoBot\events;

use HuHoBot\Main;

abstract class Event{

	protected Main $plugin;

	public function setPlugin(Main $plugin) : void{
		$this->plugin = $plugin;
	}

	protected function getPlugin() : Main{
		return $this->plugin;
	}

	abstract function getHeaderType() : string;

	abstract function onReceive(string $packId, array $data) : void;
}