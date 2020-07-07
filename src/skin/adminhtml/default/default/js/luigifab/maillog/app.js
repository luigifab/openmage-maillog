/**
 * Created J/03/12/2015
 * Updated D/31/05/2020
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
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

if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}

var maillog = new (function () {

	"use strict";
	this.template = null;
	this.nextIdx  = 0;

	this.start = function () {

		if (document.getElementById('maillog_sync_bounces_stats_customer')) {

			console.info('maillog.app - hello');
			var customer   = document.getElementById('maillog_sync_bounces_stats_customer'),
			    bounce     = document.getElementById('maillog_sync_bounces_stats_bounce'),
			    subscriber = document.getElementById('maillog_sync_unsubscribers_stats_subscriber'),
			    i, j;

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(bounce.textContent.trim(), 10);
			bounce.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(subscriber.textContent.trim(), 10);
			subscriber.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';
		}
		else if (document.getElementById('row_maillog_directives_general_special_config')) {

			console.info('maillog.app - hello');

			var elem = document.querySelector('tr.template');
			this.template = elem.innerHTML;
			this.nextIdx  = parseInt(elem.getAttribute('data-next'), 10);
			elem.parentNode.removeChild(elem);
		}
	};

	this.add = function () {

		var sys = document.getElementById('maillog_sync_general_mapping_system'),
		    mag = document.getElementById('maillog_sync_general_mapping_openmage'),
		    cnf = document.getElementById('maillog_sync_general_mapping_config');

		if ((sys.value.length > 0) && (mag.value.length > 0)) {
			cnf.value = (cnf.value + '\n' + sys.value + ':' + mag.value).trim();
			cnf.scrollTop = cnf.scrollHeight;
			sys.selectedIndex = 0;
			mag.selectedIndex = 0;
			this.mark();
		}
	};

	this.mark = function () {

		var fields = document.getElementById('maillog_sync_general_mapping_config').value;

		document.getElementById('maillog_sync_general_mapping_system').querySelectorAll('option').forEach(function (elem) {
			if ((fields.indexOf('\n' + elem.value + ':') > -1) || (fields.indexOf(elem.value + ':') === 0))
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});

		document.getElementById('maillog_sync_general_mapping_openmage').querySelectorAll('option').forEach(function (elem) {
			if (fields.indexOf(':' + elem.value) > -1)
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});
	};

	this.iframe = function (elem) {

		try {
			elem.removeAttribute('onload');
			elem.contentDocument.querySelector('body').parentNode.innerHTML = decodeURIComponent(escape(self.atob(elem.firstChild.nodeValue)));
			elem.style.height = elem.contentDocument.querySelector('body').scrollHeight + 'px';
			self.setTimeout(function () {
				var elem = document.querySelector('div.content iframe');
				elem.style.height = elem.contentDocument.querySelector('body').scrollHeight + 'px';
			}, 1000);
		}
		catch (e) {
			elem.contentDocument.querySelector('body').textContent = e;
		}
	};

	this.resetLifetime = function (elem, color) {

		elem = elem.parentNode;
		elem.querySelectorAll('select')[0].selectedIndex = 0;
		elem.querySelectorAll('select')[1].selectedIndex = 0;
		elem.querySelectorAll('input')[0].value = color.toLowerCase();
		elem.querySelectorAll('input')[1].value = '#000000';
	};

	this.removeLifetime = function (elem) {

		if (confirm(Translator.translate('Are you sure?')))
			elem.parentNode.parentNode.removeChild(elem.parentNode);
	};

	this.addPicture = function (elem) {

		var tpl = document.createElement('tr'), root = elem.parentNode;
		while (root.nodeName !== 'TABLE')
			root = root.parentNode;

		root = root.querySelector('tbody');

		tpl.setAttribute('class', 'added');
		tpl.innerHTML = this.template.
			replace(/IIDDXX/g, this.nextIdx).
			replace(/KKEEYY/g, '1');

		root.appendChild(tpl);
		this.nextIdx += 1;
	};

	this.deletePicture = function (elem) {

		if (confirm(Translator.translate('Are you sure?')))
			elem.parentNode.parentNode.parentNode.removeChild(elem.parentNode.parentNode);
	};

	this.addBreak = function (elem, idx) {

		var tpl = document.createElement('ul'), ul = elem.parentNode.parentNode.parentNode.querySelector('ul');

		tpl.innerHTML = this.template.
			replace(/IIDDXX/g, idx).
			replace(/KKEEYY/g, ul.querySelectorAll('li').length);

		ul.appendChild(tpl.querySelector('li.breakpoint'));
	};

	this.deleteBreak = function (elem) {

		if (confirm(Translator.translate('Are you sure?')))
			elem.parentNode.parentNode.parentNode.removeChild(elem.parentNode.parentNode);
	};

})();

if (typeof self.addEventListener == 'function')
	self.addEventListener('load', maillog.start.bind(maillog));