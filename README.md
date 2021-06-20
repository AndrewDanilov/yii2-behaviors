Yii2 Behaviors
===================
Various behaviors for AR models

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require andrewdanilov/yii2-behaviors "~1.0.0"
```

or add

```
"andrewdanilov/yii2-behaviors": "~1.0.0"
```

to the `require` section of your `composer.json` file.


Usage
=====

DateBehavior
-----

At your controller class add to `behaviors()` method:

```php
use yii\db\ActiveRecord;
use andrewdanilov\behaviors\DateBehavior;

class MyController extends ActiveRecord
{
	public function behaviors()
	{
		return [
			// ...
			[
				'class' => DateBehavior::class,
				// AR model attributes to process by behavior
				'dateAttributes' => [
					// DateTime format
					'date_1' => DateBehavior::DATETIME_FORMAT,
					// DateTime format with current datetime as default value if param is empty
					'date_2' => DateBehavior::DATETIME_FORMAT_AUTO,
					// Date format without time
					'date_3' => DateBehavior::DATE_FORMAT,
					// Date format without time with current datetime as default value if param is empty
					'date_4' => DateBehavior::DATE_FORMAT_AUTO,
					// Short notation equal to: 'date_5' => DateBehavior::DATE_FORMAT
					'date_5',
				],
			],
			// ...
		];
	}
}
```

DateBehavior converts date into mysql format before it will be saved to database (`onBeforeSave` event)
and into display format after it is fetched from database (`onAfterFind` event).

You can define display format by modifiyng `Yii::$app->formatter` component in your config:

```php
$config = [
	// ...
	'components' => [
		// ...
		'formatter' => [
			'defaultTimeZone' => 'Europe/Moscow',
			'dateFormat'     => 'php:d.m.Y',
			'datetimeFormat' => 'php:d.m.Y H:i:s',
			'timeFormat'     => 'php:H:i:s',
		],
	],
];
```

If you have problems with time shifting, set `defaultTimeZone` property of formatter.


TagBehavior
-----

Use this behavior to link two models with many-to-many relation via staging table. This behavior will take care of saving new links to staging table and removing obsolete ones.

Model `Product.php`

```php
<?php
namespace common\models;

/**
 * Class Product
 *
 * @property int $id
 * ...
 * @property int[] $category_ids
 */
class Product extends \yii\db\ActiveRecord
{
	public $category_ids;

	public function behaviors()
	{
		return [
			'category' => [ // name of behavior
				'class' => 'andrewdanilov\behaviors\TagBehavior',
				'referenceModelClass' => 'common\models\ProductCategory',
				'referenceModelAttribute' => 'product_id',
				'referenceModelTagAttribute' => 'category_id',
				'tagModelClass' => 'common\models\Category',
				'ownerModelIdsAttribute' => 'category_ids',
			]
		];
	}
	
	/**
     * Getter for retrieving child models (tags) list.
     * It can be used therefore as property $product->categories
     * or link named 'categories', i.e. $product->with('categories')
     * 
	 * @return \yii\db\ActiveQuery
	 */
	public function getCategories()
	{
		$behavior = $this->getBehavior('category'); // use name of behavior here ('category')
		if ($behavior instanceof \andrewdanilov\behaviors\TagBehavior) {
			return $behavior->getTags();
		}
		return null;
	}
	
	// ...
}
```

Model `Category.php`

```php
<?php
namespace common\models;

use yii\db\ActiveRecord;

/**
 * Class Category
 *
 * @property int $id
 * @property string $name
 * ...
 */
class Category extends ActiveRecord
{
	// ...
}
```


Model `ProductCategory.php`

```php
<?php
namespace common\models;

use yii\db\ActiveRecord;

/**
 * Class ProductCategory
 *
 * @property int $id
 * @property int $product_id
 * @property int $category_id
 */
class ProductCategory extends ActiveRecord
{
	// ...
}
```


View `product/update.php`

```php
<?php

use andrewdanilov\behaviors\TagBehavior;
use common\models\Category;
use common\models\Product;
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $form yii\widgets\ActiveForm */
/* @var $model Product|TagBehavior */
?>

<?php $form = ActiveForm::begin(); ?>

...

<?= $form->field($model, 'category_ids')->checkboxList(Category::find()->select(['name', 'id'])->indexBy('id')->column(), ['prompt' => '']) ?>

...

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
</div>

<?php ActiveForm::end(); ?>
```

TagBehavior can be used several times in one model, so you can add at the same time categories, tags and linked products to your `Product` model
