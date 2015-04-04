<?php
/**
 * BaseControl.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:FormDateTime!
 * @subpackage	Controls
 * @since		5.0
 *
 * @date		11.01.15
 */

namespace IPub\FormDateTime\Controls;

use Nette;
use Nette\Application\UI;
use Nette\Bridges;
use Nette\Forms;
use Nette\Localization;
use Nette\Utils;

use Latte;

use IPub;
use IPub\FormDateTime;

abstract class BaseControl extends Forms\Controls\BaseControl
{
	/**
	 * Validator constants
	 */
	const DATE_TIME_RANGE	= ':dateTimeRange';
	const DATE_TIME_MIN		= ':dateTimeMin';
	const DATE_TIME_MAX		= ':dateTimeMax';

	/**
	 * Icon left
	 */
	const BUTTONS_POSITION_LEFT = 0;

	/**
	 * Icon right
	 */
	const BUTTONS_POSITION_RIGHT = 1;

	/**
	 * No icon
	 */
	const BUTTONS_POSITION_NONE = 2;

	/**
	 * Default language
	 */
	const DEFAULT_LANGUAGE = 'en';

	/**
	 * If true calendar will be closed on click
	 *
	 * @var bool autoclose flag
	 */
	protected $autoclose = TRUE;

	/**
	 * Switch keyboard navigation on/off
	 *
	 * @var bool
	 */
	protected $keyboardNavigation = TRUE;

	/**
	 * Style
	 *
	 * @var int
	 */
	protected $buttonsPosition = self::BUTTONS_POSITION_RIGHT;

	/**
	 * Enable or disable trigger button
	 *
	 * @var bool
	 */
	protected $triggerButton = FALSE;

	/**
	 * Enable or disable cancel button
	 *
	 * @var bool
	 */
	protected $cancelButton = FALSE;

	/**
	 * The earliest date that may be selected
	 * All earlier dates will be disabled
	 *
	 * @var Utils\DateTime
	 */
	protected $startDateTime = NULL;

	/**
	 * The latest date that may be selected
	 * All later dates will be disabled
	 *
	 * @var Utils\DateTime
	 */
	protected $endDateTime = NULL;

	/**
	 * Language
	 *
	 * @var string
	 */
	protected $language = self::DEFAULT_LANGUAGE;

	/**
	 * Whether or not to force parsing of the input value when the picker is closed
	 *
	 * @var bool
	 */
	protected $forceParse = TRUE;

	/**
	 * @var string
	 */
	protected $templatePath;

	/**
	 * @var UI\ITemplate
	 */
	protected $template;

	/**
	 * Value entered by user (unfiltered)
	 *
	 * @var string
	 */
	protected $rawValue;

	/**
	 * This method will be called when the component becomes attached to Form
	 *
	 * @param  Nette\ComponentModel\IComponent
	 */
	public function attached($form)
	{
		parent::attached($form);

		// Create control template
		$this->template = $this->createTemplate();
	}

	/**
	 * Sets class autoclose flag
	 * When autoclose flag is set to true calendar will be closed when date is selected.
	 *
	 * @param bool $autoclose
	 *
	 * @return $this
	 */
	public function setAutoclose($autoclose = TRUE)
	{
		$this->autoclose = (bool) $autoclose;

		return $this;
	}

	/**
	 * Switch keyboard navigation on / off
	 *
	 * @param bool $state
	 *
	 * @return $this
	 */
	public function setKeyboardNavigation($state = TRUE)
	{
		$this->keyboardNavigation = (bool) $state;

		return $this;
	}

	/**
	 * Whether or not to force parsing of the input value when the picker is closed
	 *
	 * @param bool $forceParse
	 *
	 * @return $this
	 */
	public function setForceParse($forceParse = TRUE)
	{
		$this->forceParse = (bool) $forceParse;

		return $this;
	}

	/**
	 * Sets input button style
	 *
	 * @param int $buttonsPosition
	 *
	 * @return $this
	 *
	 * @throws Nette\InvalidArgumentException
	 */
	public function setButtonsPosition($buttonsPosition = self::BUTTONS_POSITION_RIGHT)
	{
		if (!in_array((string) $buttonsPosition, [self::BUTTONS_POSITION_LEFT, self::BUTTONS_POSITION_RIGHT, self::BUTTONS_POSITION_NONE])) {
			throw new Nette\InvalidArgumentException('Invalid buttons position given');
		}

		$this->buttonsPosition = $buttonsPosition;

		return $this;
	}

	/**
	 * Sets input cancel button
	 *
	 * @return $this
	 */
	public function enableTriggerButton()
	{
		$this->triggerButton = TRUE;

		return $this;
	}

	/**
	 * Sets input cancel button
	 *
	 * @return $this
	 */
	public function enableCancelButton()
	{
		$this->cancelButton = TRUE;

		return $this;
	}

