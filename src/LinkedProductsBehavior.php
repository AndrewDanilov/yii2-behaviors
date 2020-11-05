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
	public $productModelClass; // класс модели товаров представляющей как исходный товар, так и прилинкованные товары, например, 'common\models\ShopProduct'
	public $referenceModelClass; // класс промежуточной таблицы, связывающей товары с товарами, например, 'common\models\ShopProductRelations'
	public $referenceModelAttribute = 'product_id'; // атрибут промежуточной таблицы (referenceModelClass), ссылающийся на первичный ключ модели исходных товаров
	public $referenceModelLinkedAttribute = 'linked_product_id'; // атрибут промежуточной таблицы (referenceModelClass), ссылающийся на первичный ключ модели прилинкованных товаров
	public $referenceModelLinksAttribute = 'relation_id'; // атрибут промежуточной таблицы (referenceModelClass), ссылающийся на первичный ключ модели списка возможных связей (linksModelClass)
	public $linksModelClass; // класс модели списка возможных связей, например, 'common\models\ShopRelation'

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
		return $ownerModel->hasMany($this->productModelClass, ['id' => $this->referenceModelLinkedAttribute])->via('productRelations');
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
		if (isset($form['linkedProducts'])) {
			$this->setLinkedProducts($form['linkedProducts']);
		}
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
				$this->_linkedProducts[$productRelation->{$this->referenceModelLinksAttribute}]['product_ids'][] = $productRelation->{$this->referenceModelLinkedAttribute};
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
				$model->{$this->referenceModelLinkedAttribute} = $product_id;
				$model->{$this->referenceModelLinksAttribute} = $link_id;
				$ownerModel->link('productRelations', $model);
			}
		}
	}
}