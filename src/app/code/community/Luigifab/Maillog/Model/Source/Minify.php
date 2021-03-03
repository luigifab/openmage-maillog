<?php
/**
 * Created M/24/03/2015
 * Updated S/14/11/2020
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Model_Source_Minify {

	public function toOptionArray() {

		if (empty($this->_options)) {

			$help = Mage::helper('maillog');
			$tidy = (extension_loaded('tidy') && class_exists('tidy', false)) ?
				date('Ymd', strtotime(tidy_get_release())) : $help->__('not available');

			$this->_options = [
				['value' => 0, 'label' => Mage::helper('adminhtml')->__('No')],
				['value' => 1, 'label' => $help->__('Yes with PHP-TIDY (%s)', $tidy)]
			];
		}

		return $this->_options;
	}
}