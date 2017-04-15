<?php

namespace PrestaShopWebservice\Entity;

use InvalidArgumentException;

class Product
{
	/**
	 * @var array
	 */
	private $attributes;

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
	 * @return mixed
	 *
	 * @throws InvalidArgumentException
	 */
	public function getRaw($attributeName)
	{
		if (isset($this->attributes[$attributeName]))
		{
			return $this->attributes[$attributeName];
		}

		throw new InvalidArgumentException(
			sprintf('The attribute doesn\'t exist: %s', $attributeName)
		);
	}

	/**
	 * @return int
	 */
	public function getPrice()
	{
		return (int)$this->getItem('price');
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return (int)$this->getItem('id');
	}

	/**
	 * @return int
	 */
	public function getDefaultImageId()
	{
		return (int)$this->getItem('id_default_image');
	}

	/**
	 * @return string
	 */
	public function getRewriteLink()
	{
		return $this->getByLanguages('link_rewrite');
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->getByLanguages('name');
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	private function getByLanguages($key)
	{
		$itemList = $this->getItem($key);

		return (string)$itemList[1]['value'];
	}

	/**
	 * @param string $key
	 * @param null   $default
	 *
	 * @return mixed
	 */
	private function getItem($key, $default = null)
	{
		return isset($this->attributes[$key]) ? $this->attributes[$key] : $default;
	}
}
