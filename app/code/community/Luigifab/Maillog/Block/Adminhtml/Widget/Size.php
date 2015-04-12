<?php
/**
 * Created M/24/03/2015
 * Updated W/25/03/2015
 * Version 2
 *
 * Copyright 2015 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Maillog_Block_Adminhtml_Widget_Size extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Number {

	public function render(Varien_Object $row) {

		$size = number_format($row->getData('size') / 1024, 2);
		$size = Zend_Locale_Format::toNumber($size, array('locale' => Mage::app()->getLocale()->getLocaleCode()));

		return $this->__('%s KB', $size);
	}
}