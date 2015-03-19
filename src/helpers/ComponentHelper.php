<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\helpers;

use craft\app\base\Component;
use craft\app\base\ComponentInterface;
use craft\app\base\SavableComponentInterface;
use craft\app\errors\InvalidComponentException;
use yii\base\InvalidConfigException;
use yii\base\UnknownClassException;

/**
 * Class ElementHelper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ComponentHelper
{
	// Public Methods
	// =========================================================================

	/**
	 * Instantiates and populates a component, and ensures that it is an instance of a given interface.
	 *
	 * @param mixed  $config     The component’s class name, or its config, with a `type` value and optionally a `settings` value.
	 * @param string $instanceOf The class or interface that the component must be an instance of.
	 * @return ComponentInterface The component
	 * @throws InvalidConfigException if $config doesn’t contain a `type` value, or the type isn’s compatible with $instanceOf.
	 * @throws InvalidComponentException if the class specified by $config doesn’t exist or isn’t an instance of ComponentInterface/$instanceOf.
	 */
	public static function createComponent($config, $instanceOf = null)
	{
		// Normalize the config
		if (is_string($config))
		{
			$class = $config;
			$config = ['type' => $class];
		}
		else
		{
			$config = ArrayHelper::toArray($config);

			if (empty($config['type']))
			{
				throw new InvalidConfigException('The config passed into ComponentHelper::createComponent() did not specify a class: '.JsonHelper::encode($config));
			}

			$class = $config['type'];
		}

		if (!class_exists($class))
		{
			throw new InvalidComponentException("Unable to find component class '$class'.");
		}

		if (!is_subclass_of($class, 'craft\app\base\ComponentInterface'))
		{
			throw new InvalidComponentException("Component class '$class' does not implement ComponentInterface.");
		}

		// Instantiate
		/** @var Component $class */
		$component = $class::instantiate($config);

		if ($instanceOf && !$component instanceof $instanceOf)
		{
			throw new InvalidComponentException("Component class '$class' is not an instance of '$instanceOf'.");
		}

		// Populate and return
		if ($component instanceof SavableComponentInterface && !empty($config['settings']))
		{
			// Merge the settings with the rest of the config
			$settings = $config['settings'];
			unset($config['settings']);

			if (is_string($settings))
			{
				$settings = JsonHelper::decode($settings);
			}

			$config = array_merge($config, $settings);
		}

		$class::populateModel($component, $config);
		return $component;
	}
}
