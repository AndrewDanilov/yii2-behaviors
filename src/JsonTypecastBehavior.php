<?php
namespace andrewdanilov\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * JsonTypecastBehavior
 * Casts field values saved in database as json-strings into array and back.
 *
 * @property array $attributes
 */
class JsonTypecastBehavior extends Behavior
{
	public ?array $attributes; // array of attributes to cast

	/**
	 * Events list
	 * @return array
	 */
	public function events(): array
    {
		return [
            BaseActiveRecord::EVENT_AFTER_FIND => 'stringToJson',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'jsonToString',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'jsonToString',
			BaseActiveRecord::EVENT_AFTER_INSERT => 'stringToJson',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'stringToJson',
		];
	}

    public function jsonToString()
    {
        /* @var ActiveRecord $ownerModel */
        $ownerModel = $this->owner;
        if (is_array($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                if ($ownerModel->hasAttribute($attribute)) {
                    if (is_array($ownerModel->$attribute)) {
                        $ownerModel->$attribute = json_encode($ownerModel->$attribute, JSON_UNESCAPED_UNICODE);
                    } else {
                        $ownerModel->$attribute = null;
                    }
                }
            }
        }
    }

    public function stringToJson()
    {
        /* @var ActiveRecord $ownerModel */
        $ownerModel = $this->owner;
        if (is_array($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                if ($ownerModel->hasAttribute($attribute)) {
                    if ($ownerModel->$attribute) {
                        $ownerModel->$attribute = json_decode($ownerModel->$attribute, true);
                    } else {
                        $ownerModel->$attribute = [];
                    }
                }
            }
        }
    }
}