<?php
/**
 * Created D/22/03/2015
 * Updated D/04/03/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * https://www.luigifab.info/magento/maillog
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
		$this->setDefaultLimit(max($this->_defaultLimit, intval(Mage::getStoreConfig('maillog/general/number'))));
	}

	protected function _prepareCollection() {
		$this->setCollection(Mage::getResourceModel('maillog/email_collection'));
		return parent::_prepareCollection();
	}

	protected function _addColumnFilterToCollection($column) {

		if (in_array($column->getId(), array('mail_recipients', 'mail_subject'))) {
			$words = explode(' ', $column->getFilter()->getValue());
			foreach ($words as $word)
				$this->getCollection()->addFieldToFilter($column->getId(), array('like' => '%'.$word.'%'));
		}
		else {
			parent::_addColumnFilterToCollection($column);
		}

		return $this;
	}

	protected function _prepareColumns() {

		$this->addColumn('email_id', array(
			'header'    => $this->__('Id'),
			'index'     => 'email_id',
			'align'     => 'center',
			'width'     => '80px'
		));

		$this->addColumn('type', array(
			'header'    => $this->__('Type'),
			'index'     => 'type',
			'type'      => 'options',
			'options'   => $this->helper('maillog')->getAllTypes(),
			'align'     => 'center',
			'width'     => '100px'
		));

		$this->addColumn('mail_recipients', array(
			'header'    => $this->__('Recipient(s)'),
			'index'     => 'mail_recipients',
			'frame_callback' => array($this, 'decorateRecipients')
		));

		$this->addColumn('mail_subject', array(
			'header'    => $this->__('Subject'),
			'index'     => 'mail_subject'
		));

		$this->addColumn('size', array(
			'header'    => $this->__('Size'),
			'index'     => 'size',
			'type'      => 'number',
			'width'     => '85px',
			'filter'    => false,
			'sortable'  => false,
			'frame_callback' => array($this, 'decorateSize')
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

		$this->addColumn('sent_at', array(
			'header'    => $this->__('Sent At'),
			'index'     => 'sent_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => array($this, 'decorateDate')
		));

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
				'sending' => $this->__('Sending'),
				'sent'    => $this->__('Sent'),
				'read'    => $this->__('Open/read'),
				'error'   => $this->helper('maillog')->_('Error'),
				'notsent' => $this->__('Unsent'),
				'bounce'  => $this->__('Blocked')
			),
			'width'     => '125px',
			'frame_callback' => array($this, 'decorateStatus')
		));

		$this->addColumn('action', array(
			'type'      => 'action',
			'getter'    => 'getId',
			'actions'   => array(
				array(
					'caption' => $this->__('View'),
					'url'     => (!empty($this->back['bid'])) ?
						array('base' => '*/maillog_history/view/back/'.$this->back['back'].'/bid/'.$this->back['bid']) :
						array('base' => '*/maillog_history/view'),
					'field'   => 'id'
				)
			),
			'align'     => 'center',
			'width'     => '55px',
			'filter'    => false,
			'sortable'  => false,
			'is_system' => true
		));

		// filtrage des colonnes
		// pour les grilles des commandes et des clients ou pour la grille principale
		if (!empty($this->back['bid'])) {
			unset($this->_columns['type']);
			unset($this->_columns['mail_recipients']);
			unset($this->_columns['size']);
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


	public function getRowClass($row) {
		return (!empty($row->getData('mail_parts'))) ? 'parts' : '';
	}

	public function getRowUrl($row) {
		return $this->getUrl('*/*/view', array('id' => $row->getId()));
	}

	public function decorateStatus($value, $row, $column, $isExport) {
		return sprintf('<span class="maillog-status grid-%s">%s</span>', $row->getData('status'), $value);
	}

	public function decorateDuration($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanDuration($row);
	}

	public function decorateSize($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getNumberToHumanSize($row->getData('size'));
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateRecipients($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanEmailAddress($row->getData('mail_recipients'));
	}


	public function _toHtml() {
		return str_replace('class="data', 'class="adminhtml-maillog-history data', parent::_toHtml());
	}
}