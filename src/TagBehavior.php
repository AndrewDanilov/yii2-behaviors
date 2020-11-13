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

	private $_tagIds;

	/**
	 * Events list
	 * @return array
	 */
	public function events()
	{
		return [
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
	 * Заполняет модель данными по тегам
	 */
	public function onBeforeValidate()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		if (Yii::$app->request->isPost) {
			$form = Yii::$app->request->post($ownerModel->formName());
			if (!empty($form['tagIds'])) {
				$this->setTagIds($form['tagIds']);
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
		if ($this->_tagIds === null) {
			$this->_tagIds = $this->getTag()->select('id')->column();
		}
		return $this->_tagIds;
	}

	public function setTagIds($ids)
	{
		return $this->_tagIds = (array)$ids;
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