<?php
/**
 * Created S/26/10/2019
 * Updated M/21/04/2020
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

class Luigifab_Maillog_Block_Adminhtml_Config_Directive extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	public function _getHeaderCommentHtml($element) {

		return implode("\n", [
			'<div class="comment">',
				'<p>'.$element->getComment().'</p>',
				'<ul>',
					'<li>{{if something gt/gte/gteq/lt/lte/lteq/eq/neq something/empty}} ... {{elseif ...}} ... {{else}} ... {{/if}}</li>',
					'<li>{{if something in/nin a,b,c,1,2,3}} ... {{elseif ...}} ... {{else}} ... {{/if}}  (in_array)</li>',
					'<li>{{if something ct/nct something}} ... {{elseif ...}} ... {{else}} ... {{/if}}  (contains)</li>',
					'<li>{{foreach something}} ... {{forelse}} ... {{/foreach}}</li>',
					'<li>{{dump something}} | {{dump}}</li>',
					'<li>{{number something}}</li>',
					'<li>{{price something}}</li>',
					'<li>{{picture ...}}</li>',
				'</ul>',
			'</div>'
		]);
	}
}
