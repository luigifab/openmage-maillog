<?php
/**
 * Created W/11/11/2015
 * Updated J/10/01/2019
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

class Luigifab_Maillog_Model_Source_Type {

	public function toOptionArray() {

		$models  = $this->searchFiles(Mage::getModuleDir('', 'Luigifab_Maillog').'/Model/System');
		$options = array();

		foreach ($models as $model) {
			$model = Mage::getSingleton($model);
			$type  = mb_strtolower($model->getType());
			$options[$type] = array('value' => $type, 'label' => $model->getType());
		}

		ksort($options);
		return $options;
	}

	private function searchFiles($source) {

		$files = array();
		$ressource = opendir($source);

		while (($file = readdir($ressource)) !== false) {
			if ((mb_strpos($file, '.') !== 0) && is_file($source.'/'.$file))
				$files[] = 'maillog/system_'.mb_strtolower(mb_substr($file, 0, -4));
		}

		closedir($ressource);
		return $files;
	}
}