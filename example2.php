<?php
/** Include class */
include( 'GoogChart.class.php' );

/** Create chart */
$chart = new GoogChart();


/*

        Example 1
        Pie chart

*/

// Set graph data
$data = array(
    'IE7' => 22,
    'IE6' => 30.7,
    'IE5' => 1.7,
    'Firefox' => 36.5,
    'Mozilla' => 1.1,
    'Safari' => 2,
    'Opera' => 1.4,
);

// Set graph colors
$color = array(
    '#99C754',
    '#54C7C5',
    '#999999',
);

/* # Chart 1 # */
$chart->setChartAttrs( array(
    'type' => 'pie',
    'title' => 'Browser market 2008',
    'data' => $data,
    'size' => array( 400, 300 ),
    'color' => $color
));
// Print chart
echo $chart;
?>