<?php
/**
 * Created W/11/11/2015
 * Updated V/11/11/2016
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

class Luigifab_Maillog_Block_Adminhtml_Sync_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	private $cache = array();

	public function __construct() {

		parent::__construct();

		$this->setId('maillogsync_grid');
		$this->setDefaultSort('sync_id');
		$this->setDefaultDir('desc');

		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(true);
		$this->setFilterVisibility(true);
		$this->setDefaultLimit(max($this->_defaultLimit, 200));
	}

	protected function _prepareCollection() {
		$this->setCollection(Mage::getResourceModel('maillog/sync_collection'));
		return parent::_prepareCollection();
	}

	protected function _prepareColumns() {

		$this->addColumn('sync_id', array(
			'header'    => $this->__('Id'),
			'index'     => 'sync_id',
			'align'     => 'center',
			'width'     => '80px',
			'frame_callback' => array($this, 'decorateId')
		));

		$this->addColumn('email', array(
			'header'    => $this->__('Email'),
			'index'     => 'email'
		));

		$this->addColumn('details', array(
			'header'    => $this->__('Details'),
			'index'     => 'details',
			'frame_callback' => array($this, 'decorateDetails')
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

		//$this->addColumn('sync_at', array(
		//	'header'    => $this->__('Synchronized At'),
		//	'index'     => 'sync_at',
		//	'type'      => 'datetime',
		//	'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
		//	'align'     => 'center',
		//	'width'     => '150px',
		//	'frame_callback' => array($this, 'decorateDate')
		//));

		$this->addColumn('duration', array(
			'header'    => $this->__('Duration'),
			'index'     => 'duration',
			'align'     => 'center',
			'width'     => '60px',
			'filter'    => false,
			'sortable'  => false,
			'frame_callback' => array($this, 'decorateDuration')
		));

		$this->addColumn('status', array(
			'header'    => $this->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => array(
				'pending' => $this->__('Pending'),
				'success' => $this->__('Success'),
				'error'   => $this->helper('maillog')->_('Error')
			),
			'align'     => 'status',
			'width'     => '125px',
			'frame_callback' => array($this, 'decorateStatus')
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


	public function getRowClass($row) {
		return (intval($this->getRequest()->getParam('id', 0)) === intval($row->getData('sync_id'))) ? 'active' : '';
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

	public function decorateStatus($value, $row, $column, $isExport) {
		return '<span class="grid-'.$row->getData('status').'">'.$value.'</span>';
	}

	public function decorateDuration($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanDuration($row, 'sync_at');
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateId($value, $row, $column, $isExport) {
		return (intval($this->getRequest()->getParam('id', 0)) === intval($row->getData('sync_id'))) ?
			'<strong>'.$row->getData('sync_id').'</strong>' : $row->getData('sync_id');
	}

	public function decorateDetails($value, $row, $column, $isExport) {

		$text = nl2br(htmlspecialchars($row->getData('details')));

		// http://stackoverflow.com/a/19907844
		// on remplace le deuxième br s'il y en a au moins 7 par la div (d'où le 2-1)
		if (substr_count($text, '<br />') >= 7) {
			preg_match_all('#<br \/>#', $text, $matches, PREG_OFFSET_CAPTURE);
			return substr_replace($text, '<div class="details2">', $matches[0][2-1][1], strlen('<br \/>')).'</div>';
		}
		else if (substr_count($text, '<br />') >= 2) {
			preg_match_all('#<br \/>#', $text, $matches, PREG_OFFSET_CAPTURE);
			return substr_replace($text, '<div class="details1">', $matches[0][2-1][1], strlen('<br \/>')).'</div>';
		}

		return $text;
	}


	public function _toHtml() {
		return str_replace('class="data', 'class="adminhtml-maillog-history data', parent::_toHtml());
	}
}