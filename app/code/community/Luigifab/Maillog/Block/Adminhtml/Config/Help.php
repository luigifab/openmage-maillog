<?php
/**
 * Created D/22/03/2015
 * Updated S/23/03/2019
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

class Luigifab_Maillog_Block_Adminhtml_Config_Help extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (($msg = $this->checkChanges()) !== true) {
			return sprintf('<p class="box">Luigifab/%s %s <span style="float:right;"><a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/magento/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('Changes in <em>%s</em> are not present. Please read the documentation.', $msg));
		}
		else if (($msg = $this->checkRewrites()) !== true) {
			return sprintf('<p class="box">Luigifab/%s %s <span style="float:right;"><a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/magento/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('There is conflict (<em>%s</em>).', $msg));
		}
		else {
			return sprintf('<p class="box">Luigifab/%s %s <span style="float:right;"><a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p>',
				'Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/magento/maillog');
		}
	}

	private function checkRewrites() {

		$rewrites = array(
			array('model', 'core/email_queue'),     // Magento 1.9.1.0 et plus
			array('model', 'newsletter/subscriber')
		);

		if (version_compare(Mage::getVersion(), '1.9.1.0', '<'))
			unset($rewrites[0]);

		foreach ($rewrites as $rewrite) {
			if ($rewrite[0] == 'model') {
				if (!method_exists(Mage::getModel($rewrite[1]), 'specialCheckRewrite'))
					return $rewrite[1];
			}
			else if ($rewrite[0] == 'resource') {
				if (!method_exists(Mage::getResourceModel($rewrite[1]), 'specialCheckRewrite'))
					return $rewrite[1];
			}
			else if ($rewrite[0] == 'block') {
				if (!method_exists(Mage::getBlockSingleton($rewrite[1]), 'specialCheckRewrite'))
					return $rewrite[1];
			}
		}

		return true;
	}

	private function checkChanges() {

		$zend = file_get_contents(BP.'/lib/Zend/Mail/Transport/Sendmail.php');
		if (mb_strpos($zend, 'return Mage::helper(\'maillog\')->sendMail($this, $this->_mail, $this->_parts);') === false)
			return 'lib/Zend/Mail/Transport/Sendmail.php';

		$varien = file_get_contents(BP.'/lib/Varien/Filter/Template.php');
		if (mb_strpos($varien, '$value = Mage::helper(\'maillog\')->filterMail($this, $value, $this->_templateVars);') === false)
			return 'lib/Varien/Filter/Template.php';
		if (mb_strpos($varien, 'return Mage::helper(\'maillog\')->variableMail($this, $value, $default);') === false)
			return 'lib/Varien/Filter/Template.php';
		if (mb_strpos($varien, 'public function _getVariable2(') === false)
			return 'lib/Varien/Filter/Template.php';

		return true;
	}
}