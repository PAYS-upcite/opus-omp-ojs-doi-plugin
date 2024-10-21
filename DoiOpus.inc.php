<?php
/**
 * @file plugins/generic/doiOpus/DoiOpus.inc.php
 *
 * Copyright (c) 2022-2023 Bourrand Erwan
 * Distributed under the GNU GPL v3.
 *
 * @class DoiOpus
 * @ingroup plugins_generic_doiOpus
 *
 * @brief apply a format of DOI used in OPUS and Emerging Neurologist.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use APP\facades\Repo;
use PKP\plugins\Hook;
use PKP\context\Context;

class DoiOpus extends GenericPlugin {

	/**
	 * @copydoc Plugin::register
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		Hook::add('Form::config::before', [$this, 'addSettings']);
		Hook::add('Doi::add', [$this, 'addDoi']);
		return true;
	}
	
    public function addSettings($hookName, $form)
    {
		$request = Application::get()->getRequest();
		$context = $request->getContext();

		$descriptionKey = '';

		if ($context instanceof Journal) {
			$descriptionKey = 'plugin.doiOpus.doi.manager.settings.doiSuffixPattern.ojs';
		} elseif ($context instanceof Press) {
			$descriptionKey = 'plugin.doiOpus.doi.manager.settings.doiSuffixPattern.omp';
		}
		
		foreach ($form->groups as &$group) {
			if ($group["id"] == 'doiCustomSuffixGroup') {
				$group = [
					'id' => "doiCustomSuffixGroup",
					'label' => __('doi.manager.settings.doiSuffix.custom'),
					'description' => __($descriptionKey),
					'showWhen' => [Context::SETTING_DOI_SUFFIX_TYPE, Repo::doi()::SUFFIX_CUSTOM_PATTERN],
				];
			}
		}
		unset($group); 
		return;
    }

	function crc32_hash($str, $num) {
		return $hashcode = substr(hash("crc32c", $str), 0, $num);
	}

	public function addDoi($hookName, $dois)
    {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		
		if ($context instanceof Journal) {
			$doi = $dois[0];
			$old_doi = $doi->_data["doi"];		
			$doi->_data["doi"] = $this->processDoiPattern($old_doi);
			Repo::doi()->dao->update($doi);
			return;
		}

		if ($context instanceof Press){
			$doi = $dois[0];
			$old_doi = $doi->_data["doi"];
			$currentUrl = $request->getRequestUrl();
			$ids = $request->getUserVar('ids');
			$submissionId = null;
			if (preg_match('/submissions\/(\d+)\//', $currentUrl, $matches))
				$submissionId = $matches[1];
			elseif (is_array($ids)) {
				if(count($ids) === 1)
					$submissionId = $ids[0];
				elseif(count($ids)>1){
					error_log("(!) ERROR OPUS DOI Plugin : Custom identifier %x can't be retrieved for bulk action with multiple submissions. (!)");			
					$old_doi = str_replace("%x", '(multiple_selection_bulk_action_error)', $old_doi);
				}
			}
			if ($submissionId) {
				$submission = Repo::submission()->get($submissionId);
				if ($submission) {
					$publication = $submission->getCurrentPublication();
					if ($publication) {
						$publisherId = $publication->getData('pub-id::publisher-id');
						$old_doi = str_replace("%x", $publisherId, $old_doi);
					}
				}
			}	
			$doi->_data["doi"] = $this->processDoiPattern($old_doi);
			Repo::doi()->dao->update($doi);
			return;
		}
    }

	private function processDoiPattern($doiPattern)
	{
		// Remplacer les placeholders %C(param1,param2)
		$new_doi = preg_replace_callback(
			'/%C\(([^,]+),\s*([^)]+)\)/',
			function ($matches) {
				$param1 = trim($matches[1]);
				$param2 = (int)trim($matches[2]);
				return $this->crc32_hash($param1, $param2);
			},
			$doiPattern
		);
		return $new_doi;
	}

	/**
	 * Install default settings on journal creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName
	 */
	public function getDisplayName() {
		return __('plugins.generic.doiOpus.name');
	}

	/**
	 * @copydoc PKPPlugin::getDescription
	 */
	public function getDescription() {
		return __('plugins.generic.doiOpus.description');
	}
}
