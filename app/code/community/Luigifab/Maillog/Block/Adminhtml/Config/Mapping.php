<?php
/**
 * Created J/03/12/2015
 * Updated D/20/10/2019
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
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

class Luigifab_Maillog_Block_Adminhtml_Config_Mapping extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$customerAttributes = Mage::getModel('customer/entity_attribute_collection');
		$addressAttributes  = Mage::getModel('customer/entity_address_attribute_collection');

		$options = ['customerid' => [], 'system' => [], 'magento' => []];
		$values  = '|'.preg_replace('#\s+#', '|', Mage::getStoreConfig('maillog_sync/general/mapping_config')).'|';
		$value   = Mage::getStoreConfig('maillog_sync/general/mapping_customerid_field');

		$system  = $this->helper('maillog')->getSystem();
		$fields  = $system->getFields();

		$search  = ['A) System', '<textarea', ' selected="selected"', '<select', '>selected:', '>has:', '>disabled:'];
		$replace = [
			'A) '.$system->getType(),
			'<textarea lang="mul" autocapitalize="off" autocorrect="off" spellcheck="false" oninput="maillog.mark();"',
			'',
			'<select lang="mul"',
			'selected="selected" class="has">',
			'class="has">',
			'disabled="disabled">'
		];

		// pour le champ id client
		// avec un petit hack pour ne pas perdre la configuration lorsque le système est HS
		if (mb_stripos($element->getHtmlId(), 'mapping_customerid_field') !== false) {

			$options['customerid'][] = ['value' => '', 'label' => '--'];

			if (!empty($value) && empty($fields)) {
				$options['customerid'][] = ['value' => $value, 'label' => 'selected:'.$value];
			}
			else {
				foreach ($fields as $field) {
					if (!$field['readonly'] && ($value == $field['id']))
						$options['customerid'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field, 'selected:')];
					else if (!$field['readonly'])
						$options['customerid'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field)];
				}
			}

			$element->setValues($options['customerid']);
		}

		// pour le champ a) système
		// liste vide lorsque le système est HS
		if (mb_stripos($element->getHtmlId(), 'mapping_system') !== false) {

			$options['system'][] = ['value' => '', 'label' => '--'];

			foreach ($fields as $field) {
				if (!empty($field['readonly']))
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field, 'disabled:')];
				else if (mb_stripos($values, '|'.$field['id'].':') !== false)
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field, 'has:')];
				else
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field)];
			}

			$element->setValues($options['system']);

			$search[]  = 'name="'.$element->getName().'"';
			$replace[] = '';
			$search[]  = '<td class="scope-label">'.$this->__('[GLOBAL]').'</td>';
			$replace[] = '<td rowspan="2" class="scope-label" style="vertical-align:middle;"><button type="button" onclick="maillog.add();">'.$this->__('Add').'</button></td>';
		}

		// pour le champ b) magento
		// liste jamais vide puisque ce sont les attributs de Magento
		if (mb_stripos($element->getHtmlId(), 'mapping_magento') !== false) {

			$options['magento'][] = ['value' => '', 'label' => '--'];
			$options['magento'][] = ['value' => 'entity_id'];

			foreach ($customerAttributes as $attribute) {
				$options['magento'][] = ['value' => $attribute->getData('attribute_code')];
			}
			foreach ($addressAttributes as $attribute) {
				$options['magento'][] = ['value' => 'address_billing_'.$attribute->getData('attribute_code')];
				if ($attribute->getData('attribute_code') == 'street') {
					$options['magento'][] = ['value' => 'address_billing_street_1'];
					$options['magento'][] = ['value' => 'address_billing_street_2'];
					$options['magento'][] = ['value' => 'address_billing_street_3'];
					$options['magento'][] = ['value' => 'address_billing_street_4'];
				}
			}
			foreach ($addressAttributes as $attribute) {
				$options['magento'][] = ['value' => 'address_shipping_'.$attribute->getData('attribute_code')];
				if ($attribute->getData('attribute_code') == 'street') {
					$options['magento'][] = ['value' => 'address_shipping_street_1'];
					$options['magento'][] = ['value' => 'address_shipping_street_2'];
					$options['magento'][] = ['value' => 'address_shipping_street_3'];
					$options['magento'][] = ['value' => 'address_shipping_street_4'];
				}
			}

			$options['magento'][] = ['value' => 'group_name'];
			$options['magento'][] = ['value' => 'last_sync_date'];
			$options['magento'][] = ['value' => 'last_login_date'];
			$options['magento'][] = ['value' => 'first_order_date'];
			$options['magento'][] = ['value' => 'first_order_total'];
			$options['magento'][] = ['value' => 'first_order_total_notax'];
			$options['magento'][] = ['value' => 'last_order_date'];
			$options['magento'][] = ['value' => 'last_order_total'];
			$options['magento'][] = ['value' => 'last_order_total_notax'];
			$options['magento'][] = ['value' => 'average_order_amount'];
			$options['magento'][] = ['value' => 'average_order_amount_notax'];
			$options['magento'][] = ['value' => 'total_order_amount'];
			$options['magento'][] = ['value' => 'total_order_amount_notax'];
			$options['magento'][] = ['value' => 'number_of_orders'];
			$options['magento'][] = ['value' => 'subscriber_status'];

			foreach ($options['magento'] as $i => $option) {
				if (!empty($option['value'])) {
					if ((mb_stripos($values, ':'.$option['value'].'|') !== false) || (mb_stripos($values, ':'.$option['value'].':') !== false))
						$options['magento'][$i]['label'] = $this->getOptionLabel(['id' => $option['value']], 'has:');
					else
						$options['magento'][$i]['label'] = $option['value'];
				}
			}

			$element->setValues($options['magento']);

			$search[]  = 'name="'.$element->getName().'"';
			$replace[] = '';
			$search[]  = '<td class="scope-label">'.$this->__('[GLOBAL]').'</td>';
			$replace[] = '<!-- rowspan -->';

		}

		return str_replace($search, $replace, parent::render($element));
	}

	private function getOptionLabel(array $field, $prefix = '') {
		return empty($field['name']) ? $prefix.$field['id'] : $prefix.$field['name'].' ('.$field['id'].')';
	}
}