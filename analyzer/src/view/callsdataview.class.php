<?php

class CallsDataView {
	
	static function generate($data) {

		return self::asJSON($data);
	}

	static function asJSON($data) {

		return json_encode($data);
	}
}
?>
