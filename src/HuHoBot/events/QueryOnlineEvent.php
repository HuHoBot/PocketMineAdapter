<?php

namespace HuHoBot\events;

use HuHoBot\events\Event;
use pocketmine\Server;
use function var_dump;

class QueryOnlineEvent extends Event{

	function getHeaderType() : string{
		return 'queryOnline';
	}

	function onReceive(string $packId, array $data) : void{
		$server = Server::getInstance();
		$players = $server->getOnlinePlayers();
		$onlinePlayers = '';
		foreach ($players as $player){
			$onlinePlayers.= $player->getName()."\n";
		}
		$onlinePlayers .= "共".count($players)."人在线";

		$config = $this->getPlugin()->getConfig();
		$response = [
			'msg' => $onlinePlayers,
			'url' => $config->get('motdUrl'),
			'serverType' => 'bedrock'
		];

		$this->getPlugin()->sendMessage('queryOnline', ['list' => $response], $packId);
	}
}