<?php

/**
 * @author John Snook
 * @date Jul 23, 2018
 * @license https://snooky.biz/site/license
 * @copyright 2018 John Snook Consulting
 * For using the console command.
 */

namespace johnsnook\parsel;

use yii\base\BootstrapInterface;
use yii\base\Module as BaseModule;

class Module extends BaseModule implements BootstrapInterface {

    /**
     * {@inheritdoc}
     *
     * If we're running from the console, change the controller namespace
     *
     * @param Application $app
     */
    public function bootstrap($app) {

        if ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'johnsnook\parsel\commands';
        }
    }

}
