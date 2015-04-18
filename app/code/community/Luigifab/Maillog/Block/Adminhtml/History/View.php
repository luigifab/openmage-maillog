<?php
/**
 * Created D/22/03/2015
 * Updated S/11/04/2015
 * Version 10
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

class Luigifab_Maillog_Block_Adminhtml_History_View extends Mage_Adminhtml_Block_Widget_Grid_Container {

	public function __construct() {

		parent::__construct();

		$email = Mage::registry('current_email');
		$this->_controller = 'adminhtml_history';
		$this->_blockGroup = 'emaillog';
		$this->_headerText = $this->__('Email number %d - %s', $email->getId(), htmlentities($email->getMailSubject()));

		$this->_removeButton('add');

		$this->_addButton('back', array(
			'label'   => $this->helper('adminhtml')->__('Back'),
			'onclick' => "setLocation('".$this->getUrl('*/*/index')."');",
			'class'   => 'back'
		));

		$this->_addButton('remove', array(
			'label'   => $this->helper('adminhtml')->__('Remove'),
			'onclick' => "deleteConfirm('".addslashes($this->helper('core')->__('Are you sure?'))."', '".$this->getUrl('*/*/delete', array('id' => $email->getId()))."');",
			'class'   => 'delete'
		));

		$this->_addButton('action', array(
			'label'   => $this->__('Resend email'),
			'onclick' => "deleteConfirm('".addslashes($this->helper('core')->__('Are you sure?'))."', '".$this->getUrl('*/*/resend', array('id' => $email->getId()))."');", // ce n'est pas un delete, mais une demande de confirmation
			'class'   => 'add'
		));
	}

	public function getGridHtml() {

		$email = Mage::registry('current_email');
		$size  = Mage::getBlockSingleton('maillog/adminhtml_history_grid')->decorateSize(null, $email, null, null);
		$date  = Mage::getSingleton('core/locale'); //date($date, $format, $locale = null, $useTimezone = null)

		$status = ($email->getStatus() === 'read') ? 'open/read' : $email->getStatus();
		$status = trim(str_replace('(0)', '', $this->__(ucfirst($status.' (%d)'), 0)));

		$html  = '<div class="content">';
		$html .= "\n".'<ul>';
		$html .= "\n".'<li>'.$this->__('Created At: %s', $date->date($email->getCreatedAt(), Zend_Date::ISO_8601)).'</li>';

		if (!in_array($email->getSentAt(), array('', '0000-00-00 00:00:00', null))) {
			$html .= "\n".'<li><strong>'.$this->__('Sent At: %s', $date->date($email->getSentAt(), Zend_Date::ISO_8601)).'</strong></li>';
			$duration = Mage::getBlockSingleton('maillog/adminhtml_history_grid')->decorateDuration(null, $email, null, null);
			if (strlen($duration) > 0)
				$html .= "\n".'<li>'.$this->__('Duration: %s', $duration).'</li>';
		}

		$html .= "\n".'</ul>';
		$html .= "\n".'<ul>';
		$html .= "\n".'<li><strong class="status-'.$email->getStatus().'">'.$this->__('Status: %s', $status).'</strong></li>';
		$html .= "\n".'<li>'.$this->__('Approximate size: %s', $size).'</li>';
		$html .= "\n".'<li>'.$this->__('Recipient(s): %s', htmlentities($email->getMailRecipients())).'</li>';
		$html .= "\n".'</ul>';
		$html .= "\n".'<object data="'.$this->getUrl('*/*/viewmail', array('id' => $email->getId())).'" type="text/html" onload="this.style.height = (this.contentDocument.body.scrollHeight + 40) + \'px\';"></object>';
		$html .= "\n".'</div>';

		return $html;
	}

	protected function _prepareLayout() {
		//return parent::_prepareLayout();
	}
}