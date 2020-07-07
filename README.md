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