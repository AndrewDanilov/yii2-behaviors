<?php
namespace andrewdanilov\behaviors;

use yii\base\Behavior;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\widgets\ActiveForm;
use mihaildev\elfinder\InputFile;
use andrewdanilov\ckeditor\CKEditor;
use andrewdanilov\helpers\CKEditorHelper;
use andrewdanilov\InputImages\InputImages;

/**
 * ValueTypeBehavior
 *
 * @property string $typeName
 */
class ValueTypeBehavior extends Behavior
{
	public $typeAttribute = 'type';
	public $valueAttribute = 'value';

	const VALUE_TYPE_STRING = 'string';
	const VALUE_TYPE_INTEGER = 'integer';
	const VALUE_TYPE_BOOLEAN = 'boolean';
	const VALUE_TYPE_TEXT = 'text';
	const VALUE_TYPE_RICHTEXT = 'richtext';
	const VALUE_TYPE_FILE = 'file';
	const VALUE_TYPE_IMAGE = 'image';

	/**
	 * Events list
	 * @return array
	 */
	public function events()
	{
		return [
			ActiveRecord::EVENT_BEFORE_VALIDATE => 'onBeforeValidate',
		];
	}

	public function onBeforeValidate()
	{
		$this->prepareValue();
	}

	/**
	 * Список возможных типов значений
	 *
	 * @return array
	 */
	public static function getTypeList()
	{
		return [
			self::VALUE_TYPE_STRING => 'Строка',
			self::VALUE_TYPE_INTEGER => 'Целое число',
			self::VALUE_TYPE_BOOLEAN => 'Двоичное',
			self::VALUE_TYPE_TEXT => 'Текст',
			self::VALUE_TYPE_RICHTEXT => 'HTML',
			self::VALUE_TYPE_FILE => 'Файл',
			self::VALUE_TYPE_IMAGE => 'Изображение',
		];
	}

	/**
	 * Возвращает человекопонятное название типа
	 * текущего значения
	 *
	 * @return string
	 */
	public function getTypeName()
	{
		$types = ValueTypeBehavior::getTypeList();
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if (isset($types[$ownerModel->{$this->typeAttribute}])) {
			return $types[$ownerModel->{$this->typeAttribute}];
		}
		return $ownerModel->{$this->typeAttribute};
	}

	/**
	 * Приводит значение поля любого типа к выбранному в настройках поля.
	 *
	 * @param $value
	 * @return bool|int|string
	 */
	public function formatValue($value=null) {
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if ($value === null) {
			$value = $ownerModel->{$this->valueAttribute};
		}
		switch ($ownerModel->{$this->typeAttribute}) {
			case self::VALUE_TYPE_BOOLEAN:
				return (boolean)$value;
			case self::VALUE_TYPE_INTEGER:
				return (int)$value;
			case self::VALUE_TYPE_TEXT:
			case self::VALUE_TYPE_RICHTEXT:
				$value = preg_replace("/[\n\r]+/", "\n", (string)$value);
				return $value;
			default:
				$value = preg_replace("/[\n\r]+/", " ", (string)$value);
				return $value;
		}
	}

	/**
	 * Возвращает человекопонятное представление для вывода в админке.
	 *
	 * @param $value
	 * @param int $truncateWordsCount
	 * @return bool|int|string
	 */
	public function prettifyValue($value=null, $truncateWordsCount=0)
	{
		$value = $this->formatValue($value);
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		switch ($ownerModel->{$this->typeAttribute}) {
			case self::VALUE_TYPE_BOOLEAN:
				return $value ? 'Да' : 'Нет';
			case self::VALUE_TYPE_INTEGER:
				return $value;
			case self::VALUE_TYPE_TEXT:
				$value = Html::encode($value);
				if ($truncateWordsCount) {
					$value = StringHelper::truncateWords($value, $truncateWordsCount, '...');
				}
				return nl2br($value);
			default:
				$value = strip_tags($value);
				if ($truncateWordsCount) {
					$value = StringHelper::truncateWords($value, $truncateWordsCount, '...');
				}
				return $value;
		}
	}

	/**
	 * Возвращает html-код поля формы для атрибута текущего типа
	 *
	 * @param ActiveForm $form
	 * @param string $attribute
	 * @param string $label
	 * @return string
	 */
	public function formField($form, $attribute, $label)
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		switch ($ownerModel->{$this->typeAttribute}) {
			case self::VALUE_TYPE_RICHTEXT:
				return $form->field($ownerModel, $attribute)->widget(CKEditor::class, [
					'editorOptions' => CKEditorHelper::defaultOptions(),
				])->label($label);
			case self::VALUE_TYPE_TEXT:
				return $form->field($ownerModel, $attribute)
					->textarea(['rows' => 6])
					->label($label);
			case self::VALUE_TYPE_BOOLEAN:
				return $form->field($ownerModel, $attribute)
					->dropDownList(['0' => 'Нет', '1' => 'Да'])
					->label($label);
			case self::VALUE_TYPE_FILE:
				return $form->field($ownerModel, $attribute)->widget(InputFile::class, [
					'language'      => 'ru',
					'controller'    => 'elfinder', // вставляем название контроллера, по умолчанию равен elfinder
					'template'      => '<div class="input-group">{input}<span class="input-group-btn">{button}</span></div>',
					'options'       => ['class' => 'form-control'],
					'buttonOptions' => ['class' => 'btn btn-default'],
					'multiple'      => false,      // возможность выбора нескольких файлов
				])->label($label);
			case self::VALUE_TYPE_IMAGE:
				return $form->field($ownerModel, $attribute)
					->widget(InputImages::class)
					->label($label);
			default:
				return $form->field($ownerModel, $attribute)
					->textInput(['maxlength' => true])
					->label($label);
		}
	}

	/**
	 * Подготавливает значение параметра в соответствии с его типом,
	 * а также задает сценарии валидации модели
	 */
	public function prepareValue()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if ($ownerModel->{$this->typeAttribute} == self::VALUE_TYPE_BOOLEAN) {
			$ownerModel->{$this->valueAttribute} = (boolean)$ownerModel->{$this->valueAttribute};
			$ownerModel->setScenario(self::VALUE_TYPE_BOOLEAN);
		} elseif ($ownerModel->{$this->typeAttribute} == self::VALUE_TYPE_INTEGER) {
			$ownerModel->{$this->valueAttribute} = (int)$ownerModel->{$this->valueAttribute};
			$ownerModel->setScenario(self::VALUE_TYPE_INTEGER);
		} else {
			$ownerModel->{$this->valueAttribute} = (string)$ownerModel->{$this->valueAttribute};
			$ownerModel->setScenario(Model::SCENARIO_DEFAULT);
		}
	}
}