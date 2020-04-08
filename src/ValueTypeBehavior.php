<?php

namespace andrewdanilov\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\widgets\ActiveForm;
use mihaildev\elfinder\InputFile;
use sadovojav\ckeditor\CKEditor;
use andrewdanilov\helpers\CKEditorHelper;

/**
 * ValueTypeBehavior
 *
 * @property array $typeList
 * @property string $typeName
 */
class ValueTypeBehavior extends Behavior
{
	public $typeAttribute = 'type';

	const VALUE_TYPE_STRING = 'string';
	const VALUE_TYPE_INTEGER = 'integer';
	const VALUE_TYPE_BOOLEAN = 'boolean';
	const VALUE_TYPE_TEXT = 'text';
	const VALUE_TYPE_RICHTEXT = 'richtext';
	const VALUE_TYPE_FILE = 'file';
	const VALUE_TYPE_IMAGE = 'image';

	/**
	 * Список возможных типов значений
	 *
	 * @return array
	 */
	public function getTypeList()
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
		$types = $this->getTypeList();
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
	public function formatValue($value) {
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
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
	public function prettifyValue($value, $truncateWordsCount=0)
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
	 * @param ActiveRecord $model
	 * @param string $valueAttribute
	 * @return string
	 */
	public function formField($form, $attribute, $label, $model=null, $valueAttribute='value')
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if ($model === null) {
			$model = $ownerModel;
		}
		switch ($ownerModel->{$this->typeAttribute}) {
			case self::VALUE_TYPE_RICHTEXT:
				return $form->field($model, $attribute)->widget(CKEditor::class, [
					'editorOptions' => CKEditorHelper::defaultOptions(),
				])->label($label);
			case self::VALUE_TYPE_TEXT:
				return $form->field($model, $attribute)
					->textarea(['rows' => 6])
					->label($label);
			case self::VALUE_TYPE_BOOLEAN:
				return $form->field($model, $attribute)
					->dropDownList(['0' => 'Нет', '1' => 'Да'])
					->label($label);
			case self::VALUE_TYPE_FILE:
				return $form->field($model, $attribute)->widget(InputFile::class, [
					'language'      => 'ru',
					'controller'    => 'elfinder', // вставляем название контроллера, по умолчанию равен elfinder
					'template'      => '<div class="input-group">{input}<span class="input-group-btn">{button}</span></div>',
					'options'       => ['class' => 'form-control'],
					'buttonOptions' => ['class' => 'btn btn-default'],
					'multiple'      => false,      // возможность выбора нескольких файлов
				])->label($label);
			case self::VALUE_TYPE_IMAGE:
				$field_id = $form->id . '-' . $model->formName() . '-' . preg_replace("/[\[\]]/", '-', $attribute);
				$form->view->registerJs("$('#" . $field_id . "').on('change', function() { $(this).parents('.form-group').find('img.preview').attr('src', $(this).val()); });");
				return $form->field($model, $attribute)->widget(InputFile::class, [
					'language'      => 'ru',
					'controller'    => 'elfinder', // вставляем название контроллера, по умолчанию равен elfinder
					'filter'        => 'image',    // фильтр файлов, можно задать массив фильтров https://github.com/Studio-42/elFinder/wiki/Client-configuration-options#wiki-onlyMimes
					'template'      => '<div>' . Html::img($model->{$valueAttribute}, ['width' => '300', 'class' => 'preview']) . '</div><div class="input-group">{input}<span class="input-group-btn">{button}</span></div>',
					'options'       => ['class' => 'form-control', 'id' => $field_id],
					'buttonOptions' => ['class' => 'btn btn-default'],
					'multiple'      => false,      // возможность выбора нескольких файлов
				])->label($label);
			default:
				return $form->field($model, $attribute)
					->textInput(['maxlength' => true])
					->label($label);
		}
	}
}