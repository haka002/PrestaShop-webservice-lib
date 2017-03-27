<?php

namespace PrestaShopWebservice\Entity;

use InvalidArgumentException;

class Product
{
	/**
	 * @var array
	 */
	protected $attributes;

	/**
	 * @param array $attributes
	 */
	public function __construct(array $attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * @param string $attributeName
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException
	 */
	public function get($attributeName)
	{
		if (isset($this->attributes[$attributeName]))
		{
			return $this->attributes[$attributeName];
		}

		throw new InvalidArgumentException(
			sprintf('The attribute doesn\'t exist: %s', $attributeName)
		);
	}
}
