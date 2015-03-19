<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\base;

use Craft;
use craft\app\behaviors\ContentTrait;
use craft\app\dates\DateTime;
use craft\app\elements\db\ElementQuery;
use craft\app\elements\db\ElementQueryInterface;
use craft\app\helpers\ArrayHelper;
use craft\app\helpers\HtmlHelper;
use craft\app\helpers\TemplateHelper;
use craft\app\helpers\UrlHelper;
use craft\app\models\Content;
use craft\app\models\FieldLayout;
use craft\app\web\UploadedFile;
use Exception;
use yii\base\ErrorHandler;
use yii\base\InvalidCallException;
use yii\base\UnknownPropertyException;

/**
 * Element is the base class for classes representing elements in terms of objects.
 *
 * @property string $title The element’s title.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class Element extends Component implements ElementInterface
{
	// Traits
	// =========================================================================

	use ElementTrait;
	use ContentTrait;

	// Constants
	// =========================================================================

	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const ARCHIVED = 'archived';

	// Static
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public static function hasContent()
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasTitles()
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function isLocalized()
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function hasStatuses()
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function getStatuses()
	{
		return [
			static::ENABLED => Craft::t('app', 'Enabled'),
			static::DISABLED => Craft::t('app', 'Disabled')
		];
	}

	/**
	 * @inheritdoc
	 * @return ElementQuery The newly created [[ElementQuery]] instance.
	 */
	public static function find()
	{
		return new ElementQuery(get_called_class());
	}

	/**
	 * @inheritdoc
	 * @return static Element instance matching the condition, or `null` if nothing matches.
	 */
	public static function findOne($criteria = null)
	{
		return static::findByCondition($criteria, true);
	}

	/**
	 * @inheritdoc
	 * @return static[] An array of Element instances, or an empty array if nothing matches.
	 */
	public static function findAll($criteria = null)
	{
		return static::findByCondition($criteria, false);
	}

	/**
	 * @inheritdoc
	 */
	public static function getSources($context = null)
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function getSourceByKey($key, $context = null)
	{
		$contextKey = ($context ? $context : '*');

		if (!isset(self::$_sourcesByContext[$contextKey]))
		{
			self::$_sourcesByContext[$contextKey] = static::getSources($context);
		}

		return static::_findSource($key, self::$_sourcesByContext[$contextKey]);
	}

	/**
	 * @inheritdoc
	 */
	public static function getAvailableActions($source = null)
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public static function defineSearchableAttributes()
	{
		return [];
	}

	// Element index methods
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public static function getIndexHtml($elementQuery, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes)
	{
		$variables = [
			'viewMode'            => $viewState['mode'],
			'context'             => $context,
			'elementType'         => new static(),
			'disabledElementIds'  => $disabledElementIds,
			'collapsedElementIds' => Craft::$app->getRequest()->getParam('collapsedElementIds'),
			'showCheckboxes'      => $showCheckboxes,
		];

		// Special case for sorting by structure
		if (isset($viewState['order']) && $viewState['order'] == 'structure')
		{
			$source = static::getSourceByKey($sourceKey, $context);

			if (isset($source['structureId']))
			{
				$elementQuery->orderBy('lft asc');
				$variables['structure'] = Craft::$app->structures->getStructureById($source['structureId']);

				// Are they allowed to make changes to this structure?
				if ($context == 'index' && $variables['structure'] && !empty($source['structureEditable']))
				{
					$variables['structureEditable'] = true;

					// Let StructuresController know that this user can make changes to the structure
					Craft::$app->getSession()->authorize('editStructure:'.$variables['structure']->id);
				}
			}
			else
			{
				unset($viewState['order']);
			}
		}
		else if (!empty($viewState['order']) && $viewState['order'] == 'score')
		{
			$elementQuery->orderBy('score');
		}
		else
		{
			$sortableAttributes = static::defineSortableAttributes();

			if ($sortableAttributes)
			{
				$order = (!empty($viewState['order']) && isset($sortableAttributes[$viewState['order']])) ? $viewState['order'] : array_shift(array_keys($sortableAttributes));
				$sort  = (!empty($viewState['sort']) && in_array($viewState['sort'], ['asc', 'desc'])) ? $viewState['sort'] : 'asc';

				// Combine them, accounting for the possibility that $order could contain multiple values,
				// and be defensive about the possibility that the first value actually has "asc" or "desc"

				// typeId             => typeId [sort]
				// typeId, title      => typeId [sort], title
				// typeId, title desc => typeId [sort], title desc
				// typeId desc        => typeId [sort]

				$elementQuery->orderBy(preg_replace('/^(.*?)(?:\s+(?:asc|desc))?(,.*)?$/i', "$1 {$sort}$2", $order));
			}
		}

		switch ($viewState['mode'])
		{
			case 'table':
			{
				// Get the table columns
				$variables['attributes'] = static::defineTableAttributes($sourceKey);

				break;
			}
		}

		$variables['elements'] = $elementQuery->all();

		$template = '_elements/'.$viewState['mode'].'view/'.($includeContainer ? 'container' : 'elements');
		return Craft::$app->templates->render($template, $variables);
	}

	/**
	 * @inheritdoc
	 */
	public static function defineSortableAttributes()
	{
		return static::defineTableAttributes();
	}

	/**
	 * @inheritdoc
	 */
	public static function defineTableAttributes($source = null)
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public static function getTableAttributeHtml(ElementInterface $element, $attribute)
	{
		switch ($attribute)
		{
			case 'uri':
			{
				$url = $element->getUrl();

				if ($url)
				{
					$value = $element->uri;

					if ($value == '__home__')
					{
						$value = '<span data-icon="home" title="'.Craft::t('app', 'Homepage').'"></span>';
					}
					else
					{
						// Add some <wbr> tags in there so it doesn't all have to be on one line
						$find = ['/'];
						$replace = ['/<wbr>'];

						$wordSeparator = Craft::$app->config->get('slugWordSeparator');

						if ($wordSeparator)
						{
							$find[] = $wordSeparator;
							$replace[] = $wordSeparator.'<wbr>';
						}

						$value = str_replace($find, $replace, $value);
					}

					return '<a href="'.$url.'" target="_blank" class="go"><span dir="ltr">'.$value.'</span></a>';
				}
				else
				{
					return '';
				}
			}

			default:
			{
				$value = $element->$attribute;

				if ($value instanceof DateTime)
				{
					return '<span title="'.$value->localeDate().' '.$value->localeTime().'">'.$value->uiTimestamp().'</span>';
				}

				return HtmlHelper::encode($value);
			}
		}
	}

	// Methods for customizing the content table
	// -----------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public static function getFieldsForElementsQuery(ElementQueryInterface $query)
	{
		$contentService = Craft::$app->content;
		$originalFieldContext = $contentService->fieldContext;
		$contentService->fieldContext = 'global';
		$fields = Craft::$app->fields->getAllFields();
		$contentService->fieldContext = $originalFieldContext;
		return $fields;
	}

	// Methods for customizing element queries
	// -------------------------------------------------------------------------

	/**
	 * @inheritdoc
	 */
	public static function getElementQueryStatusCondition(ElementQueryInterface $query, $status)
	{
	}

	// Element methods

	/**
	 * @inheritdoc
	 */
	public static function getEditorHtml(ElementInterface $element)
	{
		$html = '';

		$fieldLayout = $element->getFieldLayout();

		if ($fieldLayout)
		{
			$originalNamespace = Craft::$app->templates->getNamespace();
			$namespace = Craft::$app->templates->namespaceInputName('fields', $originalNamespace);
			Craft::$app->templates->setNamespace($namespace);

			foreach ($fieldLayout->getFields() as $fieldLayoutField)
			{
				$fieldHtml = Craft::$app->templates->render('_includes/field', [
					'element'  => $element,
					'field'    => $fieldLayoutField->getField(),
					'required' => $fieldLayoutField->required
				]);

				$html .= Craft::$app->templates->namespaceInputs($fieldHtml, 'fields');
			}

			Craft::$app->templates->setNamespace($originalNamespace);
		}

		return $html;
	}

	/**
	 * @inheritdoc
	 */
	public static function saveElement(ElementInterface $element, $params)
	{
		return Craft::$app->elements->saveElement($element);
	}

	/**
	 * @inheritdoc
	 */
	public static function getElementRoute(ElementInterface $element)
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public static function onAfterMoveElementInStructure(ElementInterface $element, $structureId)
	{
	}

	// Properties
	// =========================================================================

	/**
	 * @var array
	 */
	private static $_sourcesByContext;

	/**
	 * @var
	 */
	private $_fieldsByHandle;

	/**
	 * @var
	 */
	private $_contentPostLocation;

	/**
	 * @var
	 */
	private $_rawPostContent;

	/**
	 * @var
	 */
	private $_content;

	/**
	 * @var
	 */
	private $_preppedContent;

	/**
	 * @var
	 */
	private $_nextElement;

	/**
	 * @var
	 */
	private $_prevElement;

	/**
	 * @var
	 */
	private $_parent;

	/**
	 * @var
	 */
	private $_prevSibling;

	/**
	 * @var
	 */
	private $_nextSibling;

	// Public Methods
	// =========================================================================

	/**
	 * Returns the string representation of the element.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return (string) $this->getTitle();
		}
		catch (Exception $e)
		{
			ErrorHandler::convertExceptionToError($e);
		}
	}

	/**
	 * Checks if a property is set.
	 *
	 * This method will check if $name is one of the following:
	 *
	 * - "title"
	 * - a magic property supported by [[\yii\base\Component::__isset()]]
	 * - a custom field handle
	 *
	 * @param string $name The property name
	 * @return boolean Whether the property is set
	 */
	public function __isset($name)
	{
		if ($name == 'title' || parent::__isset($name) || $this->getFieldByHandle($name))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns a property value.
	 *
	 * This method will check if $name is one of the following:
	 *
	 * - a magic property supported by [[\yii\base\Component::__isset()]]
	 * - a custom field handle
	 *
	 * @param string $name The property name
	 * @return mixed The property value
	 * @throws UnknownPropertyException if the property is not defined
	 * @throws InvalidCallException if the property is write-only.
	 */
	public function __get($name)
	{
		try
		{
			return parent::__get($name);
		}
		catch (UnknownPropertyException $e)
		{
			// Is $name a field handle?
			if ($this->getFieldByHandle($name))
			{
				return $this->getFieldValue($name);
			}
			else
			{
				throw $e;
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		if (!$this->locale)
		{
			$this->locale = Craft::$app->i18n->getPrimarySiteLocaleId();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels()
	{
		return [
			'slug' => Craft::t('app', 'Slug'),
			'uri' => Craft::t('app', 'URI'),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['id'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['locale'], 'craft\\app\\validators\\Locale'],
			[['dateCreated'], 'craft\\app\\validators\\DateTime'],
			[['dateUpdated'], 'craft\\app\\validators\\DateTime'],
			[['root'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['lft'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['rgt'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
			[['level'], 'number', 'min' => -2147483648, 'max' => 2147483647, 'integerOnly' => true],
		];
	}

	/**
	 * Returns the element’s ID.
	 *
	 * @return int|null
	 *
	 * @internal This method is required by [[\yii\web\IdentityInterface]], but might as well
	 * go here rather than only in [[\craft\app\elements\User]].
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Returns the field layout used by this element.
	 *
	 * @return FieldLayout|null
	 */
	public function getFieldLayout()
	{
		return Craft::$app->fields->getLayoutByType($this->elementType);
	}

	/**
	 * Returns the locale IDs this element is available in.
	 *
	 * @return array
	 */
	public function getLocales()
	{
		if (static::isLocalized())
		{
			return Craft::$app->getI18n()->getSiteLocaleIds();
		}
		else
		{
			return [Craft::$app->getI18n()->getPrimarySiteLocaleId()];
		}
	}

	/**
	 * Returns the URL format used to generate this element’s URL.
	 *
	 * @return string|null
	 */
	public function getUrlFormat()
	{
	}

	/**
	 * Returns the element’s full URL.
	 *
	 * @return string
	 */
	public function getUrl()
	{
		if ($this->uri !== null)
		{
			$useLocaleSiteUrl = (
				($this->locale != Craft::$app->language) &&
				($localeSiteUrl = Craft::$app->config->getLocalized('siteUrl', $this->locale))
			);

			if ($useLocaleSiteUrl)
			{
				// Temporarily set Craft to use this element’s locale's site URL
				$siteUrl = Craft::$app->getSiteUrl();
				Craft::$app->setSiteUrl($localeSiteUrl);
			}

			if ($this->uri == '__home__')
			{
				$url = UrlHelper::getSiteUrl();
			}
			else
			{
				$url = UrlHelper::getSiteUrl($this->uri);
			}

			if ($useLocaleSiteUrl)
			{
				Craft::$app->setSiteUrl($siteUrl);
			}

			return $url;
		}
	}

	/**
	 * Returns an anchor pre-filled with this element’s URL and title.
	 *
	 * @return \Twig_Markup
	 */
	public function getLink()
	{
		$link = '<a href="'.$this->getUrl().'">'.HtmlHelper::encode($this->__toString()).'</a>';
		return TemplateHelper::getRaw($link);
	}

	/**
	 * Returns the reference string to this element.
	 *
	 * @return string|null
	 */
	public function getRef()
	{
	}

	/**
	 * Returns whether the current user can edit the element.
	 *
	 * @return bool
	 */
	public function isEditable()
	{
		return false;
	}

	/**
	 * Returns the element’s CP edit URL.
	 *
	 * @return string|false
	 */
	public function getCpEditUrl()
	{
		return false;
	}

	/**
	 * Returns the URL to the element’s thumbnail, if there is one.
	 *
	 * @param int|null $size
	 *
	 * @return string|false
	 */
	public function getThumbUrl($size = null)
	{
		return false;
	}

	/**
	 * Returns the URL to the element’s icon image, if there is one.
	 *
	 * @param int|null $size
	 *
	 * @return string|false
	 */
	public function getIconUrl($size = null)
	{
		return false;
	}

	/**
	 * Returns the element’s status.
	 *
	 * @return string|null
	 */
	public function getStatus()
	{
		if ($this->archived)
		{
			return static::ARCHIVED;
		}
		else if (!$this->enabled || !$this->localeEnabled)
		{
			return static::DISABLED;
		}
		else
		{
			return static::ENABLED;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getNext($criteria = false)
	{
		if ($criteria !== false || !isset($this->_nextElement))
		{
			return $this->_getRelativeElement($criteria, 1);
		}
		else if ($this->_nextElement !== false)
		{
			return $this->_nextElement;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getPrev($criteria = false)
	{
		if ($criteria !== false || !isset($this->_prevElement))
		{
			return $this->_getRelativeElement($criteria, -1);
		}
		else if ($this->_prevElement !== false)
		{
			return $this->_prevElement;
		}
	}

	/**
	 * Sets the default next element.
	 *
	 * @param ElementInterface|false $element
	 *
	 * @return null
	 */
	public function setNext($element)
	{
		$this->_nextElement = $element;
	}

	/**
	 * Sets the default previous element.
	 *
	 * @param ElementInterface|false $element
	 *
	 * return void
	 */
	public function setPrev($element)
	{
		$this->_prevElement = $element;
	}

	/**
	 * Get the element’s parent.
	 *
	 * @return ElementInterface|null
	 */
	public function getParent()
	{
		if ($this->_parent === null)
		{
			$this->_parent = $this->getAncestors(1)
				->status(null)
				->localeEnabled(null)
				->one();

			if ($this->_parent === null)
			{
				$this->_parent = false;
			}
		}

		return $this->_parent ?: null;
	}

	/**
	 * Sets the element’s parent.
	 *
	 * @param ElementInterface|null $parent
	 *
	 * @return null
	 */
	public function setParent($parent)
	{
		$this->_parent = $parent;

		if ($parent)
		{
			$this->level = $parent->level + 1;
		}
		else
		{
			$this->level = 1;
		}
	}

	/**
	 * Returns the element’s ancestors.
	 *
	 * @param int|null $dist
	 *
	 * @return ElementQueryInterface
	 */
	public function getAncestors($dist = null)
	{
		return static::find()
			->ancestorOf($this)
			->locale($this->locale)
			->ancestorDist($dist);
	}

	/**
	 * Returns the element’s descendants.
	 *
	 * @param int|null $dist
	 *
	 * @return ElementQueryInterface
	 */
	public function getDescendants($dist = null)
	{
		return static::find()
			->descendantOf($this)
			->locale($this->locale)
			->descendantDist($dist);
	}

	/**
	 * Returns the element’s children.
	 *
	 * @return ElementQueryInterface
	 */
	public function getChildren()
	{
		return $this->getDescendants(1);
	}

	/**
	 * Returns all of the element’s siblings.
	 *
	 * @return ElementQueryInterface
	 */
	public function getSiblings()
	{
		return static::find()
			->siblingOf($this)
			->locale($this->locale);
	}

	/**
	 * Returns the element’s previous sibling.
	 *
	 * @return ElementInterface|null
	 */
	public function getPrevSibling()
	{
		if ($this->_prevSibling === null)
		{
			$this->_prevSibling = static::find()
				->prevSiblingOf($this)
				->locale($this->locale)
				->status(null)
				->localeEnabled(false)
				->one();

			if ($this->_prevSibling === null)
			{
				$this->_prevSibling = false;
			}
		}

		return $this->_prevSibling ?: null;
	}

	/**
	 * Returns the element’s next sibling.
	 *
	 * @return ElementInterface|null
	 */
	public function getNextSibling()
	{
		if ($this->_nextSibling === null)
		{
			$this->_nextSibling = static::find()
				->nextSiblingOf($this)
				->locale($this->locale)
				->status(null)
				->localeEnabled(false)
				->one();

			if ($this->_nextSibling === null)
			{
				$this->_nextSibling = false;
			}
		}

		return $this->_nextSibling ?: null;
	}

	/**
	 * Returns whether the element has descendants.
	 *
	 * @return bool
	 */
	public function hasDescendants()
	{
		return ($this->lft && $this->rgt && $this->rgt > $this->lft + 1);
	}

	/**
	 * Returns the total number of descendants that the element has.
	 *
	 * @return bool
	 */
	public function getTotalDescendants()
	{
		if ($this->hasDescendants())
		{
			return ($this->rgt - $this->lft - 1) / 2;
		}

		return 0;
	}

	/**
	 * Returns whether this element is an ancestor of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isAncestorOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->lft < $element->lft && $this->rgt > $element->rgt);
	}

	/**
	 * Returns whether this element is a descendant of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isDescendantOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->lft > $element->lft && $this->rgt < $element->rgt);
	}

	/**
	 * Returns whether this element is a direct parent of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isParentOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->level == $element->level - 1 && $this->isAncestorOf($element));
	}

	/**
	 * Returns whether this element is a direct child of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isChildOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->level == $element->level + 1 && $this->isDescendantOf($element));
	}

	/**
	 * Returns whether this element is a sibling of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isSiblingOf(ElementInterface $element)
	{
		if ($this->root == $element->root && $this->level && $this->level == $element->level)
		{
			if ($this->level == 1 || $this->isPrevSiblingOf($element) || $this->isNextSiblingOf($element))
			{
				return true;
			}
			else
			{
				$parent = $this->getParent();

				if ($parent)
				{
					return $element->isDescendantOf($parent);
				}
			}
		}

		return false;
	}

	/**
	 * Returns whether this element is the direct previous sibling of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isPrevSiblingOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->level == $element->level && $this->rgt == $element->lft - 1);
	}

	/**
	 * Returns whether this element is the direct next sibling of another one.
	 *
	 * @param ElementInterface $element
	 *
	 * @return bool
	 */
	public function isNextSiblingOf(ElementInterface $element)
	{
		return ($this->root == $element->root && $this->level == $element->level && $this->lft == $element->rgt + 1);
	}

	/**
	 * Returns the element’s title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		$content = $this->getContent();
		return $content->title;
	}

	/**
	 * Treats custom fields as array offsets.
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		if ($offset == 'title' || parent::offsetExists($offset) || $this->getFieldByHandle($offset))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns the content for the element.
	 *
	 * @return Content
	 */
	public function getContent()
	{
		if (!isset($this->_content))
		{
			$this->_content = Craft::$app->content->getContent($this);

			if (!$this->_content)
			{
				$this->_content = Craft::$app->content->createContent($this);
			}
		}

		return $this->_content;
	}

	/**
	 * Sets the content for the element.
	 *
	 * @param Content|array $content
	 *
	 * @return null
	 */
	public function setContent($content)
	{
		if (is_array($content))
		{
			if (!isset($this->_content))
			{
				$this->_content = Craft::$app->content->createContent($this);
			}

			$this->_content->setAttributes($content, false);
		}
		else if ($content instanceof Content)
		{
			$this->_content = $content;
		}
	}

	/**
	 * Sets the content from post data, calling prepValueFromPost() on the field types.
	 *
	 * @param array|string $content
	 *
	 * @return null
	 */
	public function setContentFromPost($content)
	{
		if (is_string($content))
		{
			// Keep track of where the post data is coming from, in case any field types need to know where to
			// look in $_FILES
			$this->setContentPostLocation($content);

			$content = Craft::$app->getRequest()->getBodyParam($content, []);
		}

		if (!isset($this->_rawPostContent))
		{
			$this->_rawPostContent = [];
		}

		$fieldLayout = $this->getFieldLayout();

		if ($fieldLayout)
		{
			// Make sure $this->_content is set
			$this->getContent();

			foreach ($fieldLayout->getFields() as $fieldLayoutField)
			{
				$field = $fieldLayoutField->getField();

				if ($field)
				{
					$handle = $field->handle;

					// Do we have any post data for this field?
					if (isset($content[$handle]))
					{
						$value = $this->_rawPostContent[$handle] = $content[$handle];
					}
					// Were any files uploaded for this field?
					else if (!empty($this->_contentPostLocation) && UploadedFile::getInstancesByName($this->_contentPostLocation.'.'.$handle))
					{
						$value = null;
					}
					else
					{
						// No data was submitted so just skip this field
						continue;
					}

					// Give the field a chance to make changes
					$field->element = $this;
					$value = $field->prepValueFromPost($value);

					// Now set the prepped value on the Content
					$this->_content->$handle = $value;
				}
			}
		}
	}

	/**
	 * Returns the raw content from the post data, before it was passed through [[prepValueFromPost()]].
	 *
	 * @return array
	 */
	public function getContentFromPost()
	{
		if (isset($this->_rawPostContent))
		{
			return $this->_rawPostContent;
		}
		else
		{
			return [];
		}
	}

	/**
	 * Returns the location in POST that the content was pulled from.
	 *
	 * @return string|null
	 */
	public function getContentPostLocation()
	{
		return $this->_contentPostLocation;
	}

	/**
	 * Sets the location in POST that the content was pulled from.
	 *
	 * @param $contentPostLocation
	 *
	 * @return string|null
	 */
	public function setContentPostLocation($contentPostLocation)
	{
		$this->_contentPostLocation = $contentPostLocation;
	}

	/**
	 * Returns the prepped content for a given field.
	 *
	 * @param string $fieldHandle
	 *
	 * @throws Exception
	 * @return mixed
	 */
	public function getFieldValue($fieldHandle)
	{
		if (!isset($this->_preppedContent) || !array_key_exists($fieldHandle, $this->_preppedContent))
		{
			$field = $this->getFieldByHandle($fieldHandle);

			if (!$field)
			{
				throw new Exception(Craft::t('app', 'No field exists with the handle “{handle}”', ['handle' => $fieldHandle]));
			}

			$content = $this->getContent();

			if (isset($content->$fieldHandle))
			{
				$value = $content->$fieldHandle;
			}
			else
			{
				$value = null;
			}

			// Give the field a chance to prep the value for use
			$field->element = $this;
			$value = $field->prepValue($value);

			$this->_preppedContent[$fieldHandle] = $value;
		}

		return $this->_preppedContent[$fieldHandle];
	}

	/**
	 * Returns the name of the table this element’s content is stored in.
	 *
	 * @return string
	 */
	public function getContentTable()
	{
		return Craft::$app->content->contentTable;
	}

	/**
	 * Returns the field column prefix this element’s content uses.
	 *
	 * @return string
	 */
	public function getFieldColumnPrefix()
	{
		return Craft::$app->content->fieldColumnPrefix;
	}

	/**
	 * Returns the field context this element’s content uses.
	 *
	 * @return string
	 */
	public function getFieldContext()
	{
		return Craft::$app->content->fieldContext;
	}

	// Protected Methods
	// =========================================================================

	/**
	 * Finds Element instance(s) by the given condition.
	 *
	 * This method is internally called by [[findOne()]] and [[findAll()]].
	 *
	 * @param mixed   $criteria Refer to [[findOne()]] and [[findAll()]] for the explanation of this parameter
	 * @param boolean $one      Whether this method is called by [[findOne()]] or [[findAll()]]
	 * @return static|static[]
	 */
	protected static function findByCondition($criteria, $one)
	{
		if ($criteria !== null && !ArrayHelper::isAssociative($criteria))
		{
			$criteria = ['id' => $criteria];
		}

		/** @var ElementQueryInterface $query */
		$query = static::find()->configure($criteria);
		return $one ? $query->one() : $query->all();
	}

	/**
	 * Returns the field with a given handle.
	 *
	 * @param string $handle
	 *
	 * @return Field|null
	 */
	protected function getFieldByHandle($handle)
	{
		if (!isset($this->_fieldsByHandle) || !array_key_exists($handle, $this->_fieldsByHandle))
		{
			$contentService = Craft::$app->content;

			$originalFieldContext = $contentService->fieldContext;
			$contentService->fieldContext = $this->getFieldContext();

			$this->_fieldsByHandle[$handle] = Craft::$app->fields->getFieldByHandle($handle);

			$contentService->fieldContext = $originalFieldContext;
		}

		return $this->_fieldsByHandle[$handle];
	}

	// Private Methods
	// =========================================================================

	/**
	 * Finds a source by its key, even if it's nested.
	 *
	 * @param array  $sources
	 * @param string $key
	 *
	 * @return array|null
	 */
	private static function _findSource($key, $sources)
	{
		if (isset($sources[$key]))
		{
			return $sources[$key];
		}
		else
		{
			// Look through any nested sources
			foreach ($sources as $source)
			{
				if (!empty($source['nested']) && ($nestedSource = static::_findSource($key, $source['nested'])))
				{
					return $nestedSource;
				}
			}
		}
	}

	/**
	 * Returns an element right before/after this one, from a given set of criteria.
	 *
	 * @param ElementQueryInterface|array|null $criteria
	 * @param int   $dir
	 *
	 * @return ElementInterface|null
	 */
	private function _getRelativeElement($criteria, $dir)
	{
		if ($this->id)
		{
			if ($criteria instanceof ElementQueryInterface)
			{
				$query = $criteria;
			}
			else
			{
				$query = static::find()
					->locale($this->locale)
					->configure($criteria);
			}

			$elementIds = $query->ids();
			$key = array_search($this->id, $elementIds);

			if ($key !== false && isset($elementIds[$key+$dir]))
			{
				return static::find()
					->id($elementIds[$key+$dir])
					->locale($query->locale)
					->one();
			}
		}
	}
}
