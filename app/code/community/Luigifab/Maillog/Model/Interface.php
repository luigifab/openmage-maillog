<?php
/**
 * Created S/17/09/2016
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog
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

class Luigifab_Maillog_Model_Interface extends Mage_Core_Model_Abstract {

	//const UPDATE_CUSTOMER_REQUEST_TYPE = mixed;
	//const UPDATE_CUSTOMER_REQUEST_ENDPOINT = mixed;
	//const DELETE_CUSTOMER_REQUEST_TYPE = mixed;
	//const DELETE_CUSTOMER_REQUEST_ENDPOINT = mixed;

	public function getType() {
		// return string
	}

	public function getFields() {
		// return one of the following array
		//  array(array(id => field_code, name => field_name, readonly => true|false), ...)
		//  array(field_code => array(id => field_code, name => field_name, readonly => true|false), ...)
	}

	public function mapFields($object) {
		// $object is getModel('customer/customer') or getModel('customer/address') or
		//  getModel('newsletter/subscriber') or Varien_Object
		// AND it return the following array
		//  array(field_code => value, ...)
	}

	public function mapUniqueField($idField, $newMail) {
		// $idField is getStoreConfig('maillog/sync/mapping_customerid_field')
		// $newMail is true if customer has changed is email address
		// AND it return one of the following array
		//  array(field_code => value, ...)  or  array()
		return array();
	}

	public function sendRequest($type, $endPoint, $data) {
		// $type/$endPoint is one of the const
		// $data is the array created by mapFields() and mapUniqueField()
		// AND it return string
	}

	public function checkResponse($data) {
		// $data is string from sendRequest() AND it return true or false
		return true;
	}

	public function extractResponseData($data) {
		// $data is string from sendRequest() AND it return string or array()
		return $data;
	}
}