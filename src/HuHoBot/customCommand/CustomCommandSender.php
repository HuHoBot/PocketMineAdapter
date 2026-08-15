<?php

namespace HuHoBot\customCommand;

final class CustomCommandSender{
	public function __construct(
		public string $nick,
		public string $logoUrl,
		public string $userOpenId,
		public string $groupOpenId
	){}

	/** @param array<string, mixed> $data */
	public static function fromProtocolData(array $data) : self{
		$author = is_array($data['author'] ?? null) ? $data['author'] : [];
		$group = is_array($data['group'] ?? null) ? $data['group'] : [];

		return new self(
			self::readString($author, 'bindNick', 'error bindNick'),
			self::readString($author, 'qlogoUrl', 'error qlogoUrl'),
			self::readString($author, 'openId', 'error author openId'),
			self::readString($group, 'openId', 'error group openId')
		);
	}

	/** @param array<string, mixed> $data */
	private static function readString(array $data, string $key, string $fallback) : string{
		$value = $data[$key] ?? null;
		return is_string($value) && $value !== '' ? $value : $fallback;
	}
}
