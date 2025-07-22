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
		$address = explode(':', $config->get('motdUrl'));
		$response = [
			'msg' => $onlinePlayers,
			'url' => $config->get('motdUrl'),
			'imgUrl' => "https://motd.txssb.cn/api/iframe_img?ip={$address[0]}&port={$address[1]}&dark=true",
			'post_img' => true,
			'serverType' => 'bedrock'
		];

		$this->getPlugin()->sendMessage('queryOnline', ['list' => $response], $packId);
	}
}