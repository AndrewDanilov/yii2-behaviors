<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ShopOptionBehavior class
 *
 * @property string $formName
 * @property ActiveQuery $optionsRef
 * @property ActiveQuery $options
 */
class ShopOptionBehavior extends \yii\base\Behavior
{
	public $referenceModelClass; // класс промежуточной таблицы
	public $referenceModelAttribute = 'product_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ исходной модели
	public $referenceModelOptionAttribute = 'option_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ модели опций
	public $optionModelClass; // класс модели опций, например, 'common\models\ShopOption'
	public $optionModelOrderAttribute = 'id'; // атрибут модели опций, по которому выполняется сортировка
	public $optionModelOrder = SORT_ASC; // направление сортировки SORT_ASC|SORT_DESC
	public $optionsFilter = []; // ID опций, доступных для исходной модели
	public $createDefaultValues = false; // при инициализации создает по одному пустому значению для каждой опции, если опция не имеет значений

	/* @var ActiveRecord[] $_options */
	private $_options = null;

	/**
	 * Events list
	 * @return array
	 */
	public function events()
	{
		return [
			ActiveRecord::EVENT_AFTER_INSERT => 'onAfterSave',
			ActiveRecord::EVENT_AFTER_UPDATE => 'onAfterSave',
			ActiveRecord::EVENT_BEFORE_DELETE => 'onBeforeDelete',
		];
	}

	/**
	 * Связь один-ко-многим для промежуточной таблицы опций текущей модели
	 *
	 * @return ActiveQuery
	 */
	public function getOptionsRef()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		/* @var ActiveRecord $ownerModel */
		$referenceModel = $this->referenceModelClass;
		/* @var ActiveRecord $optionModel */
		$optionModel = $this->optionModelClass;
		$optionsRefs = $ownerModel->hasMany($this->referenceModelClass, [$this->referenceModelAttribute => 'id']);
		if (!empty($this->optionsFilter)) {
			$optionsRefs->where([$referenceModel::tableName() . '.' . $this->referenceModelOptionAttribute => $this->optionsFilter]);
		}
		$optionsRefs->innerJoin($optionModel::tableName(), $optionModel::tableName() . '.id = ' . $referenceModel::tableName() . '.' . $this->referenceModelOptionAttribute);
		$optionsRefs->orderBy([$optionModel::tableName() . '.' . $this->optionModelOrderAttribute => $this->optionModelOrder]);
		return $optionsRefs;
	}

	/**
	 * Связь один-ко-многим для таблицы опций текущей модели
	 *
	 * @return ActiveQuery
	 */
	public function getOptions()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		/* @var ActiveRecord $optionModel */
		$optionModel = $this->optionModelClass;
		/* @var ActiveRecord $referenceModel */
		$referenceModel = $this->referenceModelClass;
		// We can not use `via()` method here because it refers to links stored in owner model,
		// and in case if we apply several tag behaviors to owner model, only first will have correct link,
		// so we use `viaTable()` method here
		$options = $ownerModel->hasMany($optionModel, ['id' => $this->referenceModelOptionAttribute])->viaTable($referenceModel::tableName(), [$this->referenceModelAttribute => 'id']);
		if (!empty($this->optionsFilter)) {
			$options->where([$optionModel::tableName() . '.id' => $this->optionsFilter]);
		}
		$options->orderBy([$optionModel::tableName() . '.' . $this->optionModelOrderAttribute => $this->optionModelOrder]);
		return $options;
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Инициализирует и возвращает массив, содержащий модели опций
	 * и массивы элементов моделей референсной таблицы опций,
	 * сгруппированные по ID опций:
	 * options = [
	 *   '1' => [
	 *     'option' => \ShopOption,
	 *     'items' => [
	 *       \ShopProductOptions,
	 *       \ShopProductOptions,
	 *       ...
	 *     ]
	 *   ],
	 *   ...
	 * ]
	 *
	 * @return array
	 */
	public function initOptions()
	{
		if ($this->_options === null) {
			/* @var ActiveRecord $optionModel */
			$optionModel = $this->optionModelClass;
			$options = $optionModel::find()->indexBy('id');
			$options->orderBy([$optionModel::tableName() . '.' . $this->optionModelOrderAttribute => $this->optionModelOrder]);
			if (!empty($this->optionsFilter)) {
				$options->where([$optionModel::tableName() . '.id' => $this->optionsFilter]);
			}
			// первичный массив опций
			$this->_options = [];
			foreach ($options->all() as $option) {
				$this->_options[$option->id] = [
					'option' => $option,
					'items' => [],
				];
			}
			// заполним данными из базы
			$optionsRefs = $this->getOptionsRef();
			foreach ($optionsRefs->all() as $optionRef) {
				$this->_options[$optionRef->{$this->referenceModelOptionAttribute}]['items'][] = $optionRef;
			}
			// заполним пустыми значениями, если нужно
			if ($this->createDefaultValues) {
				foreach ($this->_options as $id => $option) {
					if (empty($this->_options[$id]['items'])) {
						$o = new $this->referenceModelClass;
						$o->{$this->referenceModelOptionAttribute} = $id;
						$this->_options[$id]['items'][] = $o;
					}
				}
			}
		}
		return $this->_options;
	}

	/**
	 * Возвращает имя формы переданной модели,
	 * либо имя формы исходной модели, если
	 * $model=null
	 *
	 * @param string|Model $model
	 * @return string
	 */
	public function getFormName($model=null)
	{
		if ($model === null) {
			$model = $this->owner;
		} elseif (is_string($model)) {
			$model = new $model;
		}
		if ($model instanceof Model) {
			return $model->formName();
		}
		return '';
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Удаляет все связанные с моделью опции
	 */
	public function onBeforeDelete()
	{
		$this->deleteOptions();
	}

	/**
	 * Сохраняет значения опций текущей модели
	 */
	public function onAfterSave()
	{
		$formName = $this->getFormName($this->referenceModelClass);
		$data = Yii::$app->request->post($formName, []);
		// удаляем все прежние связи с опциями
		$this->deleteOptions();
		// формируем новые связи
		$pk = 'id';
		foreach ($data as $option_id => $optionsRefs) {
			foreach ($optionsRefs as $optionsRef) {
				/* @var ActiveRecord $model */
				$model = new $this->referenceModelClass([$this->referenceModelOptionAttribute => $option_id]);
				$model->load($optionsRef, '');
				$model->{$this->referenceModelAttribute} = $this->owner->$pk;
				// перед сохранением подменим данные формы, чтобы вложенные
				// в модель поведения смогли их подхватить как свои
				$_POST[$formName] = $optionsRef;
				Yii::$app->request->setBodyParams($_POST);
				$model->save();
			}
		}
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Удаляет все связи исходной модели с опциями
	 */
	public function deleteOptions()
	{
		foreach ($this->getOptionsRef()->all() as $optionsRef) {
			$optionsRef->delete();
		}
	}
}