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

    private function displayOperatorsImproper($arrayPenalties)
    {
        if (array_key_exists('OPERATOR', $arrayPenalties)) {
            foreach ($arrayPenalties['OPERATOR'] as $strOperator => $arrayMatchingDetails) {
                foreach ($arrayMatchingDetails as $arrayMatchingDetails2) {
                    echo vsprintf('<p style="color:red;">Operator %s seen but has an issue of type %s'
                            . ' and this happened on line %d position %d</p>', [
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
        if (array_key_exists('SINGLE_LINE_STATEMENT_KEYWORD', $arrayPenalties)) {
            foreach ($this->arraySqlFlavours[$strSqlFlavour]['Single Line Statement Keywords'] as $strStatementKeyword) {
                if (array_key_exists($strStatementKeyword, $arrayPenalties['SINGLE_LINE_STATEMENT_KEYWORD'])) {
                    $arrayOccurence = [];
                    foreach ($arrayPenalties['SINGLE_LINE_STATEMENT_KEYWORD'][$strStatementKeyword] as $arrayMatchingDetails) {
                        $arrayOccurence[] = vsprintf('line %d position %d', [
                            $arrayMatchingDetails['whichLine'],
                            $arrayMatchingDetails['whichPosition'],
                        ]);
                    }
                    echo vsprintf('<p style="color:red;">Single Line Statement Keyword %s seen but not by its own'
                            . ' and this happened on line %s</p>', [
                        $strStatementKeyword,
                        implode(', ', $arrayOccurence),
                    ]);
                }
            }
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
        $strHtmlContent = <<<HTML_CONTENT
<footer>
%s - &copy; %s by %s
</footer>
</body>
</html>
HTML_CONTENT;
        echo vsprintf($strHtmlContent, [
            (new \SebastianBergmann\Timer\ResourceUsageFormatter)->resourceUsage($this->classTimer->stop()),
            date('Y'),
            $this->arrayConfiguration['authors'][0]['name'],
        ]);
    }

    public function setHtmlHeader(): void
    {
        $strHtmlContent = <<<HTML_CONTENT
<!doctype html>
<html lang="en">
<head>
    <title>%s</title>
    <meta name="Author" content="%s">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="../styles/sqa_main_style.css">
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous" defer></script>
    <h1>%s</h1>
HTML_CONTENT;
        echo vsprintf($strHtmlContent, [
            $this->arrayConfiguration['name'],
            $this->arrayConfiguration['authors'][0]['name'],
            $this->arrayConfiguration['name'],
        ]);
    }

}
