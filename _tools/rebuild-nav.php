#! /usr/bin/env php
<?php

// ========================================================================
//
// GLOBALS AND CONSTANTS GO HERE
//
// ------------------------------------------------------------------------

define('TOP_DIR', realpath(__DIR__ . '/../'));
define('NAVBAR_INCLUDE', TOP_DIR . '/nav.html');

// ========================================================================
//
// FUNCTIONS GO HERE
//
// ------------------------------------------------------------------------

function buildNavBar($toc)
{
	// make sure we have the metadata to use
	if (!isset($toc->navbar)) {
		dieMsg("toc.json does not define 'navbar' section at all");
	}

	if (!is_object($toc->navbar)) {
		dieMsg("'navbar' section in toc.json must be an object");
	}

	// if we get here, everything (should) be fine
	//
	// let's convert the data into a navbar

	$navBarHtml = '';

	foreach ($toc->navbar as $sectionName => $contents)
	{
		// are we looking at a simple link?
		if (is_string($contents)) {
			$navBarHtml .= '<li><a href="' . BASE_URL . $contents . '.html">' . htmlentities($sectionName) . "</a></li>\n";
			continue;
		}

		// start a dropdown menu for this section
		$navBarHtml .= '<li class="dropdown">' . "\n"
					 . '<a href="#" role="button" class="dropdown-toggle" data-toggle="dropdown">'
					 . htmlentities($sectionName)
					 . '<b class="caret"></b>'
					 . "</a>\n"
					 . '<ul class="dropdown-menu">' . "\n";

		// add the contents of this section
		foreach ($contents as $name => $url) {
			if (empty($url)) {
				$navBarHtml .= '<li class="divider"></li>' . "\n";
			}
			else {
				$navBarHtml .= '<li><a href="' . BASE_URL . $url . '.html">' . htmlentities($name) . "</a></li>\n";
			}
		}

		// all done - close the dropdown
		$navBarHtml .= "</ul>\n</li>\n";
	}

	// all done
	return $navBarHtml;
}

function buildPageMap($toc)
{
	// make sure we have the metadata to use
	if(!isset($toc->contents)) {
		dieMsg("toc.json does not define 'contents' section at all");
	}

	if (!is_array($toc->contents)) {
		dieMsg("'contents' section in toc.json must be an array");
	}

	// we need to know about each page
	$pages = array(
		"ordered" => array(),
		"indexed" => array()
	);

	// understand the contents of each page
	foreach ($toc->contents as $filename)
	{
		// extract the info for the page
		$page = getPageInfo($filename);

		// was that successful?
		if (!is_null($page)) {
			// yes
			$pages["indexed"][$filename] = $page;
		}
	}

	// additional processing can go here in the future

	// finally, build our list of pages in running order, for next / prev
	// calculations
	foreach ($pages["indexed"] as $filename => $page)
	{
		$pages["ordered"][] = $pages["indexed"][$filename];
	}

	// all done
	return $pages;
}

function buildSidebar($sidebar, $toc, $pages)
{
	// build up our sidebar HTML
	$sidebarHtml = "<h3>Contents</h3>\n<ol>";
	foreach ($sidebar["contents"] as $filename)
	{
		// find the metadata about our page
		if (!isset($pages["indexed"][$filename])) {
			// the page doesn't exist yet
			continue;
		}
		$page = $pages["indexed"][$filename];

		// add the links into the sidebar
		$sidebarHtml .= '<li><a href="' . BASE_URL . $filename . '.html">' . $page['title'] . "</a></li>\n";
		echo $page['title'] . "\n";

		if (isset($page['h2']))
		{
			$sidebarHtml .="<ul>\n";
			foreach ($page['h2'] as $h2)
			{
				$sidebarHtml .= '<li><a href="' . basename($page['name']) . '.html#' . $h2['id'] . '">' . $h2['text'] . "</a></li>\n";
				echo "- " . $h2["text"] . "\n";
			}

			$sidebarHtml .= "</ul>\n";
		}
	}

	// all done
	return $sidebarHtml;
}

function buildSidebarList($toc)
{
	// make sure we have the metadata to use
	if(!isset($toc->contents)) {
		dieMsg("toc.json does not define 'contents' section at all");
	}

	if (!is_array($toc->contents)) {
		dieMsg("'contents' section in toc.json must be an array");
	}

	// if we get here, everything (should) be fine

	// this will hold our list of sidebars to build
	$sidebars = array();

	// trun the contents list into a list of sidebars
	foreach ($toc->contents as $filename) {
		// convert the filename into a sidebar name
		$parts = explode("/", $filename);

		if (count($parts) == 1) {
			$sidebarName = "top-level";
		}
		else {
			unset($parts[count($parts) - 1]);
			$sidebarName = implode("-", $parts);
		}

		if (!isset($sidebars[$sidebarName])) {
			$sidebars[$sidebarName] = array();
		}

		$sidebars[$sidebarName]["contents"][] = $filename;
	}

	// now we need to build up the sections list for each sidebar
	/*
	foreach ($sidebars as $sidebarName => $sidebarDetails) {

	}
	*/

	// all done
	return $sidebars;
}

function dieMsg($msg)
{
	echo "*** error: " . $msg . "\n";
	exit(1);
}

function getToc($topDir)
{
	// where is the table of contents metadata?
	$tocFilename = str_replace('//', '/', $topDir . "/toc.json");

	if (!file_exists($tocFilename))
	{
		dieMsg("Unable to find book's table of contents file $tocFilename");
	}

	// our table of contents tells us what pages go in what order
	$toc = json_decode(file_get_contents($tocFilename));

	// all done
	return $toc;
}

