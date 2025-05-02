<?php

namespace HuHoBot;

use HuHoBot\events\AddWhiteListEvent;
use HuHoBot\events\BindRequestEvent;
use HuHoBot\events\DelWhiteListEvent;
use HuHoBot\events\Event;
use HuHoBot\events\QueryOnlineEvent;
use HuHoBot\events\QueryWhiteListEvent;
use HuHoBot\events\RunCommandEvent;
use HuHoBot\events\RunCustomCommandEvent;
use HuHoBot\events\SendConfigEvent;
use HuHoBot\events\ShakedEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;
use function hrtime;
use function json_encode;
use function str_replace;
use function var_dump;

class Main extends PluginBase implements Listener {
	public WebSocketThread $ws;
	private SleeperHandlerEntry $sleeper;
	/** @var Event[] */
	private array $events = [];

	public bool $handShaked = false;
	public string $bindCode = "";
	public string $bindId = "";

	public function onEnable() : void{
		$this->saveDefaultConfig();

		if($this->getConfig()->get('serverId') == false){
			$this->getConfig()->set('serverId', str_replace("-", '', $this->getServer()->getServerUniqueId()->toString()));
			$this->saveConfig();
		}

		//网络事件注册
		$this->registerEvent(new ShakedEvent());
		$this->registerEvent(new BindRequestEvent());
		$this->registerEvent(new SendConfigEvent());
		$this->registerEvent(new QueryOnlineEvent());
		$this->registerEvent(new AddWhiteListEvent());
		$this->registerEvent(new DelWhiteListEvent());
		$this->registerEvent(new QueryWhiteListEvent());
		$this->registerEvent(new RunCommandEvent());
		$this->registerEvent(new RunCustomCommandEvent());

		//连接和握手
		$this->sleeper = $this->getServer()->getTickSleeper()->addNotifier(fn() => $this->onTextReceived());
		$this->ws = new WebSocketThread($this->sleeper);
		$this->ws->start();

		$this->shakeHand();
		$this->getScheduler()->scheduleRepeatingTask(new HeartBeatTask($this), 20 * 3); //协议是5s，为了低tps情况计时考虑
	}

	public function reConnect() : void{
		$this->ws->send(WebSocketThread::COMMAND_RECONNECT); //HACK！
		$this->shakeHand();
	}

	public function onDisable() : void{
		$this->handShaked = false;
		$this->getServer()->getTickSleeper()->removeNotifier($this->sleeper->getNotifierId());
		$this->ws->shutdown();
	}

	public function registerEvent(Event $e){
		$e->setPlugin($this);
		$this->events[$e->getHeaderType()] = $e;
	}

	public function onTextReceived() : void{
		while(($data = $this->ws->receive()) != false){
			if(isset($data['header']) and isset($data['body'])){
				$type = $data['header']['type'];
				if(isset($this->events[$type])){
					$this->events[$type]->onReceive($data['header']['id'], $data['body']);
				}
			}
		}
	}

	public function sendResponse(string $msg, array $groupId, string $type, ?string $packId = null) : void{
		$this->sendMessage($type, ['msg' => $msg, 'group' => $groupId], $packId);
	}

	public function sendMessage(string $type, array $body, ?string $packId = null) : void{
		if($packId === null){
			$packId = str_replace("-", '', Uuid::fromInteger(hrtime(true)));
		}

		$data = [
			'header' => [
				'type' => $type,
				'id' => $packId
			],
			'body' => $body
		];

		$this->ws->send(json_encode($data));
	}

	private function shakeHand() : void{
		$this->sendMessage('shakeHand', [
			'serverId' => $this->getConfig()->get('serverId'),
			'hashKey' => $this->getConfig()->get('hashKey', null),
			'name' => $this->getConfig()->get('serverName'),
			'version' => $this->getDescription()->getVersion(),
			'platform' => 'dev'
		]);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case 'bind':
				if(count($args) == 1){
					if($args[0] === $this->bindCode){
						$this->sendMessage('bindConfirm', [], $this->bindId);

						$sender->sendMessage(TextFormat::GREEN."绑定成功");
					}else{
						$sender->sendMessage(TextFormat::RED."绑定校验码错误");
					}
					return true;
				}
				break;
		}
		return false;
	}
}