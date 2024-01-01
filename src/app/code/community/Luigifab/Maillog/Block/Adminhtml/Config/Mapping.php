<?php
/**
 * Created J/03/12/2015
 * Updated S/09/12/2023
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

class Luigifab_Maillog_Block_Adminhtml_Config_Mapping extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$code = explode('_', $element->getHtmlId());
		$code = $code[2];

		$options = ['customerid' => [], 'system' => [], 'core' => []];
		$system  = $this->helper('maillog')->getSystem($code);

		if ($system instanceof Luigifab_Maillog_Model_Interface) {
			$values = $system->getMapping();
			$fields = $system->getFields();
		}
		else {
			$values = [];
			$fields = [];
		}

		$search = [
			'A) System',
			'<textarea',
			'class=" textarea"',
			' selected="selected"',
			'<select',
			'>selected:',
			'>has:',
			'>disabled:',
			'class=" select"',
		];

		$replace = [
			'A) '.ucfirst($code),
			'<textarea lang="mul" autocapitalize="off" autocorrect="off" spellcheck="false" oninput="maillog.mark(\''.$code.'\');"',
			'class="textarea maillogsync"',
			'',
			'<select lang="mul"',
			'selected="selected" class="has">',
			'class="has">',
			'disabled="disabled">',
			'class="select maillogsync"',
		];

		// pour le champ id client
		// avec un petit hack pour ne pas perdre la configuration lorsque le système est HS
		if (str_contains($element->getHtmlId(), 'mapping_customerid_field')) {

			$options['customerid'][] = ['value' => '', 'label' => '-- ('.count($fields).')'];

			$value = $element->getValue();
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
		else if (str_contains($element->getHtmlId(), 'mapping_system')) {

			$options['system'][] = ['value' => '', 'label' => '-- ('.count($fields).')'];

			foreach ($fields as $field) {
				if (!empty($field['readonly']))
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field, 'disabled:')];
				else if (array_key_exists($field['id'], $values))
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field, 'has:')];
				else
					$options['system'][] = ['value' => $field['id'], 'label' => $this->getOptionLabel($field)];
			}

			$element->unsPath()->setValues($options['system']);

			$search[]  = 'name="'.$element->getName().'"';
			$replace[] = '';
			$search[]  = '<td class="scope-label">'.$this->__('[GLOBAL]').'</td>';
			$replace[] = '<td rowspan="2" class="scope-label" style="padding-top:20px !important;">'.
				'<button type="button" onclick="maillog.add(\''.$code.'\');">'.$this->__('Add').'</button></td>';
		}

		// pour le champ b) openmage
		// liste jamais vide puisque ce sont les attributs clients
		else if (str_contains($element->getHtmlId(), 'mapping_openmage')) {

			$customerAttributes = Mage::getModel('customer/entity_attribute_collection')->setOrder('attribute_code', 'asc');
			$addressAttributes  = Mage::getModel('customer/entity_address_attribute_collection')->setOrder('attribute_code', 'asc');

			$options['core'][] = ['value' => '', 'label' => '--'];
			$options['core'][] = ['value' => 'entity_id'];
			$options['core'][] = ['value' => 'customer_login_key'];

			foreach ($customerAttributes as $attribute) {
				$attrCode = $attribute->getData('attribute_code');
				if (!empty($attrCode))
					$options['core'][] = ['value' => $attrCode];
			}

			foreach ($addressAttributes as $attribute) {
				$attrCode = $attribute->getData('attribute_code');
				if (!empty($attrCode)) {
					$options['core'][] = ['value' => 'address_billing_'.$attrCode];
					if ($attrCode == 'street') {
						$options['core'][] = ['value' => 'address_billing_street_1'];
						$options['core'][] = ['value' => 'address_billing_street_2'];
						$options['core'][] = ['value' => 'address_billing_street_3'];
						$options['core'][] = ['value' => 'address_billing_street_4'];
					}
				}
			}

			foreach ($addressAttributes as $attribute) {
				$attrCode = $attribute->getData('attribute_code');
				if (!empty($attrCode)) {
					$options['core'][] = ['value' => 'address_shipping_'.$attrCode];
					if ($attrCode == 'street') {
						$options['core'][] = ['value' => 'address_shipping_street_1'];
						$options['core'][] = ['value' => 'address_shipping_street_2'];
						$options['core'][] = ['value' => 'address_shipping_street_3'];
						$options['core'][] = ['value' => 'address_shipping_street_4'];
					}
				}
			}

			$options['core'][] = ['value' => 'group_name'];
			$options['core'][] = ['value' => 'last_sync_date'];
			$options['core'][] = ['value' => 'last_login_date'];
			$options['core'][] = ['value' => 'first_order_id'];
			$options['core'][] = ['value' => 'first_order_incrementid'];
			$options['core'][] = ['value' => 'first_order_date'];
			$options['core'][] = ['value' => 'first_order_status'];
			$options['core'][] = ['value' => 'first_order_payment'];
			$options['core'][] = ['value' => 'first_order_total'];
			$options['core'][] = ['value' => 'first_order_total_notax'];
			$options['core'][] = ['value' => 'first_order_names_list'];
			$options['core'][] = ['value' => 'first_order_skus_list'];
			$options['core'][] = ['value' => 'first_order_skus_number'];
			$options['core'][] = ['value' => 'last_order_id'];
			$options['core'][] = ['value' => 'last_order_incrementid'];
			$options['core'][] = ['value' => 'last_order_date'];
			$options['core'][] = ['value' => 'last_order_status'];
			$options['core'][] = ['value' => 'last_order_payment'];
			$options['core'][] = ['value' => 'last_order_total'];
			$options['core'][] = ['value' => 'last_order_total_notax'];
			$options['core'][] = ['value' => 'last_order_names_list'];
			$options['core'][] = ['value' => 'last_order_skus_list'];
			$options['core'][] = ['value' => 'last_order_skus_number'];

			foreach (range(1, 5) as $idx) {
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_sku'];
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_name'];
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_price'];
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_rating'];
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_image'];
				$options['core'][] = ['value' => 'last_order_product_'.$idx.'_url'];
			}

			$options['core'][] = ['value' => 'average_days_between_orders'];
			$options['core'][] = ['value' => 'average_order_amount'];
			$options['core'][] = ['value' => 'average_order_amount_notax'];
			$options['core'][] = ['value' => 'total_order_amount'];
			$options['core'][] = ['value' => 'total_order_amount_notax'];
			$options['core'][] = ['value' => 'all_ordered_skus'];
			$options['core'][] = ['value' => 'all_ordered_names'];
			$options['core'][] = ['value' => 'number_of_products_ordered'];
			$options['core'][] = ['value' => 'number_of_orders'];
			$options['core'][] = ['value' => 'subscriber_status'];
			$options['core'][] = ['value' => 'number_of_reviews'];

			if ($code == 'mautic') {
				$options['core'][] = ['value' => 'rating_order_monetary'];
				$options['core'][] = ['value' => 'rating_order_frequency'];
				$options['core'][] = ['value' => 'rating_order_recency'];
			}

			foreach ($options['core'] as $i => $option) {
				if (is_numeric($i) && !empty($option['value'])) {
					if ($this->inArray($option['value'], $values))
						$options['core'][$i]['label'] = $this->getOptionLabel(['id' => $option['value']], 'has:');
					else
						$options['core'][$i]['label'] = $this->getOptionLabel(['id' => $option['value']]);
				}
			}

			$options['core'][0]['label'] = '-- ('.(count($options['core']) - 1).')';
			$element->unsPath()->setValues($options['core']);

			$search[]  = 'name="'.$element->getName().'"';
			$replace[] = '';
			$search[]  = '<td class="scope-label">'.$this->__('[GLOBAL]').'</td>';
			$replace[] = '<!-- rowspan -->';

		}

		return str_replace($search, $replace, parent::render($element));
	}

	protected function inArray($needle, array $haystack, bool $strict = false) {

		// @see https://stackoverflow.com/a/4128377/2980105
		foreach ($haystack as $item) {
			if (($strict ? ($item === $needle) : ($item == $needle)) || (is_array($item) && $this->inArray($needle, $item, $strict)))
				return true;
		}

		return false;
	}

	protected function getOptionLabel(array $field, $prefix = '') {
		return empty($field['name']) ? $prefix.$field['id'] : $prefix.$field['name'].' ('.$field['id'].')';
	}
}