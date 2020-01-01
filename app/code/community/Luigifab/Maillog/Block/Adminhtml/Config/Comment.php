<?php
/**
 * Created S/26/10/2019
 * Updated J/07/11/2019
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

class Luigifab_Maillog_Block_Adminhtml_Config_Comment extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	public function _getHeaderCommentHtml($element) {

		return implode("\n", [
			'<div class="comment">',
			'<p>'.$element->getComment().'</p>',
			'<ul>',
			'<li>{{if something gt/gte/gteq/lt/lte/lteq/eq/neq something/empty}} ... {{elseif ...}} ... {{else}} ... {{/if}}</li>',
			'<li>{{foreach something}} ... {{forelse}} ... {{/foreach}}</li>',
			'<li>{{dump something}} | {{dump}}</li>',
			'<li>{{number something}}</li>',
			'</ul>',
			'</div>'
		]);
	}
}
