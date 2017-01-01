<?php
/**
 * Created W/11/11/2015
 * Updated M/08/11/2016
 *
 * Copyright 2015-2017 | Fabrice Creuzot <fabrice.creuzot~label-park~com>, Fabrice Creuzot (luigifab) <code~luigifab~info>
 * https://redmine.luigifab.info/projects/magento/wiki/maillog
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

class Luigifab_Maillog_Model_Source_Type extends Luigifab_Maillog_Helper_Data {

	public function toOptionArray() {

		$models = $this->searchFiles(BP.'/app/code/community/Luigifab/Maillog/Model/System');
		$options = array();

		foreach ($models as $model) {
			$model = Mage::getSingleton($model);
			$options[strtolower($model->getType())] = array('value' => strtolower($model->getType()), 'label' => $model->getType());
		}

		ksort($options);
		return $options;
	}

	private function searchFiles($source) {

		$files = array();
		$ressource = opendir($source);

		while (($file = readdir($ressource)) !== false) {

			if ((strpos($file, '.') !== 0) && is_file($source.'/'.$file))
				$files[] = 'maillog/system_'.strtolower(substr($file, 0, -4));
		}

		closedir($ressource);
		return $files;
	}
}