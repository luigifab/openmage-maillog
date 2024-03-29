<?php
/**
 * Created M/28/09/2021
 * Updated V/22/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Mautic extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'luigifab/maillog/mautic.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$config = $this->helper('maillog')->getConfigUnserialized('maillog_sync/mautic/mautic_config');

		if (empty($config)) {
			$config = [
				'5a' => 60,
				'5b' => 20,
				'5c' => 1000,
				'4a' => 180,
				'4b' => 20,
				'4c' => 1000,
				'3a' => 385,
				'3b' => 8,
				'3c' => 500,
				'2a' => 730,
				'2b' => 4,
				'2c' => 150,
				'1a' => 730,
				'1b' => 1,
				'1c' => 50,
			];
		}

		$this->setData('element', $element);
		$this->setData('config', $config);

		return $this->toHtml();
	}
}