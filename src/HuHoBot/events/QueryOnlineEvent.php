<?php

namespace HuHoBot\events;

use pocketmine\Server;

class QueryOnlineEvent extends Event{

	function getHeaderType() : string{
		return 'queryOnline';
	}

	/** @param array<string, mixed> $data */
	function onReceive(string $packId, array $data) : void{
		$server = Server::getInstance();
		$playerNames = [];
		foreach($server->getOnlinePlayers() as $player){
			$playerNames[] = $player->getName();
		}

		$onlineCount = count($playerNames);
		$config = $this->getPlugin()->getConfig();
		$motd = $config->get('motd', []);
		if(!is_array($motd)){
			$motd = [];
		}
		/** @var array<string, mixed> $motd */

		$serverIp = $this->stringValue($motd['server_ip'] ?? null, 'play.easecation.net');
		$serverPort = $this->intValue($motd['server_port'] ?? null, 19132);
		$imageApi = $this->stringValue($motd['api'] ?? null, 'https://motdbe.blackbe.work/status_img?host={server_ip}:{server_port}');
		$text = $this->stringValue($motd['text'] ?? null, '共{online}人在线');
		$outputOnlineList = $this->boolValue($motd['output_online_list'] ?? null, true);
		$useMarkdown = $this->boolValue($motd['markdown'] ?? null, true);

		if($useMarkdown){
			$message = implode(', ', $playerNames);
		}else{
			$message = '';
			if($outputOnlineList){
				if($playerNames === []){
					$message .= "\n当前没有在线玩家\n";
				}else{
					$message .= "\n在线玩家列表：\n" . implode("\n", $playerNames) . "\n";
				}
			}
			$message .= str_replace('{online}', (string) $onlineCount, $text);
		}

		$response = [
			'msg' => $message,
			'url' => "$serverIp:$serverPort",
			'imgUrl' => str_replace(
				['{server_ip}', '{server_port}'],
				[$serverIp, (string) $serverPort],
				$imageApi
			),
			'post_img' => $this->boolValue($motd['post_img'] ?? null, true),
			'serverType' => 'bedrock',
			'useMarkdown' => $useMarkdown,
			'serverName' => $this->stringValue($config->get('serverName'), 'PocketMine-MP Server'),
			'currentOnline' => (string) $onlineCount
		];
		if($this->boolValue($motd['customMarkdown'] ?? null, false)){
			$response['customMarkdown'] = $this->readCustomMarkdown();
		}

		$this->getPlugin()->sendMessage('queryOnline', ['list' => $response], $packId);
	}

	private function readCustomMarkdown() : string{
		$file = $this->getPlugin()->getDataFolder() . 'online.md';
		if(!is_file($file)){
			$this->getPlugin()->getLogger()->warning('未找到 online.md，无法发送自定义 Markdown');
			return '';
		}

		$content = @file_get_contents($file);
		if($content === false){
			$this->getPlugin()->getLogger()->warning('读取 online.md 失败');
			return '';
		}
		return $content;
	}

	private function stringValue(mixed $value, string $default) : string{
		return is_string($value) ? $value : $default;
	}

	private function intValue(mixed $value, int $default) : int{
		return is_int($value) && $value >= 1 && $value <= 65535 ? $value : $default;
	}

	private function boolValue(mixed $value, bool $default) : bool{
		return is_bool($value) ? $value : $default;
	}
}
