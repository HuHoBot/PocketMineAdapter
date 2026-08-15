<?php

namespace HuHoBot\customCommand;

use pocketmine\event\Event;

class RunCustomCommandEvent extends Event{

	private string $response = "无此命令！";
	private bool $responseSet = false;

	/**
	 * @param array<mixed> $args
	 * @param array<string, mixed>|null $data
	 */
	public function __construct(
		private string $command,
		private array $args,
		public bool $isAdmin,
		private CustomCommandSender $sender,
		private ?array $data = null,
		private ?string $packId = null
	){}

	public function getCommand(): string{
		return $this->command;
	}

	/** @return array<mixed> */
	public function getArgs(): array{
		return $this->args;
	}

	public function getSender(): CustomCommandSender{
		return $this->sender;
	}

	/** @return array<string, mixed> */
	public function getData() : array{
		return $this->data ?? [
			'key' => $this->command,
			'runParams' => $this->args
		];
	}

	public function getPackId() : ?string{
		return $this->packId;
	}

	public function isAdminCommand() : bool{
		return $this->isAdmin;
	}

	public function setResponseMessage(string $msg): void{
		$this->response = $msg;
		$this->responseSet = true;
	}

	public function getResponseMessage(): string{
		return $this->response;
	}

	public function hasResponseMessage() : bool{
		return $this->responseSet;
	}
}
