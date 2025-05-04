<?php

namespace HuHoBot\customCommand;

final class CustomCommandSender{
	public function __construct(
		public string $nick,
		public string $logoUrl,
		public string $userOpenId,
		public string $groupOpenId
	){}
}