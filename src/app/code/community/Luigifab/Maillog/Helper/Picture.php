<?php
/**
 * Created V/03/01/2020
 * Updated V/18/06/2021
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

class Luigifab_Maillog_Helper_Picture extends Luigifab_Maillog_Helper_Data {

	public function getTag(array $values) {

		$config = $this->getPictureConfig();

		// event before (vendor/singleton::method)
		// avant de commencer la recherche des valeurs
		if (!empty($this->_configUpdateConfigandvaluesBefore)) {
			$event = $this->_configUpdateConfigandvaluesBefore;
			$event = Mage::helper($event[0])->{$event[1]}($values, $config);
			if ($event !== true)
				return $event;
		}

		// [code, file|product|category, attribute=image, helper=catalog/image]
		//  code toujours obligatoire avec file ou product ou category
		$code      = empty($values['code'])      ? null : $values['code'];
		$file      = empty($values['file'])      ? null : $values['file'];
		$product   = empty($values['product'])   ? null : $values['product'];
		$category  = empty($values['category'])  ? null : $values['category'];
		$attribute = empty($values['attribute']) ? 'image' : $values['attribute'];
		$helper    = Mage::helper(empty($values['helper']) ? 'catalog/image' : $values['helper']);

		// récupére ou charge l'éventuel produit
		if (!empty($product)) {
			if (is_numeric($product)) {
				$id = $product;
				$storeId  = Mage::app()->getStore()->getId();
				$resource = Mage::getResourceSingleton('catalog/product');
				$product  = Mage::getModel('catalog/product')
					->setId($id)
					->setData('name', $resource->getAttributeRawValue($id, 'name', $storeId))
					->setData($attribute, $resource->getAttributeRawValue($id, $attribute, $storeId));
			}
			else if (is_string($product)) {
				$id = Mage::getModel('catalog/product')->getIdBySku($product);
				$storeId  = Mage::app()->getStore()->getId();
				$resource = Mage::getResourceSingleton('catalog/product');
				$product  = Mage::getModel('catalog/product')
					->setId($id)
					->setData('name', $resource->getAttributeRawValue($id, 'name', $storeId))
					->setData($attribute, $resource->getAttributeRawValue($id, $attribute, $storeId));
			}
		}
		if (is_object($product) && !empty($product->getId())) {
			if (empty($file))
				$file = $product->getData($attribute);
			if (!array_key_exists('alt', $values))
				$values['alt'] = $this->escapeEntities($product->getData('name'), true);
		}

		// récupére ou charge l'éventuelle catégorie
		if (!empty($category) && is_numeric($category)) {
			$id = $category;
			$storeId  = Mage::app()->getStore()->getId();
			$resource = Mage::getResourceSingleton('catalog/category');
			$category = Mage::getModel('catalog/category')
				->setId($id)
				->setData('name', $resource->getAttributeRawValue($id, 'name', $storeId))
				->setData($attribute, $resource->getAttributeRawValue($id, $attribute, $storeId));
		}
		if (is_object($category) && !empty($category->getId())) {
			$attribute = 'category'; // sinon ça marchera pas
			if (empty($file))
				$file = $category->getData('image');
			if (!array_key_exists('alt', $values))
				$values['alt'] = $this->escapeEntities($category->getData('name'), true);
		}

		// reconstruit les éventuels attributs
		$extra = [];
		foreach ($values as $key => $value) {
			if (!is_numeric($key) && !in_array($key, ['code', 'file', 'helper', 'attribute', 'product', 'category']))
				$extra[] = $key.'="'.str_replace('"', '', $value).'"';
		}

		// action
		if (empty($code) || empty($file) || empty($config[$code]))
			return null;
		if (!is_object($product))
			$product = Mage::getModel('catalog/product');

		$sizes = $config[$code];

		// event ready (vendor/singleton::method)
		// avant la génération des balises html
		if (!empty($this->_configUpdateConfigandvaluesReady)) {
			$event = $this->_configUpdateConfigandvaluesReady;
			$event = Mage::helper($event[0])->{$event[1]}($product, $helper, $sizes, $extra, $attribute, $file);
			if ($event !== true)
				return $event;
		}

		// crée les tags
		$key  = md5($product->getId().$product->getStoreId().$code.implode('', $extra).$attribute.$file);
		$html = $this->createTag($product, $helper, $sizes, $extra, $attribute, $file, $key);

		// event after (vendor/singleton::method)
		// après la génération des balises html
		if (!empty($this->_configUpdateConfigandvaluesAfter)) {
			$event = $this->_configUpdateConfigandvaluesAfter;
			return Mage::helper($event[0])->{$event[1]}($html, $code);
		}

		return $html;
	}

	private function createTag(object $product, object $helper, array $sizes, array $extra, string $attribute, string $file, string $key) {

		// cache des tags générés
		if (empty($this->_cacheTags)) {

			$this->_cacheTags = Mage::app()->useCache('block_html') ? @json_decode(Mage::app()->loadCache('maillog_tags'), true) : null;
			if (empty($this->_cacheTags) || !is_array($this->_cacheTags)) {
				$this->_cacheTags = [
					'date' => date('Y-m-d H:i:s \U\T\C')
				];
			}
		}

		$tags = $this->_cacheTags[$key] ?? null;

		// crée le tag
		if (empty($tags)) {

			$tags = ['<picture>'];

			if (substr($file, -4) == '.svg') {
				$size   = (array) end($sizes); // (yes)
				$tags[] = '<img src="'.$helper->init($product, $attribute, $file)->resize($size['w'], $size['h']).'" '.implode(' ', $extra).' />';
			}
			else {
				foreach ($sizes as $breakpoint => $size) {
					$srcs = [
						(string) $helper->init($product, $attribute, $file)->resize($size['w'] * 1, $size['h'] * 1),
						(string) $helper->init($product, $attribute, $file)->resize($size['w'] * 2, $size['h'] * 2)
					];
					// https://blog.55minutes.com/2012/04/media-queries-and-browser-zoom/
					// 16 parce qu'en JavaScript getComputedStyle(document.documentElement).fontSize = 16 ($this->_configFontSize)
					if (count($sizes) == count($tags)) { // min-width uniquement sur le dernier
						$rem    = empty($rem) ? 0 : $rem;
						$tags[] = '<source data-debug="'.$breakpoint.' '.$size['w'].'/'.($size['w'] * 2).'" media="(min-width:'.$rem.'rem)" srcset="'.sprintf('%s 1x, %s 2x', ...$srcs).'" />';
					}
					else {
						$rem    = round($breakpoint / $this->_configFontSize, 1);
						$tags[] = '<source data-debug="'.$breakpoint.' '.$size['w'].'/'.($size['w'] * 2).'" media="(max-width:'.$rem.'rem)" srcset="'.sprintf('%s 1x, %s 2x', ...$srcs).'" />';
					}
				}
				$tags[] = '<img data-debug="'.$size['w'].'/'.($size['w'] * 2).'" src="'.$srcs[0].'" srcset="'.$srcs[1].' 2x" '.implode(' ', $extra).' />';
			}

			$tags[] = '</picture>';
			$this->_cacheTags[$key] = $tags;
		}

		if ($this->_configShowImageSize) {

			array_unshift($tags, '<span class="maillogdebug" style="position:absolute; height:14px; line-height:14px; z-index:1; font-size:12px; color:#FFF; background-color:#000;">...</span>');

			// ajoute un js une seule fois
			if (Mage::registry('maillog_debug') !== true) {

				// event custom js (vendor/singleton::method)
				$event = Mage::getStoreConfig('maillog_directives/general/update_maillogdebug_js');
				if (!empty($event) && preg_match('#\w+(?:/\w+)?::\w+#', $event) === 1) {
					$event  = (array) explode('::', $event); // (yes)
					$custom = Mage::helper($event[0])->{$event[1]}();
				}

				array_unshift($tags, '<script type="text/javascript">'.preg_replace('#\s+#', ' ', trim('
if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}
function maillogdebug() {
	document.querySelectorAll("span.maillogdebug ~ picture img").forEach(function (elem) {
		elem.setAttribute("onload", "maillogdebug();");
		var cur = elem.currentSrc, src = (elem.parentNode.querySelector("source[srcset*=\"" + cur + "\"]")), tmp = elem.getAttribute("src").substr(-4);
		if (!src && (tmp !== ".svg")) return;
		elem = elem.parentNode.previousSibling;
		while ((elem.nodeName !== "BODY") && (elem.nodeName !== "SPAN")) elem = elem.previousSibling;
		if (tmp !== ".svg") {
			tmp = src.getAttribute("data-debug");
			tmp = tmp.slice(tmp.indexOf(" ") + 1).split("/");
			if (cur.indexOf("/" + tmp[0] + "x") > 0)
				elem.textContent = tmp[0];
			else if (cur.indexOf("/" + tmp[1] + "x") > 0)
				elem.textContent = tmp[1];
			else
				elem.textContent = "???";
		}
		else {
			elem.textContent = "SVG";
		}
	});
}
self.addEventListener("load", maillogdebug);
self.addEventListener("resize", maillogdebug);
'.(empty($custom) ? '' : $custom))).'</script>');
				Mage::register('maillog_debug', true);
			}
		}

		return implode("\n", $tags);
	}

	private function getPictureConfig() {

		if (empty($this->_pictureConfig)) {

			// config général
			$event = Mage::getStoreConfig('maillog_directives/general/update_configandvalues_before');
			if (!empty($event) && preg_match('#\w+(?:/\w+)?::\w+#', $event) === 1)
				$this->_configUpdateConfigandvaluesBefore = (array) explode('::', $event); // (yes)

			$event = Mage::getStoreConfig('maillog_directives/general/update_configandvalues_ready');
			if (!empty($event) && preg_match('#\w+(?:/\w+)?::\w+#', $event) === 1)
				$this->_configUpdateConfigandvaluesReady = (array) explode('::', $event); // (yes)

			$event = Mage::getStoreConfig('maillog_directives/general/update_configandvalues_after');
			if (!empty($event) && preg_match('#\w+(?:/\w+)?::\w+#', $event) === 1)
				$this->_configUpdateConfigandvaluesAfter = (array) explode('::', $event); // (yes)

			$this->_configFontSize = (float) Mage::getStoreConfig('maillog_directives/general/font_size');
			$this->_configFontSize = ($this->_configFontSize > 0) ? $this->_configFontSize : 16;

			$this->_configShowImageSize = Mage::getStoreConfigFlag('maillog_directives/general/show_image_size');

			// config des tags (avec mise en cache)
			$config = Mage::app()->useCache('config') ? @json_decode(Mage::app()->loadCache('maillog_config'), true) : null;
			if (empty($config) || !is_array($config)) {

				$config = @unserialize(Mage::getStoreConfig('maillog_directives/general/special_config'), ['allowed_classes' => false]);
				$config = is_array($config) ? $config : [];

				// à partir de
				// $config[0] => Array(
				//  [c] => test
				//  [d] =>
				//  [0] => Array( [w] => 560 [h] => 480 )
				//  [1] => Array( [b] => 320 [w] => 209 [h] => 177 )
				//  [3] => Array( [b] => 768 [w] => 420 [h] => 360 )
				//  [2] => Array( [b] => 380 [w] => 252 [h] => 216 )
				// )
				// génère
				// $config[test] => Array(
				//  [320] => Array( [b] => 320 [w] => 209 [h] => 177 )
				//  [380] => Array( [b] => 380 [w] => 252 [h] => 216 )
				//  [768] => Array( [b] => 768 [w] => 420 [h] => 360 )
				//  [769] => Array( [w] => 560 [h] => 480 )
				// )
				foreach ($config as $key => $data) {

					$config[$data['c']] = [];
					foreach ($data as $subdata) {
						if (is_array($subdata))
							$config[$data['c']][empty($subdata['b']) ? 0 : $subdata['b']] = $subdata;
					}
					unset($config[$key]);

					ksort($config[$data['c']]);
					$last = array_keys($config[$data['c']]);
					$last = array_pop($last);
					$config[$data['c']][$last + 1] = $config[$data['c']][0];
					unset($config[$data['c']][0]);
				}

				if (Mage::app()->useCache('config'))
					Mage::app()->saveCache(json_encode($config), 'maillog_config', [Mage_Core_Model_Config::CACHE_TAG]);
			}

			$this->_pictureConfig = $config;
		}

		return $this->_pictureConfig;
	}

	public function __destruct() {

		if (!empty($this->_cacheTags) && Mage::app()->useCache('block_html'))
			Mage::app()->saveCache(json_encode($this->_cacheTags), 'maillog_tags', [Mage_Core_Model_Config::CACHE_TAG, Mage_Core_Block_Abstract::CACHE_GROUP]);
	}
}