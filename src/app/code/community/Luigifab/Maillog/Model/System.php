<?php
/**
 * Created M/21/01/2020
 * Updated J/21/09/2023
 *
 * Copyright 2015-2023 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

abstract class Luigifab_Maillog_Model_System implements Luigifab_Maillog_Model_Interface {

	protected $_fields = [];

	public function getMapping() {

		$values = [];
		$lines  = array_filter(preg_split('#\s+#', Mage::getStoreConfig('maillog_sync/'.$this->_code.'/mapping_config')));

		foreach ($lines as $line) {
			if (str_contains($line, ':') && (strlen($line) > 3) && ($line[0] != '#')) { // not mb_strlen
				$line = array_map('trim', explode(':', $line));
				$code = trim(array_shift($line));
				$values[$code] = $line;
			}
		}

		return $values;
	}

	public function checkResponse($data) {

		if (empty($data))
			return false;

		return (mb_stripos($data, 'HTTP/1.0 2') === 0) || (mb_stripos($data, 'HTTP/1.1 2') === 0) ||
			(mb_stripos($data, 'HTTP/2 2') === 0) || (mb_stripos($data, 'HTTP/3 2') === 0);
	}
}