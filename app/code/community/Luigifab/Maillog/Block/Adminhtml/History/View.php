<?php
/**
 * Created D/22/03/2015
 * Updated D/03/02/2019
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

class Luigifab_Maillog_Block_Adminhtml_History_View extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$object = Mage::registry('current_email');
		$params = array('id' => $object->getId(), 'back' => $this->getRequest()->getParam('back'), 'bid' => $this->getRequest()->getParam('bid'));

		$this->_controller = 'adminhtml_history';
		$this->_blockGroup = 'emaillog';
		$this->_headerText = $this->__('Email number %d - %s', $object->getId(), htmlentities($object->getData('mail_subject')));

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

		if (Mage::getStoreConfigFlag('maillog/general/send') && empty($object->getData('deleted')) &&
		    !in_array($object->getData('status'), array('notsent', 'bounce'))) {
			$this->_addButton('resend', array(
				'label'   => $this->__('Resend email'),
				'onclick' => "deleteConfirm('".addslashes($this->__('Are you sure?'))."', '".$this->getUrl('*/*/resend', $params)."');",
				'class'   => 'add'
			));
		}

		$this->_addButton('view', array(
			'label'   => $this->__('View'),
			'onclick' => "self.open('".$object->getEmbedUrl('index', array('nomark' => 1, '_store' => Mage::app()->getDefaultStoreView()->getId()))."');",
			'class'   => 'go'
		));
	}

	public function getGridHtml() {

		$object = Mage::registry('current_email');
		$class  = 'class="maillog-status grid-'.$object->getData('status').'"';
		$help   = $this->helper('maillog');

		// status
		if ($object->getData('status') == 'read')
			$status = $this->__('Open/read');
		else if ($object->getData('status') == 'error')
			$status = $this->helper('maillog')->_('Error');
		else if ($object->getData('status') == 'notsent')
			$status = $this->__('Unsent');
		else if ($object->getData('status') == 'bounce')
			$status = $this->__('Blocked');
		else
			$status = $this->__(ucfirst($object->getData('status')));

		// html
		$html   = array();
		$html[] = '<div class="content">';
		$html[] = '<div>';
		$html[] = '<ul>';
		$html[] = '<li>'.$this->__('Created At: %s', $help->formatDate($object->getData('created_at'))).'</li>';

		if (!in_array($object->getData('sent_at'), array('', '0000-00-00 00:00:00', null))) {

			$html[] = '<li><strong>'.$this->__('Sent At: %s', $help->formatDate($object->getData('sent_at'))).'</strong></li>';

			$duration = $help->getHumanDuration($object);
			if (!empty($duration))
				$html[] = '<li>'.$this->__('Duration: %s', $duration).'</li>';
		}

		$html[] = '</ul>';
		$html[] = '<ul>';
		$html[] = '<li><strong>'.$this->__('Status: <span %s>%s</span>', $class, $status).'</strong></li>';

		if (!empty($object->getSize()))
			$html[] = '<li>'.$this->__('Approximate size: %s', $help->getNumberToHumanSize($object->getSize())).'</li>';
		if ($object->getData('mail_sender'))
			$html[] = '<li>'.$this->__('Sender: %s', $help->getHumanEmailAddress($object->getData('mail_sender'))).'</li>';

		$html[] = '<li>'.$this->__('Recipient(s): %s', $help->getHumanEmailAddress($object->getData('mail_recipients'))).'</li>';
		$html[] = '</ul>';
		$html[] = '</div>';
		$html[] = '<iframe type="text/html" srcdoc="data:text/html;base64,'.base64_encode('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>...</body></html>').'" onload="this.contentDocument.body.parentNode.innerHTML = decodeURIComponent(escape(self.atob(this.firstChild.nodeValue))); this.style.height = this.contentDocument.body.scrollHeight + \'px\';" scrolling="no">'.
			base64_encode(str_replace('<body style="', '<body style="overflow-y:hidden; ', $object->toHtml(true))).
		'</iframe>'; // true pour nomark
		$html[] = '</div>';

		return implode("\n", $html);
	}

	private function getBackUrl() {

		if ($this->getRequest()->getParam('back') == 'order')
			return $this->getUrl('*/sales_order/view',
				array('order_id' => $this->getRequest()->getParam('bid'), 'active_tab' => 'maillog_order_grid'));
		else if ($this->getRequest()->getParam('back') == 'customer')
			return $this->getUrl('*/customer/edit',
				array('id' => $this->getRequest()->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_customer_grid'));
		else
			return $this->getUrl('*/*/index');
	}

	protected function _prepareLayout() {
		//return parent::_prepareLayout();
	}
}