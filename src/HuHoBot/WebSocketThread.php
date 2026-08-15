<?php

declare(strict_types=1);

namespace HuHoBot;

use JsonException;
use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class WebSocketThread extends Thread{
	public const COMMAND_RECONNECT = "##--reconnect--##@123";

	private const RECONNECT_DELAY = 3;
	private const LOOP_WAIT_MICROSECONDS = 10000;
	private const MAX_OUTBOUND_PACKETS = 1024;
	private const MAX_INBOUND_PACKETS = 4096;
	private const MAX_PACKET_BYTES = 8 * 1024 * 1024;

	/** @phpstan-var ThreadSafeArray<int, NonThreadSafeValue<array<string, mixed>>> */
	public ThreadSafeArray $externalQueue;
	/** @phpstan-var ThreadSafeArray<int, string> */
	public ThreadSafeArray $internalQueue;

	protected bool $connected = false;

	public function __construct(
		protected ThreadSafeLogger $logger,
		protected SleeperHandlerEntry $sleeper,
		protected string $serverId,
		protected ?string $hashKey,
		protected string $serverName,
		protected string $connectUrl
	){
		$this->externalQueue = new ThreadSafeArray();
		$this->internalQueue = new ThreadSafeArray();
	}

	public function isConnected() : bool{
		return $this->connected && $this->isRunning();
	}

	public function send(string $data) : void{
		if(strlen($data) > self::MAX_PACKET_BYTES){
			$this->log("warning", "待发送数据超过 8 MiB 安全限制，已丢弃");
			return;
		}

		$queued = $this->synchronized(function() use ($data) : bool{
			if($this->isKilled || count($this->internalQueue) >= self::MAX_OUTBOUND_PACKETS){
				return false;
			}
			$this->internalQueue[] = $data;
			$this->notify();
			return true;
		});
		if(!$queued){
			$this->log("warning", "WebSocket 发送队列已满或线程已停止，数据未发送");
		}
	}

	/** @return array<string, mixed>|false */
	public function receive() : array|false{
		$value = $this->externalQueue->shift();
		if(!$value instanceof NonThreadSafeValue){
			return false;
		}

		$data = $value->deserialize();
		return is_array($data) ? $data : false;
	}

	protected function onRun() : void{
		// 与 RakLibServer 一致：让线程异常和业务日志共用主服务端 logger。
		\GlobalLogger::set($this->logger);
		$notifier = $this->sleeper->createNotifier();
		try{
			$connection = new WebSocketConnection(
				$this->connectUrl,
				function(string $payload) use ($notifier) : void{
					$this->onText($notifier, $payload);
				},
				function(string $level, string $message) : void{
					$this->log($level, $message);
				},
				self::RECONNECT_DELAY
			);
		}catch(Throwable $e){
			$this->log("error", "WebSocket 初始化失败: " . $e->getMessage());
			return;
		}

		$pending = null;
		try{
			while(!$this->isKilled){
				$wasConnected = $this->connected;
				try{
					$connection->tick();
				}catch(Throwable $e){
					$this->log("error", "WebSocket 网络循环发生异常: " . $e->getMessage());
					$connection->reconnect();
				}

				$this->connected = $connection->isConnected();
				if($this->connected && !$wasConnected){
					try{
						if(!$connection->sendText($this->createHandshakePacket())){
							throw new RuntimeException("连接已在握手完成后关闭");
						}
					}catch(Throwable $e){
						$this->log("error", "发送 HuHoBot 握手失败: " . $e->getMessage());
						$this->connected = false;
						$connection->reconnect();
					}
				}

				if($this->connected){
					$this->drainOutboundQueue($connection, $pending);
				}

				$this->synchronized(function() : void{
					if(!$this->isKilled){
						$this->wait(self::LOOP_WAIT_MICROSECONDS);
					}
				});
			}
		}finally{
			$this->connected = false;
			$connection->close();
		}
	}

	private function drainOutboundQueue(WebSocketConnection $connection, ?string &$pending) : void{
		while($connection->isConnected()){
			if($pending === null){
				$packet = $this->internalQueue->shift();
				if(!is_string($packet)){
					return;
				}
				$pending = $packet;
			}
			if($pending === self::COMMAND_RECONNECT){
				$pending = null;
				$connection->reconnect();
				return;
			}

			if(!$connection->sendText($pending)){
				return;
			}
			$pending = null;
		}
	}

	private function onText(SleeperNotifier $notifier, string $payload) : void{
		try{
			$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
		}catch(JsonException $e){
			$this->log("warning", "收到无法解析的 JSON: " . $e->getMessage());
			return;
		}
		if(!is_array($data)){
			return;
		}
		/** @var array<string, mixed> $data */

		if(count($this->externalQueue) >= self::MAX_INBOUND_PACKETS){
			$this->externalQueue->shift();
			$this->log("warning", "WebSocket 接收队列已满");
		}
		$this->externalQueue[] = new NonThreadSafeValue($data);
		$notifier->wakeupSleeper();
	}

	private function createHandshakePacket() : string{
		return json_encode([
			"header" => [
				"type" => "shakeHand",
				"id" => str_replace("-", "", Uuid::uuid4()->toString())
			],
			"body" => [
				"serverId" => $this->serverId,
				"hashKey" => $this->hashKey,
				"name" => $this->serverName,
				"version" => "1.0.0",
				"platform" => "pmmp"
			]
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	private function log(string $level, string $message) : void{
		$this->logger->log($level, "[HuHoBot] " . $message);
	}
}
