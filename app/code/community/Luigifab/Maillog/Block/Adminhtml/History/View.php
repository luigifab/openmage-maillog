<?php
/**
 * Created D/22/03/2015
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

class Luigifab_Maillog_Block_Adminhtml_History_View extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$email  = Mage::registry('current_email');
		$params = array('id' => $email->getId(), 'back' => $this->getRequest()->getParam('back'), 'bid' => $this->getRequest()->getParam('bid'));

		$this->_controller = 'adminhtml_history';
		$this->_blockGroup = 'emaillog';
		$this->_headerText = $this->__('Email number %d - %s', $email->getId(), htmlentities($email->getMailSubject()));

		$this->_removeButton('add');

		$this->_addButton('back', array(
			'label'   => $this->__('Back'),
			'onclick' => "setLocation('".$this->getBackUrl()."');",
			'class'   => 'back'
		));

		$this->_addButton('remove', array(
			'label'   => $this->__('Remove'),
			'onclick' => "deleteConfirm('".addslashes($this->__('Are you sure?'))."', '".$this->getUrl('*/*/delete', $params)."');",
			'class'   => 'delete'
		));

		if (Mage::getStoreConfigFlag('maillog/general/send') && !in_array($email->getStatus(), array('notsent', 'bounce'))) {
			$this->_addButton('resend', array(
				'label'   => $this->__('Resend email'),
				'onclick' => "deleteConfirm('".addslashes($this->__('Are you sure?'))."', '".$this->getUrl('*/*/resend', $params)."');",
				'class'   => 'add'
			));
		}

		$this->_addButton('view', array(
			'label'   => $this->__('View'),
			'onclick' => "window.open('".$email->getEmbedUrl('index', array('nomark' => 1, '_store' => Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId()))."');",
			'class'   => 'go'
		));
	}

	public function getGridHtml() {

		$help  = $this->helper('maillog');
		$email = Mage::registry('current_email');
		$date  = Mage::getSingleton('core/locale'); //date($date, $format, $locale = null, $useTimezone = null)

		$data  = str_replace(
			array('adminhtml/base/default/images/luigifab/maillog', '<body style="'),
			array('frontend/default/default/images/luigifab/maillog', '<body style="overflow-y:hidden; '),
			$email->toHtml(true) // true pour nomark
		);

		if ($email->getStatus() === 'read')
			$status = $this->__('Open/read');
		else if ($email->getStatus() === 'error')
			$status = $this->helper('maillog')->_('Error');
		else if ($email->getStatus() === 'notsent')
			$status = $this->__('Unsent');
		else if ($email->getStatus() === 'bounce')
			$status = $this->__('<span>Blocked</span> Invalid (bounce)');
		else
			$status = $this->__(ucfirst($email->getStatus()));

		$html = array();
		$html[] = '<div class="content">';
		$html[] = '<div>';
		$html[] = '<ul>';
		$html[] = '<li>'.$this->__('Created At: %s', $date->date($email->getCreatedAt(), Zend_Date::ISO_8601)).'</li>';

		if (!in_array($email->getSentAt(), array('', '0000-00-00 00:00:00', null))) {

			$html[] = '<li><strong>'.$this->__('Sent At: %s', $date->date($email->getSentAt(), Zend_Date::ISO_8601)).'</strong></li>';

			$duration = $help->getHumanDuration($email);
			if (strlen($duration) > 0)
				$html[] = '<li>'.$this->__('Duration: %s', $duration).'</li>';
		}

		$html[] = '</ul>';
		$html[] = '<ul>';
		$html[] = '<li><strong class="status-'.$email->getStatus().'">'.$this->__('Status: <span>%s</span>', $status).'</strong></li>';

		if ($email->getSize() > 0)
			$html[] = '<li>'.$this->__('Approximate size: %s', $help->getNumberToHumanSize($email->getSize())).'</li>';

		$html[] = '<li>'.$this->__('Recipient(s): %s', $help->getHumanRecipients($email)).'</li>';
		$html[] = '</ul>';
		$html[] = '</div>';

		$html[] = '<iframe srcdoc="data:text/html;base64,'.base64_encode('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>...</body></html>').'" type="text/html" onload="this.contentDocument.body.parentNode.innerHTML = decodeURIComponent(escape(window.atob(this.firstChild.nodeValue))); this.style.height = (this.contentDocument.body.scrollHeight + 40) + \'px\';">'.base64_encode($data).'</iframe>';
		$html[] = '</div>';

		return implode("\n", $html);
	}

	private function getBackUrl() {

		if ($this->getRequest()->getParam('back') === 'order')
			return $this->getUrl('*/sales_order/view',
				array('order_id' => $this->getRequest()->getParam('bid'), 'active_tab' => 'maillog_grid_order'));
		else if ($this->getRequest()->getParam('back') === 'customer')
			return $this->getUrl('*/customer/edit',
				array('id' => $this->getRequest()->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_grid_customer'));
		else
			return $this->getUrl('*/*/index');
	}

	protected function _prepareLayout() {
		//return parent::_prepareLayout();
	}
}