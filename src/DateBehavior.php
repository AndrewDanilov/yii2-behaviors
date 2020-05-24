<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\db\ActiveRecord;

/**
 * DateBehavior class
 */
class DateBehavior extends \yii\base\Behavior
{
	public $dateAttributes = [];

	const DATE_FORMAT = 1;
	const DATETIME_FORMAT = 2;
	const DATE_FORMAT_AUTO = 10;
	const DATETIME_FORMAT_AUTO = 20;

	/**
	 * Events list
	 * @return array
	 */
	public function events()
	{
		return [
			ActiveRecord::EVENT_AFTER_FIND => 'onAfterFind',
			ActiveRecord::EVENT_BEFORE_INSERT => 'onBeforeSave',
			ActiveRecord::EVENT_BEFORE_UPDATE => 'onBeforeSave',
		];
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * После выборки данных из базы преобразует все даты к формату для отображения
	 */
	public function onAfterFind()
	{
		if (is_array($this->dateAttributes)) {
			foreach ($this->dateAttributes as $key => $attribute) {
				if (is_string($key)) {
					$format = $attribute;
					$attribute = $key;
				} else {
					$format = self::DATE_FORMAT;
				}
				$use_time = ($format == self::DATETIME_FORMAT || $format == self::DATETIME_FORMAT_AUTO);
				$this->owner->{$attribute} = $this->getDisplayDate($attribute, $use_time);
			}
		}
	}

	/**
	 * Перед сохранением данных в базу преобразует все даты к формату БД
	 */
	public function onBeforeSave()
	{
		if (is_array($this->dateAttributes)) {
			foreach ($this->dateAttributes as $key => $attribute) {
				if (is_string($key)) {
					$format = $attribute;
					$attribute = $key;
				} else {
					$format = self::DATE_FORMAT;
				}
				$use_time = ($format == self::DATETIME_FORMAT || $format == self::DATETIME_FORMAT_AUTO);
				$default = ($format == self::DATE_FORMAT_AUTO || $format == self::DATETIME_FORMAT_AUTO);
				$this->owner->{$attribute} = $this->getISODate($attribute, $use_time, $default);
			}
		}
	}

	//////////////////////////////////////////////////////////////////

	/**
	 * Возвращает дату в формате БД
	 *
	 * @param $attribute
	 * @param bool $use_time
	 * @param bool $default
	 * @return false|null|string
	 */
	public function getISODate($attribute, $use_time=false, $default=false)
	{
		$date_format = 'Y-m-d';
		if ($use_time) {
			$date_format .= ' H:i:s';
		}
		$value = $this->owner->{$attribute};
		if (empty($value) && $default) {
			return date($date_format);
		}
		return $value ? date($date_format, strtotime($value)) : null;
	}

	/**
	 * Возвращает дату в формате для отображения
	 *
	 * @param $attribute
	 * @param bool $use_time
	 * @return false|null|string
	 */
	public function getDisplayDate($attribute, $use_time=false)
	{
		if ($use_time) {
			return $this->owner->{$attribute} ? Yii::$app->formatter->asDateTime($this->owner->{$attribute}) : null;
		}
		return $this->owner->{$attribute} ? Yii::$app->formatter->asDate($this->owner->{$attribute}) : null;
	}
}