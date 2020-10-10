<?php
/**
 * Created D/22/03/2015
 * Updated J/08/10/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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

class Luigifab_Maillog_Block_Adminhtml_History_View extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$email   = Mage::registry('current_email');
		$params  = ['id' => $email->getId(), 'back' => $this->getRequest()->getParam('back'), 'bid' => $this->getRequest()->getParam('bid')];
		$confirm = $this->helper('maillog')->escapeEntities($this->__('Are you sure?'), true);

		$this->_controller = 'adminhtml_history';
		$this->_blockGroup = 'emaillog';
		$this->_headerText = $this->__('Email number %d - %s', $email->getId(), $email->getSubject());

		$this->_removeButton('add');

		$this->_addButton('back', [
			'label'   => $this->__('Back'),
			'onclick' => "setLocation('".$this->getBackUrl()."');",
			'class'   => 'back'
		]);

		$this->_addButton('remove', [
			'label'   => $this->__('Remove'),
			'onclick' => "deleteConfirm('".$confirm."', '".$this->getUrl('*/*/delete', $params)."');",
			'class'   => 'delete'
		]);

		if (Mage::getStoreConfigFlag('maillog/general/enabled') && Mage::getStoreConfigFlag('maillog/general/send') &&
		    empty($email->getData('deleted')) && !in_array($email->getData('status'), ['notsent', 'bounce'])) {
			$this->_addButton('resend', [
				'label'   => $this->__('Resend email'),
				'onclick' => "deleteConfirm('".$confirm."', '".$this->getUrl('*/*/resend', $params)."');",
				'class'   => 'add'
			]);
		}

		$this->_addButton('view', [
			'label'   => $this->__('View'),
			'onclick' => "self.open('".$email->getEmbedUrl('index', ['nomark' => 1])."');",
			'class'   => 'go'
		]);
	}

	public function getGridHtml() {

		$email = Mage::registry('current_email');
		$class = 'class="maillog-status grid-'.$email->getData('status').'"';
		$help  = $this->helper('maillog');

		// status
		if ($email->getData('status') == 'read')
			$status = $this->__('Open/read');
		else if ($email->getData('status') == 'error')
			$status = $this->helper('maillog')->_('Error');
		else if ($email->getData('status') == 'notsent')
			$status = $this->__('Unsent');
		else if ($email->getData('status') == 'bounce')
			$status = $this->__('Blocked');
		else
			$status = $this->__(ucfirst($email->getData('status')));

		// html
		$html   = [];
		$html[] = '<div class="content">';
		$html[] = '<div>';
		$html[] = '<ul>';
		$html[] = '<li>'.$this->__('Created At: %s', $help->formatDate($email->getData('created_at'))).'</li>';

		if (!in_array($email->getData('sent_at'), ['', '0000-00-00 00:00:00', null])) {
			$html[] = '<li><strong>'.$this->__('Sent At: %s', $help->formatDate($email->getData('sent_at'))).'</strong></li>';
			if (!empty($duration = $help->getHumanDuration($email->getData('duration'))))
				$html[] = '<li>'.$this->__('Duration: %s', $duration).'</li>';
		}

		$html[] = '</ul>';
		$html[] = '<ul>';
		$html[] = '<li><strong>'.$this->__('Status: <span %s>%s</span>', $class, $status).'</strong></li>';

		if (!empty($email->getSize()))
			$html[] = '<li>'.$this->__('Approximate size: %s', $help->getNumberToHumanSize($email->getSize())).'</li>';
		if ($email->getData('mail_sender'))
			$html[] = '<li>'.$this->__('Sender: %s', $help->getHumanEmailAddress($email->getData('mail_sender'))).'</li>';

		$html[] = '<li>'.$this->__('Recipient(s): %s', $help->getHumanEmailAddress($email->getData('mail_recipients'))).'</li>';
		$html[] = '</ul>';
		$html[] = '</div>';
		$base   = '<html lang="mul"><head><title></title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>...</body></html>';
		$html[] = '<iframe type="text/html" scrolling="no" srcdoc="data:text/html;base64,'.base64_encode($base).'" onload="maillog.iframe(this)">'.
			base64_encode($email->toHtml(true)).
		'</iframe>'; // true pour nomark
		$html[] = '</div>';

		return implode("\n", $html);
	}

	private function getBackUrl() {

		if ($this->getRequest()->getParam('back') == 'order')
			return $this->getUrl('*/sales_order/view',
				['order_id' => $this->getRequest()->getParam('bid'), 'active_tab' => 'maillog_order_grid']);

		if ($this->getRequest()->getParam('back') == 'customer')
			return $this->getUrl('*/customer/edit',
				['id' => $this->getRequest()->getParam('bid'), 'back' => 'edit', 'tab' => 'customer_info_tabs_maillog_customer_grid']);

		return $this->getUrl('*/*/index');
	}

	protected function _prepareLayout() {
		//return parent::_prepareLayout();
	}
}