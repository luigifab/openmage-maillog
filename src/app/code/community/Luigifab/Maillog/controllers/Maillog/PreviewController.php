<?php
/**
 * Created D/17/01/2021
 * Updated V/21/05/2021
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

class Luigifab_Maillog_Maillog_PreviewController extends Mage_Adminhtml_Controller_Action {

	protected function _isAllowed() {
		return Mage::getSingleton('admin/session')->isAllowed('system/config');
	}

	public function indexAction() {

		$store  = (int) $this->getRequest()->getParam('store', 0);
		$locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);

		Mage::getSingleton('core/app_emulation')->startEnvironmentEmulation($store);
		Mage::getDesign()->setTheme(Mage::getStoreConfig('design/theme/default', $store));
		Mage::register('maillog_preview', true);

		$template = Mage::getModel('core/email_template');
		$template->setSentSuccess(false);
		$template->setDesignConfig(['store' => $store]);
		$template->loadDefault($this->getRequest()->getParam('code'), $locale);

		$vars = [
			'store'      => Mage::app()->getStore($store),
			'quote'      => Mage::getResourceModel('sales/quote_collection')
				->addFieldToFilter('is_active', 1)
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'order'      => Mage::getResourceModel('sales/order_collection')
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'invoice'    => Mage::getResourceModel('sales/order_invoice_collection')
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'creditmemo' => Mage::getResourceModel('sales/order_creditmemo_collection')
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'shipment'   => Mage::getResourceModel('sales/order_shipment_collection')
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'customer'   => Mage::getResourceModel('customer/customer_collection')
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'subscriber' => Mage::getResourceModel('newsletter/subscriber_collection')
				->setOrder('subscriber_id', 'desc')
				->setPageSize(1)
				->getFirstItem(),
			'product'    => Mage::getResourceModel('catalog/product_collection')
				->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
				->addAttributeToFilter('visibility', ['neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE])
				->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
				->addStoreFilter($store)
				->setOrder('entity_id', 'desc')
				->setPageSize(1)
				->getFirstItem()
		];

		if (is_object($vars['customer'])) {
			$vars['customerName'] = $vars['customer']->load($vars['customer']->getId())->getName();
			$vars['customer']->{(random_int(0, 1) == 0) ? 'setIsChangeEmail' : 'setIsChangePassword'}(true);
		}

		if (is_object($vars['order'])) {
			if (is_object($vars['order']->getPayment())) {
				$vars['billing'] = $vars['order']->getBillingAddress();
				$paymentBlock = Mage::helper('payment')->getInfoBlock($vars['order']->getPayment())->setIsSecureMode(true);
				$paymentBlock->getMethod()->setStore($store);
				$vars['payment_html'] = $paymentBlock->toHtml();
			}
			else {
				$vars['order']->setData('created_at', date('Y-m-d H:i:s'));
			}
		}

		$email = Mage::getModel('maillog/email')
			->setMailSubject($template->getProcessedTemplateSubject($vars), false)
			->setMailContent($template->getProcessedTemplate($vars))
			->setId(9999999);

		$this->getResponse()->setBody($email->toHtml(true));
	}
}