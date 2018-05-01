<?php
/**
 * Created W/11/11/2015
 * Updated J/29/03/2018
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

class Luigifab_Maillog_Block_Adminhtml_Sync_Grid extends Mage_Adminhtml_Block_Widget_Grid {

	private $cache = array();

	public function __construct() {

		parent::__construct();

		$this->setId('maillog_sync_grid');
		$this->setDefaultSort('sync_id');
		$this->setDefaultDir('desc');

		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(true);
		$this->setFilterVisibility(true);
		$this->setDefaultLimit(max($this->_defaultLimit, intval(Mage::getStoreConfig('maillog/general/number'))));
	}

	protected function _prepareCollection() {
		$this->setCollection(Mage::getResourceModel('maillog/sync_collection'));
		return parent::_prepareCollection();
	}

	protected function _addColumnFilterToCollection($column) {

		if (in_array($column->getId(), array('details'))) {
			$infos = Mage::getVersionInfo();
			$words = explode(' ', $column->getFilter()->getValue());
			if (version_compare(Mage::getVersion(), '1.7', '>=') || ($infos['minor'] == 5)) { // Magento 1.5 ou Magento 1.7 et +
				foreach ($words as $word)
					$this->getCollection()->addFieldToFilter(array('email', 'details'),
						array(array('like' => '%'.$word.'%'), array('like' => '%'.$word.'%')));
			}
			else {
				foreach ($words as $word)
					$this->getCollection()->addFieldToFilter('details', array('like' => '%'.$word.'%'));
			}
		}
		else {
			parent::_addColumnFilterToCollection($column);
		}

		return $this;
	}

	protected function _prepareColumns() {

		$this->addColumn('sync_id', array(
			'header'    => $this->__('Id'),
			'index'     => 'sync_id',
			'align'     => 'center',
			'width'     => '80px',
			'frame_callback' => array($this, 'decorateId')
		));

		$infos = Mage::getVersionInfo();
		$this->addColumn('details', array(
			'header'    => (version_compare(Mage::getVersion(), '1.7', '>=') || ($infos['minor'] == 5)) ?
				$this->__('Email').' / '.$this->__('Details') : $this->__('Details'),
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
				'running' => $this->__('Running'),
				'success' => $this->__('Success'),
				'error'   => $this->helper('maillog')->_('Error')
			),
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
		return '';
	}

	public function getRowUrl($row) {

		$email = $row->getData('email');

		if (!array_key_exists($email, $this->cache))
			$id = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', $email)->getFirstItem()->getId();
		if (!empty($id))
			$this->cache[$email] = array('id' => $id);

		return (!empty($this->cache[$email])) ? $this->getUrl('*/customer/edit', $this->cache[$email]) : false;
	}

	public function decorateAction($value, $row, $column, $isExport) {
		return (!empty($url = $this->getRowUrl($row))) ? sprintf('<a href="%s">%s</a>', $url, $this->__('View')) : '';
	}

	public function decorateStatus($value, $row, $column, $isExport) {
		return sprintf('<span class="maillog-status grid-%s">%s</span>', $row->getData('status'), $value);
	}

	public function decorateDuration($value, $row, $column, $isExport) {
		return $this->helper('maillog')->getHumanDuration($row, 'sync_at');
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateId($value, $row, $column, $isExport) {
		return (intval($this->getRequest()->getParam('id', 0)) === intval($row->getData('sync_id'))) ?
			sprintf('<strong>%s</strong>', $value) : $value;
	}

	public function decorateDetails($value, $row, $column, $isExport) {

		$text = 'For '.$row->getData('email').' '.$row->getData('store_id').' / '.nl2br(htmlspecialchars($row->getData('details')));

		// on remplace le second br s'il y en a au moins 8 (d'où le 2-1)
		// https://stackoverflow.com/a/19907844
		if (substr_count($text, '<br />') >= 8) {
			preg_match_all('#<br \/>#', $text, $matches, PREG_OFFSET_CAPTURE);
			return substr_replace($text, '<div class="details2">', $matches[0][2-1][1], strlen('<br \/>')).'</div>';
		}
		else if (substr_count($text, '<br />') >= 3) {
			preg_match_all('#<br \/>#', $text, $matches, PREG_OFFSET_CAPTURE);
			return substr_replace($text, '<div class="details1">', $matches[0][2-1][1], strlen('<br \/>')).'</div>';
		}

		return $text;
	}


	public function _toHtml() {
		return str_replace('class="data', 'class="adminhtml-maillog-history data', parent::_toHtml());
	}
}