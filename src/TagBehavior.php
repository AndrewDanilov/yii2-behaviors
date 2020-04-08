<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\base\Behavior;

/**
 * TagBehavior class
 *
 * @property ActiveQuery $tags
 * @property ActiveQuery $tagsRef
 * @property array $tagIds
 */
class TagBehavior extends Behavior
{
	public $referenceModelClass; // класс промежуточной таблицы
	public $referenceModelAttribute; // атрибут промежуточной таблицы, ссылающийся на первичный ключ исходной модели
	public $referenceTagModelAttribute = 'tag_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ модели тегов
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
	public function getTagsRef()
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
	public function getTags()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->tagModelClass, ['id' => $this->referenceTagModelAttribute])->via('tagsRef');
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Заполняет модель данными по тегам
	 */
	public function onBeforeValidate()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$form = Yii::$app->request->post($ownerModel->formName());
		$this->setTagIds($form['tagIds']);
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
		$ownerModel->unlinkAll('tags', true);
	}

	//////////////////////////////////////////////////////////////////

	public function getTagIds()
	{
		if ($this->_tagIds === null) {
			$this->_tagIds = $this->getTags()->select('id')->column();
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
		/* @var ActiveRecord $tagModel */
		$tagModel = $this->tagModelClass;
		// теги до изменения
		$oldTagIds = $this->getTags()->select('id')->column();
		// теги после изменения
		$tagIds = $this->getTagIds();
		// добавляем новые
		foreach (array_filter(array_diff($tagIds, $oldTagIds)) as $tagId) {
			if ($model = $tagModel::findOne($tagId)) {
				$ownerModel->link('tags', $model);
			}
		}
		// удаляем старые
		foreach (array_filter(array_diff($oldTagIds, $tagIds)) as $tagId) {
			if ($model = $tagModel::findOne($tagId)) {
				$ownerModel->unlink('tags', $model, true);
			}
		}
	}
}