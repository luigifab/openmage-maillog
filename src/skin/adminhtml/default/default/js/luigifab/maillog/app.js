/**
 * Created J/03/12/2015
 * Updated V/22/12/2023
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

if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}

if (!Element.prototype.closest) {
	if (!Element.prototype.matches)
	    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
	Element.prototype.closest = function (s) {
		var el = this;
		if (!document.documentElement.contains(el))
			return null;
		do {
			if (el.matches(s))
				return el;
			el = el.parentElement || el.parentNode;
		} while (el !== null && el.nodeType == 1);
		return null;
	};
}

var maillog = new (function () {

	"use strict";
	this.template = null;
	this.nextIdx  = 0;

	this.init = function () {

		var elem, customer, bounce, subscriber, i, j;

		if (document.getElementById('maillog_sync_bounces_stats_customer')) {

			console.info('maillog.app - hello');
			customer   = document.getElementById('maillog_sync_bounces_stats_customer');
			bounce     = document.getElementById('maillog_sync_bounces_stats_bounce');
			subscriber = document.getElementById('maillog_sync_unsubscribers_stats_subscriber');

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(bounce.textContent.trim(), 10);
			bounce.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(subscriber.textContent.trim(), 10);
			subscriber.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';
		}
		else if (elem = document.getElementById('row_maillog_directives_general_special_config')) {

			console.info('maillog.app - hello');
			elem = elem.querySelector('tr.template');

			this.template = elem.innerHTML;
			this.nextIdx  = parseInt(elem.getAttribute('data-next'), 10);

			elem.remove();
		}
	};

	this.add = function (code) {

		var sys = document.getElementById('maillog_sync_' + code + '_mapping_system'),
		    mag = document.getElementById('maillog_sync_' + code + '_mapping_openmage'),
		    cnf = document.getElementById('maillog_sync_' + code + '_mapping_config');

		if ((sys.value.length > 0) && (mag.value.length > 0)) {
			cnf.value = (cnf.value + '\n' + sys.value + ':' + mag.value).trim();
			cnf.scrollTop = cnf.scrollHeight;
			sys.selectedIndex = 0;
			mag.selectedIndex = 0;
			this.mark(code);
		}
	};

	this.mark = function (code) {

		var fields = document.getElementById('maillog_sync_' + code + '_mapping_config').value;

		document.getElementById('maillog_sync_' + code + '_mapping_system').querySelectorAll('option').forEach(function (elem) {
			if ((fields.indexOf('\n' + elem.value + ':') > -1) || (fields.indexOf(elem.value + ':') === 0))
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});

		document.getElementById('maillog_sync_' + code + '_mapping_openmage').querySelectorAll('option').forEach(function (elem) {
			if (fields.indexOf(':' + elem.value) > -1)
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});
	};

	this.decode = function (data) {

		// utf-8 avec Webkit (https://stackoverflow.com/q/3626183)
		return decodeURIComponent(escape(atob(data)));
	};

	this.iframe = function (elem) {

		try {
			elem.removeAttribute('onload');
			elem.contentDocument.querySelector('body').parentNode.innerHTML = this.decode(elem.firstChild.nodeValue);
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

	this.resetValues = function (elem) {

		elem = elem.closest('li, tr');
		elem.querySelectorAll('select, input').forEach(function (input) {

			if (input.nodeName === 'SELECT') {
				input.selectedIndex = input.querySelector('option[selected]').index;
			}
			else if (input.color) {
				input.value = input.defaultValue.toUpperCase();
				input.color.importColor();
			}
			else if (input.jscolor) {
				input.value = input.defaultValue.toUpperCase();
				input.jscolor.fromString(input.value);
			}
			else {
				input.value = input.defaultValue;
			}

			if (input.classList.contains('h') && (input = input.closest('li').querySelector('div.rt')))
				this.ratioPicture(input);

		}, this); // pour que ci-dessus this = this
	};

	this.defaultLifetime = function (elem, bg, tt) {

		elem = elem.parentNode;
		elem.querySelectorAll('select')[0].selectedIndex = 0;
		elem.querySelectorAll('select')[1].selectedIndex = 0;

		var input = elem.querySelectorAll('input')[0];
		input.value = bg.toUpperCase();
		if (input.color)
			input.color.importColor();
		else
			input.jscolor.fromString(input.value);

		input = elem.querySelectorAll('input')[1];
		input.value = tt.toUpperCase();
		if (input.color)
			input.color.importColor();
		else
			input.jscolor.fromString(input.value);
	};

	this.removeLifetime = function (elem) {

		if (confirm(Translator.translate('Are you sure?')))
			elem.parentNode.remove();
	};

	this.addPicture = function (elem) {

		var tpl = document.createElement('tr'), root = elem.parentNode, tr, td;
		while (root.nodeName !== 'TABLE')
			root = root.parentNode;

		root = root.querySelector('tbody');

		tpl.setAttribute('class', 'added');
		tpl.setAttribute('data-idx', this.nextIdx.toString());
		tpl.innerHTML = this.template.
			replace(/IIDDXX/g, this.nextIdx).
			replace(/KKEEYY/g, '1');

		if (!root.querySelector('tr.new')) {
			tr = document.createElement('tr');
			tr.setAttribute('class', 'letter new');
			td = document.createElement('td');
			td.setAttribute('colspan', '2');
			td.setAttribute('class', 'a-center');
			td.appendChild(document.createTextNode('-'));
			tr.appendChild(td);
			root.appendChild(tr);
		}

		root.appendChild(tpl);
		tpl.querySelector('input').focus();
		this.nextIdx += 1;

		return tpl;
	};

	this.ratioPicture = function (elem) {

		// @see https://stackoverflow.com/a/11832950/2980105
		var root = elem.closest('li'), w = parseInt(root.querySelector('input.w').value, 10), h = parseInt(root.querySelector('input.h').value, 10);
		root.querySelector('div.rt').innerHTML = (isNaN(w) || isNaN(h) || (w == 0) || (h == 0)) ? '' :
			(Math.round((w / h + (Number.EPSILON ? Number.EPSILON : 0)) * 100) / 100).toLocaleString();
	};

	this.copyPicture = function (elem, json) {

		var config = {}, name;

		elem.closest('tr').querySelectorAll('input').forEach(function (input) {
			name = input.name.replace('groups[general][fields][special_config][value][', '').replace(/]\[/g, ':').replace(']', '');
			name = name.substring(name.indexOf(':') + 1);
			config[name] = input.value;
		});

		config = JSON.stringify(config);
		if (json === true) {
			return config;
		}
		else if (typeof navigator.clipboard == 'object') {
			navigator.clipboard.writeText(config).then(function () {
				elem.closest('tr').classList.add('maillog-flash');
				self.setTimeout(function () {
					elem.closest('tr').classList.remove('maillog-flash');
				}, 1000);
			}, function () {
				self.prompt('copy', config);
			});
		}
		else {
			self.prompt('copy', config);
		}
	};

	this.pastePicture = function (elem, ev, jsonOnly) {

		var idx = 1, config, root, line;
		jsonOnly = jsonOnly === true;

		try {
			config = JSON.parse((ev.clipboardData || window.clipboardData).getData('text'));
			ev.preventDefault();
		}
		catch (e) {
			if (jsonOnly) {
				console.error(e);
				ev.preventDefault();
				elem.value = '';
			}
			return;
		}

		// soit c'est collé dans l'input du tfoot (on cherche donc le bon input.cde)
		elem.closest('table').querySelectorAll('input.cde').forEach(function (input) {
			if (input.value == config.c)
				root = input.closest('tr');
		});

		// soit c'est collé dans un des input.cde du tbody
		if (!root && elem.classList.contains('cde'))
			root = elem.closest('tr');

		// soit c'est un ajout, soit c'est un remplacement
		if (!root)
			root = this.addPicture(document.querySelector('button.add.picture'));
		else if (!confirm(Translator.translate('Are you sure?') + ' (paste/replace picture ' + config.c + ')'))
			return;

		root.querySelector('input.cde').value = config.c;
		root.querySelector('input.cmt').value = config.d;
		root.querySelector('li.default input.w').value = config['0:w'];
		root.querySelector('li.default input.h').value = config['0:h'];
		this.ratioPicture(root.querySelector('li.default'));

		root.querySelectorAll('li.breakpoint').forEach(function (elem) { elem.remove(); });
		while (config[idx + ':b']) {
			line = this.addBreak(root.querySelector('button.add.break'), false);
			line.querySelector('input.b').value = config[idx + ':b'];
			line.querySelector('input.w').value = config[idx + ':w'];
			line.querySelector('input.h').value = config[idx + ':h'];
			this.ratioPicture(line);
			idx++;
		}

		self.scrollTo(0, root.getBoundingClientRect().top + self.scrollY - 150);
		root.querySelector('input').focus();

		root.closest('tr').classList.add('maillog-flash');
		self.setTimeout(function () {
			root.closest('tr').classList.remove('maillog-flash');
		}, 1000);
	};

	this.removePicture = function (elem) {

		var empty = true;
		elem.closest('tr').querySelectorAll('input').forEach(function (input) { empty = empty && (input.value == ''); });

		if (empty || confirm(Translator.translate('Are you sure?') + ' (remove picture)'))
			elem.closest('tr').remove();
	};

	this.addBreak = function (elem, focus) {

		var tpl = document.createElement('ul'), ul = elem.closest('td').querySelector('ul');

		tpl.innerHTML = this.template.
			replace(/IIDDXX/g, elem.closest('tr').getAttribute('data-idx')).
			replace(/KKEEYY/g, ul.querySelectorAll('li').length.toString());

		tpl = tpl.querySelector('li.breakpoint');
		ul.appendChild(tpl);

		if (focus !== false)
			tpl.querySelector('input').focus();

		return tpl;
	};

	this.removeBreak = function (elem) {

		var empty = true;
		elem.closest('li').querySelectorAll('input').forEach(function (input) { empty = empty && (input.value == ''); });

		if (empty || confirm(Translator.translate('Are you sure?') + ' (remove breakpoint)'))
			elem.closest('li').remove();
	};

})();

if (typeof self.addEventListener == 'function')
	self.addEventListener('load', maillog.init.bind(maillog));