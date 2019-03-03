<?php
/**
 * Created W/11/11/2015
 * Updated S/16/02/2019
 *
 * Copyright 2015-2019 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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

		if (in_array($column->getId(), array('action'))) {

			$infos = Mage::getVersionInfo();
			$words = explode(' ', $column->getFilter()->getValue());

			if (($infos['minor'] == 5) || version_compare(Mage::getVersion(), '1.7', '>=')) { // Magento 1.5 ou Magento 1.7 et +
				$a = array('action', 'request', 'response');
				foreach ($words as $word) {
					$b = array_fill(0, count($a), array('like' => '%'.$word.'%'));
					$this->getCollection()->addFieldToFilter($a, $b);
				}
			}
			else {
				foreach ($words as $word)
					$this->getCollection()->addFieldToFilter('action', array('like' => '%'.$word.'%'));
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
			'width'     => '80px'
		));

		$infos = Mage::getVersionInfo();
		if (($infos['minor'] == 5) || version_compare(Mage::getVersion(), '1.7', '>=')) { // Magento 1.5 ou Magento 1.7 et +
			$this->addColumn('action', array(
				'header'    => $this->__('Action / Request / Response *'),
				'index'     => 'action',
				'frame_callback' => array($this, 'decorateDetails'),
				'sortable'  => false
			));
		}
		else {
			$this->addColumn('action', array(
				'header'    => $this->__('Action').' *',
				'index'     => 'action',
				'frame_callback' => array($this, 'decorateDetails'),
				'sortable'  => false
			));
		}

		//$this->addColumn('created_at', array(
		//	'header'    => $this->__('Created At'),
		//	'index'     => 'created_at',
		//	'type'      => 'datetime',
		//	'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
		//	'align'     => 'center',
		//	'width'     => '150px',
		//	'frame_callback' => array($this, 'decorateDate')
		//));

		$this->addColumn('sync_at', array(
			'header'    => $this->__('Synchronized At'),
			'index'     => 'sync_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => array($this, 'decorateDate')
		));

		$this->addColumn('status', array(
			'header'    => $this->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => array(
				'pending' => $this->__('Pending'),
				'running' => $this->__('Running'),
				'success' => $this->__('Success'),
				'error'   => $this->helper('maillog')->_('Error'),
				'notsync' => $this->__('Unsent')
			),
			'width'     => '125px',
			'frame_callback' => array($this, 'decorateStatus'),
			'sortable'  => false
		));

		return parent::_prepareColumns();
	}


	public function getRowClass($row) {
		return '';
	}

	public function getRowUrl($row) {
		return false;
	}

	public function decorateStatus($value, $row, $column, $isExport) {
		return sprintf('<span class="maillog-status grid-%s">%s</span>', $row->getData('status'), $value);
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return (!in_array($row->getData($column->getIndex()), array('', '0000-00-00 00:00:00', null))) ? $value : '';
	}

	public function decorateDetails($value, $row, $column, $isExport) {

		$text = sprintf('<div>By <em>%s</em> for <em>%s</em> %s</div>',
			$row->getData('user'), $row->getData('action'),
			!empty($duration = $this->helper('maillog')->getHumanDuration($row)) ? ' / duration '.$duration : '');

		if (!empty($data = $row->getData('request')))
			$text .= ' == request == <div class="details">'.nl2br(htmlspecialchars($data)).'</div>';

		if (!empty($data = $row->getData('response'))) {
			if (stripos($data, 'STOP! ') !== false) {
				$text .= ' == response == <br />'.nl2br(htmlspecialchars($data));
			}
			else {
				$text .= ' == response == <div class="details">'.nl2br(htmlspecialchars($data)).'</div>';
				if (Mage::getStoreConfig('maillog/sync/type') == 'dolist') {
					$url  = 'https://extranet.dolist.net/Contacts/ViewContact.aspx?m=10&amp;t=2&amp;c=';
					$text = preg_replace('#(\[memberid\] (\d+))#', '<a href="'.$url.'$2">$1</a>', $text);
				}
				else if (Mage::getStoreConfig('maillog/sync/type') == 'dolibarr') {
					$url = Mage::getStoreConfig('maillog/sync/api_url');
					$url = mb_substr($url, 0, mb_stripos($url, '/api'));
					if (mb_stripos($text, ':customer:') !== false)
						$text = preg_replace('#(\[id\] (\d+))#', '<a href="'.$url.'/comm/card.php?socid=$2">$1</a>', $text);
				}
			}
		}

		return '<div lang="mul">'.$text.'</div>';
	}


	protected function _toHtml() {
		return str_replace('class="data', 'class="adminhtml-maillog-history data', parent::_toHtml());
	}
}