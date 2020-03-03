<?php
/**
 * Created V/03/01/2020
 * Updated L/24/02/2020
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

class Luigifab_Maillog_Helper_Picture extends Luigifab_Maillog_Helper_Data {

	public function getTag(array $values) {

		$config = new ArrayObject($this->getPictureConfig());
		$values = new ArrayObject($values);
		Mage::dispatchEvent('maillog_update_picture', ['values' => $values, 'config' => $config]);

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
			if (is_numeric($product))
				$product = Mage::getModel('catalog/product')->load($product);
			else if (is_string($product))
				$product = Mage::getModel('catalog/product')->load($product, 'sku');
		}
		if (is_object($product) && !empty($product->getId())) {
			if (empty($file))
				$file = $product->getData($attribute);
			if (empty($values['alt']))
				$values['alt'] = $this->escapeEntities($product->getData('name'), true);
		}

		// récupére ou charge l'éventuelle catégorie
		if (!empty($category) && is_numeric($category))
			$category = Mage::getModel('catalog/category')->load($category);
		if (is_object($category) && !empty($category->getId())) {
			$attribute = 'category'; // sinon ça marchera pas
			if (empty($file))
				$file = $category->getData('image');
			if (empty($values['alt']))
				$values['alt'] = $this->escapeEntities($category->getData('name'), true);
		}

		// reconstruit les éventuels attributs
		$extra = [];
		foreach ($values as $key => $value) {
			if (!is_numeric($key) && !in_array($key, ['code', 'file', 'helper', 'attribute', 'product', 'category']))
				$extra[] = $key.'="'.str_replace('"', '', $value).'"';
		}

		// action
		if (!is_object($product))
			$product = Mage::getModel('catalog/product');

		return (empty($code) || empty($file) || empty($config[$code])) ? null :
			$this->createTag($product, $helper, $config[$code], $extra, $attribute, $file);
	}

	private function createTag(object $product, object $helper, array $sizes, array $extra, string $attribute, string $file) {

		$font = (float) Mage::getStoreConfig('maillog_directives/general/fontsize');
		$tags = ['<picture>'];

		foreach ($sizes as $breakpoint => $size) {

			$srcs = [
				(string) $helper->init($product, $attribute, $file)->resize($size['w'] * 1, $size['h'] * 1),
				(string) $helper->init($product, $attribute, $file)->resize($size['w'] * 2, $size['h'] * 2)
			];

			// https://blog.55minutes.com/2012/04/media-queries-and-browser-zoom/
			// 16 parce qu'en javascript getComputedStyle(document.documentElement).fontSize = 16 ($font)
			if (count($sizes) == count($tags)) { // min-width uniquement sur le dernier
				$tags[] = '<source data-debug="'.$breakpoint.' '.$size['w'].'/'.($size['w'] * 2).'" media="(min-width:'.$rem.'rem)" srcset="'.sprintf('%s 1x, %s 2x', ...$srcs).'" />';
			}
			else {
				$rem    = round($breakpoint / (($font > 0) ? $font : 16), 1);
				$tags[] = '<source data-debug="'.$breakpoint.' '.$size['w'].'/'.($size['w'] * 2).'" media="(max-width:'.$rem.'rem)" srcset="'.sprintf('%s 1x, %s 2x', ...$srcs).'" />';
			}
		}

		$tags[] = '<img data-debug="'.$size['w'].'/'.($size['w'] * 2).'" src="'.$srcs[0].'" srcset="'.$srcs[1].' 2x" '.implode(' ', $extra).' />';
		$tags[] = '</picture>';

		if (Mage::getStoreConfigFlag('maillog_directives/general/show_image_size')) {

			$tags[0] = '<picture style="outline:1px dotted gray;">';
			array_unshift($tags, '<span class="maillogdebug" style="position:absolute; z-index:9999999; color:#FFF; background-color:#000;">...</span>');

			// ajoute un js une seule fois
			if (Mage::registry('maillog_debug') !== true) {
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
		var cur = elem.currentSrc, src = (elem.parentNode.querySelector("source[srcset*=\"" + cur + "\"]")), tmp;
		if (!src) return;
		tmp  = src.getAttribute("data-debug");
		elem = elem.parentNode.previousSibling;
		while ((elem.nodeName !== "BODY") && (elem.nodeName !== "SPAN"))
			elem = elem.previousSibling;
		tmp = tmp.slice(tmp.indexOf(" ") + 1).split("/");
		if (cur.indexOf("/" + tmp[0] + "x") > 0)
			elem.textContent = tmp[0];
		else if (cur.indexOf("/" + tmp[1] + "x") > 0)
			elem.textContent = tmp[1];
		else
			elem.textContent = "???";
	});
}
self.addEventListener("load", maillogdebug);
self.addEventListener("resize", maillogdebug);
				')).'</script>');
				Mage::register('maillog_debug', true);
			}
		}

		return implode("\n", $tags);
	}

	private function getPictureConfig() {

		if (empty($this->pictureConfig)) {

			$config = @unserialize(Mage::getStoreConfig('maillog_directives/general/special_config'));
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

			$this->pictureConfig = $config;
		}

		return $this->pictureConfig;

	}
}