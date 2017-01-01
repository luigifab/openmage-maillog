<?php
/**
 * Created J/26/11/2015
 * Updated M/08/11/2016
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

class Luigifab_Maillog_Block_Adminhtml_Bounce_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	private $cache = array();

	public function __construct() {

		parent::__construct();

		$this->setId('maillogbounce_grid');
		$this->setDefaultSort('bounce_id');
		$this->setDefaultDir('desc');

		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(true);
		$this->setFilterVisibility(true);
		$this->setDefaultLimit(max($this->_defaultLimit, 200));
	}

	protected function _prepareCollection() {
		$this->setCollection(Mage::getResourceModel('maillog/bounce_collection'));
		return parent::_prepareCollection();
	}

	protected function _prepareColumns() {

		$this->addColumn('bounce_id', array(
			'header'    => $this->__('Id'),
			'index'     => 'bounce_id',
			'align'     => 'center',
			'width'     => '80px'
		));

		$this->addColumn('email', array(
			'header'    => $this->__('Email'),
			'index'     => 'email'
		));

		$this->addColumn('source', array(
			'header'    => $this->__('Source'),
			'index'     => 'source'
		));

		$this->addColumn('notsent', array(
			'header'    => $this->__('Email unsent'),
			'index'     => 'notsent',
			'align'     => 'left',
			'width'     => '130px'
		));

		$this->addColumn('created_at', array(
			'header'    => $this->__('Created At'),
			'index'     => 'created_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('action', array(
			'type'      => 'action',
			'align'     => 'center',
			'width'     => '55px',
			'filter'    => false,
			'sortable'  => false,
			'is_system' => true,
			'frame_callback' => array($this, 'decorateAction')
		));

		return parent::_prepareColumns();
	}


	public function getRowUrl($row) {

		$email = $row->getData('email');

		if (!array_key_exists($email, $this->cache))
			$id = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', $email)->getFirstItem()->getId();
		if (isset($id) && ($id > 0))
			$this->cache[$email] = array('id' => $id);

		return (isset($this->cache[$email])) ? $this->getUrl('*/customer/edit', $this->cache[$email]) : null;
	}

	public function decorateAction($value, $row, $column, $isExport) {

		$email = $row->getData('email');

		if (!array_key_exists($email, $this->cache))
			$id = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', $email)->getFirstItem()->getId();
		if (isset($id) && ($id > 0))
			$this->cache[$email] = array('id' => $id);

		if (isset($this->cache[$email]))
			return '<a href="'.$this->getUrl('*/customer/edit', $this->cache[$email]).'">'.$this->__('Edit').'</a>';
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}
}