<?php
namespace andrewdanilov\behaviors;

use Yii;
use yii\base\Behavior;
use yii\web\Controller;
use yii\web\TooManyRequestsHttpException;

/**
 * Поведение для ограничения количества запросов
 * к экшенам контроллера.
 *
 * Пример реализации в классе:
 * class MyController extends \yii\web\Controller
 * {
 *     public function behaviors(): array
 *     {
 *         return array_merge(parent::behaviors(), [
 *             'rateLimit' => [
 *                 'class' => RateLimitBehavior::class,
 *                 // identityKey - key to identify current visitor, optional
 *                 'identityKey' => Yii::$app->user->id ?? Yii::$app->session->getId(),
 *                 'limits' => [
 *                     // '<action>' => [<interval>, <rate>]
 *                     // <action>   - идентификатор действия
 *                     // <interval> - интервал, на котором проводим измерения
 *                     // <rate>     - доступное число запросов на указанном интервале
 *                     'index'  => [1, 100], // 100 раз в секунду
 *                     'view'   => 10,       // 10 раз в секунду (сокращенная запись)
 *                     'create' => [60, 1],  // 1 раз в минуту
 *                     'update' => [1, 1],   // 1 раз в секунду
 *                 ],
 *             ],
 *         ]);
 *     }
 * }
 */
class RateLimitBehavior extends Behavior
{
    /**
     * @var int|string|null
     */
    public $identityKey = null;

    /**
     * @var array
     */
    public array $limits = [];

    public function events(): array
    {
        return [
            Controller::EVENT_BEFORE_ACTION => 'beforeActionEvent',
        ];
    }

    public function beforeActionEvent($event)
    {
        if (!$this->getIdentityKey()) {
            exit;
        }

        $action = $event->action->id;

        if (is_array($this->limits) && array_key_exists($action, $this->limits)) {

            $limit = $this->limits[$action];
            if (is_array($limit) && count($limit) === 2) {
                $interval = $limit[0];
                $rate = $limit[1];
            } elseif (is_int($limit)) {
                $interval = 1;
                $rate = $limit;
            } else {
                exit;
            }
            $delay = $interval / $rate;

            $actionStartedAt = $this->getActionStartedAt($action);
            if ($actionStartedAt !== null) {
                $now = microtime(true);
                if ($now - $actionStartedAt < $delay) {
                    throw new TooManyRequestsHttpException('Запросы приходят слишком часто.');
                }
            }
            $this->setActionStartedAt($action);
        }
    }

    public function getActionStartedAt($action)
    {
        $cacheKey = $this->getCacheKey($action);

        return Yii::$app->cache->get($cacheKey);
    }

    public function setActionStartedAt($action)
    {
        $cacheKey = $this->getCacheKey($action);
        $now = microtime(true);

        Yii::$app->cache->set($cacheKey, $now);
    }

    public function getIdentityKey(): int
    {
        if ($this->identityKey === null) {
            $this->identityKey = Yii::$app->user->id ?? Yii::$app->session->getId();
        }
        return $this->identityKey;
    }

    public function getCacheKey($action): string
    {
        /* @var Controller $controller */
        $controller = $this->owner;
    	return 'RateLimit|' . $this->getIdentityKey() . '|' . $controller->id . '|' . $action;
    }
}