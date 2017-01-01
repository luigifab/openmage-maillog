<?php
/**
 * Created S/14/11/2015
 * Updated W/09/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Bounce extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {

		if (true) {
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('maillog_read');

			$select = $read->select()
				->from('information_schema.TABLES', 'table_rows')
				->where('table_name = ?', $resource->getTableName('luigifab_maillog_bounce'));

			$element->setValue(intval($read->fetchOne($select)));

			return '<span id="'.$element->getHtmlId().'">'.$this->__('~%d (is very approximate)', $element->getValue()).'</span>';
		}
		else {
			return '<span id="'.$element->getHtmlId().'">'.Mage::getResourceModel('maillog/bounce_collection')->getSize().'</span>';
		}
	}
}