<?php
/**
 * Created D/22/03/2015
 * Updated M/23/11/2021
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Block_Adminhtml_History_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	public function __construct() {

		parent::__construct();

		$this->setId('maillog_grid');
		$this->setDefaultSort('email_id');
		$this->setDefaultDir('desc');

		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(true);
		$this->setFilterVisibility(true);
		$this->setDefaultLimit(max($this->_defaultLimit, (int) Mage::getStoreConfig('maillog/general/number')));

		// embed tab
		if (!is_object(Mage::registry('current_order')) && ($this->getRequest()->getParam('back') == 'order')) {
			$order = Mage::getModel('sales/order')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_order', $order);
		}
		else if (!is_object(Mage::registry('current_customer')) && ($this->getRequest()->getParam('back') == 'customer')) {
			$customer = Mage::getModel('customer/customer')->load($this->getRequest()->getParam('bid'));
			Mage::register('current_customer', $customer);
		}

		if (is_object($order = Mage::registry('current_order'))) {
			$this->setId('maillog_order_grid_'.$order->getId());
			$this->_backUrl = ['back' => 'order', 'bid' => $order->getId()];
			$this->_defaultFilter = ['mail_subject' => $order->getData('increment_id')];
		}
		else if (is_object($customer = Mage::registry('current_customer'))) {
			$this->setId('maillog_customer_grid_'.$customer->getId());
			$this->_backUrl = ['back' => 'customer', 'bid' => $customer->getId()];
			$this->_defaultFilter = ['mail_recipients' => $customer->getData('email')];
		}
	}

	protected function _prepareCollection() {

		$collection = Mage::getResourceModel('maillog/email_collection');

		// embed tab
		if (is_object($order = Mage::registry('current_order'))) {
			//$collection->addFieldToFilter('mail_recipients', ['like' => '%'.$order->getData('customer_email').'%']);
			$collection->addFieldToFilterWithMatch('mail_recipients', $order->getData('customer_email'));
			// non car on veut pouvoir enlever ce filtre depuis le back-office
			//$collection->addFieldToFilter('mail_subject', ['like' => '%'.$order->getData('increment_id').'%']);
			//$collection->addFieldToFilterWithMatch('mail_subject', $order->getData('increment_id'));
		}
		else if (is_object($customer = Mage::registry('current_customer'))) {
			//$collection->addFieldToFilter('mail_recipients', ['like' => '%'.$customer->getData('email').'%']);
			$collection->addFieldToFilterWithMatch('mail_recipients', $customer->getData('email'));
		}

		$this->setCollection($collection);
		return parent::_prepareCollection();
	}

	protected function _addColumnFilterToCollection($column) {

		if (in_array($column->getId(), ['mail_recipients', 'mail_subject'])) {
			$words = explode(' ', $column->getFilter()->getValue());
			foreach ($words as $word) {
				//$this->getCollection()->addFieldToFilter($column->getId(), ['like' => '%'.$word.'%']);
				$this->getCollection()->addFieldToFilterWithMatch($column->getId(), $word);
			}
		}
		else {
			parent::_addColumnFilterToCollection($column);
		}

		return $this;
	}

	protected function _prepareColumns() {

		if (!empty($this->getRequest()->getParam('test'))) {
			$this->addColumn('choice', [
				'header_css_class' => 'a-center',
				'index'      => 'email_id',
				'type'       => 'checkbox',
				'values'     => $this->getChoices(true),
				'field_name' => 'choice',
				'align'      => 'center',
				'width'      => '75px',
				'sortable'   => false
			]);
			$this->addColumn('position', [
				'index'      => 'position',
				'name'       => 'position',
				'align'      => 'left',
				'width'      => '75px',
				'editable'   => true,
				'filter'     => false,
				'sortable'   => false,
				'validate_class' => 'validate-number'
			]);
		}

		$this->addColumn('email_id', [
			'header'    => $this->__('Id'),
			'index'     => 'email_id',
			'align'     => 'center',
			'width'     => '80px'
		]);

		$this->addColumn('type', [
			'header'    => $this->__('Type'),
			'index'     => 'type',
			'type'      => 'options',
			'options'   => $this->helper('maillog')->getAllTypes(),
			'align'     => 'center',
			'width'     => '100px'
		]);

		$this->addColumn('mail_recipients', [
			'header'    => $this->__('Recipient(s)').' *',
			'index'     => 'mail_recipients',
			'frame_callback' => [$this, 'decorateRecipients']
		]);

		$this->addColumn('mail_subject', [
			'header'    => $this->__('Subject').' *',
			'index'     => 'mail_subject',
			'frame_callback' => [$this, 'decorateSubject']
		]);

		$this->addColumn('size', [
			'header'    => $this->__('Size'),
			'index'     => 'size',
			'type'      => 'number',
			'width'     => '85px',
			'filter'    => false,
			'sortable'  => false,
			'frame_callback' => [$this, 'decorateSize']
		]);

		$this->addColumn('created_at', [
			'header'    => $this->__('Created At'),
			'index'     => 'created_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => [$this, 'decorateDate']
		]);

		$this->addColumn('sent_at', [
			'header'    => $this->__('Sent At'),
			'index'     => 'sent_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => [$this, 'decorateDate']
		]);

		$this->addColumn('duration', [
			'header'    => $this->__('Duration'),
			'index'     => 'duration',
			'align'     => 'center',
			'width'     => '60px',
			'filter'    => false,
			'sortable'  => false,
			'frame_callback' => [$this, 'decorateDuration']
		]);

		$this->addColumn('status', [
			'header'    => $this->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => [
				'pending' => $this->__('Pending'),
				'sending' => $this->__('Sending'),
				'sent'    => $this->__('Sent'),
				'read'    => $this->__('Open/read'),
				'error'   => $this->helper('maillog')->_('Error'),
				'notsent' => $this->__('Unsent'),
				'bounce'  => $this->__('Blocked')
			],
			'width'     => '125px',
			'frame_callback' => [$this, 'decorateStatus']
		]);

		$this->addColumn('action', [
			'type'      => 'action',
			'getter'    => 'getId',
			'actions'   => [
				[
					'caption' => $this->__('View'),
					'url'     => (!empty(Mage::registry('current_order')) || !empty(Mage::registry('current_customer'))) ?
						['base' => '*/maillog_history/view/back/'.$this->_backUrl['back'].'/bid/'.$this->_backUrl['bid']] :
						['base' => '*/maillog_history/view'],
					'field'   => 'id'
				]
			],
			'align'     => 'center',
			'width'     => '55px',
			'filter'    => false,
			'sortable'  => false,
			'is_system' => true
		]);

		// embed tab (filtrage des colonnes)
		if (!empty(Mage::registry('current_order')) || !empty(Mage::registry('current_customer'))) {
			unset($this->_columns['type'], $this->_columns['mail_recipients'], $this->_columns['size']);
		}
		else {
			if (count($this->getColumn('type')->getData('options')) < 1)
				unset($this->_columns['type']);
			if (!Mage::getStoreConfigFlag('maillog/general/created'))
				unset($this->_columns['created_at']);
			if (!Mage::getStoreConfigFlag('maillog/general/subject'))
				unset($this->_columns['mail_subject']);
			if (!Mage::getStoreConfigFlag('maillog/general/size'))
				unset($this->_columns['size']);
		}

		return parent::_prepareColumns();
	}


	public function getChoices(bool $onlyIds = false) {
		if (is_array($this->getRequest()->getPost('choice')))
			return $this->getRequest()->getPost('choice');
		return $onlyIds ? [67667] : [67667 => ['position' => 5]];
	}

	public function getId() {

		// embed tab
		if (is_object($order = Mage::registry('current_order')))
			return 'maillog_order_grid_'.$order->getId();
		if (is_object($customer = Mage::registry('current_customer')))
			return 'maillog_customer_grid_'.$customer->getId();

		return parent::getId();
	}

	public function getRowClass($row) {
		return empty($row->getData('mail_parts')) ? '' : 'parts';
	}

	public function getGridUrl() {

		// embed tab
		if (!empty(Mage::registry('current_order')) || !empty(Mage::registry('current_customer'))) {
			$query = empty($query = getenv('QUERY_STRING')) ? '' : '?'.$query;
			return $this->getUrl('*/maillog_history/index', $this->_backUrl).$query;
		}

		return parent::getGridUrl();
	}

	public function getRowUrl($row) {

		// embed tab
		if (!empty(Mage::registry('current_order')) || !empty(Mage::registry('current_customer'))) {
			$params = array_merge($this->_backUrl, ['id' => $row->getId()]);
			return empty($this->getRequest()->getParam('test')) ? $this->getUrl('*/maillog_history/view', $params) : false;
		}

		return empty($this->getRequest()->getParam('test')) ? $this->getUrl('*/*/view', ['id' => $row->getId()]) : false;
	}


	public function decorateStatus($value, $row, $column, $isExport) {
		return $isExport ? $value : sprintf('<span class="maillog-status grid-%s">%s</span>', $row->getData('status'), $value);
	}

	public function decorateDuration($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanDuration($row->getData('duration'));
	}

	public function decorateSize($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getNumberToHumanSize($row->getData('size'));
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return in_array($row->getData($column->getIndex()), ['', '0000-00-00 00:00:00', null]) ? '' : $value;
	}

	public function decorateRecipients($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanEmailAddress($row->getData('mail_recipients'));
	}

	public function decorateSubject($value, $row, $column, $isExport) {
		return $row->getSubject();
	}


	protected function _toHtml() {
		return str_replace('class="data', 'class="adminhtml-maillog-history data', parent::_toHtml());
	}
}