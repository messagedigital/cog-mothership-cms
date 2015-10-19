<?php

namespace Message\Mothership\CMS\Analytics;

class BasicProvider implements AnalyticsProvidorInterface
{
	/**
	 * @var string
	 */
	private $_name;

	/**
	 * @var string
	 */
	private $_viewRef;

	/**
	 * @var array
	 */
	private $_params;

	public function __construct($name, $viewRef, $params)
	{
		$this->_name    = $name;
		$this->_viewRef = $viewRef;
		$this->_params  = $params;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getViewReference()
	{
		return $this->_viewRef;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getViewParams()
	{
		return $this->_params;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return $this->_name;
	}
}