<?php


require_mock_env('env2');

import('plugins.importexport.rosetta.tests.functional.TestJournal');
import('plugins.importexport.rosetta.RosettaExportPlugin');
import('plugins.importexport.rosetta.RosettaExportDeployment');
import('lib.pkp.tests.plugins.PluginTestCase');

require_mock_env('env2');

import('lib.pkp.tests.PKPTestCase');

import('lib.pkp.classes.oai.OAIStruct');
import('lib.pkp.classes.oai.OAIUtils');
import('plugins.oaiMetadataFormats.dc.OAIMetadataFormat_DC');
import('plugins.oaiMetadataFormats.dc.OAIMetadataFormatPlugin_DC');
import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.services.PKPSchemaService'); // Constants


class FunctionalRosettaExportTest extends PluginTestCase
{
	protected TestJournal $testJournal;


	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);

		$this->testJournal = new TestJournal($this);


	}

	/**
	 * @covers OAIMetadataFormat_DC
	 * @covers Dc11SchemaArticleAdapter
	 */
	public function testSipContent()
	{

		$request = Application::get()->getRequest();
		if (is_null($request->getRouter())) {
			$router = new PKPRouter();
			$request->setRouter($router);
		}
		$router = $this->getRouter();
		$this->getRequest($router);

		$this->getDublinCore();
		$this->getMets();

	}

	public function getRouter()
	{
		import('lib.pkp.classes.core.PKPRouter');
		$router = $this->getMockBuilder(PKPRouter::class)
			->setMethods(array('url'))
			->getMock();
		$application = Application::get();
		$router->setApplication($application);
		$router->expects($this->any())
			->method('url')
			->will($this->returnCallback(array($this, 'getRouterUrl')));
		return $router;
	}

	public function getRequest($router)
	{
		import('classes.core.Request');
		$request = $this->getMockBuilder(Request::class)
			->setMethods(array('getRouter'))
			->getMock();
		$request->expects($this->any())
			->method('getRouter')
			->will($this->returnValue($router));
		Registry::set('request', $request);
		return $request;
	}

	public function getDublinCore(): void
	{
		$dcDom = new RosettaDCDom($this->getTestJournal()->getContext(), $this->getTestJournal()->getSubmission()->getLatestPublication(), false);

		$this->removeCustomNodes($dcDom);


		$dcXml = join(DIRECTORY_SEPARATOR, array(getcwd(), $this->getPlugin()->getPluginPath(), 'tests', 'data', 'dc.xml'));

		$this->assertXmlStringEqualsXmlFile($dcXml, $dcDom->saveXML());
	}

	/**
	 * @return TestJournal
	 */
	public function getTestJournal(): TestJournal
	{
		return $this->testJournal;
	}

	/**
	 * @param TestJournal $TestJournal
	 */
	public function setTestJournal(TestJournal $TestJournal): void
	{
		$this->testJournal = $TestJournal;
	}

	public function removeCustomNodes($dcDom): void
	{
		$nodeModified = $dcDom->getElementsByTagName('dcterms:modified')->item(0);
		if ($nodeModified) $nodeModified->parentNode->removeChild($nodeModified);

		$nodePartOf = $dcDom->getElementsByTagName('dcterms:isPartOf')->item(0);
		if ($nodePartOf) $nodePartOf->parentNode->removeChild($nodePartOf);
	}

	public function getPlugin()
	{
		$importExportPlugins = PluginRegistry::loadCategory('importexport');
		return $importExportPlugins['RosettaExportPlugin'];
	}

	public function getMets(): void
	{

		$metsDom = new RosettaMETSDom($this->getTestJournal()->getContext(), $this->getTestJournal()->getSubmission(), $this->getTestJournal()->getSubmission()->getLatestPublication(), $this->getPlugin(), true);
		$metsDom->preserveWhiteSpace = false;
		$metsDom->formatOutput = true;
		$this->removeCustomNodes($metsDom);

		$expectedDom = new DOMDocument();
		$expectedDom->preserveWhiteSpace = false;
		$expectedDom->formatOutput = true;
		$expectedDom->loadXML(file_get_contents(join(DIRECTORY_SEPARATOR, array(getcwd(), $this->getPlugin()->getPluginPath(), 'tests', 'data', 'ie1.xml'))));

		$regExLineBreaks = '/\r\n|\r|\n|\t/';
		$saveXML = $metsDom->saveXML();
		$this->assertEqualsCanonicalizing(array_filter(preg_split($regExLineBreaks, $saveXML)), array_filter(preg_split($regExLineBreaks, $expectedDom->saveXML())));


	}

	public function getLatestPublication(): ?Publication
	{
		return $this->getSubmission()->getLatestPublication();
	}

	function getRouterUrl($request, $newContext = null, $handler = null, $op = null, $path = null)
	{
		return $handler . '-' . $op . '-' . implode('-', $path);
	}

	/**
	 * @see PKPTestCase::getMockedDAOs()
	 */
	protected function getMockedDAOs()
	{
		return array('AuthorDAO', 'OAIDAO', 'ArticleGalleyDAO');
	}

	/**
	 * @see PKPTestCase::getMockedRegistryKeys()
	 */
	protected function getMockedRegistryKeys()
	{
		return array('request');
	}


}
