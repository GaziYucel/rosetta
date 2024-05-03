<?php

namespace TIBHannover\Rosetta\Mods;

use Author;
use Context;
use DOMDocument;
use DOMElement;
use Publication;

define('MODS_NS', 'http://www.loc.gov/mods/v3');

class ModsDOM extends DOMDocument
{
		public Context $context;

		public string $locale;

		public DOMElement $record;

		public array $supportedFormLocales;

		private Publication $publication;

		public function __construct(Context $context, Publication $publication)
	{
		parent::__construct('1.0', 'UTF-8');
		$this->context = $context;
		$this->publication = $publication;
		$this->supportedFormLocales = $context->getSupportedFormLocales();
		$this->createPublication();
	}

		private function createPublication(): void
	{
		$this->createRootElement();

		// titleInfo
		$this->createTitleInfo();

		// abstract
		$this->createAbstract();

		// authors
		$this->createName();

		//subjects
		$this->createDataElement('disciplines', $this->publication, $this->record,
			'subject', array('authority' => 'disciplines'));
		$this->createDataElement('keywords', $this->publication, $this->record,
			'subject', array('authority' => 'keywords'));
		$this->createDataElement('languages', $this->publication, $this->record,
			'subject', array('authority' => 'languages'));
		$this->createDataElement('subjects', $this->publication, $this->record,
			'subject', array('authority' => 'subjects'));
		$this->createDataElement('supportingAgencies', $this->publication, $this->record,
			'subject', array('authority' => 'supportingAgencies'));

		// coverage
		$this->createDataElement('coverage', $this->publication,
			$this->record, 'location', array('displayLabel' => 'coverage'));

		// rights
		$this->createDataElement('rights', $this->publication,
			$this->record, 'accessCondition', array('displayLabel' => 'rights'));

		// Source
		$recordInfo = $this->createElementNS(MODS_NS, 'mods:recordInfo');
		$this->createDataElement('source', $this->publication, $recordInfo, 'recordContentSource');

		// doi
		$this->createDataElement('pub-id::doi', $this->publication,
			$this->record, 'identifier', array('type' => 'doi'));

		$languageOfCataloging = $this->createElementNS(MODS_NS, 'mods:languageOfCataloging');
		#$languageTerm = $this->createDataElement('locale',$this->publication,$languageOfCataloging,'languageTerm');
		#$recordInfo->appendChild($languageTerm);
		$this->record->appendChild($recordInfo);

		// publisher
		$originInfo = $this->createElementNS(MODS_NS, 'mods:originInfo');
		$this->createDataElement('copyrightHolder', $this->publication, $originInfo, 'publisher');
		$this->record->appendChild($originInfo);
		$this->createDataElement('type', $this->publication, $this->record, 'genre');

		// Add  Context Info

		$this->createContext($this->context);
	}

		private function createRootElement(): void
	{
		$this->record = $this->createElementNS(MODS_NS, 'mods:mods');
		$this->record->setAttributeNS('http://www.w3.org/2000/xmlns/',
			'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$this->record->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance',
			'schemaLocation', 'http://www.loc.gov/standards/mods/v3/mods-3-6.xsd');
		$this->record->setAttribute('version', '3.6');
		$this->appendChild($this->record);
	}

		private function createTitleInfo(): void
	{
		$titleInfo = $this->createElementNS(MODS_NS, 'titleInfo');
		$titles = $this->publication->getData('title');
		if ($titles) {
			foreach ($titles as $lang => $title) {
				$prefix = $this->publication->getData('prefix');
				$titleDom = $this->createModsElement($title, 'title', $lang);
				$titleInfo->appendChild($titleDom);
				$this->record->appendChild($titleInfo);
			}
		}
		$subTitles = $this->publication->getData('subtitle');
		if ($subTitles) {
			foreach ($subTitles as $lang => $subTitle) {
				$subTitle = $this->createModsElement($subTitle, 'subTitle', $lang);
				$titleInfo->appendChild($subTitle);
				$this->record->appendChild($titleInfo);
			}
		}
	}

