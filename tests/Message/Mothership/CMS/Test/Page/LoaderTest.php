<?php

namespace Message\Mothership\CMS\Test\Page;

use Message\Mothership\CMS\Page\Loader;

use Message\Mothership\CMS\Page\Page;
use Message\Cog\DB\Adapter\Faux\ConnectionCsv;
use Message\Cog\DB\Query;
use Message\Mothership\CMS\Test\PageType\Blog;

class LoaderTest extends \PHPUnit_Framework_TestCase
{

	public function testGetByID()
	{
		$paths = array(
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/empty_table.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/full_table_results.csv',
		);

		$loader = $this->_getLoader($paths);

		// test that the correct instance is returned for a single ID
		$page = $loader->getByID(1);
		$this->assertInstanceOf('Message\Mothership\CMS\Page\Page', $page);

		// test that an array of Page objects are returned if an array of them are
		// passed through
		$page = $loader->getByID(0);
		$this->assertFalse($page);

		$pageTypes = array(1);
		$page = $loader->getByID($pageTypes);
		$this->assertEquals(count($page), count($pageTypes));

	}

	public function testGetBySlug()
	{

		$paths = array(
			__DIR__.'/Data/page_table.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/page_id_empty.csv',
			__DIR__.'/Data/historical_result.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/page_id_empty.csv',
		);

		// Check that a page instance is returned for a valid slug from a current page with history false
		$loader = $this->_getLoader($paths);

		$page = $loader->getBySlug('/blog/hello-world');
		$this->assertTrue($page instanceof Page);

		// Check that a page instance is returned for a historical slug
		$page = $loader->getBySlug('/blog/hello-world-old', true);
		$this->assertTrue($page instanceof Page);

		$page = $loader->getBySlug('/blog/blah-blah', false);

		$this->assertFalse($page);

		// test for false to be returned is no slug is found
	}

	public function testGetByType()
	{
		$paths = array(
			__DIR__.'/Data/blog_page_types.csv',
			__DIR__.'/Data/blog_1.csv',
			__DIR__.'/Data/blog_2.csv',
			__DIR__.'/Data/blog_3.csv',
		);

		// Check that a page instance is returned for a valid slug from a current page with history false
		$loader = $this->_getLoader($paths);

		$pages = $loader->getByType(new Blog);

		$this->assertTrue(is_array($pages));

		foreach ($pages as $page) {
			$this->assertTrue($page instanceof $page);
		}
	}

	public function testGetChildren()
	{
		$paths = array(
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/blog_children.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/full_table_results.csv',
			__DIR__.'/Data/full_table_results.csv',
		);

		// Check that a page instance is returned for a valid slug from a current page with history false
		$loader = $this->_getLoader($paths);

		// Return the blog page as this has children
		$page = $loader->getByID(2);
		$children = $loader->getChildren($page);

		$this->assertEquals(count($children),1);
	}

	public function testGetSiblings()
	{
		$paths = array(
			__DIR__.'/Data/full_table_results_siblings.csv',
			__DIR__.'/Data/blog_sibling.csv',
			__DIR__.'/Data/sibling_result1.csv',
			__DIR__.'/Data/full_table_results_siblings3.csv',
			__DIR__.'/Data/sibling_results_for_id_3.csv',
			__DIR__.'/Data/siblings_results3.csv',
			__DIR__.'/Data/siblings_results4.csv',
		);

		// Check that a page instance is returned for a valid slug from a current page with history false
		$loader = $this->_getLoader($paths);

		// Return the blog page as this has children
		$page = $loader->getByID(2);
		$siblings = $loader->getSiblings($page);

		$this->assertTrue(is_array($siblings));

		foreach ($siblings as $pageObject) {
			$this->assertTrue($pageObject instanceof Page);
		}

		$page = $loader->getByID(3);

		$siblings = $loader->getSiblings($page);

		$this->assertTrue(is_array($siblings));
		$this->assertEquals(count($siblings),1);

		foreach ($siblings as $pageObject) {
			$this->assertTrue($pageObject instanceof Page);
		}

	}

	protected function _getLoader(array $paths)
	{
		$connection = new ConnectionCsv;
		$connection->setSequence($paths);
		$query = new Query($connection);

		return new Loader('gb', $query);
	}
}