	/**
	 * Generates control's HTML element
	 *
	 * @return Utils\Html
	 */
	public function getControl()
	{
		// If template file was not defined before...
		if ($this->template->getFile() === NULL) {
			// ...try to get base control template file
			$templatePath = !empty($this->templatePath) ? $this->templatePath : __DIR__ . DIRECTORY_SEPARATOR .'template'. DIRECTORY_SEPARATOR .'default.latte';
			// ...& set it to template engine
			$this->template->setFile($templatePath);
		}

		// Assign vars to template
		$this->template->value		= $this->getValue();
		$this->template->caption	= $this->caption;
		$this->template->_form		= $this->getForm();

		// Control js settings
		$this->template->settings	= $this->getControlSettings();

		// Template settings
		$this->template->showButtons		= $this->buttonsPosition == self::BUTTONS_POSITION_LEFT || $this->buttonsPosition == self::BUTTONS_POSITION_RIGHT ? TRUE : FALSE;
		$this->template->buttonsPosition	= $this->buttonsPosition == self::BUTTONS_POSITION_LEFT ? 'before' : ($this->buttonsPosition == self::BUTTONS_POSITION_RIGHT ? 'after' : 'none');
		$this->template->showTriggerButton	= $this->triggerButton ? TRUE : FALSE;
		$this->template->showCancelButton	= $this->cancelButton ? TRUE : FALSE;

		return FormDateTime\Utils\Html::el()
			->add($this->template);
	}

	/**
	 * Returns unfiltered value.
	 *
	 * @return string
	 */
	public function getRawValue()
	{
		return $this->rawValue;
	}

	/**
	 * @return bool
	 */
	public function isFilled()
	{
		$value = $this->getRawValue();

		return $value !== NULL && $value !== array() && $value !== '';
	}

	/**
	 * Adds a validation rule
	 *
	 * @param  mixed $validator rule type
	 * @param  string $message message to display for invalid data
	 * @param  mixed $arg optional rule arguments
	 *
	 * @return $this
	 *
	 * @throws Nette\InvalidArgumentException
	 */
	public function addRule($validator, $message = NULL, $arg = NULL)
	{
		// Check for date rage rule
		if ($validator == self::DATE_TIME_RANGE) {
			list($ruleMin, $ruleMax) = $arg;

			// Check if range is set
			if ($ruleMin !== NULL && $ruleMax !== NULL && $ruleMin instanceof \DateTime && $ruleMax instanceof \DateTime) {
				if ($ruleMin > $ruleMax) {
					$arg = [$ruleMax, $ruleMin];

				} else {
					$arg = [$ruleMin, $ruleMax];
				}

				// Register rule
				parent::addRule($validator, $message, $arg);

				// Set minimal date from range
				$this->startDateTime = $ruleMin;
				// Set maximal date from range
				$this->endDateTime = $ruleMax;

			} else {
				throw new Nette\InvalidArgumentException('Provided rule arguments are not valid. Date range rule expect array od min & max \DateTime objects.');
			}

		// Check for minimal date rule
		} else if ($validator == self::DATE_TIME_MIN) {
			if ($arg instanceof \DateTime) {
				// Register rule
				parent::addRule($validator, $message, $arg);

				// Set minimal date
				$this->startDateTime = $arg;

			} else {
				throw new Nette\InvalidArgumentException('Provided rule argument is not valid. Date min rule expect \DateTime object.');
			}

		// Check for maximal date rule
		} else if ($validator == self::DATE_TIME_MAX) {
			if ($arg instanceof \DateTime) {
				// Register rule
				parent::addRule($validator, $message, $arg);

				// Set maximal date
				$this->endDateTime = $arg;

			} else {
				throw new Nette\InvalidArgumentException('Provided rule argument is not valid. Date max rule expect \DateTime object.');
			}

		// Add the any other standard rule
		} else {
			parent::addRule($validator, $message, $arg);
		}

		return $this;
	}

	/**
	 * Is entered values within allowed range?
	 *
	 * @param Forms\IControl $control
	 * @param array 0 => minDate, 1 => maxDate
	 *
	 * @return   bool
	 *
	 * @throws Nette\InvalidStateException
	 */
	public static function validateDateTimeRange(Forms\IControl $control, array $range)
	{
		if (!$control instanceof self) {
			throw new Nette\InvalidStateException('Unable to validate ' . get_class($control) . ' instance.');
		}

		return ($range[0] === NULL || $control->getValue() >= $range[0]) && ($range[1] === NULL || $control->getValue() <= $range[1]);
	}

	/**
	 * Is entered values within allowed minimum
	 *
	 * @param Forms\IControl $control
	 * @param \DateTime $minDate
	 *
	 * @return bool
	 *
	 * @throws Nette\InvalidStateException
	 */
	public static function validateDateTimeMin(Forms\IControl $control, \DateTime $minDate = NULL)
	{
		if (!$control instanceof self) {
			throw new Nette\InvalidStateException('Unable to validate ' . get_class($control) . ' instance.');
		}

		return ($minDate === NULL || $control->getValue() >= $minDate);
	}