		private function createModsElement(string $value, string $qualifiedName, string $locale = ''): DOMElement
	{
		$node = $this->createElementNS(MODS_NS, $qualifiedName);

		if (!empty($value)) {
			$node->nodeValue = htmlspecialchars($value, ENT_XHTML, 'UTF-8');
		}

		if (strlen($locale) > 0) {
			$langAttr = $this->createAttribute('xml:lang');
			if (preg_match('/^([a-z]{2})_/i', $locale, $matches)) {
				$locale = $matches[1];
			}
			$langAttr->value = $locale;
			//$node->appendChild($langAttr);
		}

		return $node;
	}

		private function createAbstract(): void
	{
		$abstracts = $this->publication->getData('abstract');
		if ($abstracts) {
			foreach ($abstracts as $lang => $abstract) {
				$abstractDom = $this->createModsElement($abstract, 'abstract', $lang);
				$this->record->appendChild($abstractDom);
			}
		}
	}

		private function createName(): void
	{
		$authors = $this->publication->getData('authors');
		foreach ($authors as $author) {
			$nameDom = $this->createElementNS(MODS_NS, 'mods:name');
			foreach ($this->supportedFormLocales as $locale) {


				// namePart
				$authorGivenNameEmpty = !array_filter(array_values($author->getData('givenName')));
				$authorType = ($authorGivenNameEmpty) ? 'corporate' : 'personal';
				$nameDom->setAttribute('type', $authorType);
				if (array_key_exists($locale, $author->getData('familyName'))) {
					$familyNamePartDom = $this->createElementNS(MODS_NS, 'namePart',
						$author->getData('familyName')[$locale]);
					if (preg_match('/^([a-z]{2})_/i', $locale, $matches)) {
						$shortLocale =  $matches[1];
					}
					$familyNamePartDom->setAttribute('xml:lang', $shortLocale);
					$familyNamePartDom->setAttribute('type', 'family');
					$nameDom->appendChild($familyNamePartDom);
				}
				if (array_key_exists($locale, $author->getData('givenName'))) {

					$givenNamePartDom = $this->createElementNS(MODS_NS, 'namePart', $author->getData('givenName')[$locale]);
					if (preg_match('/^([a-z]{2})_/i', $locale, $matches)) {
						$locale = $matches[1];
					}

					$givenNamePartDom->setAttribute('xml:lang', $locale);
					$givenNamePartDom->setAttribute('type', 'given');
					$nameDom->appendChild($givenNamePartDom);
				}
				// Create user properties
				$properties = array(
					'affiliation' => 'affiliation',
					'biography' => 'description',
					'preferredPublicName' => 'displayForm'
				);
				foreach ($properties as $key => $value) {
					$this->createDataElement($key, $author, $nameDom, $value);
				}
				$this->createNameRoles($author, $locale, $nameDom);

			}
			$this->createNameOrcid($author, $nameDom);
			$this->createNameURL($author, $nameDom);
			$this->createNameCountry($author, $nameDom);

			$this->record->appendChild($nameDom);

		}
	}

		public function createDataElement(string $orig, mixed $dataProvider, DOMElement $parent,
									  string $new = '', array $attrs = []): void
	{
		$newElement = null;
		$data = $dataProvider->getData($orig);
		if (gettype($data) == 'array') {
			foreach ($data as $locale => $entry) {
				$elemName = (strlen($new) > 0) ? $new : $orig;
				if (gettype($entry) == 'string') {
					$newElement = $this->createElementNS(MODS_NS, $elemName,
						htmlspecialchars($entry, ENT_XHTML, 'UTF-8'));
					$this->setAllAttributes($attrs, $newElement, $parent);
				}
				if (gettype($entry) == 'array') {
					foreach ($entry as $part) {
						$newElement = $this->createElementNS(MODS_NS, $elemName,
							htmlspecialchars($part, ENT_XHTML, 'UTF-8'));
						$this->setAllAttributes($attrs, $newElement, $parent);
					}
				}
			}
		} elseif ($data != null && gettype('data') == 'string') {
			$newElement = $this->createModsElement($data, $new);
			$this->setAllAttributes($attrs, $newElement, $parent);
		}
	}

