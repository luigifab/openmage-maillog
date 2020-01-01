<?php
/**
 * Created J/18/01/2018
 * Updated J/23/05/2019
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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
		return mb_substr(get_class($this), mb_strripos(get_class($this), '_') + 1);
	}


	public function getFields() { }

	public function mapFields($object) { }


	public function updateCustomer(&$data) { }

	public function deleteCustomer(&$data) { }

	public function updateCustomers(&$data) { }


	public function checkResponse($data) { }

	public function extractResponseData($data, $forHistory = false, $multiple = false) { }
}