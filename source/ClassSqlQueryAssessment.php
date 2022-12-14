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

class ClassSqlQueryAssessment
{

    use \danielgp\io_operations\InputOutputQueries,
        \danielgp\sql_query_assessment\TraitConfiguration,
        \danielgp\sql_query_assessment\TraitUserInterface;

    private $arraySqlFlavours;
    private $arrayNonVisibleCharactersMapping;

    public function __construct()
    {
        $this->initiateUserInterface();
        $this->arrayNonVisibleCharactersMapping = [
            \IntlChar::chr(\IntlChar::charFromName("SPACE")) => \IntlChar::chr(\IntlChar::charFromName("FULL STOP")),
            \IntlChar::chr(9)                                => \IntlChar::chr(\IntlChar::charFromName("COMBINING DOUBLE RIGHTWARDS ARROW BELOW")),
        ];
        $this->arraySqlFlavours                 = $this->configurationStructure();
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
        if (array_key_exists('TRAILLING_SPACES_OR_TABS', $arrayPenalties)) {
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

    public function getSqlQueryType($strQueryContent)
    {
        return $this->getMySQLqueryType($strQueryContent);
    }

    public function displaySqlQueryType($arrayDetected)
    {
        echo vsprintf('<p style="color:green;">Given query has been detected to be %s which stands for %s.'
                . '<span style="font-size:0.6em;">This determination was done by 1st keyword begin %s which %s.</span></p>', [
            $arrayDetected['Type'],
            $arrayDetected['Type Description'],
            $arrayDetected['1st Keyword Within Query'],
            $arrayDetected['Description'],
        ]);
    }

    public function evaluateSqlQuery($strSqlFlavour, $strQueryType, $arrayQueryLines)
    {
        $arrayQueryLinesEnhanced = $this->packArrayWithQueryLines($arrayQueryLines);
        $arrayNumbers            = [];
        $arrayPenalties          = [];
        $longTotalLength         = 0;
        echo '<pre>';
        foreach ($arrayQueryLinesEnhanced as $intLineNo => $arrayLineAttributes) {
            echo '<code>';
            echo $this->setContentWithAllCharactersVisible($arrayLineAttributes['content']);
            $longTotalLength += $arrayLineAttributes['length'];
            echo '<span style="color:#888;font-style:italic;font-size:0.5em;">'
            . '=> length=' . $arrayLineAttributes['length']
            . ', EOL length=' . $longTotalLength
            . ', Indentation=' . $arrayLineAttributes['indentation'];
            if ($arrayLineAttributes['lengthTrimmed'] == 0) {
                echo ', Empty line';
            } else {
                $arrayMatches = [];
                $strReg       = '/('
                        . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Statement Keywords']) . ')/i';
                $flagMatch    = PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL;
                preg_match_all($strReg, $arrayLineAttributes['content'], $arrayMatches, $flagMatch);
                unset($strReg);
                if ($arrayMatches[0] == []) {
                    echo ', No statement keywords match found';
                } else {
                    foreach ($arrayMatches[0] as $intMatchNo => $arrayMatchDetails) {
                        echo vsprintf(', Match %d of &quot;%s&quot; statement keyword found at %d character position', [
                            ($intMatchNo + 1),
                            $arrayMatchDetails[0],
                            $arrayMatchDetails[1],
                        ]);
                        if ((in_array(strtoupper($arrayMatchDetails[0]), $this->arraySqlFlavours[$strSqlFlavour]['Single Line Statement Keywords'])) && ($arrayLineAttributes['contentTrimmed'] !== $arrayMatchDetails[0])) {
                            $arrayPenalties['SINGLE_LINE_STATEMENT_KEYWORD'][strtoupper($arrayMatchDetails[0])][] = [
                                'whichLine'     => ($intLineNo + 1),
                                'whichPosition' => $arrayMatchDetails[1],
                            ];
                        }
                    }
                }
                $strRegCoOp = '/('
                        . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Operators']['Composite']) . ')/i';
                preg_match_all($strRegCoOp, $arrayLineAttributes['content'], $arrayMatchesCoOp, $flagMatch);
                unset($strRegCoOp);
                if ($arrayMatchesCoOp[0] == []) {
                    $strRegSiOp = '/('
                            . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Operators']['Single']) . ')/i';
                    preg_match_all($strRegSiOp, $arrayLineAttributes['content'], $arrayMatchesSiOp, $flagMatch);
                    unset($strRegSiOp);
                    if ($arrayMatchesSiOp[0] != []) {
                        foreach ($arrayMatchesSiOp[0] as $intMatchNo => $arrayMatchDetails) {
                            if ((substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1) != ' ') && (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]), 1) != ' ')) {
                                $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                    'fault'         => 'MISSING BOTH SPACES',
                                    'whichLine'     => ($intLineNo + 1),
                                    'whichPosition' => $arrayMatchDetails[1],
                                ];
                            } elseif (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1) != ' ') {
                                $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                    'fault'         => 'MISSING SPACE BEFORE',
                                    'whichLine'     => ($intLineNo + 1),
                                    'whichPosition' => $arrayMatchDetails[1],
                                ];
                            } elseif (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]), 1) != ' ') {
                                $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                    'fault'         => 'MISSING SPACE AFTER',
                                    'whichLine'     => ($intLineNo + 1),
                                    'whichPosition' => $arrayMatchDetails[1],
                                ];
                            }
                        }
                        /* echo '<br/>Single Operator';
                          var_dump($arrayMatchesSiOp[0]); */
                    }
                } else {
                    foreach ($arrayMatchesCoOp[0] as $intMatchNo => $arrayMatchDetails) {
                        if ((substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1) != ' ') && (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]), 1) != ' ')) {
                            $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                'fault'         => 'MISSING BOTH SPACES',
                                'whichLine'     => ($intLineNo + 1),
                                'whichPosition' => $arrayMatchDetails[1],
                            ];
                        } elseif (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1) != ' ') {
                            $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                'fault'         => 'MISSING SPACE BEFORE',
                                'whichLine'     => ($intLineNo + 1),
                                'whichPosition' => $arrayMatchDetails[1],
                            ];
                        } elseif (substr($arrayLineAttributes['content'], $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]), 1) != ' ') {
                            $arrayPenalties['OPERATOR'][$arrayMatchDetails[0]][] = [
                                'fault'         => 'MISSING SPACE AFTER',
                                'whichLine'     => ($intLineNo + 1),
                                'whichPosition' => $arrayMatchDetails[1],
                            ];
                        }
                    }
                    /* echo '<br/>Composite Operator';
                      var_dump($arrayMatchesCoOp[0]); */
                }
            }
            if ($arrayLineAttributes['length'] != $arrayLineAttributes['lengthWithoutSpaces']) {
                $arrayNumbers['Spaces'][] = [
                    'howMany'   => $arrayLineAttributes['length'] - $arrayLineAttributes['lengthWithoutSpaces'],
                    'whichLine' => ($intLineNo + 1),
                ];
            }
            if ($arrayLineAttributes['length'] != $arrayLineAttributes['lengthWithoutTabs']) {
                $arrayNumbers['Tabs'][] = [
                    'howMany'   => $arrayLineAttributes['length'] - $arrayLineAttributes['lengthWithoutTabs'],
                    'whichLine' => ($intLineNo + 1),
                ];
            }
            if ($arrayLineAttributes['length'] !== $arrayLineAttributes['lengthRightTrimmed']) {
                echo ', trailling spaces seen... :-(';
                $arrayPenalties['TRAILLING_SPACES_OR_TABS'][] = [
                    'howMany'   => ($arrayLineAttributes['length'] - $arrayLineAttributes['lengthRightTrimmed']),
                    'whichLine' => ($intLineNo + 1),
                ];
            }
            echo '</span>' . '</code>' . '<br/>';
        }
        echo '</pre>';
        $this->displayTrailingSpaces($arrayPenalties);
        $this->displaySingleLineStatementKeyword($strSqlFlavour, $arrayPenalties);
        $this->displayTabsAndSpacesInconsistency($arrayNumbers);
        $this->displayOperatorsImproper($arrayPenalties);
    }

    public function getQueryForAssessmentToArray($strInputFile): array
    {
        $contentInputFile = file($strInputFile, FILE_IGNORE_NEW_LINES);
        if ($contentInputFile === false) {
            throw new \RuntimeException(sprintf('Unable to read file %s...', $strInputFile));
        }
        return $contentInputFile;
    }

    private function packArrayWithQueryLines($arrayQueryLines)
    {
        $arrayQueryLinesEnhanced = [];
        foreach ($arrayQueryLines as $intLineNo => $strLineContent) {
            $arrayQueryLinesEnhanced[$intLineNo] = [
                'content'             => $strLineContent,
                'contentRightTrimmed' => rtrim($strLineContent),
                'contentTrimmed'      => trim($strLineContent),
                'length'              => strlen($strLineContent),
                'lengthRightTrimmed'  => strlen(rtrim($strLineContent)),
                'lengthTrimmed'       => strlen(trim($strLineContent)),
                'lengthWithoutSpaces' => strlen(str_replace(' ', '', $strLineContent)),
                'lengthWithoutTabs'   => strlen(str_replace(chr(9), '', $strLineContent)),
                'indentation'         => (strlen($strLineContent) - strlen(ltrim($strLineContent))),
            ];
        }
        unset($arrayQueryLines); // garbage collection
        return $arrayQueryLinesEnhanced;
    }

    private function setContentWithAllCharactersVisible($strContent): string
    {
        $arrayReplace = [
            array_keys($this->arrayNonVisibleCharactersMapping),
            $this->setVisualStyleForNonVisibleCharacters(array_values($this->arrayNonVisibleCharactersMapping)),
        ];
        return str_replace($arrayReplace[0], $arrayReplace[1], $strContent);
    }

    private function setVisualStyleForNonVisibleCharacters($arrayInputStrings): array
    {
        $arrayStrings = [];
        foreach ($arrayInputStrings as $strInputString) {
            $arrayStrings[] = '<span style="color:#888;">' . $strInputString . '</span>';
        }
        return $arrayStrings;
    }

}