function findInYaml($key, $source)
{
	$i = 1;
	while ($i < count($source))
	{
		// have we hit the end?
		if ($source[$i] == '---')
		{
			// yes we have
			return $i;
		}

		// have we found the item we want?
		if (substr($source[$i], 0, strlen($key) + 1) == $key . ':')
		{
			// yes we have
			return $i;
		}

		// if we get here, we need to carry on
		$i++;
	}
}

function getFromYaml($key, $source)
{
	$i = findInYaml($key, $source);

	// is this the end?
	if ($source[$i] == '---') {
		return null;
	}

	// we have data to return
	return substr($source[$i], strlen($key) + 2);
}

function putInYaml($key, $value, $source)
{
	$line = "$key: '$value'";

	$i = findInYaml($key, $source);

	// is this the end of the Yaml?
	if ($source[$i] == '---')
	{
		// inject the line into the array
		array_splice($source, $i, 0, $line);
		return $source;
	}

	// we have data to replace
	$source[$i] = $line;

	return $source;
}

function getPageInfo($filename)
{
	$page = array();

	// where is the source file?
	$page['MdFilename'] = TOP_DIR . "/{$filename}.md";

	// where is the HTML version?
	$page['HtmlFilename'] = TOP_DIR . "/_site/{$filename}.html";

	// does the HTML version exist?
	if (!file_exists($page['HtmlFilename'])) {
		echo "Skipping {$filename} - HTML page not found\n";
		return null;
	}

	// load the HTML version of the page
	// we don't add this to the page[] array, because we don't want to
	// run out of memory if the site gets too big
	$pageHtml = file_get_contents($page['HtmlFilename']);

	// extract the page title
	preg_match("|<title>(.*)</title>|", $pageHtml, $matches);
	$page['title'] = $matches[1];

	// extract the h2 headings
	preg_match_all('|<h2 id="(.*)">(.*)</h2>|', $pageHtml, $matches);
	if (isset($matches[1]))
	{
		$i = 0;

		foreach ($matches[1] as $id)
		{
			$page['h2'][] = array(
				'id' => $id,
				'text' => $matches[2][$i]
			);

			$i++;
		}
	}

	// we're done with the HTML
	unset($pageHtml);

	// add this page to the list of pages we know about
	$page['name'] = $filename;

	// all done
	return $page;
}

function rebuildPrevNextLinks($toc, $pages)
{
	// update the 'prev' / 'next' navigation in all of the source pages
	for($i = 0; $i < count($pages["ordered"]); $i++)
	{
		// shortcut to save typing
		$page = $pages["ordered"][$i];

		// what should the 'prev' line be?
		if ($i == 0)
		{
			$prev='&nbsp;';
		}
		else
		{
			$prev='<a href="' . getRelPathTo($pages["ordered"][$i]['name'], $pages["ordered"][$i-1]['name']) . '.html">Prev: ' . $pages["ordered"][$i-1]['title'] . "</a>";
		}

		// what should the 'next' line be?
		if ($i == count($pages["ordered"]) - 1)
		{
			$next = '<a href="' . getRelPathTo("", $pages["ordered"][0]['name']) . '.html">Back to: ' . $pages["ordered"][0]['title'] . "</a>";
		}
		else
		{
			$next='<a href="' . getRelPathTo($pages["ordered"][$i]['name'], $pages["ordered"][$i+1]['name']) . '.html">Next: ' . $pages["ordered"][$i+1]['title'] . "</a>";
		}

		$pageSource = explode("\n", file_get_contents($page['MdFilename']));

		// search the nav at the top for the 'prev' element
		$pageSource = putInYaml('prev', $prev, $pageSource);
		$pageSource = putInYaml('next', $next, $pageSource);

		// all done
		$pageSource = implode("\n", $pageSource);
		file_put_contents($page['MdFilename'], $pageSource);
	}
}

function getRelPathTo($lastPath, $path) {
	$parts = explode("/", $lastPath);
	if (count($parts) == 1) {
		return $path;
	}

	return str_repeat("../", count($parts) - 1) . $path;
}

function getBaseUrl($topDir)
{
	// do we have a Jekyll config file?
	$filename = $topDir . '/_config.yml';
	if (!file_exists($filename)) {
		// no - so assume that we're publishing into the root of the
		// website
		return "/";
	}

	// load the config file
	$raw = file_get_contents($filename);

	// does it contain basename?
	$lines = explode("\n", $raw);
	foreach ($lines as $line) {
		$parts = explode(":", $line);

		if (is_array($parts) && $parts[0] == "baseurl") {
			return trim($parts[1]) . "/";
		}
	}

	// if we get here, then there's no baseurl in the config file
	return "/";
}

// ========================================================================
//
// IT ALL HAPPENS HERE
//
// ------------------------------------------------------------------------

define("BASE_URL", getBaseUrl(TOP_DIR));
$toc = getToc(TOP_DIR);

// build up data about all of our pages
$pages = buildPageMap($toc);

if (isset($toc->navbar))
{
	// build the navbar
	$navBarHtml = buildNavBar($toc);

	// write the navbar our to disk
	file_put_contents(TOP_DIR . '/_includes/nav.html', $navBarHtml);
}

// extract our list of sections from the navbar
// $sections = buildSectionsList($toc);

// get the list of sidebars that we need to build
$sidebars = buildSidebarList($toc);

// now, built each sidebar in turn
foreach ($sidebars as $sidebarName => $sidebar) {
	$sidebarHtml = buildSidebar($sidebar, $toc, $pages);
	file_put_contents(TOP_DIR . "/_includes/sidebar/{$sidebarName}.html", $sidebarHtml);
}

// finally, we need to go through and sort out the next / prev links
rebuildPrevNextLinks($toc, $pages);