<?php

namespace Message\Mothership\CMS\Page;

use Message\Mothership\CMS\Field\FieldInterface;
use Message\Mothership\CMS\Field\RepeatableContainer;

use Message\Cog\Validation\Validator;

/**
 * Container for page content.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Content implements \IteratorAggregate
{
	protected $_fields;
	protected $_validator;

	/**
	 * Set a content part.
	 *
	 * @param string                             $var   Content part name
	 * @param FieldInterface|RepeatableContainer $value The content part
	 */
	public function __set($var, $value)
	{
		if (!($value instanceof FieldInterface || $value instanceof RepeatableContainer)) {
			throw new \InvalidArgumentException(
				'Page content must be a `FieldInterface` or a `RepeatableContainer`, `%s` given',
				get_class($value)
			);
		}

		$this->_fields[$var] = $value;
	}

	/**
	 * Get a content part by name.
	 *
	 * @param  string $var Content part name
	 *
	 * @return FieldInterface|RepeatableContainer $value The content part
	 */
	public function __get($var)
	{
		return $this->_fields[$var];
	}

	/**
	 * Check if a content part is set on this object.
	 *
	 * @param  string  $var Content part name
	 *
	 * @return boolean
	 */
	public function __isset($var)
	{
		return isset($this->_fields[$var]);
	}

	/**
	 * Get the validator set on this object.
	 *
	 * @return Validator
	 */
	public function getValidator()
	{
		return $this->_validator;
	}

	/**
	 * Set the validator used for fields on this object.
	 *
	 * @param Validator $validator
	 */
	public function setValidator(Validator $validator)
	{
		$this->_validator = $validator;
	}

	/**
	 * Get the iterator to use for looping over this object.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->_fields);
	}
}