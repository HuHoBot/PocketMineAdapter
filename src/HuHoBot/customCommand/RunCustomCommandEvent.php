<?php

namespace HuHoBot\customCommand;

use pocketmine\event\Event;

class RunCustomCommandEvent extends Event{

	private string $response = "无此命令！";

	public function __construct(
		private string $command,
		private array $args,
		public bool $isAdmin,
		private CustomCommandSender $sender
	){}

	public function getCommand(): string{
		return $this->command;
	}

	public function getArgs(): array{
		return $this->args;
	}

	public function getSender(): CustomCommandSender{
		return $this->sender;
	}

	public function isAdminCommand() : bool{
		return $this->isAdmin;
	}

	public function setResponseMessage(string $msg): void{
		$this->response = $msg;
	}

	public function getResponseMessage(): string{
		return $this->response;
	}
}