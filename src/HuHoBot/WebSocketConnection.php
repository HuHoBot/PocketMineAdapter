<?php

declare(strict_types=1);

namespace HuHoBot;

use Closure;
use InvalidArgumentException;
use Throwable;

final class WebSocketConnection{
	private const STATE_DISCONNECTED = 0;
	private const STATE_CONNECTING = 1;
	private const STATE_HANDSHAKE = 2;
	private const STATE_OPEN = 3;

	private const CONNECT_TIMEOUT = 8.0;
	private const PING_INTERVAL = 30.0;
	private const IDLE_TIMEOUT = 90.0;
	private const MAX_BUFFER_BYTES = 8 * 1024 * 1024;
	private const MAX_READ_BYTES_PER_TICK = 1024 * 1024;
	private const MAX_FRAMES_PER_TICK = 256;

	private string $host;
	private int $port;
	private string $path;
	private int $reconnectDelay;

	/** @var Closure(string): void */
	private Closure $textHandler;
	/** @var Closure(string, string): void */
	private Closure $logger;

	/** @var resource|null */
	private $socket = null;
	private int $state = self::STATE_DISCONNECTED;
	private float $stateSince = 0.0;
	private float $nextConnectAt = 0.0;
	private float $lastReadAt = 0.0;
	private float $lastPingAt = 0.0;
	private string $readBuffer = "";
	private string $writeBuffer = "";
	private string $handshakeKey = "";
	private ?int $fragmentOpcode = null;
	private string $fragmentBuffer = "";
	private bool $closed = false;

	/**
	 * @param Closure(string): void $textHandler
	 * @param Closure(string, string): void $logger
	 */
	public function __construct(
		string $url,
		Closure $textHandler,
		Closure $logger,
		int $reconnectDelay = 3
	){
		self::validateUrl($url);
		$parts = parse_url($url);
		assert($parts !== false);

		$this->host = trim((string) ($parts["host"] ?? ""), "[]");
		$this->port = (int) ($parts["port"] ?? 80);
		$this->path = (string) ($parts["path"] ?? "/");
		if($this->path === ""){
			$this->path = "/";
		}
		if(isset($parts["query"])){
			$this->path .= "?" . $parts["query"];
		}

		$this->textHandler = $textHandler;
		$this->logger = $logger;
		$this->reconnectDelay = max(1, $reconnectDelay);
	}

	public static function validateUrl(string $url) : void{
		$parts = parse_url($url);
		if($parts === false || strtolower((string) ($parts["scheme"] ?? "")) !== "ws"){
			throw new InvalidArgumentException("WebSocket 地址必须是有效的 ws:// URL");
		}

		$host = trim((string) ($parts["host"] ?? ""), "[]");
		$port = (int) ($parts["port"] ?? 80);
		if($host === "" || $port < 1){
			throw new InvalidArgumentException("WebSocket 地址中的主机或端口无效");
		}
		if(isset($parts["user"]) || isset($parts["pass"])){
			throw new InvalidArgumentException("WebSocket 地址不能包含用户信息");
		}
	}

	public function isConnected() : bool{
		return $this->state === self::STATE_OPEN && is_resource($this->socket);
	}

	public function tick() : void{
		if($this->closed){
			return;
		}

		$now = microtime(true);
		if($this->state === self::STATE_DISCONNECTED){
			if($now < $this->nextConnectAt){
				return;
			}
			$this->beginConnect($now);
		}

		if($this->state === self::STATE_CONNECTING){
			$this->pollConnect($now);
		}

		if($this->state === self::STATE_CONNECTING || $this->state === self::STATE_HANDSHAKE){
			if($now - $this->stateSince > self::CONNECT_TIMEOUT){
				$this->fail("连接或握手超时");
				return;
			}
		}

		if(!is_resource($this->socket) || $this->state === self::STATE_DISCONNECTED){
			return;
		}

		$this->flushWrites();
		$this->readAvailable();
		if($this->state === self::STATE_HANDSHAKE){
			$this->processHandshake();
		}
		if($this->state === self::STATE_OPEN){
			$this->processFrames();
			if($this->state === self::STATE_OPEN){
				$this->maintainConnection($now);
			}
		}
	}

	public function sendText(string $payload) : bool{
		return $this->queueFrame(0x1, $payload);
	}

