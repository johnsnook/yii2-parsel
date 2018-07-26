<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\ScriptSearch */
/* @var $form yii\widgets\ActiveForm */

$form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'fieldConfig' => ['enableLabel' => false],
            'options' => ['role' => "form"]
        ]);
?>
<div class="input-group">
    <input id="parselQuery-userQuery" name="<?= $model::basename() ?>[userQuery]"
           type="text" class="form-control"
           aria-label="Marriage Search" autofocus="true" value="<?= $model->parsel->userQuery ?>"
           placeholder="Enter a search term">
    <div class="input-group-append">
        <div class="input-group-btn">
            <?= Html::submitButton('Search', ['class' => 'btn btn-sm btn-outline-primary']) ?>
        </div>
    </div>
    <div class="input-group-btn">
        <?=
        Html::button('<i class="fas fa fa-question-circle"></i>', [
            'class' => 'btn btn-sm btn-outline-info',
            'data-toggle' => 'collapse',
            'data-target' => '#help',
            'autocomplete' => 'off',
            'aria-expanded' => "false",
            "aria-controls" => "help"
        ])
        ?>
    </div>
    <div class="input-group-btn">
        <?=
        Html::button('<i class="fas fa fa-database"></i>', [
            'class' => 'btn btn-sm btn-outline-info',
            'data-toggle' => 'collapse',
            'data-target' => '#sql',
            'autocomplete' => 'off',
            'aria-expanded' => "false",
            "aria-controls" => "sql"
        ])
        ?>
    </div>
</div>
<div id="help"  class="card outline-info collapse" >
    <div class="card-body">
        <b>Conjunctives:</b><br>
        <ul class="list-group">
            <li class="list-group-item">'AND' is the default behavior. "smart pretty" is the same as "smart AND pretty."</li>
            <li class="list-group-item">'OR' allows more results in your query: "smart OR pretty."</li>
        </ul>
        <b>Operators:</b><br>
        <ul class="list-group">
            <li class="list-group-item">Negation: '-'. The user query "smart pretty -judgmental" parses to "smart AND pretty AND NOT judgmental"</li>
            <li class="list-group-item">Sub-query : '()', Allows grouping of terms . The user query "-crazy (smart AND pretty)" parses to "NOT crazy AND (smart AND pretty)".</li>
            <li class="list-group-item">Wildcard: '*', fuzzy matches. "butt*" matches butt, buttery, buttered etc.</li>
            <li class="list-group-item">Character wildcard: '_', matches one character. "boo_" matches boot, book, bool, boon, etc.</li>
            <li class="list-group-item">Full match: '=', field match. Entire fields must be equal to the term. "=georgia" only matches where one or more fields is exactly equal to the search term. The search term will NOT be bracketed with %, but wildcards can still be used.</li>
            <li class="list-group-item">Phrase: double quotes. '"Super fun"' searches for the full phrase, space include. Wild cards, negation and exact match operators all work within the phrase.</li>
            <li class="list-group-item">Phrase, no wildcards: single quotes. The term will not be evaluated for * or _, but will be wrapped in wildcards. If a % or _ is in the term, it will be escaped. 'P%on*' becomes '%P%on*%'.</li>
        </ul>

    </div>
</div>
<?php if (!empty($model->queryError)) { ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $model->parsel->queryError; ?>
    </div>
<?php } elseif (!empty($model->sql)) { ?>
    <div id="sql"  class="card outline-info collapse" >
        <div class="card-body">
            <?php
            echo $model->sql;
            ?>
        </div>
    </div>
<?php } ?>
<?php ActiveForm::end(); ?>
