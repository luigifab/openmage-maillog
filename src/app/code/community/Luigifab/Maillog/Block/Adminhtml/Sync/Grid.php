<?php
/**
 * Created W/11/11/2015
 * Updated S/23/12/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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
		$this->setDefaultLimit(max($this->_defaultLimit, (int) Mage::getStoreConfig('maillog/general/number')));
	}

	protected function _prepareCollection() {
		$this->setCollection(Mage::getResourceModel('maillog/sync_collection'));
		return parent::_prepareCollection();
	}

	protected function _addColumnFilterToCollection($column) {

		if ($column->getId() == 'action') {
			$words  = array_filter(explode(' ', $column->getFilter()->getValue()));
			$fields = ['action', 'request', 'response'];
			foreach ($words as $word) {
				$values = array_fill(0, count($fields), ['like' => '%'.$word.'%']);
				$this->getCollection()->addFieldToFilter($fields, $values);
			}
		}
		else {
			parent::_addColumnFilterToCollection($column);
		}

		return $this;
	}

	protected function _prepareColumns() {

		$this->addColumn('sync_id', [
			'header'    => $this->__('Id'),
			'index'     => 'sync_id',
			'align'     => 'center',
			'width'     => '80px',
		]);

		$this->addColumn('action', [
			'header'   => '[??] '.$this->__('Action / Request / Response'),
			'index'    => 'action',
			'sortable' => false,
			'frame_callback' => [$this, 'decorateDetails'],
		]);

		$this->addColumn('sync_at', [
			'header'    => $this->__('Synchronized At'),
			'index'     => 'sync_at',
			'type'      => 'datetime',
			'format'    => Mage::getSingleton('core/locale')->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
			'align'     => 'center',
			'width'     => '150px',
			'frame_callback' => [$this, 'decorateDate'],
		]);

		$this->addColumn('status', [
			'header'    => $this->__('Status'),
			'index'     => 'status',
			'type'      => 'options',
			'options'   => [
				'pending' => $this->__('Pending'),
				'running' => $this->__('Running'),
				'success' => $this->helper('maillog')->_('Success'),
				'error'   => $this->helper('maillog')->_('Error'),
				'notsync' => $this->__('Unsent'),
			],
			'width'     => '125px',
			'sortable'  => false,
			'frame_callback' => [$this, 'decorateStatus'],
		]);

		return parent::_prepareColumns();
	}


	public function getRowClass($row) {
		return '';
	}

	public function getRowUrl($row) {
		return false;
	}


	public function decorateStatus($value, $row, $column, $isExport) {
		return $isExport ? $value : sprintf('<span class="maillog-status grid-%s">%s</span>', $row->getData('status'), $value);
	}

	public function decorateDate($value, $row, $column, $isExport) {
		return in_array($row->getData($column->getIndex()), ['', '0000-00-00 00:00:00', null]) ? '' : $value;
	}

	public function decorateDetails($value, $row, $column, $isExport) {

		$helper = $this->helper('maillog');
		$action = preg_replace(
			'#update:customer:(\d+):#',
			'update:<a href="'.str_replace('999999', '\\1', $this->getUrl('*/customer/edit', ['id' => 999999])).'">customer:\1</a>:',
			$row->getData('action')
		);

		if (in_array($row->getData('sync_at'), ['', '0000-00-00 00:00:00', null])) {
			$text = sprintf(
				'<div>By <em>%s</em> for <em>%s</em> with <em>%s</em>.<br />Created at <em>%s UTC</em>.</div>',
				$row->getData('user'), $action, $row->getData('model'),
				$this->formatDate($row->getData('created_at'), Zend_Date::DATETIME_SHORT)
			);
		}
		else {
			$text = sprintf(
				'<div>By <em>%s</em> for <em>%s</em> with <em>%s</em>.<br />Created at <em>%s UTC</em> and synced at <em>%s UTC</em> %s.</div>',
				$row->getData('user'), $action, $row->getData('model'),
				$this->formatDate($row->getData('created_at'), Zend_Date::DATETIME_SHORT),
				$this->formatDate($row->getData('sync_at'), Zend_Date::DATETIME_SHORT),
				empty($duration = $helper->getHumanDuration($row->getData('duration'))) ? '' : '(duration <em>'.$duration.'</em>)'
			);
		}

		$data = $row->getData('request');
		if (!empty($data))
			$text .= ' <em>== request ==</em> <div class="details data">'.nl2br($helper->escapeEntities($data)).'</div>';

		$data = $row->getData('response');
		if (!empty($data)) {
			if (mb_stripos($data, 'STOP! ') === 0) // old way < 5.8.0 @todo
				$text .= ' <em>== exception ==</em> <div class="details">'.nl2br($helper->escapeEntities($data)).'</div>';
			else
				$text .= ' <em>== response ==</em> <div class="details data">'.nl2br($helper->escapeEntities($data)).'</div>';
		}

		$data = $row->getData('exception');
		if (!empty($data)) {

			$data = $helper->escapeEntities($data);
			$dir  = str_contains(__FILE__, 'vendor/luigifab') ? dirname(BP) : BP;

			// @see https://github.com/luigifab/webext-openfileeditor
			$data = preg_replace_callback('#(\#\d+ )([^(]+)\((\d+)\): #', static function ($data) use ($dir) {
				return $data[1].'<span class="openfileeditor" data-file="'.$dir.$data[2].'" data-line="'.$data[3].'">'.$data[2].'</span>('.$data[3].'): ';
			}, $data);
			$data = preg_replace_callback('#  thrown in (.+) on line (\d+)#', static function ($data) use ($dir) {
				return '  thrown in <span class="openfileeditor" data-file="'.$dir.$data[1].'" data-line="'.$data[2].'">'.$data[1].'</span> on line '.$data[2];
			}, $data);

			$text .= ' <em>== exception ==</em> <div class="details exception">'.nl2br($data).'</div>';
		}

		return '<div lang="mul">'.$text.'</div>';
	}


	protected function _toHtml() {
		return str_replace(
			['class="data', '[??] '],
			['class="adminhtml-maillog-history data', '<span style="opacity:0.7;" title="'.$this->helper('maillog')->escapeEntities($this->__('Search for one or more words in action or request or response. Separate words by a space.'), true).'">[?]</span> '],
			parent::_toHtml()
		);
	}
}