	public function hasPendingWrites() : bool{
		return $this->writeBuffer !== "";
	}

	public function reconnect() : void{
		$this->closeSocket();
		$this->closed = false;
		$this->state = self::STATE_DISCONNECTED;
		$this->nextConnectAt = 0.0;
	}

	public function close() : void{
		$this->closed = true;
		$socket = $this->socket;
		if($this->isConnected() && is_resource($socket)){
			@fwrite($socket, $this->encodeFrame(0x8, pack("n", 1000)));
		}
		$this->closeSocket();
		$this->state = self::STATE_DISCONNECTED;
	}

	private function beginConnect(float $now) : void{
		$remoteHost = str_contains($this->host, ":") ? "[{$this->host}]" : $this->host;
		$remote = "tcp://{$remoteHost}:{$this->port}";
		$errno = 0;
		$error = "";
		$socket = @stream_socket_client(
			$remote,
			$errno,
			$error,
			0,
			STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
		);
		if($socket === false){
			$this->fail("无法连接 {$this->host}:{$this->port}" . ($error !== "" ? ": {$error}" : ""));
			return;
		}

		stream_set_blocking($socket, false);
		stream_set_read_buffer($socket, 0);
		stream_set_write_buffer($socket, 0);
		$this->socket = $socket;
		$this->state = self::STATE_CONNECTING;
		$this->stateSince = $now;
		$this->readBuffer = "";
		$this->writeBuffer = "";
		$this->fragmentOpcode = null;
		$this->fragmentBuffer = "";
		$this->log("info", "正在连接服务器 {$this->host}:{$this->port}");
	}

	private function pollConnect(float $now) : void{
		if(!is_resource($this->socket)){
			$this->fail("连接套接字无效");
			return;
		}

		$read = null;
		$write = [$this->socket];
		$except = null;
		$ready = @stream_select($read, $write, $except, 0, 0);
		if($ready === false){
			$this->fail("检查连接状态失败");
			return;
		}
		if($ready === 0){
			return;
		}
		if(@stream_socket_get_name($this->socket, true) === false){
			$this->fail("无法建立 TCP 连接");
			return;
		}

		try{
			$this->handshakeKey = base64_encode(random_bytes(16));
		}catch(Throwable $e){
			$this->fail("无法生成 WebSocket 握手密钥: " . $e->getMessage());
			return;
		}

		$hostHeader = str_contains($this->host, ":") ? "[{$this->host}]" : $this->host;
		if($this->port !== 80){
			$hostHeader .= ":{$this->port}";
		}
		$this->writeBuffer =
			"GET {$this->path} HTTP/1.1\r\n" .
			"Host: {$hostHeader}\r\n" .
			"Upgrade: websocket\r\n" .
			"Connection: Upgrade\r\n" .
			"Sec-WebSocket-Key: {$this->handshakeKey}\r\n" .
			"Sec-WebSocket-Version: 13\r\n" .
			"User-Agent: HuHoBot-PocketMineAdapter/1.0.4\r\n\r\n";
		$this->state = self::STATE_HANDSHAKE;
		$this->stateSince = $now;
	}

	private function flushWrites() : void{
		if($this->writeBuffer === "" || !is_resource($this->socket)){
			return;
		}

		$read = null;
		$write = [$this->socket];
		$except = null;
		$ready = @stream_select($read, $write, $except, 0, 0);
		if($ready === false){
			$this->fail("检查 WebSocket 写入状态失败");
			return;
		}
		if($ready === 0){
			return;
		}

		$written = @fwrite($this->socket, $this->writeBuffer);
		if($written === false){
			$this->fail("写入 WebSocket 失败");
			return;
		}
		if($written > 0){
			$this->writeBuffer = (string) substr($this->writeBuffer, $written);
		}
	}

	private function readAvailable() : void{
		if(!is_resource($this->socket)){
			return;
		}

		$total = 0;
		while($total < self::MAX_READ_BYTES_PER_TICK){
			$read = [$this->socket];
			$write = null;
			$except = null;
			$ready = @stream_select($read, $write, $except, 0, 0);
			if($ready === false){
				$this->fail("检查 WebSocket 读取状态失败");
				return;
			}
			if($ready === 0){
				break;
			}

			$chunk = @fread($this->socket, min(65536, self::MAX_READ_BYTES_PER_TICK - $total));
			if($chunk === false){
				$this->fail("读取 WebSocket 失败");
				return;
			}
			if($chunk === ""){
				if(feof($this->socket)){
					$this->fail("服务器已断开连接");
				}
				break;
			}

			$this->readBuffer .= $chunk;
			$total += strlen($chunk);
			$this->lastReadAt = microtime(true);
			if(strlen($this->readBuffer) > self::MAX_BUFFER_BYTES){
				$this->fail("收到的数据超过 8 MiB 安全限制");
				return;
			}
		}
	}

