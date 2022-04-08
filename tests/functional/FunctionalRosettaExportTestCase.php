<?php


require_mock_env('env2');

import('plugins.importexport.rosetta.tests.data.JournalTest');
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
	private JournalTest $journalTest;

	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
		$this->journalTest = new JournalTest($this);
	}


	/**
	 * @covers OAIMetadataFormat_DC
	 * @covers Dc11SchemaArticleAdapter
	 */
	public function testToXml()
	{

		$request = Application::get()->getRequest();
		if (is_null($request->getRouter())) {
			$router = new PKPRouter();
			$request->setRouter($router);
		}
		//
		// Create test data.
		//
		$journalId = 10000;


		$primaryLocale = 'en_US';
		$context =$this->journalTest->createContext($primaryLocale, $journalId);
		$issue = $this->journalTest->createIssue($context);
		$section = $this->journalTest->createSection($context);
		$this->journalTest->createOAI($context, $section, $issue);

		$submission = $this->journalTest->createSubmission($context, $section);
		$this->journalTest->createAuthors($submission);
		$this->journalTest->createGalleys($submission);
		// Article


		// Router
		import('lib.pkp.classes.core.PKPRouter');
		$router = $this->getMockBuilder(PKPRouter::class)
			->setMethods(array('url'))
			->getMock();
		$application = Application::get();
		$router->setApplication($application);
		$router->expects($this->any())
			->method('url')
			->will($this->returnCallback(array($this, 'routerUrl')));

		// Request
		import('classes.core.Request');
		$request = $this->getMockBuilder(Request::class)
			->setMethods(array('getRouter'))
			->getMock();
		$request->expects($this->any())
			->method('getRouter')
			->will($this->returnValue($router));
		Registry::set('request', $request);


		$importExportPlugins = PluginRegistry::loadCategory('importexport');
		$rosettaExportPlugin = $importExportPlugins['RosettaExportPlugin'];


		$dcDom = new RosettaDCDom($context, $submission->getLatestPublication(), false);

		//check dc.xml

		$nodeModified = $dcDom->getElementsByTagName('dcterms:modified')->item(0);
		$nodeModified->parentNode->removeChild($nodeModified);

		$dcXml = join(DIRECTORY_SEPARATOR, array(getcwd(), $rosettaExportPlugin->getPluginPath(), 'tests','data','dc.xml'));
		$this->assertXmlStringEqualsXmlFile($dcXml,$dcDom->saveXML());

		//check mets
		$metsDom = new RosettaMETSDom($context, $submission, $submission->getLatestPublication(), $rosettaExportPlugin);
		$nodeModified = $metsDom->getElementsByTagName('dcterms:modified')->item(0);
		$nodeModified->parentNode->removeChild($nodeModified);

		$saveXML = $metsDom->saveXML();
		$c2 = preg_split('/\r\n|\r|\n/', $saveXML);
		$ie1Xml = join(DIRECTORY_SEPARATOR, array(getcwd(), $rosettaExportPlugin->getPluginPath(), 'tests','data','ie1.xml'));
		$doc = new DOMDocument();
		$doc->loadXML(file_get_contents($ie1Xml), XML_PARSE_PEDANTIC);
		$parentNode = $doc->parentNode;
		$nodeModified = $parentNode->getElementsByTagName('dcterms:modified')->item(0);
		$nodeModified->parentNode->removeChild($nodeModified);
		#$this->assertEquals(preg_split('/\r\n|\r|\n/', $metsDom->saveXML()), preg_split('/\r\n|\r|\n/', ));
		$c1 = preg_split('/\r\n|\r|\n/', );
		$this->assertEquals($c2, $c1);



		$x= 1;
	}

	function routerUrl($request, $newContext = null, $handler = null, $op = null, $path = null)
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
