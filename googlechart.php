<?php
require_once __DIR__ . '/vendor/autoload.php';

//set some of options
$options = [
    'xAxis'=>[
        'title'=>'xAxis'
    ]
];

$chart = Gufy\GoogleCharts\Chart\Area::setOptions($options);

//create columns
$chart->setColumns([
    'Year',
    'Extinction'
]);

//set some data
$chart->setData([
    ['2010', 15000],
    ['2011', 14037],
    ['2012', 16021],
    ['2013', 13520],
]);

//run this on view
$chart->render();