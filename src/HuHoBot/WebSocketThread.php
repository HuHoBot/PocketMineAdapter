<?php

namespace HuHoBot;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use pocketmine\utils\UUID;
use Throwable;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
use function base64_decode;
use function dirname;
use function is_array;
use function json_decode;

class WebSocketThread extends Thread{
	public const COMMAND_RECONNECT = "##--reconnect--##@123";

	public ThreadSafeArray $externalQueue;
	public ThreadSafeArray $internalQueue;

	protected bool $shutdown = false;

	protected bool $connected = false;

	public function __construct(
		protected SleeperHandlerEntry $sleeper,
		protected string $serverId,
		protected ?string $hashKey,
		protected string $serverName
	){
		$this->externalQueue = new ThreadSafeArray();
		$this->internalQueue = new ThreadSafeArray();
	}

	public function shutdown() : void{
		$this->shutdown = true;
	}

	protected function connect() : void{
		$this->start();
	}

	public function isConnected() : bool{
		return $this->connected;
	}

	public function send(string $data) : void{
		$this->internalQueue[] = $data;
	}

	public function receive() : array|false{
		/** @var ?NonThreadSafeValue $data */
		$data = $this->externalQueue->shift();
		if($data !== null){
			return $data->deserialize();
		}
		return false;
	}

	private function encodedUrl($encoded): string {
		$zlibData = base64_decode($encoded);
		$base64Data = zlib_decode($zlibData);
		return strrev(base64_decode($base64Data));
	}

	protected function onRun() : void{
		require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');
		while(!$this->shutdown){
			$client = new Client($this->encodedUrl('eNrzqXI19ssKrfTPNc3yySswTg2PMvHJ9SvxqbQwSzZOtgUAs8kKqw=='));
			try{
				$client
					->setPersistent(true) //持久连接(?)
					->setTimeout(15) //连接时超时数值
					->onDisconnect(function() : void{
						$this->connected = false;
						\GlobalLogger::get()->info("[HuhoBot] 与服务器断开连接");
					})
					->onHandshake(function() use ($client) : void{
						$this->connected = true;
						\GlobalLogger::get()->info("[HuhoBot] 连接到服务器");
						$this->shakeHand(); //握手
						$client->setTimeout(2); //连接成功后超时数值
					})
					->onText(fn(Client $client, Connection $connection, Message $message) => $this->onText($client, $connection, $message))
					->onTick(function () use ($client) : void{
						$this->tick($client); //主循环
					});

				$client->start();
			}catch(Throwable $e){
				$client->disconnect();
				\GlobalLogger::get()->logException($e);
				\GlobalLogger::get()->notice("[HuhoBot] 机器人已经断开连接，三秒后自动重连...");
				sleep(3);
			}
		}
	}

	/**
	 * 接收数据包
	 */
	public function onText(Client $client, Connection $connection, Message $message) : void{
		try{
			$data = json_decode($message->getContent(), true);
		}catch(\Throwable $e){
			\GlobalLogger::get()->logException($e);
			//TODO 重新连接
		}finally{
			if(is_array($data)){
				$this->externalQueue[] = new NonThreadSafeValue($data);
				$this->sleeper->createNotifier()->wakeupSleeper(); //回调
			}
		}
	}

	/**
	 * 主循环 | 发送数据包
	 */
	public function tick(Client $client) : void{ //1tps
		if($this->shutdown or !$client->isConnected()){
			$client->disconnect();
			$client->stop();
		}

		while(count($this->internalQueue) > 0){
			$data = $this->internalQueue->shift();
			if($data === self::COMMAND_RECONNECT){
				$client->disconnect();
				$client->stop();
			}else{
				$client->text($data);
			}
		}
	}

	private function shakeHand() : void{
		$data = [
			'header' => [
				'type' => 'shakeHand',
				'id' => str_replace("-", '', UUID::fromRandom())
			],
			'body' => [
				'serverId' => $this->serverId,
				'hashKey' => $this->hashKey,
				'name' => $this->serverName,
				'version' => "1.0.0", //需要硬编码 //$this->getDescription()->getVersion(),
				'platform' => 'pmmp'
			]
		];

		$this->internalQueue[] = json_encode($data);
	}

}