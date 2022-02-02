<?php
/**
 * Created V/03/01/2020
 * Updated J/20/01/2022
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

class Luigifab_Maillog_Helper_Picture extends Luigifab_Maillog_Helper_Data {

	public function getTag(array $params) {

		$config = $this->getConfig();
		$params = $this->before($params, $config);

		// code toujours obligatoire avec file ou product ou category
		// [code, file|product|category, attribute=image, helper=catalog/image]
		$code      = empty($params['code'])      ? null : $params['code'];
		$file      = empty($params['file'])      ? null : $params['file'];
		$product   = empty($params['product'])   ? null : $params['product'];
		$category  = empty($params['category'])  ? null : $params['category'];
		$attribute = empty($params['attribute']) ? 'image' : $params['attribute'];

		// vérifie
		if (empty($code) || empty($config[$code]))
			return null;

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
				$params['file'] = $product->getData($attribute);
			if (!array_key_exists('alt', $params))
				$params['alt']  = $this->escapeEntities($product->getData('name'), true);
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
				$params['file'] = $category->getData('image');
			if (!array_key_exists('alt', $params))
				$params['alt']  = $this->escapeEntities($category->getData('name'), true);
		}

		// vérifie
		if (empty($params['file']))
			return null;

		// action
		$sizes  = $config[$code];
		$object = $this->getObject($params, $product ?? $category);
		return $this->after($this->createHtml($params, $sizes, $object, $attribute), $code, $sizes);
	}

	protected function getConfig() {

		if (empty($this->_pictureConfig)) {

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

	protected function before(array $params, array $config) {
		return $params;
	}

	protected function getObject(array $params, $object) {
		return is_object($object) ? $object : Mage::getModel('catalog/product');
	}

	protected function createHtml(array $params, array $sizes, object $object, string $attribute) {

		// cache des tags html générés
		if (empty($this->_cacheTags)) {
			$this->_cacheTags = Mage::app()->useCache('block_html') ? @json_decode(Mage::app()->loadCache('maillog_tags'), true) : null;
			if (empty($this->_cacheTags) || !is_array($this->_cacheTags))
				$this->_cacheTags = ['date' => date('Y-m-d H:i:s \U\T\C')];
		}

		$attrs = $this->createHtmlAttributes($params, $sizes);
		$cache = md5($object->getId().$object->getStoreId().$params['code'].implode('', $attrs).$attribute.$params['file']);
		$tags  = $this->_cacheTags[$cache] ?? null;

		// crée les tags html
		if (empty($tags)) {
			$tags = $this->createHtmlTags($params, $sizes, $attrs, $object, $attribute);
			$this->_cacheTags[$cache] = $tags;
		}

		// debug
		if ($this->_configShowImageSize) {

			array_unshift($tags, '<span class="maillogdebug" style="position:absolute; height:14px; line-height:14px; padding:0 1px; z-index:4; font-size:12px; color:#FFF; background-color:#000;">...</span>');

			// ajoute un js une seule fois
			if (empty(Mage::registry('maillog_debug'))) {
				Mage::register('maillog_debug', true, true);
				array_unshift($tags, '<script type="text/javascript">'.preg_replace('#\s+#', ' ', trim($this->createHtmlScript())).'</script>');
			}
		}

		return implode("\n", $tags);
	}

	protected function createHtmlTags(array $params, array $sizes, array $attrs, object $object, string $attribute) {

		$help = Mage::helper(empty($params['helper']) ? 'catalog/image' : $params['helper']);
		$file = $params['file'];
		$tags = ['<picture>'];

		if (substr($file, -4) == '.svg') {
			$size   = (array) end($sizes); // (yes)
			$tags[] = '<img src="'.$help->init($object, $attribute, $file)->resize($size['w'], $size['h']).'" '.implode(' ', $attrs).' />';
		}
		else {
			$total = count($sizes);
			foreach ($sizes as $breakpoint => $size) {
				$srcs = [
					(string) $help->init($object, $attribute, $file)->resize($size['w'] * 1, $size['h'] * 1),
					(string) $help->init($object, $attribute, $file)->resize($size['w'] * 2, $size['h'] * 2)
				];
				// n'ajoute pas une seule balise source
				if ($total == 1) break;
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
			$tags[] = '<img data-debug="'.$size['w'].'/'.($size['w'] * 2).'" src="'.$srcs[0].'" srcset="'.$srcs[1].' 2x" '.implode(' ', $attrs).' />';
		}

		$tags[] = '</picture>';
		return $tags;
	}

	protected function createHtmlAttributes(array $params, array $sizes) {

		$attrs = [];

		foreach ($params as $key => $param) {
			if (!is_numeric($key) && !in_array($key, ['code', 'file', 'helper', 'attribute', 'product', 'category']))
				$attrs[] = $key.'="'.str_replace('"', '', $param).'"';
		}

		return $attrs;
	}

	protected function createHtmlScript() {

		return '
if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}
function maillogdebug() {
	document.querySelectorAll("span.maillogdebug ~ picture img").forEach(function (elem) {
		if (elem.hasAttribute("onload")) {
			if (elem.getAttribute("onload").indexOf("maillogdebug") < 0)
				elem.setAttribute("onload", "maillogdebug(); " + elem.getAttribute("onload"));
		}
		else {
			elem.setAttribute("onload", "maillogdebug();");
		}
		if (!elem.currentSrc) return;
		var cur = elem.currentSrc.slice(elem.currentSrc.indexOf("/", 9)),
		    src = elem.parentNode.querySelector("source[srcset*=\"" + cur + "\"], img"),
		    nbs = elem.parentNode.querySelectorAll("source").length,
		    tmp = elem.getAttribute("src").substr(-4);
		if (!src && (tmp !== ".svg")) return;
		elem = elem.parentNode.previousSibling;
		while ((elem.nodeName !== "BODY") && (elem.nodeName !== "SPAN")) elem = elem.previousSibling;
		if (tmp !== ".svg") {
			tmp = src.getAttribute("data-debug");
			tmp = tmp.slice(tmp.indexOf(" ") + 1).split("/");
			if (cur.indexOf("/" + tmp[0] + "x") > 0)
				elem.textContent = tmp[0] + " (" + nbs + "s)";
			else if (cur.indexOf("/" + tmp[1] + "x") > 0)
				elem.textContent = tmp[1] + " (" + nbs + "s)";
			else
				elem.textContent = "??? (" + nbs + "s)";
		}
		else {
			elem.textContent = "SVG";
		}
	});
}
self.addEventListener("load", maillogdebug);
self.addEventListener("resize", maillogdebug);
';
	}

	protected function after(string $html, string $code, array $sizes) {
		return $html;
	}

	public function __destruct() {

		if (!empty($this->_cacheTags) && Mage::app()->useCache('block_html'))
			Mage::app()->saveCache(json_encode($this->_cacheTags), 'maillog_tags',
				[Mage_Core_Model_Config::CACHE_TAG, Mage_Core_Block_Abstract::CACHE_GROUP]);
	}
}