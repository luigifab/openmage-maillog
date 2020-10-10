<?php
/**
 * Created W/11/11/2015
 * Updated J/08/10/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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

class Luigifab_Maillog_Block_Adminhtml_Sync extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$this->_controller = 'adminhtml_sync';
		$this->_blockGroup = 'maillog';
		$this->_headerText = sprintf('%s <span>%s</span>', $this->__('Customers synchronization'), $this->helper('maillog')->getCronStatus());

		$this->_removeButton('add');

		$enabled = false;
		$this->_addButton('syncall', [
			'label'   => $this->__('Synchronize all customers'),
			'onclick' => $enabled ? "maillog.confirm('".addslashes($this->__('Are you sure?'))."', '".addslashes($this->__('Be careful, all customers will be synchronized.'))."', '".$this->getUrl('*/*/syncall')."')" : '',
			'class'   => $enabled ? 'add' : 'add disabled'
		]);
	}
}