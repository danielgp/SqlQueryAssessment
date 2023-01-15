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

    private $arrayPenalties;
    private $arraySqlFlavours;
    private $arrayNonVisibleCharactersMapping;
    private $flagMatch = PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL;

    public function __construct()
    {
        $this->initiateUserInterface();
        $strNames                               = [
            9 => 'COMBINING DOUBLE RIGHTWARDS ARROW BELOW',
        ];
        $this->arrayNonVisibleCharactersMapping = [
            \IntlChar::chr(\IntlChar::charFromName('SPACE')) => \IntlChar::chr(\IntlChar::charFromName('FULL STOP')),
            \IntlChar::chr(9)                                => \IntlChar::chr(\IntlChar::charFromName($strNames[9])),
        ];
        $this->arraySqlFlavours                 = $this->configurationStructure();
    }

    private function detectOperators($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes)
    {
        $arrayMatchesCoOp   = [];
        $arrayOperatorsKind = array_keys($this->arraySqlFlavours['MySQL']['Operators']);
        foreach ($arrayOperatorsKind as $strOperatorKind) {
            $strRegCoOp = '/('
                    . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Operators'][$strOperatorKind]) . ')/i';
            preg_match_all($strRegCoOp, $arrayLineAttributes['content'], $arrayMatchesCoOp, $this->flagMatch);
            unset($strRegCoOp);
            if ($arrayMatchesCoOp[0] != []) {
                foreach ($arrayMatchesCoOp[0] as $intMatchNo => $arrayMatchDetails) {
                    $strCharacterBefore = substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1);
                    $intPositionAfter   = $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]);
                    $strCharacterAfter  = substr($arrayLineAttributes['content'], $intPositionAfter, 1);
                    if (($strCharacterBefore != ' ') && ($strCharacterAfter != ' ')) {
                        $this->arrayPenalties[$intFileNo]['OPERATOR'][$arrayMatchDetails[0]][] = [
                            'fault'         => 'MISSING BOTH SPACES',
                            'whichLine'     => ($intLineNo + 1),
                            'whichPosition' => $arrayMatchDetails[1],
                        ];
                    } elseif ($strCharacterBefore != ' ') {
                        $this->arrayPenalties[$intFileNo]['OPERATOR'][$arrayMatchDetails[0]][] = [
                            'fault'         => 'MISSING SPACE BEFORE',
                            'whichLine'     => ($intLineNo + 1),
                            'whichPosition' => $arrayMatchDetails[1],
                        ];
                    } elseif ($strCharacterAfter != ' ') {
                        $this->arrayPenalties[$intFileNo]['OPERATOR'][$arrayMatchDetails[0]][] = [
                            'fault'         => 'MISSING SPACE AFTER',
                            'whichLine'     => ($intLineNo + 1),
                            'whichPosition' => $arrayMatchDetails[1],
                        ];
                    }
                }
            }
        }
    }

    private function detectStatements($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes)
    {
        $arrayMatches = [];
        $strReg       = '/('
                . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Statement Keywords']) . ')/i';
        preg_match_all($strReg, $arrayLineAttributes['content'], $arrayMatches, $this->flagMatch);
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
                    $this->arrayPenalties[$intFileNo]['SINGLE_LINE_STATEMENT_KEYWORD'][strtoupper($arrayMatchDetails[0])][] = [
                        'whichLine'     => ($intLineNo + 1),
                        'whichPosition' => $arrayMatchDetails[1],
                    ];
                }
            }
        }
    }

    public function getSqlQueryType($strQueryContent)
    {
        return $this->getMySQLqueryType($strQueryContent);
    }

    public function displaySqlQueryType($arrayDetected)
    {
        echo vsprintf('<p style="color:green;">Given query has been detected to be %s which stands for %s.'
                . '<span style="font-size:0.6em;">'
                . 'This determination was done by 1st keyword begin %s which %s.</span></p>', [
            $arrayDetected['Type'],
            $arrayDetected['Type Description'],
            $arrayDetected['1st Keyword Within Query'],
            $arrayDetected['Description'],
        ]);
    }

    public function evaluateSqlQuery($strSqlFlavour, $strQueryType, $intFileNo, $arrayQueryLines)
    {
        $arrayQueryLinesEnhanced = $this->packArrayWithQueryLines($arrayQueryLines);
        $arrayNumbers            = [];
        $longTotalLength         = 0;
        $arrayOperatorsKind      = array_keys($this->arraySqlFlavours['MySQL']['Operators']);
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
                $this->arrayPenalties[$intFileNo]['EMPTY_LINE'][] = [
                    'whichLine' => ($intLineNo + 1),
                ];
            } else {
                $this->detectStatements($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes);
                $this->detectOperators($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes);
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
                $this->arrayPenalties[$intFileNo]['TRAILLING_SPACES_OR_TABS'][] = [
                    'howMany'   => ($arrayLineAttributes['length'] - $arrayLineAttributes['lengthRightTrimmed']),
                    'whichLine' => ($intLineNo + 1),
                ];
            }
            echo '</span>' . '</code>' . '<br/>';
        }
        echo '</pre>';
        $this->displayTrailingSpaces($this->arrayPenalties[$intFileNo]);
        $this->displaySingleLineStatementKeyword($strSqlFlavour, $this->arrayPenalties[$intFileNo]);
        $this->displayTabsAndSpacesInconsistency($arrayNumbers);
        $this->displayEmptyLines($this->arrayPenalties[$intFileNo]);
        $this->displayOperatorsImproper($this->arrayPenalties[$intFileNo]);
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
