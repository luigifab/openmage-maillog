<?php
/**
 * Created J/03/12/2015
 * Updated V/01/03/2019
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

class Luigifab_Maillog_Block_Adminhtml_Config_Mapping extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_template = 'luigifab/maillog/mapping.phtml';

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$customerAttributes = Mage::getModel('customer/entity_attribute_collection');
		$addressAttributes  = Mage::getModel('customer/entity_address_attribute_collection');

		$options = array('field' => array(), 'system' => array(), 'magento' => array(array('value' => 'entity_id')));
		$values  = '|'.preg_replace('#\s+#', '|', Mage::getStoreConfig('maillog/sync/mapping_config')).'|';
		$value   = Mage::getStoreConfig('maillog/sync/mapping_customerid_field');

		if (empty(Mage::getStoreConfig('maillog/sync/type')) || empty(Mage::getStoreConfig('maillog/sync/api_url')) ||
		    empty(Mage::getStoreConfig('maillog/sync/api_username')) || empty(Mage::getStoreConfig('maillog/sync/api_password'))) {
			$system = new Varien_Object(array('fields' => array(), 'type' => 'Emarsys'));
			$fields = array();
		}
		else {
			$system = Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'));
			$fields = $system->getFields();
		}

		// champ id client (maillog/sync/mapping_customerid_field)
		// avec un petit hack pour ne pas perdre la configuration lorsque le système est HS
		if (true) {

			if (!empty($value) && empty($fields)) {
				$options['field'][] = array('value' => $value, 'selected' => true);
			}
			else {
				foreach ($fields as $field) {
					if (!$field['readonly'] && ($value == $field['id']))
						$options['field'][] = array('value' => $field['id'], 'label' => $field['name'], 'selected' => true);
					else if (!$field['readonly'])
						$options['field'][] = array('value' => $field['id'], 'label' => $field['name']);
				}
			}
		}

		// champ a) système
		// liste vide lorsque le système est HS
		if (true) {

			foreach ($fields as $field) {
				if (!empty($field['readonly']))
					$options['system'][] = array('value' => $field['id'], 'label' => $field['name'], 'disabled' => true);
				else if (mb_strpos($values, '|'.$field['id'].':') !== false)
					$options['system'][] = array('value' => $field['id'], 'label' => $field['name'], 'selected' => true);
				else
					$options['system'][] = array('value' => $field['id'], 'label' => $field['name']);
			}
		}

		// champ b) magento
		// liste jamais vide puisque ce sont les attributs de Magento
		if (true) {

			foreach ($customerAttributes as $attribute) {
				$options['magento'][] = array('value' => $attribute->getData('attribute_code'));
			}
			foreach ($addressAttributes as $attribute) {
				$options['magento'][] = array('value' => 'address_billing_'.$attribute->getData('attribute_code'));
				if ($attribute->getData('attribute_code') == 'street') {
					$options['magento'][] = array('value' => 'address_billing_street_1');
					$options['magento'][] = array('value' => 'address_billing_street_2');
					$options['magento'][] = array('value' => 'address_billing_street_3');
					$options['magento'][] = array('value' => 'address_billing_street_4');
				}
			}
			foreach ($addressAttributes as $attribute) {
				$options['magento'][] = array('value' => 'address_shipping_'.$attribute->getData('attribute_code'));
				if ($attribute->getData('attribute_code') == 'street') {
					$options['magento'][] = array('value' => 'address_shipping_street_1');
					$options['magento'][] = array('value' => 'address_shipping_street_2');
					$options['magento'][] = array('value' => 'address_shipping_street_3');
					$options['magento'][] = array('value' => 'address_shipping_street_4');
				}
			}

			$options['magento'][] = array('value' => 'group_name');
			$options['magento'][] = array('value' => 'last_sync_date');
			$options['magento'][] = array('value' => 'last_login_date');
			$options['magento'][] = array('value' => 'first_order_date');
			$options['magento'][] = array('value' => 'first_order_total');
			$options['magento'][] = array('value' => 'first_order_total_notax');
			$options['magento'][] = array('value' => 'last_order_date');
			$options['magento'][] = array('value' => 'last_order_total');
			$options['magento'][] = array('value' => 'last_order_total_notax');
			$options['magento'][] = array('value' => 'average_order_amount');
			$options['magento'][] = array('value' => 'average_order_amount_notax');
			$options['magento'][] = array('value' => 'total_order_amount');
			$options['magento'][] = array('value' => 'total_order_amount_notax');
			$options['magento'][] = array('value' => 'number_of_orders');
			$options['magento'][] = array('value' => 'subscriber_status');

			foreach ($options['magento'] as &$option) {
				if (mb_strpos($values, '|'.$option['value'].'|') !== false)
					$option['selected'] = true;
			}

			unset($option);
		}

		// ajoute le champ configuration après les champs spéciaux
		$this->setName($system->getType());
		$this->setOptions($options);

		return $this->toHtml().str_replace('<textarea', '<textarea oninput="maillog.mark();" lang="mul"', parent::render($element));
	}
}