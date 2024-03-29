<?php

/*
 * The MIT License
 *
 * Copyright 2022 - 2023 Daniel Popiniuc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


require_once 'vendor/autoload.php';

use \SebastianBergmann\Timer\ResourceUsageFormatter;
use \SebastianBergmann\Timer\Timer;

$app           = new danielgp\sql_query_assessment\ClassSqlQueryAssessment();
$app->setHtmlHeader();
echo \IntlChar::charFromName("TAB");
$strInputFiles = [
    $app->strMainFolder . 'tests\\more_complex_query.sql',
    $app->strMainFolder . 'tests\\simple_query.sql',
    $app->strMainFolder . 'tests\\single_line_query.sql'
];
echo '<div class="tabber" id="tabStandard">';
foreach ($strInputFiles as $intFileNo => $strInputFile) {
    $app->arrayPenalties[$intFileNo] = [];
    echo '<div class="tabbertab" id="tab' . $intFileNo . '" title="' . basename($strInputFile) . '">';
    $arrayQueryLines                 = $app->getQueryForAssessmentToArray($strInputFile);
    if ($arrayQueryLines != []) {
        $arrayDetected = $app->getSqlQueryType(implode(' ', $arrayQueryLines));
        echo '<pre>';
        $app->evaluateSqlQuery('MySQL', $intFileNo, $arrayQueryLines);
        echo '</pre>';
        $app->displayIssuesFound('MySQL', $intFileNo, $arrayDetected);
    }
    echo '</div><!-- tabStandard -->';
}
echo '</div><!-- tabStandard -->';
$app->setHtmlFooter();
