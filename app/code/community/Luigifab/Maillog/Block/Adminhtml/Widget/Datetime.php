<?php
/**
 * Created D/22/03/2015
 * Updated D/22/03/2015
 * Version 1
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

class Luigifab_Maillog_Block_Adminhtml_Widget_Datetime extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Datetime {

	public function render(Varien_Object $row) {
		return (!in_array($this->_getValue($row), array('', '0000-00-00 00:00:00', null))) ? parent::render($row) : '';
	}
}