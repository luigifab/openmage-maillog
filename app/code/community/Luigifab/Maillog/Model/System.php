<?php
/**
 * Created J/18/01/2018
 * Updated D/25/03/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * https://www.luigifab.info/magento/maillog
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

abstract class Luigifab_Maillog_Model_System {

	public function getType() {
		return substr(get_class($this), strrpos(get_class($this), '_') + 1);
	}


	public function getFields() { }

	public function mapFields($object) { }


	public function updateCustomer($data) { }

	public function deleteCustomer($data) { }

	public function updateCustomers($data) { }


	public function checkResponse($data) { }

	public function extractResponseData($data, $forHistory = false) { }
}