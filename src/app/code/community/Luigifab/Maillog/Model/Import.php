<?php
/**
 * Created S/04/04/2015
 * Updated D/17/12/2023
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

class Luigifab_Maillog_Model_Import extends Luigifab_Maillog_Model_Observer {

	// CRON maillog_bounces_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message de la tâche cron et dans le fichier status.dat
	public function bouncesFile($cron = null, $source = null) {

		Mage::register('maillog_no_sync', true, true);
		$diff = ['started_at' => date('Y-m-d H:i:s'), 'errors' => []];

		try {
			$folder = Mage::getStoreConfig('maillog_sync/bounces/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog_sync/bounces/format');
			$source = $this->searchTodayFile($folder, $type = mb_substr($config, 0, 3));

			$newItems = $this->extractDataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('customer/customer_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['email']); // optimisation maximale
			$oldItems->addAttributeToFilter('is_bounce', 2); // 1/No 2/Yes 3/Yes-forced 4/No-forced

			$this->updateCustomersDatabase($newItems, $oldItems->getColumnValues('email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Throwable $t) {

			$error = empty($diff['errors']) ? $t->getMessage() : implode("\n", $diff['errors']);
			$this->writeLog($folder, empty($source) ? 'none' : $source, $error, $cron);

			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}

		return $diff;
	}

	// CRON maillog_unsubscribers_import
	// récupère la configuration et cherche le fichier le plus récent
	// extrait les données du fichier et les données de la base de données de manière à traiter uniquement les différences
	// déplace et compresse le fichier traité et les autres puis génère le log dans le message de la tâche cron et dans le fichier status.dat
	public function unsubscribersFile($cron = null, $source = null) {

		Mage::register('maillog_no_sync', true, true);
		$diff = ['started_at' => date('Y-m-d H:i:s'), 'errors' => []];

		try {
			$folder = Mage::getStoreConfig('maillog_sync/unsubscribers/directory');
			$folder = str_replace('//', '/', Mage::getBaseDir('var').'/'.trim($folder, "/ \t\n\r\0\x0B").'/');
			$config = Mage::getStoreConfig('maillog_sync/unsubscribers/format');
			$source = $this->searchTodayFile($folder, $type = mb_substr($config, 0, 3));

			$newItems = $this->extractDataFromFile($folder, $source, $config);

			$oldItems = Mage::getResourceModel('newsletter/subscriber_collection');
			$oldItems->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_email']); // optimisation maximale
			$oldItems->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);

			$this->updateUnsubscribersDatabase($newItems, $oldItems->getColumnValues('subscriber_email'), $diff);
			$this->moveFiles($folder, $source, $type);
			$this->writeLog($folder, $source, $diff, $cron);

			Mage::unregister('maillog_no_sync');
		}
		catch (Throwable $t) {

			$error = empty($diff['errors']) ? $t->getMessage() : implode("\n", $diff['errors']);
			$this->writeLog($folder, empty($source) ? 'none' : $source, $error, $cron);

			Mage::unregister('maillog_no_sync');
			Mage::throwException($error);
		}

		return $diff;
	}

	// 7 exceptions - c'est ici que tout se joue car si tout va bien nous avons un fichier et un dossier qui sont accessibles et modifiables
	// dossier de base : inexistant, non accessible en lecture, non accessible en écriture, vide
	// fichier : non accessible en lecture, non accessible en écriture, trop vieux
	protected function searchTodayFile(string $folder, string $type) {

		// vérifications du dossier
		if (!is_dir($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" does not exist.');
		if (!is_readable($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" is not readable.');
		if (!is_writable($folder))
			Mage::throwException('Sorry, the directory "'.$folder.'" is not writable.');

		// recherche des fichiers
		// utilise un tableau pour pouvoir trier par date
		$allfiles = glob($folder.'*.'.$type);
		$files = [];

		foreach ($allfiles as $file)
			$files[(int) filemtime($file)] = basename($file); // (yes)

		if (empty($files))
			Mage::throwException('Sorry, there is no file in directory "'.$folder.'".');

		// du plus grand au plus petit, donc du plus récent au plus ancien
		// de manière à avoir le fichier le plus récent en premier car on souhaite traiter le fichier du jour uniquement
		krsort($files);
		$time = key($files);
		$file = current($files);

		// vérifications du fichier
		// pour la date, seul compte le jour
		if (!is_readable($folder.$file))
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is not readable.');
		if (!is_writable($folder.$file))
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is not writable.');
		if ($time < Mage::getSingleton('core/locale')->date()->setHour(0)->setMinute(0)->getTimestamp())
			Mage::throwException('Sorry, the file "'.$folder.$file.'" is too old for today.');

		return $file;
	}

	// 1 exception - le fichier n'est pas en utf-8
	// lecture du fichier à importer en supprimant l'éventuel marqueur BOM
	// mise à jour de la base de données (ne touche pas à ce qui ne change pas - ajoute/supprime/modifie)
	// déplace et compresse les fichiers (base/done/skip)
	// enregistre le log final
	protected function extractDataFromFile(string $folder, string $source, string $config) {

		$type = mb_substr($config, 0, 3);

		// type=txt
		// type=tsv2"  pour type=tsv delim=→ colum=2 separ="
		// type=csv;2" pour type=csv delim=; colum=2 separ="
		if ($type == 'csv') {
			$delim = mb_substr($config, 3, 1);
			$colum = mb_substr($config, 4, 1);
			$separ = mb_substr($config, 5, 1);
		}
		else if ($type == 'tsv') {
			$delim = '→';
			$colum = mb_substr($config, 3, 1);
			$separ = mb_substr($config, 4, 1);
		}

		$items = [];
		$colum = ($colum > 1) ? $colum - 1 : 1;
		$lines = trim(str_replace("\xEF\xBB\xBF", '', file_get_contents($folder.$source)));

		if (mb_detect_encoding($lines, 'utf-8', true) === false)
			Mage::throwException('Sorry, the file "'.$folder.$source.'" is not an utf-8 file.');

		$lines = array_map('trim', explode("\n", $lines));
		foreach ($lines as $line) {
			if (mb_strlen($line) > 5) {
				if ($type == 'csv') {
					$delim = ($delim == '→') ? "\t" : $delim;
					$data  = array_map('trim', explode($delim, $line));
					if (!empty($data[$colum]) && (mb_stripos($data[$colum], '@') !== false))
						$items[] = str_replace($separ, '', $data[$colum]);
				}
				else if (($type == 'txt') && (mb_stripos($line, '@') !== false)) {
					$items[] = $line;
				}
			}
		}

		return $items;
	}

	protected function updateCustomersDatabase(array $newItems, array $oldItems, array &$diff) {

		$diff['oldItems']    = count($oldItems);
		$diff['newItems']    = count($newItems);
		$diff['invalidated'] = [];
		$diff['validated']   = [];

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse email devenue invalide
		// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
		$allEmails     = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('customer/customer_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['entity_id', 'entity_type_id', 'email']); // optimisation maximale
			$items->addAttributeToSelect('is_bounce');
			// non, car cela génère un INNER JOIN, et donc cela merde si l'attribut n'a pas de ligne dans customer_entity_int
			//$items->addAttributeToFilter('is_bounce', ['nin' => [1, 2, 3, 4]]); // donc 0/No ou null
			$items->addAttributeToFilter('email', ['in' => $emails]);

			foreach ($emails as $email) {
				try {
					$customer = $items->getItemByColumnValue('email', $email);

					if (!empty($customer) && empty($customer->getData('is_bounce'))) { // n'est PAS forcément 0/No ou null

						$customer->setData('is_bounce', 1); // 1 pour Yes
						$customer->getResource()->saveAttribute($customer, 'is_bounce');

						$diff['invalidated'][] = $email;
					}
				}
				catch (Throwable $t) {
					$diff['errors'][] = $email.' - '.$t->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog_sync/bounces/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse email devenue valide
			// 0/No 1/Yes 2/Yes-forced-admin 3/No-forced-admin 4/No-forced-customer
			$allEmails     = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('customer/customer_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['entity_id', 'entity_type_id', 'email']); // opt. maximale
				$items->addAttributeToSelect('is_bounce');
				$items->addAttributeToFilter('is_bounce', ['nin' => [0, 2, 3, 4]]); // donc 1/Yes ou null
				$items->addAttributeToFilter('email', ['in' => $emails]);

				foreach ($emails as $email) {
					try {
						$customer = $items->getItemByColumnValue('email', $email);

						if (!empty($customer)) { // est forcément 1/Yes ou null

							$customer->setData('is_bounce', 0); // 0 pour No
							$customer->getResource()->saveAttribute($customer, 'is_bounce');

							$diff['validated'][] = $email;
						}
					}
					catch (Throwable $t) {
						$diff['errors'][] = $email.' - '.$t->getMessage();
					}
				}
			}
		}
	}

	protected function updateUnsubscribersDatabase(array $newItems, array $oldItems, array &$diff) {

		$diff['oldItems']     = count($oldItems);
		$diff['newItems']     = count($newItems);
		$diff['unsubscribed'] = [];
		$diff['subscribed']   = [];

		// traitement des adresses emails AJOUTÉES
		// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
		// une adresse email AJOUTÉE = une adresse désinscrite de la newsletter (STATUS_UNSUBSCRIBED)
		$allEmails = array_diff($newItems, $oldItems);
		$chunkedEmails = array_chunk($allEmails, 1000);

		foreach ($chunkedEmails as $emails) {

			$items = Mage::getResourceModel('newsletter/subscriber_collection');
			$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_id', 'subscriber_email']); // optimisation maximale
			// oui, car pour être inscrit, il faut forcément une ligne dans newsletter_subscriber
			$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
			$items->addFieldToFilter('subscriber_email', ['in' => $emails]);

			foreach ($emails as $email) {
				try {
					$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

					if (!empty($subscriber)) { // est forcément STATUS_SUBSCRIBED

						$subscriber->setData('change_status_at', date('Y-m-d H:i:s'));
						$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
						$subscriber->getResource()->save($subscriber);

						$diff['unsubscribed'][] = $email;
					}
				}
				catch (Throwable $t) {
					$diff['errors'][] = $email.' - '.$t->getMessage();
				}
			}
		}

		if (Mage::getStoreConfigFlag('maillog_sync/unsubscribers/subscribe')) {

			// traitement des adresses emails SUPPRIMÉES
			// array_diff retourne un tableau contenant toutes les entités du premier tableau qui ne sont présentes dans aucun autres tableaux
			// une adresse email SUPPRIMÉE = une adresse inscrite à la newsletter (STATUS_SUBSCRIBED)
			$allEmails = array_diff($oldItems, $newItems);
			$chunkedEmails = array_chunk($allEmails, 1000);

			foreach ($chunkedEmails as $emails) {

				$items = Mage::getResourceModel('newsletter/subscriber_collection');
				$items->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns(['subscriber_id', 'subscriber_email']);  // opt. maximale
				$items->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
				$items->addFieldToFilter('subscriber_email', ['in' => $emails]);

				foreach ($emails as $email) {
					try {
						$subscriber = $items->getItemByColumnValue('subscriber_email', $email);

						if (!empty($subscriber)) { // est forcément STATUS_UNSUBSCRIBED

							$subscriber->setData('change_status_at', date('Y-m-d H:i:s'));
							$subscriber->setData('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
							$subscriber->getResource()->save($subscriber);

							$diff['subscribed'][] = $email;
						}
					}
					catch (Throwable $t) {
						$diff['errors'][] = $email.' - '.$t->getMessage();
					}
				}
			}
		}
	}

	protected function moveFiles(string $folder, string &$source, string $type) {

		$uniq = 1;
		$date = Mage::getSingleton('core/locale')->date();
		$donedir = $folder.'done/'.$date->toString('YMM').'/';
		$skipdir = $folder.'skip/'.$date->toString('YMM').'/';

		if (!is_dir($donedir))
			@mkdir($donedir, 0755, true);
		if (!is_dir($skipdir))
			@mkdir($skipdir, 0755, true);

		// déplace et compresse le fichier traité
		$name = $date->toString('YMMdd-HHmmss').'.'.$type;
		rename($folder.$source, $donedir.$name.'.gz');
		file_put_contents($donedir.$name.'.gz', gzencode(file_get_contents($donedir.$name.'.gz'), 9));
		$source = $donedir.$name.'.gz';

		// déplace et compresse les fichiers ignorés
		// reste silencieux en cas d'erreur (car si le fichier n'est pas déplaçable, le fichier ne sera jamais traité)
		$files = glob($folder.'*.'.$type);
		foreach ($files as $file) {

			$file = basename($file);
			$name = $date->setTimestamp(filemtime($folder.$file))->toString('YMMdd-HHmmss').'-'.str_pad($uniq++, 3, '0', STR_PAD_LEFT).'.'.$type;

			@rename($folder.$file, $skipdir.$name.'.gz');
			if (is_file($skipdir.$name.'.gz') && is_writable($skipdir.$name.'.gz'))
				file_put_contents($skipdir.$name.'.gz', gzencode(file_get_contents($skipdir.$name.'.gz'), 9));
		}

		// supprime le dossier des fichiers ignorés si celui-ci est VIDE
		@rmdir($skipdir);
	}

	protected function writeLog(string $folder, string $source, $diff, $cron = null) {

		$diff = is_string($diff) ? ['started_at' => date('Y-m-d H:i:s'), 'exception' => $diff] : $diff;

		// pour le message du cron
		if (is_object($cron)) {
			$text = str_replace(['    ', ' => Array', "\n\n"], [' ', '', "\n"], preg_replace('#\s+[()]#', '', print_r($diff, true)));
			$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".$text);
			$diff['cron'] = $cron->getId();
		}

		// pour le status.dat
		if ($source != 'none') {
			$diff['size'] = mb_strlen(gzdecode(file_get_contents($source)));
			$diff['file'] = basename($source);
		}

		// pour le status.dat
		// n'affiche pas les adresses, uniquement les nombres d'adresses
		if (isset($diff['invalidated'])) {
			$diff['invalidated']  = empty($diff['invalidated'])  ? 0 : count($diff['invalidated']);
			$diff['validated']    = empty($diff['validated'])    ? 0 : count($diff['validated']);
		}

		if (isset($diff['unsubscribed'])) {
			$diff['unsubscribed'] = empty($diff['unsubscribed']) ? 0 : count($diff['unsubscribed']);
			$diff['subscribed']   = empty($diff['subscribed'])   ? 0 : count($diff['subscribed']);
		}

		$diff['errors']      = empty($diff['errors']) ? 0 : count($diff['errors']);
		$diff['finished_at'] = date('Y-m-d H:i:s');

		if (!is_dir($folder))
			@mkdir($folder, 0755, true);

		file_put_contents($folder.'status.dat', json_encode($diff));
	}
}