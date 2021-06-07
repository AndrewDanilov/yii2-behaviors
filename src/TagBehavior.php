<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\base\Behavior;

/**
 * TagBehavior class
 *
 * @property ActiveQuery $tag
 * @property ActiveQuery $tagRef
 * @property array $tagIds
 */
class TagBehavior extends Behavior
{
	public $referenceModelClass; // класс промежуточной таблицы, например, 'common\models\TagRef'
	public $referenceModelAttribute; // атрибут промежуточной таблицы, ссылающийся на первичный ключ исходной модели, например, 'article_id'
	public $referenceModelTagAttribute; // атрибут промежуточной таблицы, ссылающийся на первичный ключ модели тегов, например, 'tag_id'
	public $tagModelClass; // класс модели тегов, например, 'common\models\Tag'

	/**
	 * $ownerModelIdsAttribute - Атрибут исходной модели для сохранения массива ID тегов,
	 * которые связаны с этой моделью. Этот атрибут можно использовать в качестве поля
	 * формы ActiveForm, хранящего список связаных значений. Указанный атрибут в исходной
	 * модели должен существовать и быть публичным свойством.
	 *
	 * Если $ownerModelIdsAttribute не задан - то для хранения списка будет использоваться
	 * приватное свойство поведения $this->_tagIds, читать/изменять список можно будет
	 * с помощью магического свойства $model->tagIds, а в качестве поля формы можно
	 * будет указывать 'tagIds'.
	 *
	 * Если $ownerModelIdsAttribute не задан и к модели привязано несколько поведений TagBehavior,
	 * то для получения/изменения списка ID тегов, можно воспользоваться доступом к поведению:
	 * $model->getBehavior('behavior_name')->getTagIds();
	 * $model->getBehavior('behavior_name')->setTagIds([...]);
	 */
	public $ownerModelIdsAttribute;

	private $_tagIds;

	/**
	 * Events list
	 * @return array
	 */
	public function events()
	{
		return [
			ActiveRecord::EVENT_AFTER_FIND => 'onAfterFind',
			ActiveRecord::EVENT_BEFORE_VALIDATE => 'onBeforeValidate',
			ActiveRecord::EVENT_AFTER_INSERT => 'onAfterSave',
			ActiveRecord::EVENT_AFTER_UPDATE => 'onAfterSave',
			ActiveRecord::EVENT_BEFORE_DELETE => 'onBeforeDelete',
		];
	}

	/**
	 * Связь с таблицей связей объекта и тегов
	 *
	 * @return ActiveQuery
	 */
	public function getTagRef()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->referenceModelClass, [$this->referenceModelAttribute => 'id']);
	}

	/**
	 * Связь с тегами объекта
	 *
	 * @return ActiveQuery
	 */
	public function getTag()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		/* @var ActiveRecord $referenceModel */
		$referenceModel = $this->referenceModelClass;
		// We can not use `via()` method here because it refers to links stored in owner model,
		// and in case if we apply several tag behaviors to owner model, only first will have correct link,
		// so we use `viaTable()` method here
		return $ownerModel->hasMany($this->tagModelClass, ['id' => $this->referenceModelTagAttribute])->viaTable($referenceModel::tableName(), [$this->referenceModelAttribute => 'id']);
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Заполняет исходную модель данными по тегам после ее выборки из базы
	 */
	public function onAfterFind()
	{
		if ($this->ownerModelIdsAttribute !== null) {
			/* @var ActiveRecord $ownerModel */
			$ownerModel = $this->owner;
			$ownerModel->{$this->ownerModelIdsAttribute} = $this->getTag()->select('id')->column();
		}
	}

	/**
	 * Заполняет модель данными по тегам перед ее валидацией
	 */
	public function onBeforeValidate()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if (Yii::$app->request->isPost) {
			$form = Yii::$app->request->post($ownerModel->formName());
			if ($this->ownerModelIdsAttribute !== null) {
				$idsAttribute = $this->ownerModelIdsAttribute;
			} else {
				$idsAttribute = 'tagIds';
			}
			if (!empty($form[$idsAttribute])) {
				$this->setTagIds($form[$idsAttribute]);
			}
		}
	}

	/**
	 * Обновляет связи тегов после сохранения объекта
	 */
	public function onAfterSave()
	{
		$this->updateTagIds();
	}

	/**
	 * Удаляет связи объекта с тегами
	 */
	public function onBeforeDelete()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$ownerModel->unlinkAll('tag', true);
	}

	//////////////////////////////////////////////////////////////////

	public function getTagIds()
	{
		if ($this->ownerModelIdsAttribute !== null) {
			/* @var ActiveRecord $ownerModel */
			$ownerModel = $this->owner;
			if ($ownerModel->{$this->ownerModelIdsAttribute} === null) {
				$ownerModel->{$this->ownerModelIdsAttribute} = $this->getTag()->select('id')->column();
			}
			return $ownerModel->{$this->ownerModelIdsAttribute};
		} else {
			if ($this->_tagIds === null) {
				$this->_tagIds = $this->getTag()->select('id')->column();
			}
			return $this->_tagIds;
		}
	}

	public function setTagIds($ids)
	{
		if ($this->ownerModelIdsAttribute !== null) {
			/* @var ActiveRecord $ownerModel */
			$ownerModel = $this->owner;
			$ownerModel->{$this->ownerModelIdsAttribute} = (array)$ids;
		} else {
			$this->_tagIds = (array)$ids;
		}
	}

	public function updateTagIds() {
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		/* @var ActiveRecord $referenceModel */
		$referenceModel = $this->referenceModelClass;
		// теги до изменения
		$oldTagIds = $this->getTag()->select('id')->column();
		// теги после изменения
		$tagIds = $this->getTagIds();
		// добавляем новые
		foreach (array_filter(array_diff($tagIds, $oldTagIds)) as $tagId) {
			if ($model = new $referenceModel()) {
				$model->{$this->referenceModelAttribute} = $ownerModel->id;
				$model->{$this->referenceModelTagAttribute} = $tagId;
				$model->save();
			}
		}
		// удаляем старые
		foreach (array_filter(array_diff($oldTagIds, $tagIds)) as $tagId) {
			if ($model = $referenceModel::find()->where([
				$this->referenceModelAttribute => $ownerModel->id,
				$this->referenceModelTagAttribute => $tagId
			])->one()) {
				$model->delete();
			}
		}
	}
}