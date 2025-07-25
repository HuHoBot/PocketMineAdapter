<?php

namespace HuHoBot;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
use function base64_decode;
use function dirname;
use function is_array;
use function json_decode;
use function mt_rand;
use function zlib_decode;

class WebSocketThread extends Thread{
	public const COMMAND_RECONNECT = "##--reconnect--##@123";

	public ThreadSafeArray $externalQueue;
	public ThreadSafeArray $internalQueue;

	protected bool $shutdown = false;

	protected bool $connected = false;

	public function __construct(
		protected SleeperHandlerEntry $sleeper
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

	protected function onRun() : void{
		require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');

		$client = new Client(zlib_decode(base64_decode("\x65\112\x77\162\114\x37\x62\123\x31\60\x2f\x4b\114\71\x46\x4c\162\105\x6a\126\x79\70\172\x4c\164\152\111\171\x73\x44\x41\x48\x41\x45\x34\120\102\x71\70\x3d"))); //不要干坏事
		$client
			->setTimeout(1)
			->onDisconnect(fn() => $this->connected = false)
			->onHandshake(fn() => $this->connected = true)
			->onText(fn(Client $client, Connection $connection, Message $message) => $this->onText($client, $connection, $message))
			->onTick(function () use ($client) : void{
				$this->tick($client);
			});

		while(!$this->shutdown){
			$client->start();
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
			$client->disconnect();
			$client->stop();
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
		if($this->shutdown){
			$client->disconnect();
			$client->stop();
		}

		while(count($this->internalQueue) > 0){
			$data = $this->internalQueue->shift();
			if($data === self::COMMAND_RECONNECT){
				$client->disconnect();
				$client->stop();//在主循环处自动重启
			}else{
				$client->text($data);
			}
		}
	}

}