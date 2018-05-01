<?php
/**
 * Created J/03/12/2015
 * Updated M/27/02/2018
 *
 * Copyright 2015-2018 | Fabrice Creuzot (luigifab) <code~luigifab~info>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.info/magento/maillog
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

use Varien_Data_Form_Element_Renderer_Interface as Varien_DFER_Interface;
class Luigifab_Maillog_Block_Adminhtml_Config_Mapping extends Mage_Adminhtml_Block_System_Config_Form_Field implements Varien_DFER_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$customerAttributes = Mage::getModel('customer/entity_attribute_collection');
		$addressAttributes  = Mage::getModel('customer/entity_address_attribute_collection');

		$system = new Varien_Object(array('fields' => array(), 'type' => 'Emarsys')); // pour ne pas avoir « A) » tout seul
		$entity = Mage::getStoreConfig('maillog/sync/mapping_customerid_field');
		$lang   = substr(Mage::getSingleton('core/locale')->getLocaleCode(), 0, 2);

		if (!empty(Mage::getStoreConfig('maillog/sync/type')) &&
		    !empty(Mage::getStoreConfig('maillog/sync/api_url')) &&
		    !empty(Mage::getStoreConfig('maillog/sync/api_username')) &&
		    !empty(Mage::getStoreConfig('maillog/sync/api_password')))
			$system = Mage::getSingleton('maillog/system_'.Mage::getStoreConfig('maillog/sync/type'));

		$fields = $system->getFields();

		// champ id client (maillog/sync/mapping_customerid_field)
		// avec un petit hack pour ne pas perdre la configuration lorsque le système est HS
		$options1 = array('<option value="">--</option>');
		if (!empty($entity) && empty($fields)) {
			$options1[] = sprintf('<option value="%s" selected="selected">%1$s</option>', $entity);
		}
		else {
			foreach ($fields as $field) {
				if (!$field['readonly'] && ($entity == $field['id']))
					$options1[] = sprintf('<option value="%s" selected="selected" class="has">%s</option>', $field['id'], $field['name']);
				else if (!$field['readonly'])
					$options1[] = sprintf('<option value="%s">%s</option>', $field['id'], $field['name']);
			}
		}

		// champ a) système
		// liste vide lorsque le système est HS
		$options2 = array('<option value="">--</option>');
		foreach ($fields as $field) {
			$options2[] = ($field['readonly']) ?
				sprintf('<option value="%s" disabled="disabled">%s (%1$s) [%s]</option>', $field['id'], $field['name'], $this->__('read only')):
				sprintf('<option value="%s">%s (%1$s)</option>', $field['id'], $field['name']);
		}

		// champ b) magento
		// liste jamais vide puisque ce sont les attributs Magento
		$options3 = array('<option value="">--</option>', '<option value="entity_id">entity_id</option>');
		foreach ($customerAttributes as $attribute) {
			$options3[] = sprintf('<option value="%s">%1$s</option>', $attribute->getData('attribute_code'));
		}
		foreach ($addressAttributes as $attribute) {
			$options3[] = sprintf('<option value="address_billing_%s">address_billing_%1$s</option>', $attribute->getData('attribute_code'));
			if ($attribute->getData('attribute_code') == 'street') {
				$options3[] = sprintf('<option value="address_billing_%s">address_billing_%1$s</option>', 'street_1');
				$options3[] = sprintf('<option value="address_billing_%s">address_billing_%1$s</option>', 'street_2');
				$options3[] = sprintf('<option value="address_billing_%s">address_billing_%1$s</option>', 'street_3');
				$options3[] = sprintf('<option value="address_billing_%s">address_billing_%1$s</option>', 'street_4');
			}
		}
		foreach ($addressAttributes as $attribute) {
			$options3[] = sprintf('<option value="address_shipping_%s">address_shipping_%1$s</option>', $attribute->getData('attribute_code'));
			if ($attribute->getData('attribute_code') == 'street') {
				$options3[] = sprintf('<option value="address_shipping_%s">address_shipping_%1$s</option>', 'street_1');
				$options3[] = sprintf('<option value="address_shipping_%s">address_shipping_%1$s</option>', 'street_2');
				$options3[] = sprintf('<option value="address_shipping_%s">address_shipping_%1$s</option>', 'street_3');
				$options3[] = sprintf('<option value="address_shipping_%s">address_shipping_%1$s</option>', 'street_4');
			}
		}

		$options3[] = sprintf('<option value="%s">%1$s</option>', 'last_sync_date');
		$options3[] = sprintf('<option value="%s">%1$s</option>', 'last_login_date');
		$options3[] = sprintf('<option value="%s">%1$s</option>', 'last_order_date');
		$options3[] = sprintf('<option value="%s">%1$s</option>', 'last_order_total');
		$options3[] = sprintf('<option value="%s">%1$s</option>', 'average_order_amount');
		$options3[] = sprintf('<option value="%s">%1$s</option>', 'subscriber_status');

		// champ configuration
		// ce que fait magento à la base
		$textarea = parent::render($element);
		$textarea = ($lang != 'en') ? str_replace('<textarea', '<textarea lang="en"', $textarea) : $textarea;
		$id = $element->getHtmlId();

		return '
			<tr>
				<td class="label"><label for="maillog_sync_mapping_customerid_field">'.$this->__('Customer Id field').'</label></td>
				<td class="value">
					<select class="select" id="maillog_sync_mapping_customerid_field" name="groups[sync][fields][mapping_customerid_field][value]">'.implode($options1).'</select>
					<p class="note"><span>'.$this->__('In case of a change of email address, the customer identification in the system will be done with the customer id.').'</span></p>
				</td>
				<td class="scope-label">'.$this->__('[GLOBAL]').'</td>
				<td></td>
			</tr>
			<tr>
				<td class="label"><label for="maillog_sync_mapping_system">A) '.$system->getType().'</label></td>
				<td class="value"><select class="select" id="maillog_sync_mapping_system">'.implode($options2).'</select></td>
				<td rowspan="2" class="scope-label" style="vertical-align:middle;">
					<button type="button" onclick="addToMapping();">'.$this->__('Add').'</button>
					<script type="text/javascript">
					window.addEventListener("load", markMappingSelects, false);
					function addToMapping() {
						if ((document.getElementById("maillog_sync_mapping_system").value.length > 0) &&
						    (document.getElementById("maillog_sync_mapping_magento").value.length > 0)) {
							document.getElementById("'.$id.'").value = (
								document.getElementById("'.$id.'").value + "\n" +
								document.getElementById("maillog_sync_mapping_system").value + ":" +
								document.getElementById("maillog_sync_mapping_magento").value
							).trim();
							document.getElementById("maillog_sync_mapping_system").selectedIndex = 0;
							document.getElementById("maillog_sync_mapping_magento").selectedIndex = 0;
							markMappingSelects();
							document.getElementById("'.$id.'").scrollTop = document.getElementById("'.$id.'").scrollHeight;
						}
					}
					function markMappingSelects() {
						var elem, elems, fields = document.getElementById("'.$id.'").value;
						elems = document.getElementById("maillog_sync_mapping_system").querySelectorAll("option");
						for (elem in elems) if (elems.hasOwnProperty(elem) && !isNaN(elem) && (elem > 0)) {
							if ((fields.indexOf("\n" + elems[elem].value + ":") > -1) || (fields.indexOf(elems[elem].value + ":") === 0))
								elems[elem].setAttribute("class", "has");
							else
								elems[elem].setAttribute("class", "no");
						}
						elems = document.getElementById("maillog_sync_mapping_magento").querySelectorAll("option");
						for (elem in elems) if (elems.hasOwnProperty(elem) && !isNaN(elem) && (elem > 0)) {
							if (fields.indexOf(":" + elems[elem].value) > -1)
								elems[elem].setAttribute("class", "has");
							else
								elems[elem].setAttribute("class", "no");
						}
					}
					</script>
				</td>
				<td></td>
			</tr>
			<tr>
				<td class="label"><label for="maillog_sync_mapping_magento">B) Magento</label></td>
				<td class="value">
					<select '.(($lang != 'en') ? 'lang="en"' : '').' class="select" id="maillog_sync_mapping_magento">
						'.implode($options3).'
					</select>
				</td>
				<!-- rowspan -->
				<td></td>
			</tr>'.$textarea;
	}
}