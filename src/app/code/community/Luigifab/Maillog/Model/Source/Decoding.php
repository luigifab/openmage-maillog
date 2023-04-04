<?php
/**
 * Created V/27/01/2023
 * Updated V/27/01/2023
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

class Luigifab_Maillog_Model_Source_Decoding {

	protected $_options;

	public function toOptionArray() {

		if (empty($this->_options)) {
			$help = Mage::helper('maillog');
			$this->_options = [
				['value' => 0, 'label' => Mage::helper('adminhtml')->__('No')],
				['value' => 'sync',  'label' => $help->__('Yes - %s', 'sync')],
				['value' => 'async', 'label' => $help->__('Yes - %s', 'async')],
				['value' => 'auto',  'label' => $help->__('Yes - %s', 'auto')],
			];
		}

		return $this->_options;
	}
}