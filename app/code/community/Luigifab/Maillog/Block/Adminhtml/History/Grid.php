<?php
/**
 * Created D/22/03/2015
 * Updated J/07/05/2015
 * Version 23
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

class Luigifab_Maillog_Block_Adminhtml_History_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	public function __construct() {

		parent::__construct();

		$this->setId('maillog_grid');
		$this->setDefaultSort('email_id');
		$this->setDefaultDir('DESC');

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

	protected function _prepareColumns() {

		// comptage des emails
		// et génération de la colonne type
		$emails  = Mage::getResourceModel('maillog/email_collection');
		$pending = count($emails->getItemsByColumnValue('status', 'pending'));
		$error   = count($emails->getItemsByColumnValue('status', 'error'));
		$read    = count($emails->getItemsByColumnValue('status', 'read'));
		$sent    = count($emails) - $pending - $error - $read;

		$types = $emails->getColumnValues('type');
		$types = array_combine($types, $types);
		unset($types['']);

		// définition des colonnes
		$this->addColumn('email_id', array(
			'header'    => $this->helper('adminhtml')->__('Id'),
			'index'     => 'email_id',
			'align'     => 'center',
			'width'     => '80px'
		));

		if (count($types) > 1) {
			ksort($types);
			$this->addColumn('type', array(
				'header'    => $this->helper('adminhtml')->__('Type'),
				'index'     => 'type',
				'type'      => 'options',
				'options'   => $types,
				'align'     => 'center'
			));
		}

		$this->addColumn('mail_recipients', array(
			'header'    => $this->__('Recipient(s)'),
			'index'     => 'mail_recipients',
			'frame_callback' => array($this, 'decorateRecipients')
		));

		if (Mage::getStoreConfig('maillog/general/subject') === '1') {
			$this->addColumn('mail_subject', array(
				'header'    => $this->__('Subject'),
				'index'     => 'mail_subject'
			));
		}

		if (Mage::getStoreConfig('maillog/general/size') === '1') {
			$this->addColumn('size', array(
				'header'    => $this->__('Size'),
				'index'     => 'size',
				'type'      => 'number',
				'width'     => '80px',
				'filter'    => false,
				'sortable'  => false,
				'frame_callback' => array($this, 'decorateSize')
			));
		}

		$this->addColumn('created_at', array(
			'header'    => $this->__('Created At'),
			'index'     => 'created_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('sent_at', array(
			'header'    => $this->__('Sent At'),
			'index'     => 'sent_at',
			'type'      => 'datetime',
			'align'     => 'center',
			'width'     => '180px',
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
			'header'    => $this->helper('adminhtml')->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => array(
				'pending' => $this->__('Pending (%d)', $pending),
				'sent'    => $this->__('Sent (%d)', $sent),
				'read'    => $this->__('Open/read (%d)', $read),
				'error'   => $this->__('Error (%d)', $error)
			),
			'align'     => 'status',
			'width'     => '125px',
			'frame_callback' => array($this, 'decorateStatus')
		));

		$this->addColumn('action', array(
			'type'      => 'action',
			'getter'    => 'getId',
			'actions'   => array(
				array(
					'caption' => $this->helper('adminhtml')->__('View'),
					'url'     => array('base' => '*/*/view'),
					'field'   => 'id'
				)
			),
			'align'     => 'center',
			'width'     => '55px',
			'filter'    => false,
			'sortable'  => false,
			'is_system' => true
		));

		return parent::_prepareColumns();
	}


	public function getRowClass($row) {
		return '';
	}

	public function getRowUrl($row) {
		return $this->getUrl('*/*/view', array('id' => $row->getId()));
	}

	public function decorateStatus($value, $row, $column, $isExport) {

		$status = (strpos($value, ' (') !== false) ? substr($value, 0, strpos($value, ' (')) : $value;
		return '<span class="grid-'.$row->getData('status').'">'.trim($status).'</span>';
	}

	public function decorateDuration($value, $row, $column, $isExport) {

		if (!in_array($row->getData('created_at'), array('', '0000-00-00 00:00:00', null)) &&
		    !in_array($row->getData('sent_at'), array('', '0000-00-00 00:00:00', null))) {

			$data = strtotime($row->getData('sent_at')) - strtotime($row->getData('created_at'));
			$minutes = intval($data / 60);
			$seconds = intval($data % 60);

			if ($data > 599)
				$data = '<strong>'.(($seconds > 9) ? $minutes.':'.$seconds : $minutes.':0'.$seconds).'</strong>';
			else if ($data > 59)
				$data = '<strong>'.(($seconds > 9) ? '0'.$minutes.':'.$seconds : '0'.$minutes.':0'.$seconds).'</strong>';
			else if ($data > 0)
				$data = ($seconds > 9) ? '00:'.$data : '00:0'.$data;
			else
				$data = '&lt; 1';

			return $data;
		}
	}

	public function decorateSize($value, $row, $column, $isExport) {

		if ($row->getData('size') < 1) {
			return '';
		}
		else if (($row->getData('size') / 1024) < 1024) {
			$size = number_format($row->getData('size') / 1024, 2);
			$size = Zend_Locale_Format::toNumber($size, array('locale' => Mage::app()->getLocale()->getLocaleCode()));
			return $this->__('%s KB', $size);
		}
		else {
			$size = number_format($row->getData('size') / 1024 / 1024, 2);
			$size = Zend_Locale_Format::toNumber($size, array('locale' => Mage::app()->getLocale()->getLocaleCode()));
			return $this->__('%s MB', $size);
		}
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateRecipients($value, $row, $column, $isExport) {
		return htmlspecialchars(str_replace(array('<','>','&lt;','&gt;',','), array('(',')','(',')',', '), $row->getData('mail_recipients')));
	}
}