<?php
/**
 * Created D/17/01/2021
 * Updated L/26/04/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Preview extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$html  = [];
		$codes = [];
		$nodes = Mage::getModel('core/config')->loadBase()->loadModules()->loadDb();
		$nodes = $nodes->getXpath('/config/global/template/email/*');

		foreach ($nodes as $node) {
			$code = $node->getName();
			if (!in_array($code, ['design_email_header', 'design_email_footer', 'checkout_payment_failed_template']))
				$codes[$code] = [(string) $node->label, (string) $node->file];
		}

		ksort($codes);
		$store = $this->getRequest()->getParam('store');
		$store = empty($store) ? Mage::app()->getDefaultStoreView()->getId() : Mage::app()->getStore($store)->getId();
		$separ = null;
		$start = false;
		$end   = false;

		foreach ($codes as $code => [$label, $file]) {

			$base = (array) explode('_', $code); // (yes)
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