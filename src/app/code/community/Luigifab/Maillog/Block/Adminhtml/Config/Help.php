<?php
/**
 * Created D/22/03/2015
 * Updated V/29/12/2023
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

class Luigifab_Maillog_Block_Adminhtml_Config_Help extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$msg = $this->checkChanges();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://github.com/luigifab/%3$s">github.com</a> | <a href="https://www.%4$s">%4$s</a> - ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%5$s</strong><br />%6$s</p>',
				'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'openmage-maillog', 'luigifab.fr/openmage/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('Changes in <em>%s</em> are not present. Please read the documentation.', $msg));

		$msg = $this->checkRewrites();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://github.com/luigifab/%3$s">github.com</a> | <a href="https://www.%4$s">%4$s</a> - ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%5$s</strong><br />%6$s</p>',
				'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'openmage-maillog', 'luigifab.fr/openmage/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('There is conflict (<em>%s</em>).', $msg));

		$msg = $this->checkRobots();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://github.com/luigifab/%3$s">github.com</a> | <a href="https://www.%4$s">%4$s</a> - ⚠ IPv6</span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%5$s</strong><br />%6$s</p>',
				'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'openmage-maillog', 'luigifab.fr/openmage/maillog',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('Disallow lines are missing in <em>%s</em>, please add: %s + %s', $msg, '<code style="color:black; background-color:yellow;">Noindex: */maillog/</code>', '<code style="color:black; background-color:yellow;">Disallow: */maillog/</code>'));

		return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://github.com/luigifab/%3$s">github.com</a> | <a href="https://www.%4$s">%4$s</a> - ⚠ IPv6</span></p>',
			'Luigifab/Maillog', $this->helper('maillog')->getVersion(), 'openmage-maillog', 'luigifab.fr/openmage/maillog');
	}

	protected function checkRobots() {

		$dir = getenv('SCRIPT_FILENAME');
		if (!empty($dir))
			$dir = dirname($dir);
		if (!is_dir($dir))
			$dir = BP;

		$file = 'robots.txt';
		if (is_file($dir.'/'.$file)) {
			$robots = file_get_contents($dir.'/'.$file);
			// @todo ['maillog', 'customer', 'downloadable', 'review', 'sales', 'shipping', 'newsletter', 'wishlist']
			if (str_contains($robots, 'Disallow: */maillog/'))
				return true;
			if (preg_replace('#\s#', '', trim($robots)) == 'User-agent:*Noindex:/Disallow:/')
				return true;
		}

		// @see https://github.com/luigifab/webext-openfileeditor
		return '<span class="openfileeditor" data-file="'.$dir.'/'.$file.'">'.$file.'</span>';
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
				if (($type == 'block') && (mb_stripos(Mage::getConfig()->getBlockClassName($class), 'luigifab') === false))
					return $class;
				if (($type == 'helper') && (mb_stripos(Mage::getConfig()->getHelperClassName($class), 'luigifab') === false))
					return $class;
			}
		}

		return true;
	}

	protected function checkChanges() {

		$file = 'lib/Zend/Mail/Transport/Sendmail.php';
		if (is_file(BP.'/'.$file)) {
			$zend = file_get_contents(BP.'/'.$file);
			if (!str_contains($zend, 'return Mage::helper(\'maillog\')->sendMail($this, $this->_mail, $this->_parts);'))
				return $file;
		}

		$file = 'vendor/shardj/zf1-future/library/Zend/Mail/Transport/Sendmail.php';
		if (is_file(BP.'/../'.$file)) {
			$zend = file_get_contents(BP.'/../'.$file);
			if (!str_contains($zend, 'return Mage::helper(\'maillog\')->sendMail($this, $this->_mail, $this->_parts);'))
				return $file;
		}

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