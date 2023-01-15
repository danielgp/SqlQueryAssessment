<?php

/*
 * The MIT License
 *
 * Copyright 2022 Daniel Popiniuc.
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

namespace danielgp\sql_query_assessment;

trait TraitUserInterface
{

    use \danielgp\io_operations\InputOutputFiles;

    private $arrayConfiguration;
    public $strMainFolder;

    private function displayEmptyLines($arrayPenalties)
    {
        if (!is_null($arrayPenalties) && array_key_exists('EMPTY_LINE', $arrayPenalties)) {
            $arrayWhichLines = array_column($arrayPenalties['EMPTY_LINE'], 'whichLine');
            if ($arrayWhichLines != []) {
                echo vsprintf('<p style="color:red;">'
                        . 'EMPTY lines were found: %s which have no values added and MUST be removed!'
                        . '</p>', [
                    implode(', ', $arrayWhichLines),
                ]);
            }
        }
    }

    private function displayOperatorsImproper($arrayPenalties)
    {
        if (!is_null($arrayPenalties) && array_key_exists('OPERATOR', $arrayPenalties)) {
            foreach ($arrayPenalties['OPERATOR'] as $strOperator => $arrayMatchingDetails) {
                foreach ($arrayMatchingDetails as $arrayMatchingDetails2) {
                    if ((substr($strOperator, -1) == '=') && !in_array(substr($strOperator, 0, 1), ['!', '<', '>'])) {
                        $strOperator = '=';
                    }
                    echo vsprintf('<p style="color:red;">'
                            . 'Operator %s seen but has an issue of type %s and this happened on line %d position %d'
                            . '</p>', [
                        $strOperator,
                        $arrayMatchingDetails2['fault'],
                        $arrayMatchingDetails2['whichLine'],
                        $arrayMatchingDetails2['whichPosition'],
                    ]);
                }
            }
        }
    }

    private function displaySingleLineStatementKeyword($strSqlFlavour, $arrayPenalties)
    {
        if (!is_null($arrayPenalties) && array_key_exists('SINGLE_LINE_STATEMENT_KEYWORD', $arrayPenalties)) {
            foreach ($this->arraySqlFlavours[$strSqlFlavour]['Single Line Statement Keywords'] as $strStmntKeyword) {
                if (array_key_exists($strStmntKeyword, $arrayPenalties['SINGLE_LINE_STATEMENT_KEYWORD'])) {
                    $arrayOccurence = [];
                    foreach ($arrayPenalties['SINGLE_LINE_STATEMENT_KEYWORD'][$strStmntKeyword] as $arrayMatchDtls) {
                        $arrayOccurence[] = vsprintf('line %d position %d', [
                            $arrayMatchDtls['whichLine'],
                            $arrayMatchDtls['whichPosition'],
                        ]);
                    }
                    echo vsprintf('<p style="color:red;">'
                            . 'Single Line Statement Keyword %s seen but not by its own and this happened on line %s'
                            . '</p>', [
                        $strStmntKeyword,
                        implode(', ', $arrayOccurence),
                    ]);
                }
            }
        }
    }

    public function displaySqlQueryType($arrayDetected)
    {
        echo vsprintf('<p style="color:green;">Query Type = %s which stands for %s.'
                . '<br/><span style="font-size:0.6em;">'
                . 'This determination was done by 1st keyword begin %s which %s.</span></p>', [
            $arrayDetected['Type'],
            $arrayDetected['Type Description'],
            $arrayDetected['1st Keyword Within Query'],
            $arrayDetected['Description'],
        ]);
    }

    private function displayTabsAndSpacesInconsistency($arrayNumbers)
    {
        $intHowManySpaces = 0;
        $intHowManyTabs   = 0;
        $arrayWhere       = [
            'Spaces' => [],
            'Tabs'   => [],
        ];
        if (array_key_exists('Spaces', $arrayNumbers)) {
            foreach ($arrayNumbers['Spaces'] as $arrayMatchingDetails) {
                $intHowManySpaces       += $arrayMatchingDetails['howMany'];
                $arrayWhere['Spaces'][] = $arrayMatchingDetails['whichLine'];
            }
        }
        if (array_key_exists('Tabs', $arrayNumbers)) {
            foreach ($arrayNumbers['Tabs'] as $arrayMatchingDetails) {
                $intHowManyTabs       += $arrayMatchingDetails['howMany'];
                $arrayWhere['Tabs'][] = $arrayMatchingDetails['whichLine'];
            }
        }
        if (($intHowManySpaces !== 0) && ($intHowManyTabs !== 0)) {
            echo vsprintf('<p style="color:red;">Since a combination of both SPACES (%s) and TABS (%s)'
                    . ' were found to be present, this is considered a unacceptable inconsistency!</p>', [
                $intHowManySpaces . ' at lines ' . implode(', ', $arrayWhere['Spaces']),
                $intHowManyTabs . ' at lines ' . implode(', ', $arrayWhere['Tabs']),
            ]);
        }
    }

    private function displayTrailingSpaces($arrayPenalties)
    {
        if (!is_null($arrayPenalties) && array_key_exists('TRAILLING_SPACES_OR_TABS', $arrayPenalties)) {
            $intHowMany = 0;
            $arrayWhere = [];
            foreach ($arrayPenalties['TRAILLING_SPACES_OR_TABS'] as $arrayMatchingDetails) {
                $intHowMany   += $arrayMatchingDetails['howMany'];
                $arrayWhere[] = $arrayMatchingDetails['whichLine'];
            }
            echo vsprintf('<p style="color:red;">There are %d trailling spaces present at lines %s'
                    . ' and this is considered a unacceptable as all these characters are just wasted</p>', [
                $intHowMany,
                implode(', ', $arrayWhere),
            ]);
        }
    }

    public function initiateUserInterface()
    {
        $this->classTimer         = new \SebastianBergmann\Timer\Timer;
        $this->classTimer->start();
        $this->strMainFolder      = str_replace('source', '', __DIR__);
        $this->arrayConfiguration = $this->getArrayFromJsonFile($this->strMainFolder, 'composer.json');
    }

    public function setHtmlFooter(): void
    {
        $strHtmlContent = implode('', file(__DIR__ . '/footer.inc.html', FILE_IGNORE_NEW_LINES));
        echo vsprintf($strHtmlContent, [
            (new \SebastianBergmann\Timer\ResourceUsageFormatter)->resourceUsage($this->classTimer->stop()),
            date('Y'),
            $this->arrayConfiguration['authors'][0]['name'],
        ]);
    }

    public function setHtmlHeader(): void
    {
        $strHtmlContent = implode('', file(__DIR__ . '/header.inc.html', FILE_IGNORE_NEW_LINES));
        echo vsprintf($strHtmlContent, [
            $this->arrayConfiguration['name'],
            $this->arrayConfiguration['authors'][0]['name'],
            $this->arrayConfiguration['name'],
        ]);
    }

}
