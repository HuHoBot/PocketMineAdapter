<?php

namespace HuHoBot\events;

use HuHoBot\events\RunCustomCommandEvent;

class RunCustomAdminCommandEvent extends RunCustomCommandEvent{
	public function getHeaderType() : string{
		return 'runAdmin';
	}

	public function isAdminCommand() : bool{
		return true;
	}
}