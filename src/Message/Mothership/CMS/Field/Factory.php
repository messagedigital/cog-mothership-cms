<?php

namespace Message\Mothership\CMS\Field;

use Message\Mothership\CMS\PageType\PageTypeInterface;

use Message\Cog\Form\Handler;
use Message\Cog\Validation\Validator;

/**
 * Field factory, for building fields and groups of fields.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Factory implements \IteratorAggregate, \Countable
{
	protected $_validator;
	protected $_pageTypeName;
	protected $_fields = array();

	/**
	 * Constructor.
	 *
	 * @param Validator $validator The validator to use when building fields
	 */
	public function __construct(Validator $validator)
	{
		$this->_validator = $validator;
	}

	/**
	 * Build the fields for a given page type on this factory.
	 *
	 * @param  PageTypeInterface $pageType The page type to use
	 *
	 * @return Factory                     Returns $this for chainability
	 */
	public function build(PageTypeInterface $pageType)
	{
		$this->clear();

		$this->_pageTypeName = $pageType->getName();

		$pageType->setFields($this);

		return $this;
	}

	/**
	 * Get the validator set on this factory.
	 *
	 * @return Validator
	 */
	public function getValidator()
	{
		return $this->_validator;
	}

	/**
	 * Add a new field to the factory.
	 *
	 * @see _add
	 *
	 * @param  string      $type  The field type to get
	 * @param  string      $name  The name to use for the field
	 * @param  string|null $label The optional label for the field
	 *
	 * @return Field              The field that was added
	 */
	public function addField($type, $name, $label = null)
	{
		$field = $this->getField($type, $name, $label);

		$this->add($field);

		return $field;
	}

	/**
	 * Add a new group to the factory.
	 *
	 * @see _add
	 *
	 * @param  string      $name  The name to use for the group
	 * @param  string|null $label The optional label for the group
	 *
	 * @return Group              The group that was added
	 */
	public function addGroup($name, $label = null)
	{
		$group = $this->getGroup($name, $label);

		$this->add($group);

		return $group;
	}

	/**
	 * Add a field or a field group to the factory.
	 *
	 * @param FieldInterface $field The field or group to add
	 *
	 * @return FieldInterface       The field or group that was added
	 *
	 * @throws \InvalidArgumentException If a field with the identifier returned
	 *                                   from `getName()` on the field already exists
	 */
	public function add(FieldInterface $field)
	{
		// Check if a field with this name already exists
		if (isset($this->_fields[$field->getName()])) {
			throw new \InvalidArgumentException(sprintf(
				'A field with the name `%s` already exists on the field factory',
				$field->getName()
			));
		}

		$this->_fields[$field->getName()] = $field;

		return $field;
	}

	/**
	 * Clear all fields and groups set on this factory, restoring it to a fresh
	 * instance.
	 *
	 * @return Factory Returns $this for chainability
	 */
	public function clear()
	{
		$this->_fields = array();

		return $this;
	}

	/**
	 * Get a new instance of a field.
	 *
	 * @param  string      $type  The field type to get
	 * @param  string      $name  The name to use for the field
	 * @param  string|null $label The optional label for the field
	 *
	 * @return Group
	 *
	 * @throws \InvalidArgumentException If the field type does not exist
	 */
	public function getField($type, $name, $label = null)
	{
		$className = __NAMESPACE__ . '\\Type\\' . ucfirst($type);

		// Check if a class exists for this field type
		if (!class_exists($className)) {
			throw new \InvalidArgumentException(sprintf(
				'Field type `%s` does not exist (class `%s` not found)',
				$type,
				$className
			));
		}

		$field = new $className($this->_validator, $name, $label);
		$field->setTranslationKey($this->_pageTypeName . '.' . $name);

		return $field;
	}

	/**
	 * Get a new instance of a group field.
	 *
	 * @param  string      $name  The name to use for the group
	 * @param  string|null $label The optional label for the group
	 *
	 * @return Group
	 */
	public function getGroup($name, $label = null)
	{
		// Create a new blank validator
		$groupValidator = clone $this->_validator;
		$groupValidator->clear();

		$this->_validator->field($name, $label)->validateAgainst($groupValidator);

		$group = new Group($groupValidator, $name, $label);
		$group->setTranslationKey($this->_pageTypeName . '.' . $name);

		return $group;
	}

	/**
	 * Get a specific field/group that has been set on this factory.
	 *
	 * @param  string $name Name of the field (or group)
	 *
	 * @return FieldInterface|null
	 */
	public function get($name)
	{
		return isset($this->_fields[$name]) ? $this->_fields[$name] : false;
	}

	/**
	 * Get the number of fields registered on this factory.
	 *
	 * @return int The number of fields registered
	 */
	public function count()
	{
		return count($this->_fields);
	}

	/**
	 * Get the iterator object to use for iterating over this class.
	 *
	 * @return \ArrayIterator An \ArrayIterator instance for the `_fields`
	 *                        property
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->_fields);
	}
}