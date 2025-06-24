<?php

namespace HuHoBot;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use Throwable;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Message\Message;
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
		protected bool $safeConnect = true,
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
		while(!$this->shutdown){
			$client = new Client('wss://agent-remote.txssb.cn:2087');
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
						$client->setTimeout(2); //连接成功后超时数值
					})
					->onText(fn(Client $client, Connection $connection, Message $message) => $this->onText($client, $connection, $message))
					->onTick(function () use ($client) : void{
						$this->tick($client); //主循环
					});

				if(!$this->safeConnect){ //不安全连接
					$client->setContext([
						'ssl' => [
							'verify_peer'       => false,      // 不验证对端证书
							'verify_peer_name'  => false,      // 不验证对端域名
							'allow_self_signed' => true        // 允许自签名证书
						]
					]);
				}

				$client->start();
			}catch(Throwable $e){
				$client->disconnect();
				$this->connected = false;
				\GlobalLogger::get()->logException($e);
				\GlobalLogger::get()->notice("[HuhoBot] 机器人已经断开连接，等待自动重连...");
				sleep(15); //等待主线程HeartBeatTask探测异常
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
			$client->disconnect(); //重新连接
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

}