	/**
	 * Is entered values within allowed maximum
	 *
	 * @param Forms\IControl $control
	 * @param \DateTime $maxDate
	 *
	 * @return bool
	 *
	 * @throws Nette\InvalidStateException
	 */
	public static function validateDateTimeMax(Forms\IControl $control, \DateTime $maxDate = NULL)
	{
		if (!$control instanceof self) {
			throw new Nette\InvalidStateException('Unable to validate ' . get_class($control) . ' instance.');
		}

		return ($maxDate === NULL || $control->getValue() <= $maxDate);
	}

	/**
	 * Does user enter anything? (the value doesn't have to be valid)
	 *
	 * @param Forms\IControl $control
	 *
	 * @return bool
	 *
	 * @throws Nette\InvalidStateException
	 */
	public static function validateFilled(Forms\IControl $control)
	{
		if (!$control instanceof self) {
			throw new Nette\InvalidStateException('Unable to validate ' . get_class($control) . ' instance.');
		}

		$rawValue = $control->rawValue;

		return !empty($rawValue);
	}

	/**
	 * Is entered value valid? (empty value is also valid!)
	 *
	 * @param Forms\IControl $control
	 *
	 * @return bool
	 *
	 * @throws Nette\InvalidStateException
	 */
	public static function validateValid(Forms\IControl $control)
	{
		if (!$control instanceof self) {
			throw new Nette\InvalidStateException('Unable to validate ' . get_class($control) . ' instance.');
		}

		$value = $control->value;

		return (empty($control->rawValue) || $value instanceof Utils\DateTime);
	}

	/**
	 * @return Bridges\ApplicationLatte\Template
	 */
	protected function createTemplate()
	{
		// Create latte engine
		$latte = new Latte\Engine;

		// Check for cache dir for latte files
		if (defined('TEMP_DIR') && ($cacheFolder = TEMP_DIR . DIRECTORY_SEPARATOR .'cache'. DIRECTORY_SEPARATOR .'latte' AND is_dir($cacheFolder))) {
			$latte->setTempDirectory($cacheFolder);
		}

		$latte->onCompile[] = function($latte) {
			// Register form macros
			Bridges\FormsLatte\FormMacros::install($latte->getCompiler());
		};

		// Create nette template from latte engine
		$template = new Bridges\ApplicationLatte\Template($latte);

		// Check if translator is available
		if ($this->getTranslator() instanceof Localization\ITranslator) {
			$template->setTranslator($this->getTranslator());
		}

		return $template;
	}

	/**
	 * @return UI\ITemplate|Bridges\ApplicationLatte\Template
	 */
	public function getTemplate()
	{
		if ($this->template === NULL) {
			$this->template = $this->createTemplate();
		}

		return $this->template;
	}

	/**
	 * Change default control template path
	 *
	 * @param string $templatePath
	 *
	 * @return $this
	 *
	 * @throws \Nette\FileNotFoundException
	 */
	public function setTemplateFile($templatePath)
	{
		// Check if template file exists...
		if (!is_file($templatePath)) {
			// ...check if extension template is used
			if (is_file(__DIR__ . DIRECTORY_SEPARATOR .'template'. DIRECTORY_SEPARATOR . $templatePath)) {
				$templatePath = __DIR__ . DIRECTORY_SEPARATOR .'template'. DIRECTORY_SEPARATOR . $templatePath;

			} else {
				// ...if not throw exception
				throw new Nette\FileNotFoundException('Template file "'. $templatePath .'" was not found.');
			}
		}

		$this->templatePath = $templatePath;

		return $this;
	}

	/**
 	 * @return string
 	 */
	public function getLanguage()
	{
		return $this->language;
	}

	/**
	 * @param string $language
	 * @return self
	 */
	public function setLanguage($language)
	{
		$this->language = $language;
		return $this;
	}


	/**
	 * Explode string to array using delimiter the same way as php explode do but includes delimiter in array
	 *
	 * @param string $del
	 * @param string $str
	 *
	 * @return array
	 */
	protected function strToArray($del, $str)
	{
		$a = array();

		foreach (explode($del[1], $str) as $t) {
			$a[] = $t;
			$a[] = $del[0];
		}

		array_pop($a);

		return $a;
	}

	/**
	 * String replace function
	 * Works similar to php str_replace(array, array, string) but stops replacing when pattern found
	 *
	 * @param string $a1
	 * @param string $a2
	 * @param string $str
	 *
	 * @return string
	 */
	protected function strReplace($a1, $a2, $str)
	{
		$tokens = (array)$str;

		foreach ($a1 as $del_i => $p) {
			foreach ($tokens as $tok_i => $token) {
				if (is_string($token)) {
					if (count($new=$this->strToArray(array($del_i, $p), $token)) > 1) {
						array_splice ($tokens, $tok_i, 1, $new);
						break;
					}
				}
			}
		}

		foreach ($tokens as &$token) {
			if (is_int($token) ) $token = $a2[$token];
		}

		return implode('', $tokens);
	}
}