<?php
namespace andrewdanilov\behaviors;

use yii\db\ActiveRecordInterface;

interface LangInterface extends ActiveRecordInterface
{
	public function getId();

	public static function tableName();
	public static function getCurrentLang();
}