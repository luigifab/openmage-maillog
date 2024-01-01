<?php
/**
 * Created S/25/11/2023
 * Updated S/25/11/2023
 *
 * Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://github.com/luigifab/openmage-maillog
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

class Luigifab_Maillog_Block_Adminhtml_Config_Addresses extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract {

    public function _prepareToRender() {

		$this->addColumn('email', [
			'label' => $this->__('Email Address'),
			'style' => 'width:150px;',
			'class' => 'input-text required-entry validate-email',
		]);

		$this->addColumn('locale', [
			'label' => $this->__('Locale'),
			'style' => 'width:80px;',
		]);

		$this->_addAfter = false;
	}

	protected function _renderCellTemplate($columnName) {

		if ($columnName != 'locale')
			return parent::_renderCellTemplate($columnName);

		$name = $this->getElement()->getName();
		$html = [];

		$html[] = '<select name="'.$name.'[#{_id}]['.$columnName.']'.'" style="'.$this->_columns[$columnName]['style'].'">';
		$html[] = '<option value="0">auto</option>';

		$langs = glob(BP.'/app/locale/*/template/email/'.Mage::getConfig()->getNode('global/template/email/maillog_email_template/file'));
		foreach ($langs as $lang) {
			$lang = explode('/', mb_substr($lang, mb_strpos($lang, '/locale/') + 8));
			$lang = array_shift($lang);
			$html[] = '<option value="'.$lang.'" #{option_extra_attr_'.$this->_calcKey($name, $lang).'}>'.$lang.'</option>';
		}
		$html[] = '</select>';

		return implode($html);
	}

	protected function _prepareArrayRow(Varien_Object $row) {

		if (!empty($lang = $row->getData('locale')))
			$row->setData('option_extra_attr_'.$this->_calcKey($this->getElement()->getName(), $lang), 'selected="selected"');
	}

	protected function _calcKey($name, $lang) {
		return substr(md5($name.$lang), 0, 10); // not mb_substr
	}
}