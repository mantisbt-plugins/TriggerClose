<?php

require_once __DIR__.'/TriggerCloseApi.php';

if(PHP_SAPI == "cli") {
	TriggerCloseApi::cli();
}
