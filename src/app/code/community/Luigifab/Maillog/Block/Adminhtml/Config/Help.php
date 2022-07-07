<?php
/**
 * Created D/22/03/2015
 * Updated V/24/06/2022
 *
 * Copyright 2015-2022 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2022 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Help extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$msg = $this->checkChanges();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/openmage/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('Changes in <em>%s</em> are not present. Please read the documentation.', $msg));

		$msg = $this->checkRewrites();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/openmage/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('There is conflict (<em>%s</em>).', $msg));

		return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://www.%s">%3$s</a> | ⚠ IPv6</span></p>',
			'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'luigifab.fr/openmage/maillog');
	}

	protected function checkRewrites() {

		$rewrites = [
			['model' => 'core/email_queue'],
			['model' => 'newsletter/subscriber'],
			['model' => 'newsletter_resource/subscriber'],
		];

		foreach ($rewrites as $rewrite) {
			foreach ($rewrite as $type => $class) {
				if (($type == 'model') && (mb_stripos(Mage::getConfig()->getModelClassName($class), 'luigifab') === false))
					return $class;
				else if (($type == 'block') && (mb_stripos(Mage::getConfig()->getBlockClassName($class), 'luigifab') === false))
					return $class;
				else if (($type == 'helper') && (mb_stripos(Mage::getConfig()->getHelperClassName($class), 'luigifab') === false))
					return $class;
			}
		}

		return true;
	}

	protected function checkChanges() {

		$zend = file_get_contents(BP.'/lib/Zend/Mail/Transport/Sendmail.php');
		if (!str_contains($zend, 'return Mage::helper(\'maillog\')->sendMail($this, $this->_mail, $this->_parts);'))
			return 'lib/Zend/Mail/Transport/Sendmail.php';

		$varien = file_get_contents(BP.'/lib/Varien/Filter/Template.php');
		if (!str_contains($varien, 'Luigifab_Maillog_Model_Filter'))
			return 'lib/Varien/Filter/Template.php';
		if (!str_contains($varien, 'function filter2('))
			return 'lib/Varien/Filter/Template.php';
		if (!str_contains($varien, 'function _getVariable2'))
			return 'lib/Varien/Filter/Template.php';
		if (str_contains($varien, '(\'maillog\')'))
			return 'lib/Varien/Filter/Template.php';

		return true;
	}
}