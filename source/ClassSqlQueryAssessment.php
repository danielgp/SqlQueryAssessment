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

namespace danielgp\sql_query_assessment;

class ClassSqlQueryAssessment
{

    use \danielgp\io_operations\InputOutputQueries,
        \danielgp\sql_query_assessment\TraitConfiguration,
        \danielgp\sql_query_assessment\TraitUserInterface;

    private $arrayNumbers;
    private $arraySqlFlavours;
    private $arrayNonVisibleCharactersMapping;
    private $flagMatch = PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL;
    public $arrayPenalties;

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

    private function detectInconsistencyOnSpacesAndTabs(int $intFileNo, int $intLineNo, array $arrayLineAttributes): void
    {
        if ($arrayLineAttributes['length'] != $arrayLineAttributes['lengthWithoutSpaces']) {
            $this->arrayNumbers[$intFileNo]['Spaces'][] = [
                'howMany'   => $arrayLineAttributes['length'] - $arrayLineAttributes['lengthWithoutSpaces'],
                'whichLine' => ($intLineNo + 1),
            ];
        }
        if ($arrayLineAttributes['length'] != $arrayLineAttributes['lengthWithoutTabs']) {
            $this->arrayNumbers[$intFileNo]['Tabs'][] = [
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
    }

    private function detectOperators(string $strSqlFlavour, int $intFileNo, int $intLineNo, array $arrayLineAttributes): void
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
                    $strCharacterBefore  = substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1);
                    $intPositionAfter    = $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]);
                    $strCharacterAfter   = substr($arrayLineAttributes['content'], $intPositionAfter, 1);
                    $strCharacterBefore2 = substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 3, 1);
                    $strCharacterAfter2  = substr($arrayLineAttributes['content'], $intPositionAfter + 2, 1);
                    if (($arrayMatchDetails[0] === '-') && (($strCharacterBefore === '-') || ($strCharacterAfter === '-') || ($strCharacterBefore2 === '-') || ($strCharacterAfter2 === '-') || (in_array(ord($strCharacterBefore), array_keys(array_fill(ord('a'), 26, true)))) || (in_array(ord($strCharacterAfter), array_keys(array_fill(ord('a'), 26, true)))) || (in_array(ord($strCharacterBefore), array_keys(array_fill(ord('A'), 26, true)))) || (in_array(ord($strCharacterAfter), array_keys(array_fill(ord('A'), 26, true)))) || (ord($strCharacterAfter) === ord("'")))) {
                        // do nothing as this is an inline comment OR a date
                    } elseif (($strCharacterBefore != ' ') && ($strCharacterAfter != ' ')) {
                        $this->arrayPenalties[$intFileNo]['OPERATOR'][$arrayMatchDetails[0]][] = [
                            'fault'         => 'MISSING BOTH SPACES',
                            'whichLine'     => ($intLineNo + 1),
                            'whichPosition' => $arrayMatchDetails[1] + 1,
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

    private function detectSeparator(int $intFileNo, int $intLineNo, array $arrayLineAttributes): void
    {
        $arrayMatches = [];
        $strReg       = '/,/i';
        preg_match_all($strReg, $arrayLineAttributes['content'], $arrayMatches, $this->flagMatch);
        unset($strReg);
        if ($arrayMatches[0] != []) {
            foreach ($arrayMatches[0] as $arrayMatchDetails) {
                $strCharacterBefore = substr($arrayLineAttributes['content'], $arrayMatchDetails[1] - 1, 1);
                $intPositionAfter   = $arrayMatchDetails[1] + strlen($arrayMatchDetails[0]);
                $strCharacterAfter  = substr($arrayLineAttributes['content'], $intPositionAfter, 1);
                if (($strCharacterBefore === ' ') && ($strCharacterAfter !== ' ') && ($arrayLineAttributes['length'] != $intPositionAfter)) {
                    $this->arrayPenalties[$intFileNo]['SEPARATOR']['Un-necesary SPACE BEFORE and Missing SPACE AFTER'][] = [
                        'whichLine'     => ($intLineNo + 1),
                        'whichPosition' => $arrayMatchDetails[1] + 1,
                    ];
                } elseif (($strCharacterBefore === ' ') && (str_replace(' ', '', substr($arrayLineAttributes['content'], 0, $arrayMatchDetails[1])) != '')) {
                    $this->arrayPenalties[$intFileNo]['SEPARATOR']['Just Un-necesary SPACE BEFORE'][] = [
                        'whichLine'     => ($intLineNo + 1),
                        'whichPosition' => $arrayMatchDetails[1] + 1,
                    ];
                } elseif (($strCharacterAfter !== ' ') && ($strCharacterAfter !== '"') && ($arrayLineAttributes['length'] != $intPositionAfter)) {
                    $this->arrayPenalties[$intFileNo]['SEPARATOR']['Just Missing SPACE AFTER'][] = [
                        'whichLine'     => ($intLineNo + 1),
                        'whichPosition' => $arrayMatchDetails[1] + 1,
                    ];
                }
            }
        }
    }

    private function detectStatements(string $strSqlFlavour, int $intFileNo, int $intLineNo, array $arrayLineAttributes): void
    {
        $arrayMatches = [];
        $strReg       = '/('
                . implode('|', $this->arraySqlFlavours[$strSqlFlavour]['Statement Keywords']) . ')/i';
        preg_match_all($strReg, $arrayLineAttributes['content'], $arrayMatches, $this->flagMatch);
        unset($strReg);
        if ($arrayMatches[0] != []) {
            foreach ($arrayMatches[0] as $arrayMatchDetails) {
                if ((in_array(strtoupper($arrayMatchDetails[0]), $this->arraySqlFlavours[$strSqlFlavour]['Single Line Statement Keywords'])) && ($arrayLineAttributes['contentTrimmed'] !== $arrayMatchDetails[0])) {
                    $this->arrayPenalties[$intFileNo]['SINGLE_LINE_STATEMENT_KEYWORD'][strtoupper($arrayMatchDetails[0])][] = [
                        'whichLine'     => ($intLineNo + 1),
                        'whichPosition' => $arrayMatchDetails[1],
                    ];
                }
            }
        }
    }

    public function getSqlQueryType(string $strQueryContent): array
    {
        return $this->getMySQLqueryType($strQueryContent);
    }

    public function evaluateSqlQuery(string $strSqlFlavour, int $intFileNo, array $arrayQueryLines): void
    {
        $arrayQueryLinesEnhanced = $this->packArrayWithQueryLines($arrayQueryLines);
        $longTotalLength         = 0;
        foreach ($arrayQueryLinesEnhanced as $intLineNo => $arrayLineAttributes) {
            $longTotalLength += $arrayLineAttributes['length'];
            echo ($intLineNo == 0 ? '' : '<br/>') . '<code>'
                . $this->setContentWithAllCharactersVisible($arrayLineAttributes['content'])
                . '<span style="color:#888;font-style:italic;font-size:0.5em;">'
                . '=> length=' . $arrayLineAttributes['length'] . ', EOL length=' . $longTotalLength
                . ', Indentation=' . $arrayLineAttributes['indentation'];
            if ($arrayLineAttributes['lengthTrimmed'] == 0) {
                echo ', Empty line';
                $this->arrayPenalties[$intFileNo]['EMPTY_LINE'][] = [
                    'whichLine' => ($intLineNo + 1),
                ];
            } else {
                $this->detectStatements($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes);
                $this->detectOperators($strSqlFlavour, $intFileNo, $intLineNo, $arrayLineAttributes);
                $this->detectSeparator($intFileNo, $intLineNo, $arrayLineAttributes);
            }
            $this->detectInconsistencyOnSpacesAndTabs($intFileNo, $intLineNo, $arrayLineAttributes);
            echo '</span></code>';
        }
    }

    public function getQueryForAssessmentToArray(string $strInputFile): array
    {
        $contentInputFile = file($strInputFile, FILE_IGNORE_NEW_LINES);
        if ($contentInputFile === false) {
            throw new \RuntimeException(sprintf('Unable to read file %s...', $strInputFile));
        }
        return $contentInputFile;
    }

    private function packArrayWithQueryLines(array $arrayQueryLines): array
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

    private function setContentWithAllCharactersVisible(string $strContent): string
    {
        $arrayReplace = [
            array_keys($this->arrayNonVisibleCharactersMapping),
            $this->setVisualStyleForNonVisibleCharacters(array_values($this->arrayNonVisibleCharactersMapping)),
        ];
        return str_replace($arrayReplace[0], $arrayReplace[1], $strContent);
    }

    private function setVisualStyleForNonVisibleCharacters(array $arrayInputStrings): array
    {
        $arrayStrings = [];
        foreach ($arrayInputStrings as $strInputString) {
            $arrayStrings[] = '<span style="color:#888;">' . $strInputString . '</span>';
        }
        return $arrayStrings;
    }

}
