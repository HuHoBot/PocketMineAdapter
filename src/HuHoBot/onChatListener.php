<?php

namespace HuHoBot;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class onChatListener implements Listener{

	public function __construct(private Main $plugin){}

	public function onChat(PlayerChatEvent $event) : void{
		if((!$event->isCancelled()) && $this->plugin->getConfig()->get('enableGroupChat') === true){
			$playerName = $event->getPlayer()->getName();
			$message = $event->getMessage();
			$rawPrefix = $this->plugin->getConfig()->get('chatFormatGamePrefix');
			$prefix = is_string($rawPrefix) ? $rawPrefix : '#';

			//检测开头前缀
			if(strpos($message, $prefix) === 0){
				$rawChat = $this->plugin->getConfig()->get('chatFormatGame', '<{name}> {msg}');
				$chat = is_string($rawChat) ? $rawChat : '<{name}> {msg}';
				$noPrefixMsg = substr($message, strlen($prefix));
				$chat = str_replace(['{name}', '{msg}'], [$playerName, $noPrefixMsg], $chat);

				$this->postChat($chat);
			}
		}
	}

	public function onJoin(PlayerJoinEvent $event) : void{
		$this->postPlayerEvent('onJoin', $event->getPlayer()->getName(), '进服');
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$this->postPlayerEvent('onLeft', $event->getPlayer()->getName(), '退服');
	}

	private function postPlayerEvent(string $eventName, string $playerName, string $messageType) : void{
		$postEvent = $this->plugin->getConfig()->get('postEvent', []);
		if(!is_array($postEvent)){
			return;
		}
		/** @var array<string, mixed> $postEvent */

		$eventConfig = $postEvent[$eventName] ?? [];
		if(!is_array($eventConfig) || !(bool) ($eventConfig['enable'] ?? false)){
			return;
		}

		$rawFormat = $eventConfig['formatString'] ?? '';
		$format = is_string($rawFormat) ? $rawFormat : '';
		if($format === ''){
			return;
		}
		$this->postChat(str_replace('{playerName}', $playerName, $format), $messageType);
	}

	private function postChat(string $message, ?string $messageType = null) : void{
		$body = [
			'serverId' => $this->plugin->getConfig()->get('serverId'),
			'msg' => $message
		];
		if($messageType !== null){
			$body['msgType'] = $messageType;
		}
		$this->plugin->sendMessage('chat', $body);
	}
}
