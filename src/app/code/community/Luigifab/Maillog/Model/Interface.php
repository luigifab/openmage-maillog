<?php
/**
 * Created M/21/01/2020
 * Updated J/30/09/2021
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://www.luigifab.fr/openmage/maillog
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

interface Luigifab_Maillog_Model_Interface {

	public function getMapping();

	public function getFields();

	public function mapFields($object, string $group, bool $onlyAttributes = false);

	public function updateCustomer(array $data);

	public function deleteCustomer(array $data);

	public function checkResponse($data);

	public function extractResponseData($data, bool $forHistory = false);
}