<?php
/**
 * Created D/17/01/2021
 * Updated V/10/03/2023
 *
 * Copyright 2015-2023 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Preview extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected static $_cache;

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (empty(self::$_cache)) {
			self::$_cache = Mage::getModel('core/config')->loadBase()->loadModules()->loadDb();
			self::$_cache = self::$_cache->getXpath('/config/global/template/email/*');
		}

		$html  = [];
		$codes = [];
		$text  = str_contains($element->getHtmlId(), '_text');

		$nodes = self::$_cache;
		foreach ($nodes as $node) {
			$type = strtolower((string) $node->type);
			if ((!$text && ($type == 'html')) || ($text && ($type == 'text'))) {
				$code = $node->getName();
				if (!in_array($code, ['design_email_header', 'design_email_footer', 'checkout_payment_failed_template']))
					$codes[$code] = [(string) $node->label, (string) $node->file];
			}
		}

		$store = $this->getRequest()->getParam('store');
		$store = empty($store) ? Mage::app()->getDefaultStoreView()->getId() : Mage::app()->getStore($store)->getId();
		$separ = null;
		$start = false;
		$end   = false;

		ksort($codes);
		foreach ($codes as $code => [$label, $file]) {

			$base = explode('_', $code);
			if ($base[0] != $separ) {
				if ($start && !$end) $html[] = '<td></td></tr>';
				$html[] = '<tr>';
				$html[] = '<td colspan="2" class="scope-label">'.$base[0].'</td>';
				$html[] = '</tr>';
				$html[] = '<tr>';
				$separ  = $base[0];
				$start  = true;
				$end    = false;
			}
			else if ($start) {
				$start = false;
				$end   = true;
			}
			else {
				$html[] = '<tr>';
				$start  = true;
				$end    = false;
			}

			$langs = [];
			$names = glob(BP.'/app/locale/*/template/email/'.$file);
			foreach ($names as $name)
				$langs[] = mb_substr($name, mb_strpos($name, '/locale/') + 8, 5);

			if (empty($langs)) {
				$html[] = '<td class="label" style="width:50%;">';
				$html[] = $this->__($label);
				$html[] = '<br />'.$code;
				$html[] = '<br /><em>'.$file.' − no templates found</em>';
				$html[] = '</td>';
			}
			else {
				$url    = $this->getUrl('*/maillog_preview/index', ['code' => $code, 'file' => str_replace('.html', '', urlencode($file)), 'store' => $store]);
				$html[] = '<td class="label" style="width:50%;">';
				$html[] = '<a href="'.$url.'">'.$this->__($label).'</a>';
				$html[] = '<br />'.$code;
				$html[] = '<br /><em>'.$file.' − '.implode(' ', $langs).'</em>';
				$html[] = '</td>';
			}

			if (!$start && $end)
				$html[] = '</tr>';
		}

		if (!$end)
			$html[] = '<td></td></tr>';

		return implode("\n", $html);
	}
}