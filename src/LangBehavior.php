<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * LangBehavior class
 *
 * @property ActiveQuery $langsRef
 * @property ActiveQuery $langsRefCurrent
 * @property LangInterface $lang
 */
class LangBehavior extends Behavior
{
	public $referenceModelClass; // класс промежуточной таблицы
	public $referenceModelAttribute; // атрибут промежуточной таблицы, ссылающийся на первичный ключ исходной модели
	public $referenceLangModelAttribute = 'lang_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ модели языков
	public $langModelClass; // класс модели языков, например 'common\models\Lang'

	/* @var LangInterface[] $_langs */
	private $_langs = null;
	/* @var LangInterface $_lang */
	private $_lang = null;

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
	 * Связь один-ко-многим для переводов текущей модели
	 *
	 * @return ActiveQuery
	 */
	public function getLangsRef()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->referenceModelClass, [$this->referenceModelAttribute => 'id']);
	}

	/**
	 * Связь один-к-одному для текущего перевода текущей модели
	 *
	 * @return ActiveQuery
	 */
	public function getLangsRefCurrent()
	{
		/* @var LangInterface $langModel */
		$langModel = $this->langModelClass;
		$langId = $langModel::getCurrentLang()->id;
		return $this->getLangsRef()->where([$this->referenceLangModelAttribute => $langId]);
	}

	/**
	 * Возвращает данные текущего перевода текущей модели
	 *
	 * @return LangInterface
	 */
	public function getLang()
	{
		if ($this->_lang === null) {
			$this->_lang = $this->getLangsRefCurrent()->one();
		}
		return $this->_lang;
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Инициализирует и возвращает массив моделей переводов,
	 * дополняя его пустыми значениями для незаполнненных языков
	 *
	 * @return LangInterface[]
	 */
	public function initLangs()
	{
		if ($this->_langs === null) {
			/* @var LangInterface $langModel */
			$langModel = $this->langModelClass;
			/* @var LangInterface[] $all_langs */
			$all_langs = $langModel::find()->indexBy('id')->all();
			$this->_langs = $this->getLangsRef()->indexBy($this->referenceLangModelAttribute)->all();
			foreach (array_diff_key($all_langs, $this->_langs) as $model) {
				$this->_langs[$model->id] = new $this->referenceModelClass([$this->referenceLangModelAttribute => $model->id]);
			}
		}
		return $this->_langs;
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Удаляет все связанные с моделью переводы
	 */
	public function onBeforeDelete()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$ownerModel->unlinkAll('langsRef', true);
	}

	/**
	 * Сохраняет значения переводов текущей модели для
	 * всех языков
	 */
	public function onAfterSave()
	{
		$this->initLangs();
		Model::loadMultiple($this->_langs, Yii::$app->request->post());
		$pk = 'id';
		foreach ($this->_langs as $lang) {
			$lang->{$this->referenceModelAttribute} = $this->owner->$pk;
			$lang->save();
		}
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Возвращает список всех возможных значений поля $field референсной таблицы
	 * переводов на указанном языке. Если язык не указан - берется дефолтный перевод.
	 * Список проиндексирован по полю связи с рефересной таблицей
	 * ($referenceModelAttribute)
	 *
	 * Список пригоден для использования в формах для методов
	 * ActiveForm::dropDownList и ActiveForm::checkboxList
	 *
	 * Можно использовать, например, чтобы получить список всех имеющихся
	 * категорий магазина, названия которых будут переведены на указанный язык
	 * и проиндексированы по ID категории.
	 *
	 * @param string $field
	 * @param null|LangInterface $lang
	 * @return array
	 */
	public function getFieldItemsTranslatedList($field, $lang=null)
	{
		/* @var ActiveRecord $referenceModel */
		$referenceModel = $this->referenceModelClass;
		/* @var LangInterface $langModel */
		$langModel = $this->langModelClass;

		$items = $referenceModel::find()
			->leftJoin($langModel::tableName(), $langModel::tableName() . '.id = ' . $referenceModel::tableName() . '.' . $this->referenceLangModelAttribute)
			->select([$referenceModel::tableName() . '.' . $field, $referenceModel::tableName() . '.' . $this->referenceModelAttribute])
			->indexBy($this->referenceModelAttribute);
		if ($lang === null) {
			$items->where([$langModel::tableName() . '.is_default' => 1]);
		} else {
			$items->where([$langModel::tableName() . '.id' => $lang->getId()]);
		}

		return $items->column();
	}
}