		private function setAllAttributes(array $attrs, DOMElement $newElement, DOMElement $parent): void
	{
		foreach ($attrs as $key => $attr) {
			$newElement->setAttribute($key, $attr);
		}
		$parent->appendChild($newElement);
	}

		private function createNameRoles(Author $author, string $locale, DOMElement|false $nameDom): void
	{
		$userGroup = $author->getUserGroup();
		if ($userGroup) {
			if (preg_match('/^([a-z]{2})_/i', $locale, $matches)) {
				$locale= $matches[1];
			}
			$role = $this->createElementNS(MODS_NS, 'mods:role');
			$roleTerm = $this->createElementNS(MODS_NS, 'mods:roleTerm', $userGroup->getName($locale));
			$roleTerm->setAttribute('xml:lang', $locale);
			$roleTerm->setAttribute('type', 'text');
			$role->appendChild($roleTerm);
			$roleTerm = $this->createElementNS(MODS_NS, 'mods:roleTerm', $userGroup->getAbbrev($locale));
			$roleTerm->setAttribute('xml:lang', $locale);
			$roleTerm->setAttribute('type', 'code');
			$role->appendChild($roleTerm);
			$nameDom->appendChild($role);
		}
	}

		private function createNameOrcid(Author $author, DOMElement|false $nameDom): void
	{
		$orcidValue = $author->getData('orcid');
		if (strlen($orcidValue) > 0) {
			$orcid = $this->createElementNS(MODS_NS, 'mods:affiliation', $orcidValue);
			$orcid->setAttribute('script', 'orcid');
			$nameDom->appendChild($orcid);
		}
	}

		private function createNameURL(Author $author, DOMElement|false $nameDom): void
	{
		$orcidValue = $author->getData('url');
		if (strlen($orcidValue) > 0) {
			$orcid = $this->createElementNS(MODS_NS, 'mods:affiliation', $orcidValue);
			$orcid->setAttribute('script', 'url');
			$nameDom->appendChild($orcid);
		}
	}

		private function createNameCountry(Author $author, DOMElement|false $nameDom): void
	{
		$orcidValue = $author->getData('country');
		if (strlen($orcidValue) > 0) {
			$orcid = $this->createElementNS(MODS_NS, 'mods:affiliation', $orcidValue);
			$orcid->setAttribute('script', 'country');
			$nameDom->appendChild($orcid);
		}
	}

		private function createContext(Context $context): void
	{
		$relatedItem = $this->createElementNS(MODS_NS, 'relatedItem');
		$relatedItem->setAttribute('type', 'host');
		$relatedItem->setAttribute('displayLabel', $context->getData('acronym', 'en_US'));
		$this->record->appendChild($relatedItem);
		$extension = $this->createElement('extension');
		$relatedItem->appendChild($extension);
		$elementNames = array('abbreviation', 'acronym', 'authorInformation', 'clocksLicense', 'customHeaders', 'librarianInformation', 'lockssLicense', 'openAccessPolicy', 'privacyStatement', 'readerInformation', 'searchDescription', 'supportedLocales', 'supportedSubmissionLocales');

		foreach ($elementNames as $elementName) {
			$data = $context->getData($elementName);
			if ($data) {
				foreach ($data as $locale => $value) {
					$elem = $this->createElement($elementName, $value);
					$extension->appendChild($elem);
				}
			}
		}
		//TODO
		#$issueDao = DAORegistry::getDAO('IssueDAO'); /** @var $issueDao IssueDAO */
		#$issue = $issueDao->getById($this->publication->getData('issueId'), $this->context);

	}

		public function getRecord(): DOMElement
	{
		return $this->record;
	}

		public function getPublication(): Publication
	{
		return $this->publication;
	}

		public function setPublication(Publication $publication): void
	{
		$this->publication = $publication;
	}
}
