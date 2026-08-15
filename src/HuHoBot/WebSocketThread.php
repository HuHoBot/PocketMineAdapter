<?php

declare(strict_types=1);

namespace HuHoBot;

use JsonException;
use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\thread\Thread;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class WebSocketThread extends Thread{
	public const COMMAND_RECONNECT = "##--reconnect--##@123";

	private const ENDPOINT = "eNrzqXI19ssKrfTPNc3yySswTg2PMvHJ9SvxqbQwSzZOtgUAs8kKqw==";
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
		protected SleeperHandlerEntry $sleeper,
		protected string $serverId,
		protected ?string $hashKey,
		protected string $serverName
	){
		$this->externalQueue = new ThreadSafeArray();
		$this->internalQueue = new ThreadSafeArray();
	}

	public function isConnected() : bool{
		return $this->connected && $this->isRunning();
	}

	public function send(string $data) : void{
		if(strlen($data) > self::MAX_PACKET_BYTES){
			\GlobalLogger::get()->warning("[HuHoBot] 待发送数据超过 8 MiB 安全限制，已丢弃");
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
			\GlobalLogger::get()->warning("[HuHoBot] WebSocket 发送队列已满或线程已停止，数据未发送");
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
		$notifier = $this->sleeper->createNotifier();
		try{
			$connection = new WebSocketConnection(
				$this->decodeEndpoint(self::ENDPOINT),
				function(string $payload) use ($notifier) : void{
					$this->onText($notifier, $payload);
				},
				static function(string $level, string $message) : void{
					self::log($level, $message);
				},
				self::RECONNECT_DELAY
			);
		}catch(Throwable $e){
			self::log("error", "WebSocket 初始化失败: " . $e->getMessage());
			return;
		}

		$pending = null;
		try{
			while(!$this->isKilled){
				$wasConnected = $this->connected;
				try{
					$connection->tick();
				}catch(Throwable $e){
					self::log("error", "WebSocket 网络循环发生异常: " . $e->getMessage());
					$connection->reconnect();
				}

				$this->connected = $connection->isConnected();
				if($this->connected && !$wasConnected){
					try{
						if(!$connection->sendText($this->createHandshakePacket())){
							throw new RuntimeException("连接已在握手完成后关闭");
						}
					}catch(Throwable $e){
						self::log("error", "发送 HuHoBot 握手失败: " . $e->getMessage());
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
			self::log("warning", "收到无法解析的 JSON: " . $e->getMessage());
			return;
		}
		if(!is_array($data)){
			return;
		}

		if(count($this->externalQueue) >= self::MAX_INBOUND_PACKETS){
			$this->externalQueue->shift();
			self::log("warning", "WebSocket 接收队列已满");
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

	private function decodeEndpoint(string $encoded) : string{
		$compressed = base64_decode($encoded, true);
		$reversedBase64 = $compressed !== false ? zlib_decode($compressed) : false;
		$url = is_string($reversedBase64) ? base64_decode($reversedBase64, true) : false;
		if(!is_string($url)){
			throw new RuntimeException("内置 WebSocket 地址无法解码");
		}
		return strrev($url);
	}

	private static function log(string $level, string $message) : void{
		$logger = \GlobalLogger::get();
		$message = "[HuHoBot] " . $message;
		switch($level){
			case "info":
				$logger->info($message);
				break;
			case "warning":
				$logger->warning($message);
				break;
			default:
				$logger->error($message);
		}
	}
}