	private function processHandshake() : void{
		$headerEnd = strpos($this->readBuffer, "\r\n\r\n");
		if($headerEnd === false){
			if(strlen($this->readBuffer) > 32768){
				$this->fail("WebSocket 握手响应头过大");
			}
			return;
		}

		$headerBlock = substr($this->readBuffer, 0, $headerEnd);
		$this->readBuffer = (string) substr($this->readBuffer, $headerEnd + 4);
		$lines = explode("\r\n", $headerBlock);
		$statusLine = (string) array_shift($lines);
		if(preg_match('/^HTTP\/1\.[01]\s+101(?:\s|$)/i', $statusLine) !== 1){
			$this->fail("WebSocket 握手被拒绝: " . trim($statusLine));
			return;
		}

		$headers = [];
		foreach($lines as $line){
			if(!str_contains($line, ":")){
				continue;
			}
			[$name, $value] = explode(":", $line, 2);
			$headers[strtolower(trim($name))] = trim($value);
		}

		$expectedAccept = base64_encode(sha1($this->handshakeKey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
		if(!hash_equals($expectedAccept, (string) ($headers["sec-websocket-accept"] ?? ""))){
			$this->fail("WebSocket 握手校验失败");
			return;
		}

		$this->state = self::STATE_OPEN;
		$this->stateSince = microtime(true);
		$this->lastReadAt = $this->stateSince;
		$this->lastPingAt = $this->stateSince;
		$this->log("info", "已连接到服务器");
	}

	private function processFrames() : void{
		$processed = 0;
		while($processed < self::MAX_FRAMES_PER_TICK && $this->state === self::STATE_OPEN){
			$bufferLength = strlen($this->readBuffer);
			if($bufferLength < 2){
				return;
			}

			$first = ord($this->readBuffer[0]);
			$second = ord($this->readBuffer[1]);
			$fin = ($first & 0x80) !== 0;
			$opcode = $first & 0x0f;
			if(($first & 0x70) !== 0){
				$this->fail("不支持带 RSV 扩展位的 WebSocket 帧");
				return;
			}

			$masked = ($second & 0x80) !== 0;
			$payloadLength = $second & 0x7f;
			$offset = 2;
			if($payloadLength === 126){
				if($bufferLength < 4){
					return;
				}
				$lengthParts = unpack("nlength", substr($this->readBuffer, 2, 2));
				if($lengthParts === false){
					$this->fail("无法解析 WebSocket 帧长度");
					return;
				}
				$payloadLength = $lengthParts["length"];
				$offset = 4;
			}elseif($payloadLength === 127){
				if($bufferLength < 10){
					return;
				}
				$lengthParts = unpack("Nhigh/Nlow", substr($this->readBuffer, 2, 8));
				if($lengthParts === false){
					$this->fail("无法解析 WebSocket 帧长度");
					return;
				}
				if($lengthParts["high"] !== 0){
					$this->fail("WebSocket 帧长度超过安全限制");
					return;
				}
				$payloadLength = $lengthParts["low"];
				$offset = 10;
			}

			if($payloadLength > self::MAX_BUFFER_BYTES){
				$this->fail("WebSocket 帧超过 8 MiB 安全限制");
				return;
			}
			if($opcode >= 0x8 && (!$fin || $payloadLength > 125)){
				$this->fail("收到了无效的 WebSocket 控制帧");
				return;
			}

			$mask = "";
			if($masked){
				if($bufferLength < $offset + 4){
					return;
				}
				$mask = substr($this->readBuffer, $offset, 4);
				$offset += 4;
			}
			if($bufferLength < $offset + $payloadLength){
				return;
			}

			$payload = substr($this->readBuffer, $offset, $payloadLength);
			$this->readBuffer = (string) substr($this->readBuffer, $offset + $payloadLength);
			if($masked){
				$payload = $this->applyMask($payload, $mask);
			}

			$this->handleFrame($opcode, $fin, $payload);
			++$processed;
		}
	}

	private function handleFrame(int $opcode, bool $fin, string $payload) : void{
		switch($opcode){
			case 0x0:
				if($this->fragmentOpcode === null){
					$this->fail("收到了意外的 WebSocket continuation 帧");
					return;
				}
				$this->fragmentBuffer .= $payload;
				if(strlen($this->fragmentBuffer) > self::MAX_BUFFER_BYTES){
					$this->fail("分片消息超过 8 MiB 安全限制");
					return;
				}
				if($fin){
					$fragmentOpcode = $this->fragmentOpcode;
					$message = $this->fragmentBuffer;
					$this->fragmentOpcode = null;
					$this->fragmentBuffer = "";
					if($fragmentOpcode === 0x1){
						$this->handleText($message);
					}
				}
				break;

			case 0x1:
			case 0x2:
				if($this->fragmentOpcode !== null){
					$this->fail("上一条 WebSocket 分片消息尚未结束");
					return;
				}
				if($fin){
					if($opcode === 0x1){
						$this->handleText($payload);
					}
				}else{
					$this->fragmentOpcode = $opcode;
					$this->fragmentBuffer = $payload;
				}
				break;

			case 0x8:
				if(is_resource($this->socket)){
					@fwrite($this->socket, $this->encodeFrame(0x8, substr($payload, 0, 125)));
				}
				$this->fail("服务器主动关闭了 WebSocket");
				break;

			case 0x9:
				$this->queueFrame(0xA, $payload);
				break;

			case 0xA:
				break;

			default:
				$this->fail("收到未知的 WebSocket opcode: {$opcode}");
		}
	}

	private function handleText(string $payload) : void{
		try{
			($this->textHandler)($payload);
		}catch(Throwable $e){
			$this->log("error", "处理 WebSocket 文本消息时发生异常: " . $e->getMessage());
		}
	}

	private function maintainConnection(float $now) : void{
		if($now - $this->lastReadAt > self::IDLE_TIMEOUT){
			$this->fail("连接超过 90 秒无响应");
			return;
		}
		if($now - $this->lastPingAt > self::PING_INTERVAL){
			$this->queueFrame(0x9, "huho");
			$this->lastPingAt = $now;
		}
	}

	private function queueFrame(int $opcode, string $payload) : bool{
		if(!is_resource($this->socket) || $this->state !== self::STATE_OPEN){
			return false;
		}
		if(strlen($payload) > self::MAX_BUFFER_BYTES){
			$this->log("warning", "待发送数据超过 8 MiB 安全限制");
			return false;
		}

		$frame = $this->encodeFrame($opcode, $payload);
		if(strlen($this->writeBuffer) + strlen($frame) > self::MAX_BUFFER_BYTES){
			return false;
		}
		$this->writeBuffer .= $frame;
		return true;
	}

	private function encodeFrame(int $opcode, string $payload) : string{
		$length = strlen($payload);
		$frame = chr(0x80 | ($opcode & 0x0f));
		if($length < 126){
			$frame .= chr(0x80 | $length);
		}elseif($length <= 0xffff){
			$frame .= chr(0x80 | 126) . pack("n", $length);
		}else{
			$frame .= chr(0x80 | 127) . pack("NN", 0, $length);
		}
		$mask = random_bytes(4);
		return $frame . $mask . $this->applyMask($payload, $mask);
	}

	private function applyMask(string $payload, string $mask) : string{
		$length = strlen($payload);
		for($i = 0; $i < $length; ++$i){
			$payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i & 3]));
		}
		return $payload;
	}

	private function fail(string $reason) : void{
		$this->closeSocket();
		$this->state = self::STATE_DISCONNECTED;
		$this->nextConnectAt = microtime(true) + $this->reconnectDelay;
		$this->log("warning", "{$reason}，{$this->reconnectDelay} 秒后重连");
	}

	private function closeSocket() : void{
		if(is_resource($this->socket)){
			@fclose($this->socket);
		}
		$this->socket = null;
		$this->readBuffer = "";
		$this->writeBuffer = "";
		$this->handshakeKey = "";
		$this->fragmentOpcode = null;
		$this->fragmentBuffer = "";
	}

	private function log(string $level, string $message) : void{
		($this->logger)($level, $message);
	}
}
