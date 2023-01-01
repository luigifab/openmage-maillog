<?php
/**
 * Created W/11/11/2015
 * Updated D/11/12/2022
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

class Luigifab_Maillog_Block_Adminhtml_Sync extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$this->_controller = 'adminhtml_sync';
		$this->_blockGroup = 'maillog';
		$this->_headerText = $this->__('Customers synchronization');

		$this->_removeButton('add');

		if (Mage::getSingleton('admin/session')->isAllowed('system/config/maillog_sync')) {
			$this->_addButton('config', [
				'label'   => $this->__('Configuration'),
				'onclick' => "setLocation('".$this->getUrl('*/system_config/edit', ['section' => 'maillog_sync'])."');",
				'class'   => 'go',
			]);
		}
	}
}