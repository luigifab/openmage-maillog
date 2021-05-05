<?php
/**
 * Created D/22/03/2015
 * Updated V/16/04/2021
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

abstract class Luigifab_Maillog_Model_Filter {

	public function resetVariables(array $vars) {
		$this->_templateVars = $vars;
		return $this;
	}


	public function foreachDirective($match) {

		$items   = $this->_getVariable($match[1], '');
		$replace = '';

		if (!empty($items) && is_iterable($items)) {
			$i = 1;
			foreach ($items as &$item) {
				if (is_object($item) || is_array($item)) {
					foreach ((is_object($item) ? $item->getData() : $item) as $key => &$data)
						$this->setVariables([$match[1].$i.'_'.$key => &$data]);
					unset($data);
				}
				else {
					$this->setVariables([$match[1].$i.'_'.$key => &$item]);
				}
				$replace .= str_replace(' '.$match[1].'.', ' '.$match[1].$i.'_', $match[2]);
				$i++;
			}
			unset($item);
		}
		else if (isset($match[4])) {
			$replace = $match[4];
		}

		return $replace;
	}

	public function ifelseDirective($match) {

		array_shift($match); // $match[0] = tout le groupe = osef
		$replace = '';

		// $match[1/3/5...] = var cond var   => $value
		// $match[2/4/6...] = valeur si vrai
		// $match[...11=last-1] = {{else}}
		// $match[...12=last] = valeur si faux
		while (count($match) > 0) {
			$value = array_shift($match);
			if ($value == '{{else}}') {
				$replace = array_shift($match);
				break;
			}
			if (!empty($value) && !empty($this->_getVariable($value, ''))) {
				$replace = array_shift($match);
				break;
			}
		}

		return $replace;
	}

	public function ifconfigDirective($match) {

		$store = empty($this->_templateVars['store']) ? null : $this->_templateVars['store'];
		if (Mage::getStoreConfigFlag(trim(str_replace(['path=', '\'', '"'], '', $match[1])), $store))
			return $match[2];

		return empty($match[3]) ? '' : $match[3];
	}

	public function helperDirective($match) {

		// helper action='xx/yy::zz'
		$store = empty($this->_templateVars['store']) ? null : $this->_templateVars['store'];
		$attrs = $this->extractAttributes($match[2]);

		if (array_key_exists('ifconfig', $attrs) && !Mage::getStoreConfigFlag($attrs['ifconfig'], $store))
			$action = null;
		else if (array_key_exists('action', $attrs) && preg_match('#\w+(?:/\w+)?::\w+#', $attrs['action']) === 1)
			$action = (array) explode('::', $attrs['action']); // (yes)

		return empty($action) ? '' : Mage::helper($action[0])->{$action[1]}(...array_values(array_slice($attrs, 1)));
	}

	public function numberDirective($match) {

		$store  = empty($this->_templateVars['store']) ? null : $this->_templateVars['store'];
		$locale = Mage::app()->getStore()->isAdmin() ? Mage::getSingleton('core/translate')->getLocale() :
			Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);

		$attrs = $this->extractAttributes($match[2], 'number');
		if (array_key_exists('ifconfig', $attrs) && !Mage::getStoreConfigFlag($attrs['ifconfig'], $store))
			$number = null;
		else if (array_key_exists('path', $attrs))
			$number = Mage::getStoreConfig($attrs['path'], $store);
		else if (array_key_exists('config', $attrs))
			$number = Mage::getStoreConfig($attrs['config'], $store);
		else
			$number = $attrs['number'];

		$params = ['locale' => $locale];
		if (array_key_exists('nodecimal', $attrs))
			$params['precision'] = 0;
		else if (array_key_exists('precision', $attrs))
			$params['precision'] = (int) $attrs['precision'];

		return is_numeric($number) ? Zend_Locale_Format::toNumber((float) $number, $params) : '';
	}

	public function priceDirective($match) {

		$attrs  = $this->extractAttributes($match[2], 'number');
		$number = $attrs['number'];
		$store  = null;

		if (array_key_exists('store', $attrs))
			$store = (int) $attrs['store'];
		else if (!empty($this->_templateVars['store']))
			$store = $this->_templateVars['store'];

		$currency = Mage::app()->getStore($store);
		if (!empty($this->_templateVars['order']) && is_object($order = $this->_templateVars['order']))
			$currency = $order;

		if (array_key_exists('ifconfig', $attrs) && !Mage::getStoreConfigFlag($attrs['ifconfig'], $store))
			$number = null;
		else if (array_key_exists('path', $attrs))
			$number = Mage::getStoreConfig($attrs['path'], $store);
		else if (array_key_exists('config', $attrs))
			$number = Mage::getStoreConfig($attrs['config'], $store);
		else if (array_key_exists('product', $attrs)) {
			$website = Mage::app()->getStore($store)->getWebsite();
			$product = Mage::getResourceModel('catalog/product_collection')
				->addIdFilter($attrs['product'])
				->addStoreFilter($store)
				->addWebsiteFilter($website)
				->addPriceData(null, $website->getId()) //->addFinalPrice();
				->getFirstItem();
			$number = empty($product->getId()) ? null : $product->getFinalPrice();
		}

		if (!is_numeric($number))
			$replace = '';
		else if (array_key_exists('currency', $attrs))
			$replace = Mage::getModel('directory/currency')->load(strtoupper($attrs['currency']))->format($number);
		else
			$replace = $currency->formatPrice($number);

		if (array_key_exists('nodecimal', $attrs))
			$replace = preg_replace('#[.,]00[[:>:]]#', '', $replace);

		return $replace;
	}

	public function currencyDirective($match) {

		$attrs = $this->extractAttributes($match[2], 'code');
		$store = null;

		if (array_key_exists('store', $attrs))
			$store = (int) $attrs['store'];
		else if (!empty($this->_templateVars['store']))
			$store = $this->_templateVars['store'];

		if (array_key_exists('ifconfig', $attrs) && !Mage::getStoreConfigFlag($attrs['ifconfig'], $store))
			$currency = null;
		else if (array_key_exists('path', $attrs))
			$currency = strtoupper(Mage::getStoreConfig($attrs['path'], $store));
		else if (array_key_exists('config', $attrs))
			$currency = strtoupper(Mage::getStoreConfig($attrs['config'], $store));
		else
			$currency = strtoupper($attrs['code']);

		return empty($currency) ? '' : Mage::getSingleton('core/locale')->getTranslation($currency, 'nametocurrency');
	}

	public function includefileDirective($match) {

		$store  = empty($this->_templateVars['store']) ? null : $this->_templateVars['store'];
		$locale = Mage::app()->getStore()->isAdmin() ? Mage::getSingleton('core/translate')->getLocale() :
			Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);

		$attrs  = $this->extractAttributes($match[1]);

		if (array_key_exists('template', $attrs) && (!array_key_exists('ifconfig',$attrs)||Mage::getStoreConfigFlag($attrs['ifconfig'], $store))) {
			$file = Mage::getBaseDir('app').'/locale/'.$locale.'/template/email/'.$attrs['template'];
			if (!is_file($file))
				$file = Mage::getBaseDir('app').'/locale/en_US/template/email/'.$attrs['template'];
			if (is_file($file))
				$replace = file_get_contents($file);
		}

		return empty($replace) ? '' : $replace;
	}

	public function pictureDirective($match) {
		return Mage::helper('maillog/picture')->getTag($this->extractAttributes($match[2]));
	}

	public function dumpDirective($match) {
		$data = empty($match[2]) ? $this->_templateVars : ($this->_templateVars[$match[2]] ?? null);
		return '<pre>'.trim(is_array($data) ? print_r($this->dumpArray($data), true) : var_export($data, true)).'</pre>';
	}


	public function filter($value) {

		foreach ([
			'/{{include\s*(.*?)}}/si' => 'includefileDirective',
			'/{{foreach\s*(.*?)}}(.*?)({{forelse}}(.*?))?{{\\/foreach\s*}}/si' => 'foreachDirective',
			'/{{ifconfig\s*(.*?)}}(.*?)(?:{{elseconfig}}(.*?))?{{\/ifconfig}}/six' => 'ifconfigDirective',
			'/{{depend\s*(.*?)}}(.*?){{\\/depend\s*}}/si' => 'dependDirective',
			'/{{if\s*(.*?)}}(.*?)(?={{els|{{\/if}})
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:{{else?if\s*(.*?)}}(.*?)(?={{els|{{\/if}}))?
(?:({{else}})(.*?))?
{{\/if}}/six' => 'ifelseDirective'
		] as $pattern => $directive) {
			if (preg_match_all($pattern, $value, $constructions, PREG_SET_ORDER)) {
				foreach ($constructions as $construction) {
					$callback = [$this, $directive];
					if (is_callable($callback)) {
						try {
							$value = str_replace($construction[0], $callback($construction), $value);
						}
						catch (Throwable $e) {
							Mage::logException($e);
							$value = str_replace($construction[0], '', $value);
						}
					}
				}
			}
		}

		if (preg_match_all(Varien_Filter_Template::CONSTRUCTION_PATTERN, $value, $constructions, PREG_SET_ORDER)) {
			$debug = !empty(Mage::registry('maillog_preview'));
			foreach ($constructions as $construction) {
				$callback = [$this, $construction[1].'Directive'];
				if (is_callable($callback)) {
					try {
						$replace = $callback($construction);
						if (!$debug || !empty($replace) || in_array($construction[1], ['inlinecss', 'block']))
							$value = str_replace($construction[0], (is_object($replace) || is_array($replace)) ? '' : $replace, $value);
					}
					catch (Throwable $e) {
						Mage::logException($e);
						$value = str_replace($construction[0], '', $value);
					}
				}
			}
		}

		return $value;
	}

	protected function _getVariable($value, $default = '{no_value_defined}') {

		if (empty($value))
			return '';

		// >
		if (mb_stripos($value, ' gt ') !== false) {
			$values = array_map('trim', explode(' gt ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base > $check) : false;
		}
		// >=
		if (mb_stripos($value, ' gte ') !== false) {
			$values = array_map('trim', explode(' gte ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base >= $check) : false;
		}
		if (mb_stripos($value, ' gteq ') !== false) {
			$values = array_map('trim', explode(' gteq ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base >= $check) : false;
		}
		// <
		if (mb_stripos($value, ' lt ') !== false) {
			$values = array_map('trim', explode(' lt ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base < $check) : false;
		}
		// <=
		if (mb_stripos($value, ' lte ') !== false) {
			$values = array_map('trim', explode(' lte ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base <= $check) : false;
		}
		if (mb_stripos($value, ' lteq ') !== false) {
			$values = array_map('trim', explode(' lteq ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return (is_numeric($base) && is_numeric($check)) ? ($base <= $check) : false;
		}
		// ==
		if (mb_stripos($value, ' eq ') !== false) {
			$values = array_map('trim', explode(' eq ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			if (($check == 'empty') && empty($base)) // == empty
				return true;
			if (is_numeric($base) && ($base == 0) && !is_numeric($check)) // en PHP, tout string vaut 0
				return false;
			return $base == $check;
		}
		// !=
		if (mb_stripos($value, ' neq ') !== false) {
			$values = array_map('trim', explode(' neq ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			if (($check == 'empty') && empty($base)) // != empty
				return false;
			if (is_numeric($base) && ($base == 0) && !is_numeric($check)) // en PHP, tout string vaut 0
				return true;
			return $base != $check;
		}
		// in_array
		if (mb_stripos($value, ' in ') !== false) {
			$values = array_map('trim', explode(' in ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			$checks = array_map('trim', explode(',', $values[1]));
			return !empty($checks) && in_array($base, $checks);
		}
		if (mb_stripos($value, ' nin ') !== false) {
			$values = array_map('trim', explode(' nin ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			$checks = array_map('trim', explode(',', $values[1]));
			return !empty($checks) && !in_array($base, $checks);
		}
		// contains
		if (mb_stripos($value, ' ct ') !== false) {
			$values = array_map('trim', explode(' ct ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return mb_stripos($base, $check) !== false;
		}
		if (mb_stripos($value, ' nct ') !== false) {
			$values = array_map('trim', explode(' nct ', $value));
			$base   = $this->_getVariable2($values[0], $default);
			if (empty($check = $this->_getVariable2($values[1], $default)) && !is_numeric($check))
				$check = $values[1];
			return mb_stripos($base, $check) === false;
		}

		return $this->_getVariable2($value, $default);
	}


	private function extractAttributes($data, $default = null) {

		$attrs = empty($default) ? [] : [$default => null];
		$parts = array_map('trim', (array) explode(' ', $data)); // (yes)

		foreach ($parts as $part) {
			if (mb_stripos($part, '=') !== false) {
				$part = (array) explode('=', $part); // (yes)
				if (mb_stripos($part[1], '$') === false)
					$attrs[$part[0]] = trim($part[1], '\'"');
				else
					$attrs[$part[0]] = $this->_getVariable(trim($part[1], '$'), trim($part[1], '$'));
			}
			else if (!empty($default)) {
				$attrs[$default] = $this->_getVariable($part, $part);
			}
		}

		return $attrs;
	}

	private function dumpArray(array $vars) {

		$data = [];

		foreach ($vars as $key => $var) {
			$type = gettype($var);
			if ($type == 'object')
				$data[$key.' '.get_class($var)] = $this->dumpObject($var->getData());
			else if ($type == 'array')
				$data[$key] = $this->dumpArray($var);
			else if ($type == 'string')
				$data[$key] = 'string (+striptags) => '.strip_tags(str_replace(['<br>', '<br/>', '<br />', "\n"], ' ', $var));
			else
				$data[$key] = $type.' => '.$var;
		}

		ksort($data);
		return $data;
	}

	private function dumpObject(array $vars) {

		$data = [];

		foreach ($vars as $key => $var) {
			$type = gettype($var);
			if ($type == 'object')
				$data[$key.' '.get_class($var)] = $this->dumpObject($var->getData());
			else if ($type == 'array')
				$data[$key] = $this->dumpArray($var);
			else
				$data[$key] = $type;
		}

		ksort($data);
		return $data;
	}
}