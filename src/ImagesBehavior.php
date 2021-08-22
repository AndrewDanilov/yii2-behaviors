<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * ImagesBehavior
 *
 * @property string $image
 * @property array $images
 * @property mixed $imagesRef
 */
class ImagesBehavior extends Behavior
{
	public $imagesModelClass; // класс таблицы с изображениями
	public $imagesModelRefAttribute; // атрибут таблицы с изображениями, ссылающийся на первичный ключ исходной модели
	public $imagesModelImageAttribute = 'image';
	public $imagesModelOrderAttribute = 'order';

	/* @var string[] $_images */
	private $_images = null;

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

	public function getImagesRef()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->imagesModelClass, [$this->imagesModelRefAttribute => 'id']);
	}

	public function getImages()
	{
		if ($this->_images === null) {
			$this->_images = $this->getImagesRef()->select(['image'])->orderBy('order')->column();
		}
		return $this->_images;
	}

	public function setImages($images)
	{
		$this->_images = array_filter((array)$images);
	}

	/**
	 * Возвращает главное изображение
	 *
	 * @return string
	 */
	public function getImage()
	{
		$images = $this->getImages();
		if ($images && count($images)) {
			return reset($images);
		}
		return '';
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Сохраняет изображения для текущей модели
	 */
	public function onAfterSave()
	{
		/* @var ActiveRecord $imagesModel */
		$imagesModel = $this->imagesModelClass;
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;

		$form = Yii::$app->request->post($ownerModel->formName());
		if (!empty($form)) {
			// удаляем старые изображения
			$ownerModel->unlinkAll('imagesRef', true);

			if (!empty($form['images'])) {
				// добавляем новые изображения
				$n = 1;
				foreach ($form['images'] as $image) {
					/* @var $model ActiveRecord */
					$model = new $imagesModel();
					$model->{$this->imagesModelRefAttribute} = $ownerModel->id;
					$model->{$this->imagesModelImageAttribute} = $image;
					$model->{$this->imagesModelOrderAttribute} = $n++;
					$model->save();
				}
			}
		}
	}

	/**
	 * Удаляет все связанные с исходной моделью изображения
	 */
	public function onBeforeDelete()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$ownerModel->unlinkAll('imagesRef', true);
	}
}