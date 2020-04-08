<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\base\Behavior;

/**
 * LinkedProductsBehavior class
 *
 * @property ActiveQuery $productRelations
 * @property ActiveQuery $products
 * @property array $linkedProducts
 */
class LinkedProductsBehavior extends Behavior
{
	public $referenceModelClass; // класс промежуточной таблицы, например, 'common\models\ShopProductRelations'
	public $referenceModelAttribute = 'product_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ исходной модели
	public $referenceLinkedModelAttribute = 'linked_product_id'; // атрибут промежуточной таблицы, ссылающийся на первичный ключ модели модели прилинкованных товаров
	public $linkedModelClass; // класс модели представляющей прилинкованные товары, например, 'common\models\ShopProduct'
	public $linksModelClass; // класс модели списка возможных связей, например, 'common\models\ShopRelation'
	public $linksModelAttribute = 'relation_id'; // атрибут ссылающийся на первичный ключ модели списка возможных связей

	private $_linkedProducts = null;

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

	public function getProductRelations()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->referenceModelClass, [$this->referenceModelAttribute => 'id']);
	}

	public function getProducts()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		return $ownerModel->hasMany($this->linkedModelClass, ['id' => $this->referenceLinkedModelAttribute])->via('productRelations');
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Заполняет модель данными перед сохранением
	 */
	public function onBeforeValidate()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$form = Yii::$app->request->post($ownerModel->formName());
		$this->setLinkedProducts($form['linkedProducts']);
	}

	/**
	 * Обновляет связи после сохранения объекта
	 */
	public function onAfterSave()
	{
		$this->updateLinkedProducts();
	}

	/**
	 * Удаляет связи
	 */
	public function onBeforeDelete()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		$ownerModel->unlinkAll('products', true);
	}

	//////////////////////////////////////////////////////////////////

	public function initLinkedProducts()
	{
		if ($this->_linkedProducts == null) {
			/* @var $linksModel ActiveRecord */
			$linksModel = $this->linksModelClass;
			$links = $linksModel::find()->all();
			$this->_linkedProducts = [];
			foreach ($links as $link) {
				$this->_linkedProducts[$link->id] = [
					'name' => $link->name,
					'product_ids' => [],
				];
			}
			$productRelations = $this->getProductRelations()->with(['linkedProduct'])->all();
			foreach ($productRelations as $productRelation) {
				$this->_linkedProducts[$productRelation->{$this->linksModelAttribute}]['product_ids'][] = $productRelation->{$this->referenceLinkedModelAttribute};
			}
		}
		return $this->_linkedProducts;
	}

	public function getLinkedProducts()
	{
		$linkedProducts = [];
		foreach ($this->initLinkedProducts() as $link_id => $link) {
			foreach ($link['product_ids'] as $product_id) {
				$linkedProducts[$link_id][] = $product_id;
			}
		}
		return $linkedProducts;
	}

	public function setLinkedProducts($linkedProducts)
	{
		$this->initLinkedProducts();
		if (is_array($linkedProducts)) {
			foreach ($linkedProducts as $link_id => $link_items) {
				$this->_linkedProducts[$link_id]['product_ids'] = [];
				if (is_array($link_items)) {
					foreach ($link_items as $product_id) {
						$this->_linkedProducts[$link_id]['product_ids'][] = $product_id;
					}
				}
			}
		}
	}

	public function updateLinkedProducts()
	{
		/* @var ActiveRecord $ownerModel */
		$ownerModel = $this->owner;
		/* @var ActiveRecord $referenceModel */
		$referenceModel = $this->referenceModelClass;
		// удаляем старые связи
		$ownerModel->unlinkAll('products', true);
		// создаем новые связи
		$linkedProducts = $this->getLinkedProducts();
		foreach ($linkedProducts as $link_id => $product_ids) {
			foreach ($product_ids as $product_id) {
				$model = new $referenceModel();
				$model->{$this->referenceLinkedModelAttribute} = $product_id;
				$model->{$this->linksModelAttribute} = $link_id;
				$ownerModel->link('productRelations', $model);
			}
		}
	}
}