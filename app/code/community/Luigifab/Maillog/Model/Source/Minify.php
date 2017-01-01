<?php
/**
 * Created M/24/03/2015
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

class Luigifab_Maillog_Model_Source_Minify extends Luigifab_Maillog_Helper_Data {

	public function toOptionArray() {

		$tidy = (extension_loaded('tidy') && class_exists('tidy', false)) ?
			date('Ymd', strtotime(tidy_get_release())) : $this->__('not available');

		return array(
			array('value' => 0,        'label' => $this->__('No')),
			array('value' => 'manual', 'label' => $this->__('With search and replace')),
			array('value' => 'tidy',   'label' => $this->__('With PHP-TIDY (%s)', $tidy))
		);
	}
}